<?php

namespace Tests\Feature;

use App\Filament\Customer\RegisterOrganization;
use App\Filament\Customer\Resources\SiteResource\Pages\CreateSite;
use App\Filament\Customer\Resources\SiteResource\Pages\ListSites;
use App\Filament\Customer\Resources\SiteResource\Pages\ViewSite;
use App\Filament\Customer\Resources\SmsAssessmentResource\Pages\ListSmsAssessments;
use App\Models\Organization;
use App\Models\SmsAssessment;
use App\Models\User;
use App\Tenancy\CredentialDisplay;
use App\Tenancy\DnsProof;
use App\Tenancy\SiteManagement;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class TenancyPanelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_an_organization_and_create_a_sandbox_site(): void
    {
        $user = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('customer'));
        $this->actingAs($user)->get('/customer/new')->assertOk();

        Livewire::test(RegisterOrganization::class)
            ->fillForm(['name' => 'Team Orion'])
            ->call('register')
            ->assertHasNoFormErrors();

        $organization = $user->organizations()->sole();
        $this->assertSame('owner', $organization->pivot->role);
        Filament::setTenant($organization);

        Livewire::test(CreateSite::class)
            ->fillForm([
                'name' => 'Forms',
                'environment' => 'sandbox',
                'origin' => 'http://127.0.0.1:8075',
                'actions' => ['signup'],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $site = $organization->sites()->sole();
        $this->assertStringStartsWith('site_test_', $site->public_key);
        $this->assertSame(['signup'], $site->actions);
        $this->get('/customer/'.$organization->slug.'/sites/'.$site->id)
            ->assertOk()->assertSee($site->public_key)->assertSee('Create private key');
    }

    public function test_tenant_lists_and_record_pages_enforce_scope_and_viewer_permissions(): void
    {
        [$first, $owner] = $this->organization('First organization');
        [$second, $otherOwner] = $this->organization('Second organization');
        $viewer = User::factory()->create();
        $first->members()->attach($viewer, ['role' => 'viewer']);

        $services = app(SiteManagement::class);
        $visible = $services->create($owner, $first, $this->fields('First tenant site'));
        $hidden = $services->create($otherOwner, $second, $this->fields('Second tenant site'));
        Filament::setCurrentPanel(Filament::getPanel('customer'));
        $this->actingAs($viewer);
        Filament::setTenant($first);

        Livewire::test(ListSites::class)->assertCanSeeTableRecords([$visible])->assertCanNotSeeTableRecords([$hidden]);
        Livewire::test(ViewSite::class, ['record' => $visible->id])
            ->assertDontSee('Create private key');
        foreach ([$visible, $hidden] as $site) {
            $id = Str::uuid()->toString();
            DB::table('shield_sms_assessments')->insert([
                'id' => $id,
                'site_id' => $site->id,
                'event_digest' => str_repeat('a', 64),
                'request_digest' => str_repeat('b', 64),
                'action' => 'contact',
                'environment' => 'sandbox',
                'decision' => 'allow',
                'reason_code' => 'policy_satisfied',
                'created_at' => now(),
            ]);
            $assessments[$site->id] = SmsAssessment::query()->findOrFail($id);
        }
        Livewire::test(ListSmsAssessments::class)
            ->assertCanSeeTableRecords([$assessments[$visible->id]])
            ->assertCanNotSeeTableRecords([$assessments[$hidden->id]])
            ->assertDontSee(str_repeat('a', 64));
        $this->get('/customer/'.$first->slug.'/sites/'.$hidden->id)->assertNotFound();
        $this->get('/customer/'.$second->slug.'/sites')->assertNotFound();
        $this->get('/customer/'.$first->slug.'/sites/'.$visible->id.'/edit')->assertForbidden();
    }

    public function test_production_credential_requires_dns_proof_and_key_display_is_single_use_and_session_bound(): void
    {
        [$organization, $owner] = $this->organization('Production owner');
        $sites = app(SiteManagement::class);
        $production = $sites->create($owner, $organization, [
            ...$this->fields('Production site'),
            'environment' => 'production',
            'origin' => 'https://www.example.com',
        ]);

        $keys = app(CredentialDisplay::class);
        try {
            $keys->change($owner, $production, 'session', 'issued', 'verify');
            $this->fail('An unverified origin must be unable to issue a production key.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('site_credentials', 0);
        }

        $this->mock(DnsProof::class, function (MockInterface $mock) use ($production): void {
            $mock->shouldReceive('matches')->once()
                ->with('_shield-captcha.www.example.com', $production->domain_verification_token)
                ->andReturnTrue();
        });

        $this->assertTrue($sites->verifyOrigin($owner, $production, app(DnsProof::class)));
        $receipt = $keys->change($owner, $production, 'session', 'issued', 'verify');
        $this->assertNull($keys->consume($owner, $production, 'another-session', $receipt));
        $raw = $keys->consume($owner, $production, 'session', $receipt);
        $this->assertNotEmpty($raw);
        $this->assertDatabaseHas('site_credentials', ['secret_digest' => hash('sha256', $raw)]);
        $this->assertNull($keys->consume($owner, $production, 'session', $receipt));
        $this->assertDatabaseCount('shield_key_displays', 0);
    }

    public function test_customer_authentication_cannot_satisfy_the_operator_guard(): void
    {
        $operator = User::factory()->create();
        $operator->forceFill(['is_operator' => true])->save();

        $this->actingAs($operator, 'web')->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/register')->assertNotFound();
        $this->actingAs($operator, 'operator')->get('/admin')->assertRedirect('/admin/multi-factor-authentication/set-up');
    }

    public function test_operator_can_suspend_an_organization_with_a_redacted_audit_entry(): void
    {
        [$organization, $owner] = $this->organization('Tenant with suspended site');
        $operator = User::factory()->create();
        $operator->forceFill(['is_operator' => true])->save();

        app(SiteManagement::class)->setOrganizationStatus($operator, $organization, false, 'abuse_review');

        $this->assertFalse($organization->fresh()->is_active);
        $this->assertFalse($owner->canAccessTenant($organization));
        $this->assertDatabaseHas('shield_audit_entries', [
            'actor_id' => $operator->id,
            'organization_id' => $organization->id,
            'operation' => 'organization.suspended',
            'reason' => 'abuse_review',
        ]);
    }

    public function test_customer_password_check_and_one_time_secret_view_use_the_real_livewire_action(): void
    {
        [$organization, $owner] = $this->organization('Key owner');
        $site = app(SiteManagement::class)->create($owner, $organization, $this->fields('Key site'));
        Filament::setCurrentPanel(Filament::getPanel('customer'));
        $this->actingAs($owner)->get('/customer/'.$organization->slug.'/sites/'.$site->id)->assertOk();
        Filament::setTenant($organization);
        RateLimiter::clear('shield-confirm-password:'.$owner->id);

        Livewire::test(ViewSite::class, ['record' => $site->id])
            ->callAction('issueCredential', data: ['scope' => 'verify', 'password' => 'incorrect'])
            ->assertHasActionErrors(['password']);
        $this->assertDatabaseCount('site_credentials', 0);

        $action = Livewire::test(ViewSite::class, ['record' => $site->id])
            ->callAction('issueCredential', data: ['scope' => 'verify', 'password' => 'password'])
            ->assertHasNoActionErrors()
            ->assertRedirect();

        $redirect = $action->effects['redirect'];
        $this->assertDatabaseHas('shield_key_displays', ['session_digest' => hash('sha256', session()->getId())]);
        $response = $this->withCookie(config('session.cookie'), session()->getId())
            ->get($redirect)->assertOk()->assertHeader('cache-control', 'no-store, private');
        $this->assertMatchesRegularExpression('/<code>[^<]+<\/code>/', $response->getContent());
        $this->get($redirect)->assertNotFound();
    }

    private function organization(string $name): array
    {
        $owner = User::factory()->create();
        $organization = Organization::create(['name' => $name, 'slug' => str()->uuid()->toString()]);
        $organization->members()->attach($owner, ['role' => 'owner']);

        return [$organization, $owner];
    }

    private function fields(string $name): array
    {
        return [
            'name' => $name,
            'environment' => 'sandbox',
            'origin' => 'http://localhost:8075',
            'actions' => ['contact'],
            'is_active' => true,
        ];
    }
}
