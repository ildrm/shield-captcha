<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteCredential;
use App\Shield\SiteKeys;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ShieldApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_browser_proof_mints_one_token_and_records_one_verified_operation(): void
    {
        $site = $this->site();
        $secret = app(SiteKeys::class)->issue($site, 'verify');

        $preflight = $this->options('/api/v1/challenges', [], [
            'Origin' => $site->origin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type',
        ]);
        $preflight->assertNoContent()->assertHeader('Access-Control-Allow-Origin', $site->origin)
            ->assertHeaderMissing('Access-Control-Allow-Credentials');

        $this->getJson('/api/v1/widget/config?site_key='.$site->public_key.'&action=login', ['Origin' => $site->origin])
            ->assertOk()->assertJsonPath('modes.0', 'checkbox')->assertHeader('Access-Control-Allow-Origin', $site->origin);

        $challenge = $this->issue($site);
        $proof = $this->solution($challenge);
        $token = $this->solve($site, $challenge['id'], $proof)->assertOk()->json('token');

        $this->solve($site, $challenge['id'], $proof)->assertConflict()->assertJsonPath('code', 'challenge_already_solved');

        $response = $this->verify($site, $token, $secret);
        $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('decision', 'allow')
            ->assertJsonPath('site_key', $site->public_key)->assertJsonPath('action', 'login')
            ->assertJsonPath('origin', $site->origin)->assertJsonPath('environment', 'sandbox')->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->verify($site, $token, $secret)->assertConflict()->assertJsonPath('code', 'token_already_used');
        $this->assertDatabaseCount('shield_usage_events', 1);
        $this->assertDatabaseHas('shield_usage_events', ['site_id' => $site->id, 'action' => 'login', 'environment' => 'sandbox']);
        $this->assertDatabaseHas('shield_tokens', ['digest' => hash('sha256', $token)]);
        $this->assertDatabaseMissing('shield_tokens', ['digest' => $token]);
        $this->assertDatabaseMissing('site_credentials', ['secret_digest' => $secret]);
    }

    public function test_request_validation_and_cors_are_limited_to_the_active_registered_origin(): void
    {
        $site = $this->site();
        $this->options('/api/v1/challenges', [], [
            'Origin' => 'https://unknown.example',
            'Access-Control-Request-Method' => 'POST',
        ])->assertForbidden()->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->options('/api/v1/challenges', [], [
            'Origin' => $site->origin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization',
        ])->assertForbidden();
        $this->options('/api/v1/verify', [], [
            'Origin' => $site->origin,
            'Access-Control-Request-Method' => 'POST',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');

        $this->challenge($site, ['Origin' => 'https://wrong.example'])->assertForbidden()->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->challenge($site, ['Origin' => $site->origin], ['action' => 'unregistered'])->assertForbidden()->assertJsonPath('code', 'context_mismatch');
        $this->challenge($site, ['Origin' => $site->origin], ['unexpected' => true])->assertUnprocessable()->assertJsonPath('code', 'invalid_request');
        $this->call('POST', '/api/v1/challenges', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => $site->origin], content: '{bad')
            ->assertBadRequest()->assertJsonPath('code', 'invalid_json');
        $this->call('POST', '/api/v1/challenges', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => $site->origin], content: str_repeat(' ', 4_097))
            ->assertStatus(413)->assertJsonPath('code', 'body_too_large');
        $this->post('/api/v1/challenges', ['site_key' => $site->public_key], ['Origin' => $site->origin])
            ->assertUnsupportedMediaType()->assertJsonPath('code', 'unsupported_media_type');
        $this->assertDatabaseCount('shield_challenges', 0);
    }

    public function test_managed_widget_config_preserves_origin_and_falls_back_under_pending_load(): void
    {
        $site = $this->site();
        $body = ['site_key' => $site->public_key, 'action' => 'login'];
        $origin = ['Origin' => $site->origin];
        $url = '/api/v1/widget/config';

        $this->options($url, [], $origin + [
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Methods', 'POST');
        $this->postJson($url, $body, $origin)->assertOk()->assertJsonPath('mode', 'checkbox');
        $site->update(['challenge_mode' => 'adaptive']);
        $this->postJson($url, $body, $origin)->assertOk()->assertJsonPath('mode', 'invisible');

        for ($index = 0; $index < 12; $index++) {
            $this->issue($site);
        }

        $this->postJson($url, $body, $origin)->assertOk()->assertJsonPath('mode', 'checkbox');
        DB::table('shield_challenges')->update(['expires_at' => now()->subSecond()]);
        $this->postJson($url, $body, $origin)->assertOk()->assertJsonPath('mode', 'invisible');
        $this->postJson($url, $body, ['Origin' => 'https://different.example'])->assertForbidden()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_challenge_attempt_limit_and_expiry_are_persistent(): void
    {
        $site = $this->site();
        $challenge = $this->issue($site);
        $proof = $this->solution($challenge);
        $incorrect = $proof === 0 ? 1 : 0;

        $this->solve($site, $challenge['id'], $incorrect)->assertUnprocessable()->assertJsonPath('code', 'solution_invalid');
        $this->solve($site, $challenge['id'], $incorrect)->assertUnprocessable();
        $this->solve($site, $challenge['id'], $incorrect)->assertTooManyRequests()->assertJsonPath('code', 'attempt_limit');
        $this->solve($site, $challenge['id'], $proof)->assertTooManyRequests();
        $this->assertDatabaseHas('shield_challenges', ['id' => $challenge['id'], 'attempts' => 3]);

        $expiring = $this->issue($site);
        $this->travel(3)->minutes();
        $this->solve($site, $expiring['id'], $this->solution($expiring))->assertGone()->assertJsonPath('code', 'challenge_expired');
        $this->assertDatabaseCount('shield_tokens', 0);
    }

    public function test_verification_is_site_action_environment_and_origin_bound(): void
    {
        $site = $this->site();
        $other = $this->site(origin: 'https://second.example');
        $secret = app(SiteKeys::class)->issue($site, 'verify');
        $otherSecret = app(SiteKeys::class)->issue($other, 'verify');
        $wrongScope = app(SiteKeys::class)->issue($site, 'assess');
        $challenge = $this->issue($site);
        $proof = $this->solution($challenge);
        $this->solve($other, $challenge['id'], $proof)->assertNotFound()->assertJsonPath('code', 'challenge_invalid');
        $token = $this->solve($site, $challenge['id'], $proof)->assertOk()->json('token');

        $this->verify($other, $token, $otherSecret)->assertUnprocessable()->assertJsonPath('code', 'token_invalid');
        $this->verify($site, $token, $otherSecret)->assertUnauthorized()->assertJsonPath('code', 'invalid_key');
        $this->verify($site, $token, $wrongScope)->assertUnauthorized();
        $this->verify($site, $token, $secret, ['expected_action' => 'comment'])->assertUnprocessable()->assertJsonPath('code', 'token_invalid');
        $this->verify($site, $token, $secret, ['expected_origin' => $other->origin])->assertForbidden()->assertJsonPath('code', 'context_mismatch');
        $this->verify($site, $token, $secret, ['expected_environment' => 'production'])->assertForbidden();
        $this->assertDatabaseCount('shield_usage_events', 0);
        $this->verify($site, $token, $secret)->assertOk();
    }

    public function test_production_requires_domain_verification_and_revoked_keys_fail(): void
    {
        $site = $this->site(environment: 'production');
        $this->challenge($site, ['Origin' => $site->origin])->assertForbidden()->assertJsonPath('code', 'site_unavailable');

        $site->update(['domain_verified_at' => now()]);
        $keys = app(SiteKeys::class);
        $secret = $keys->issue($site, 'verify');
        $challenge = $this->issue($site);
        $token = $this->solve($site, $challenge['id'], $this->solution($challenge))->assertOk()->json('token');
        $newSecret = $keys->rotate($site, SiteCredential::query()->where('site_id', $site->id)->sole()->id);

        $this->verify($site, $token, $secret)->assertUnauthorized();
        $site->organization->update(['is_active' => false]);
        $this->verify($site, $token, $newSecret)->assertForbidden()->assertJsonPath('code', 'site_unavailable');
        $site->organization->update(['is_active' => true]);
        $this->verify($site, $token, $newSecret)->assertOk()->assertJsonPath('environment', 'production');
    }

    public function test_pruning_preserves_live_tokens_issued_near_challenge_expiry(): void
    {
        $site = $this->site();
        $secret = app(SiteKeys::class)->issue($site, 'verify');
        $challenge = $this->issue($site);
        $this->travel(2)->minutes();
        $token = $this->solve($site, $challenge['id'], $this->solution($challenge))->assertOk()->json('token');
        $this->travel(1)->minutes();
        $this->artisan('shield:prune', ['--batch' => 10])->assertSuccessful();
        $this->verify($site, $token, $secret)->assertOk();
        $this->travel(2)->minutes();
        $this->verify($site, $token, $secret)->assertGone()->assertJsonPath('code', 'token_expired');
        $this->artisan('shield:prune', ['--batch' => 10])->assertSuccessful();
        $this->assertDatabaseCount('shield_challenges', 0);
        $this->assertDatabaseCount('shield_tokens', 0);
        $this->assertDatabaseCount('shield_usage_events', 1);
    }

    public function test_sms_assessment_is_scoped_idempotent_and_tracks_destination_velocity(): void
    {
        $site = $this->site();
        $other = $this->site();
        $secret = app(SiteKeys::class)->issue($site, 'assess');
        $otherSecret = app(SiteKeys::class)->issue($other, 'assess');
        $destination = hash_hmac('sha256', 'synthetic-destination', 'synthetic-customer-key');
        $input = ['site_key' => $site->public_key, 'action' => 'otp', 'destination_hash' => $destination, 'event_id' => 'fixture-event-0001'];
        $headers = ['Authorization' => 'Bearer '.$secret];

        $id = $this->postJson('/api/v1/sms/assess', $input, $headers)->assertOk()->assertJsonPath('decision', 'allow')
            ->assertJsonPath('site_key', $site->public_key)->assertJsonPath('environment', 'sandbox')->assertJsonPath('action', 'otp')->json('assessment_id');
        $this->postJson('/api/v1/sms/assess', $input, $headers)->assertOk()->assertJsonPath('assessment_id', $id);
        $this->postJson('/api/v1/sms/assess', array_replace($input, ['action' => 'login']), $headers)
            ->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
        $this->postJson('/api/v1/sms/assess', array_replace($input, ['event_id' => 'fixture-event-0002']), $headers)
            ->assertOk()->assertJsonPath('decision', 'throttle')->assertJsonPath('reason_codes.0', 'destination_velocity');
        $this->postJson('/api/v1/sms/assess', array_replace($input, ['site_key' => $other->public_key]), ['Authorization' => 'Bearer '.$otherSecret])
            ->assertOk()->assertJsonPath('decision', 'allow');
        $this->assertDatabaseCount('shield_sms_assessments', 3);

        $row = (array) DB::table('shield_sms_assessments')->where('id', $id)->first();
        $this->assertNotContains($destination, $row);
        $this->assertNotContains($input['event_id'], $row);
    }

    public function test_public_issue_quota_does_not_meter_customer_verification(): void
    {
        $this->freezeTime();
        $site = $this->site();

        for ($index = 0; $index < 30; $index++) {
            $this->issue($site);
        }

        $this->challenge($site, ['Origin' => $site->origin])->assertTooManyRequests()
            ->assertJsonPath('code', 'rate_limited')->assertHeader('Retry-After');
        $this->assertDatabaseCount('shield_challenges', 30);
        $this->assertDatabaseCount('shield_usage_events', 0);
    }

    public function test_paid_allowance_extends_the_existing_production_window_only_for_its_term(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-26T12:00:00Z'));
        $production = $this->site(environment: 'production');
        $production->update(['domain_verified_at' => now()]);
        $sandbox = $this->site();
        $sandbox->organization()->associate($production->organization)->save();

        $productionSecret = app(SiteKeys::class)->issue($production, 'verify');
        $sandboxSecret = app(SiteKeys::class)->issue($sandbox, 'verify');
        foreach ([$production, $sandbox] as $site) {
            $start = intdiv(now()->timestamp, 86_400) * 86_400;
            DB::table('shield_rate_windows')->insert([
                'bucket' => hash('sha256', "verify:{$site->organization_id}:{$site->environment}:86400:{$start}"),
                'hits' => 10_000,
                'expires_at' => now()->endOfDay(),
            ]);
        }

        $challenge = $this->issue($production);
        $token = $this->solve($production, $challenge['id'], $this->solution($challenge))->assertOk()->json('token');
        $this->verify($production, $token, $productionSecret)->assertTooManyRequests()->assertJsonPath('code', 'quota_exceeded');

        $invoice = (string) Str::uuid();
        DB::table('billing_invoices')->insert([
            'id' => $invoice,
            'organization_id' => $production->organization_id,
            'checkout_key' => (string) Str::uuid(),
            'environment' => 'production',
            'merchant_fingerprint' => str_repeat('0', 64),
            'amount_irr' => 100_000,
            'term_days' => 30,
            'daily_verifications' => 50_000,
            'status' => 'paid',
            'paid_at' => now()->subDays(30)->addHour(),
        ]);
        DB::table('billing_subscriptions')->insert([
            'invoice_id' => $invoice,
            'organization_id' => $production->organization_id,
            'starts_at' => now()->subDays(30)->addHour(),
            'expires_at' => now()->addHour(),
            'daily_verifications' => 50_000,
        ]);

        $this->verify($production, $token, $productionSecret)->assertOk();
        $sandboxChallenge = $this->issue($sandbox);
        $sandboxToken = $this->solve($sandbox, $sandboxChallenge['id'], $this->solution($sandboxChallenge))->assertOk()->json('token');
        $this->verify($sandbox, $sandboxToken, $sandboxSecret)->assertTooManyRequests();

        $this->travel(1)->hour();
        $expiring = $this->issue($production);
        $fresh = $this->solve($production, $expiring['id'], $this->solution($expiring))->assertOk()->json('token');
        $this->verify($production, $fresh, $productionSecret)->assertTooManyRequests();
        $this->assertDatabaseCount('shield_usage_events', 1);
    }

    public function test_missing_application_key_disables_protocol_until_configured(): void
    {
        $site = $this->site();
        config(['app.key' => null]);

        $this->challenge($site, ['Origin' => $site->origin])->assertServiceUnavailable()->assertJsonPath('code', 'service_unavailable');
        $this->assertDatabaseCount('shield_challenges', 0);
    }

    private function site(string $environment = 'sandbox', string $origin = 'https://customer.example'): Site
    {
        $organization = Organization::query()->create(['name' => 'Fixture organization', 'slug' => 'fixture-'.bin2hex(random_bytes(6))]);

        return $organization->sites()->create([
            'name' => 'Fixture site',
            'origin' => $origin,
            'environment' => $environment,
            'public_key' => 'site_'.($environment === 'production' ? 'prod' : 'test').'_'.bin2hex(random_bytes(16)),
            'actions' => ['login', 'comment', 'otp'],
            'is_active' => true,
        ]);
    }

    private function issue(Site $site): array
    {
        return $this->challenge($site, ['Origin' => $site->origin])->assertCreated()->assertJsonPath('provider', 'sha256-pow-v1')->json();
    }

    private function challenge(Site $site, array $headers, array $overrides = []): TestResponse
    {
        return $this->postJson('/api/v1/challenges', array_replace(['site_key' => $site->public_key, 'action' => 'login'], $overrides), $headers);
    }

    private function solve(Site $site, string $id, int $number): TestResponse
    {
        return $this->postJson('/api/v1/challenges/'.$id.'/solve', [
            'site_key' => $site->public_key,
            'action' => 'login',
            'number' => $number,
        ], ['Origin' => $site->origin]);
    }

    private function verify(Site $site, string $token, string $secret, array $overrides = []): TestResponse
    {
        return $this->postJson('/api/v1/verify', array_replace([
            'site_key' => $site->public_key,
            'token' => $token,
            'expected_action' => 'login',
            'expected_origin' => $site->origin,
            'expected_environment' => $site->environment,
        ], $overrides), ['Authorization' => 'Bearer '.$secret]);
    }

    private function solution(array $challenge): int
    {
        for ($number = 0; $number <= $challenge['max_number']; $number++) {
            if (hash('sha256', $challenge['salt'].$number) === $challenge['challenge']) {
                return $number;
            }
        }

        $this->fail('No valid result within the advertised puzzle bounds.');
    }
}
