<?php

namespace Tests\Feature;

use App\Filament\Customer\Pages\ProtectionPolicies as ProtectionPoliciesPage;
use App\Filament\Customer\Resources\ProtectionEventResource\Pages\ListProtectionEvents;
use App\Filament\Customer\Widgets\ProtectionAlertsWidget;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteCredential;
use App\Models\User;
use App\Risk\ProtectionEvent;
use App\Risk\ProtectionPolicies;
use App\Shield\SiteKeys;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class ProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessments_are_authenticated_and_site_action_and_environment_bound(): void
    {
        [$site] = $this->site();
        [$other] = $this->site();
        [$production] = $this->site('production');
        $keys = app(SiteKeys::class);
        $key = $keys->issue($site, 'protect');
        $otherKey = $keys->issue($other, 'protect');

        $this->assess($site, $otherKey)->assertUnauthorized()->assertJsonPath('code', 'invalid_key');
        $this->assess($site, $keys->issue($site, 'verify'))->assertUnauthorized();
        $this->assess($site, $key, data: ['environment' => 'production'])->assertForbidden()->assertJsonPath('code', 'context_mismatch');
        $this->assess($site, $key, data: ['action' => 'unregistered'])->assertForbidden()->assertJsonPath('code', 'context_mismatch');
        $this->assess($site, $key, data: ['subject_hash' => null])->assertUnprocessable()->assertJsonPath('code', 'invalid_request');
        $this->assess($site, $key, data: ['email' => 'customer@example.test'])->assertUnprocessable()->assertJsonPath('code', 'invalid_request');

        $production->update(['domain_verified_at' => now()]);
        $productionKey = $keys->issue($production, 'protect');
        $production->update(['domain_verified_at' => null]);
        $this->assess($production, $productionKey)->assertForbidden()->assertJsonPath('code', 'site_unavailable');
        $this->assertDatabaseCount('shield_protection_events', 0);

        $this->assess($site, $key)->assertOk()->assertHeader('X-Request-ID')
            ->assertHeaderMissing('Access-Control-Allow-Origin')->assertJsonPath('mode', 'observe')
            ->assertJsonPath('decision', 'allow')->assertJsonPath('would_decision', 'allow')
            ->assertJsonPath('reason_codes.0', 'within_policy')->assertJsonPath('policy_version', 0);

        $credential = SiteCredential::query()->where('site_id', $site->id)->where('scope', 'protect')->sole();
        $replacement = $keys->rotate($site, $credential->id);
        $this->assess($site, $key)->assertUnauthorized();
        $site->organization->update(['is_active' => false]);
        $this->assess($site, $replacement)->assertForbidden()->assertJsonPath('code', 'site_unavailable');
        $this->assertDatabaseCount('shield_protection_events', 1);
    }

    public function test_identical_retries_reuse_the_event_and_do_not_count_again(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-26T12:00:02Z'));
        [$site, $owner] = $this->site();
        [$other] = $this->site();
        $key = app(SiteKeys::class)->issue($site, 'protect');
        $input = ['event_id' => 'assessment-fixture-001', 'subject_hash' => str_repeat('a', 64)];
        app(ProtectionPolicies::class)->publish($owner, $site, 'login', ['subject_limit' => 2] + ProtectionPolicies::DEFAULTS);

        $id = $this->assess($site, $key, data: $input)->assertOk()->json('assessment_id');
        $this->assess($site, $key, data: $input)->assertOk()->assertJsonPath('assessment_id', $id);
        $this->assess($site, $key, data: ['subject_hash' => str_repeat('b', 64)] + $input)
            ->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
        $this->assess($site, $key, 'fraud/assess', $input)->assertConflict()->assertJsonPath('code', 'idempotency_conflict');

        $this->assess($site, $key, data: ['event_id' => 'assessment-fixture-002'] + $input)
            ->assertOk()->assertJsonPath('would_decision', 'allow');
        $this->assess($site, $key, data: ['event_id' => 'assessment-fixture-003'] + $input)
            ->assertOk()->assertJsonPath('decision', 'allow')->assertJsonPath('would_decision', 'step_up')
            ->assertJsonPath('risk_score', 70)->assertJsonPath('reason_codes.0', 'unusual_velocity');
        $this->assess($other, app(SiteKeys::class)->issue($other, 'protect'), data: $input)->assertOk();

        $this->assertDatabaseCount('shield_protection_events', 4);
        $record = (array) DB::table('shield_protection_events')->where('id', $id)->sole();
        $this->assertNotContains($input['subject_hash'], $record);
        $this->assertNotContains($input['event_id'], $record);
        $this->assertNotContains($key, $record);
    }

    public function test_published_policies_apply_to_new_events_and_throttle_until_the_window_ends(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-26T12:00:02Z'));
        [$site, $owner] = $this->site();
        $key = app(SiteKeys::class)->issue($site, 'protect');
        $policies = app(ProtectionPolicies::class);
        $rule = ['subject_limit' => 1] + ProtectionPolicies::DEFAULTS;

        $this->assertSame(1, $policies->publish($owner, $site, 'login', $rule));
        $this->assess($site, $key, 'api-protection/assess')->assertOk()->assertJsonPath('decision', 'allow');
        $limited = ['event_id' => 'api-observation-00001'];
        $this->assess($site, $key, 'api-protection/assess', $limited)->assertOk()
            ->assertJsonPath('decision', 'allow')->assertJsonPath('would_decision', 'throttle')
            ->assertJsonMissingPath('retry_after');
        $this->assertSame(2, $policies->publish($owner, $site, 'login', ['mode' => 'enforce'] + $rule));
        $this->assess($site, $key, 'api-protection/assess', $limited)->assertOk()
            ->assertJsonPath('mode', 'observe')->assertJsonPath('policy_version', 1);

        $blocked = ['event_id' => 'api-enforcement-00001'];
        $id = $this->assess($site, $key, 'api-protection/assess', $blocked)->assertOk()
            ->assertJsonPath('policy_version', 2)->assertJsonPath('mode', 'enforce')
            ->assertJsonPath('decision', 'throttle')->assertJsonPath('retry_after', 58)->json('assessment_id');
        $this->travel(60)->seconds();
        $this->assess($site, $key, 'api-protection/assess', $blocked)->assertOk()
            ->assertJsonPath('assessment_id', $id)->assertJsonPath('retry_after', 0);
        $this->assess($site, $key, 'api-protection/assess', ['event_id' => 'api-enforcement-00002'])
            ->assertOk()->assertJsonPath('decision', 'allow');
        $this->assertDatabaseCount('shield_protection_policies', 2);
        $this->assertDatabaseHas('shield_audit_entries', ['site_id' => $site->id, 'operation' => 'protection_policy.published']);
    }

    public function test_registered_automation_credentials_only_work_on_bot_assessments_and_keep_velocity_limits(): void
    {
        [$site, $owner] = $this->site();
        $keys = app(SiteKeys::class);
        $protect = $keys->issue($site, 'protect');
        $automation = $keys->issue($site, 'automation');
        app(ProtectionPolicies::class)->publish($owner, $site, 'login', [
            'mode' => 'enforce',
            'subject_limit' => 2,
            'require_registered_automation' => true,
        ] + ProtectionPolicies::DEFAULTS);

        $this->assess($site, $automation)->assertUnauthorized();
        $this->assess($site, $protect, 'bots/assess', ['declares_automation' => 'true'])->assertUnprocessable();
        $this->assess($site, $protect, 'bots/assess', ['declares_automation' => true])->assertOk()
            ->assertJsonPath('classification', 'declared_automation')->assertJsonPath('decision', 'deny')
            ->assertJsonPath('reason_codes.0', 'registered_automation_required');
        $this->assess($site, $automation, 'bots/assess', ['event_id' => 'assessment-fixture-002'])->assertOk()
            ->assertJsonPath('classification', 'registered_automation')->assertJsonPath('decision', 'allow');
        $this->assess($site, $automation, 'bots/assess', ['event_id' => 'assessment-fixture-003'])->assertOk()
            ->assertJsonPath('classification', 'registered_automation')->assertJsonPath('decision', 'throttle');
    }

    public function test_hard_daily_quota_applies_in_observe_mode_and_allows_only_idempotent_replays(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-26T12:00:00Z'));
        [$site] = $this->site();
        $key = app(SiteKeys::class)->issue($site, 'protect');
        $id = $this->assess($site, $key)->assertOk()->json('assessment_id');
        $midnight = now()->startOfDay()->timestamp;
        $bucket = hash('sha256', "protection:v1:{$site->id}:sandbox:daily:86400:{$midnight}");
        $this->assertSame(1, DB::table('shield_rate_windows')->where('bucket', $bucket)->update(['hits' => 3_000]));

        $this->assess($site, $key)->assertOk()->assertJsonPath('assessment_id', $id);
        $this->assess($site, $key, data: ['event_id' => 'assessment-fixture-002'])
            ->assertTooManyRequests()->assertHeader('Retry-After', '43200')
            ->assertJsonPath('code', 'quota_exceeded')->assertJsonPath('retry_after', 43_200);
        $this->assertDatabaseCount('shield_protection_events', 1);
    }

    public function test_feedback_is_site_and_action_bound_and_prunes_with_its_assessment(): void
    {
        $this->freezeTime();
        [$site] = $this->site();
        [$other] = $this->site();
        $key = app(SiteKeys::class)->issue($site, 'protect');
        $otherKey = app(SiteKeys::class)->issue($other, 'protect');
        $id = $this->assess($site, $key, 'fraud/assess')->assertOk()->json('assessment_id');
        $input = [
            'site_key' => $site->public_key,
            'action' => 'login',
            'environment' => 'sandbox',
            'assessment_id' => $id,
            'label' => 'legitimate',
        ];
        $headers = ['Authorization' => 'Bearer '.$key];

        $this->postJson('/api/v1/feedback', ['action' => 'comment'] + $input, $headers)
            ->assertNotFound()->assertJsonPath('code', 'assessment_not_found');
        $this->postJson('/api/v1/feedback', ['site_key' => $other->public_key] + $input, ['Authorization' => 'Bearer '.$otherKey])
            ->assertNotFound()->assertJsonPath('code', 'assessment_not_found');
        $this->postJson('/api/v1/feedback', $input, $headers)->assertOk()->assertJsonPath('label', 'legitimate');
        $this->postJson('/api/v1/feedback', $input, $headers)->assertOk()->assertJsonPath('assessment_id', $id);
        $this->postJson('/api/v1/feedback', ['label' => 'abusive'] + $input, $headers)
            ->assertConflict()->assertJsonPath('code', 'idempotency_conflict');

        $this->travel(30)->days();
        $fresh = $this->assess($site, $key, data: ['event_id' => 'assessment-fixture-002'])->assertOk()->json('assessment_id');
        $this->artisan('shield:prune-protection', ['--batch' => 1])->assertSuccessful();
        $this->assertDatabaseMissing('shield_protection_events', ['id' => $id]);
        $this->assertDatabaseHas('shield_protection_events', ['id' => $fresh]);
    }

    public function test_dashboard_edits_policies_and_lists_only_the_current_tenants_events(): void
    {
        [$site, $owner] = $this->site();
        [$other] = $this->site();
        $keys = app(SiteKeys::class);
        $id = $this->assess($site, $keys->issue($site, 'protect'))->assertOk()->json('assessment_id');
        $otherId = $this->assess($other, $keys->issue($other, 'protect'))->assertOk()->json('assessment_id');
        $event = ProtectionEvent::query()->findOrFail($id);
        $outside = ProtectionEvent::query()->findOrFail($otherId);

        Filament::setCurrentPanel(Filament::getPanel('customer'));
        $this->actingAs($owner);
        Filament::setTenant($site->organization);

        Livewire::test(ProtectionPoliciesPage::class)
            ->set('data.site_id', $site->id)
            ->set('data.action', 'login')
            ->assertSet('publishedVersion', 0)
            ->fillForm(['mode' => 'enforce', 'window_seconds' => 90, 'velocity_decision' => 'review'])
            ->call('save')->assertHasNoFormErrors()->assertSet('publishedVersion', 1);
        Livewire::test(ProtectionPoliciesPage::class)
            ->set('data.site_id', $site->id)
            ->set('data.action', 'login')
            ->assertSet('publishedVersion', 1)->assertFormSet(['mode' => 'enforce', 'window_seconds' => 90])
            ->set('data.revision_to_load', 0)
            ->assertFormSet(['mode' => 'observe', 'window_seconds' => 60])
            ->call('save')->assertHasNoFormErrors()->assertSet('publishedVersion', 2);
        $this->assertDatabaseHas('shield_protection_policies', ['site_id' => $site->id, 'version' => 1, 'mode' => 'enforce']);
        $this->assertDatabaseHas('shield_protection_policies', ['site_id' => $site->id, 'version' => 2, 'mode' => 'observe']);
        Livewire::test(ListProtectionEvents::class)->assertCanSeeTableRecords([$event])
            ->assertCanNotSeeTableRecords([$outside])->assertDontSee($event->event_digest);
        $viewer = User::factory()->create();
        $site->organization->members()->attach($viewer, ['role' => 'viewer']);
        $this->actingAs($viewer);
        Livewire::test(ProtectionPoliciesPage::class)->assertForbidden();
    }

    public function test_recent_protection_alerts_distinguish_reported_outcomes_from_policy_decisions(): void
    {
        $this->freezeTime();
        [$site, $owner] = $this->site();
        [$other, $otherOwner] = $this->site();
        $other->update(['name' => 'Different tenant site']);
        $keys = app(SiteKeys::class);
        foreach ([[$site, $owner], [$other, $otherOwner]] as [$target, $actor]) {
            $key = $keys->issue($target, 'protect');
            app(ProtectionPolicies::class)->publish($actor, $target, 'login', ['subject_limit' => 1] + ProtectionPolicies::DEFAULTS);

            foreach (range(1, 6) as $index) {
                $id = $this->assess($target, $key, data: ['event_id' => 'alert-fixture-event-0'.$index])->assertOk()->json('assessment_id');
                if ($index <= 3) {
                    $this->postJson('/api/v1/feedback', [
                        'site_key' => $target->public_key,
                        'action' => 'login',
                        'environment' => 'sandbox',
                        'assessment_id' => $id,
                        'label' => 'abusive',
                    ], ['Authorization' => 'Bearer '.$key])->assertOk();
                }
            }
        }

        Filament::setCurrentPanel(Filament::getPanel('customer'));
        $this->actingAs($owner);
        Filament::setTenant($site->organization);
        Livewire::test(ListProtectionEvents::class)->assertSee('Protection alerts')
            ->assertSee('5 observe-mode velocity flags')->assertSee('0 enforce-mode velocity decisions')
            ->assertSee('3 customer-reported abusive outcomes')->assertDontSee($other->name);

        $this->travel(61)->minutes();
        Livewire::test(ProtectionAlertsWidget::class)->assertSee('No actions reached the alert thresholds');
    }

    private function site(string $environment = 'sandbox'): array
    {
        $organization = Organization::query()->create([
            'name' => 'Protection fixture organization',
            'slug' => 'fixture-'.bin2hex(random_bytes(6)),
        ]);
        $owner = User::factory()->create();
        $organization->members()->attach($owner, ['role' => 'owner']);
        $site = $organization->sites()->create([
            'name' => 'Protection fixture site',
            'origin' => 'https://customer.example',
            'environment' => $environment,
            'public_key' => 'site_'.($environment === 'production' ? 'prod' : 'test').'_'.bin2hex(random_bytes(16)),
            'actions' => ['login', 'comment'],
            'is_active' => true,
        ]);

        return [$site, $owner];
    }

    private function assess(Site $site, string $key, string $endpoint = 'accounts/assess', array $data = []): TestResponse
    {
        return $this->postJson('/api/v1/'.$endpoint, $data + [
            'site_key' => $site->public_key,
            'action' => 'login',
            'environment' => $site->environment,
            'event_id' => 'assessment-fixture-001',
            'subject_hash' => hash_hmac('sha256', 'fixture-subject', 'fixture-customer-key'),
        ], ['Authorization' => 'Bearer '.$key]);
    }
}
