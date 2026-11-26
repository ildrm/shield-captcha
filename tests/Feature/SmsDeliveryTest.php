<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteCredential;
use App\Models\SmsDelivery;
use App\Models\User;
use App\Settings\ProviderSelection;
use App\Shield\SiteKeys;
use App\Sms\KavenegarSettings;
use App\Sms\TwilioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SmsDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const FAKE_KEY = 'kavenegar_test_only_000000';

    private const URL = 'https://api.kavenegar.com/v1/'.self::FAKE_KEY.'/verify/lookup.json';

    private const TWILIO_ACCOUNT = 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const TWILIO_SERVICE = 'MGbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const TWILIO_API_KEY = 'SKcccccccccccccccccccccccccccccccc';

    private const TWILIO_SECRET = 'twilio_fixture_only_123456789';

    private const TWILIO_MESSAGE = 'SMdddddddddddddddddddddddddddddddd';

    private const TWILIO_URL = 'https://api.twilio.com/2010-04-01/Accounts/'.self::TWILIO_ACCOUNT.'/Messages.json';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sms.kavenegar.enabled', true);
        config()->set('sms.kavenegar.templates', ['signup-otp']);
        Http::preventStrayRequests();
    }

    public function test_provider_acceptance_through_otp_assessment_is_idempotent_and_canonical(): void
    {
        $site = $this->site();
        $secret = $this->configure($site);
        $assessmentSecret = app(SiteKeys::class)->issue($site, 'assess');
        $outerTransactionLevel = DB::transactionLevel();
        Http::fake([self::URL => function () use ($outerTransactionLevel) {
            $this->assertSame($outerTransactionLevel, DB::transactionLevel(), 'Carrier calls must run outside the dispatch transaction.');

            return Http::response($this->providerResponse());
        }]);

        // A public assessment with the same event ID cannot stand in for the delivery assessment.
        $this->postJson('/api/v1/sms/assess', [
            'site_key' => $site->public_key,
            'action' => 'signup',
            'event_id' => 'test-event-sms-001',
            'destination_hash' => str_repeat('c', 64),
        ], ['Authorization' => 'Bearer '.$assessmentSecret])->assertOk();

        $first = $this->send($site, $secret)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeaderMissing('Access-Control-Allow-Origin')->assertJsonPath('success', true)
            ->assertJsonPath('state', 'accepted')->assertJsonPath('provider_reference', '903438271');
        $id = $first->json('delivery_id');
        $this->assertNotEmpty($first->json('request_id'));

        $this->send($site, $secret, ['destination' => '+989123456789'])->assertOk()->assertJsonPath('delivery_id', $id);
        $this->send($site, $secret, ['otp' => '654321'])->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
        $this->send($site, $secret, ['destination' => '09351234567'])->assertConflict()->assertJsonPath('code', 'idempotency_conflict');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::URL && $request->method() === 'POST'
            && $request->data() === ['receptor' => '09123456789', 'token' => '012345', 'template' => 'signup-otp', 'type' => 'sms']);

        $this->assertDatabaseCount('shield_sms_deliveries', 1);
        $this->assertDatabaseCount('shield_sms_assessments', 2);
        $this->assertDatabaseHas('shield_sms_deliveries', ['id' => $id, 'state' => 'accepted', 'provider_reference' => '903438271']);
        $this->assertStringNotContainsString('09123456789', $first->getContent());
        $this->assertStringNotContainsString('012345', (string) json_encode(SmsDelivery::query()->firstOrFail()));
        $this->assertStringNotContainsString(self::FAKE_KEY, $first->getContent());

        $encrypted = DB::table(KavenegarSettings::SETTINGS_TABLE)->where('site_id', $site->id)->value('api_key');
        $this->assertNotSame(self::FAKE_KEY, $encrypted);
        $this->assertSame(self::FAKE_KEY, Crypt::decryptString($encrypted));
    }

    public function test_destination_limits_are_tenant_bound_and_survive_provider_rotation(): void
    {
        $org = Organization::create(['name' => 'SMS tenant', 'slug' => 'sms-tenant', 'is_active' => true]);
        $first = $this->site($org);
        $second = $this->site($org);
        $secret = $this->configure($first);
        $this->configure($second);
        Http::fake(['https://api.kavenegar.com/*' => fn () => Http::response($this->providerResponse())]);

        $this->send($first, $secret)->assertOk();
        $newSecret = $this->configure($second, providerKey: 'new_kavenegar_test_only_00000');
        $secondEvent = ['event_id' => 'test-event-sms-002', 'destination' => '00989123456789'];
        $this->send($second, $newSecret, $secondEvent)->assertTooManyRequests()->assertJsonPath('code', 'sms_throttled');

        $other = $this->site();
        $otherSecret = $this->configure($other);
        $this->send($other, $otherSecret, $secondEvent)->assertOk();
        Http::assertSentCount(2);
    }

    public function test_admin_switches_the_only_sms_transport_and_existing_receipts_do_not_dispatch_again(): void
    {
        $site = $this->site();
        $secret = $this->configure($site);
        $this->assertNull($this->configureTwilio($site));
        $onlyKavenegar = $this->site();
        $kavenegarOnlySecret = $this->configure($onlyKavenegar);
        $operator = $this->operator();
        $selection = app(ProviderSelection::class);
        Http::fake([
            self::URL => fn () => Http::response($this->providerResponse()),
            self::TWILIO_URL => fn () => Http::response($this->twilioResponse(), 201),
        ]);

        $legacy = $this->send($site, $secret)->assertOk()->assertJsonPath('provider', 'kavenegar')->json('delivery_id');
        $selection->update($operator, [], 'twilio');
        $this->send($onlyKavenegar, $kavenegarOnlySecret)->assertServiceUnavailable()->assertJsonPath('code', 'sms_provider_unavailable');
        $this->send($site, $secret, ['destination' => '+989123456789'])
            ->assertOk()->assertJsonPath('delivery_id', $legacy)->assertJsonPath('provider', 'kavenegar');
        $this->send($site, $secret, ['event_id' => 'test-event-sms-002', 'destination' => '00989123456789'])
            ->assertTooManyRequests()->assertJsonPath('code', 'sms_throttled');
        $this->send($site, $secret, ['provider' => 'kavenegar'])->assertUnprocessable();

        $twilioInput = ['event_id' => 'test-event-sms-003', 'destination' => '+14155550123'];
        $receipt = $this->send($site, $secret, $twilioInput)->assertOk()
            ->assertJsonPath('provider', 'twilio')->assertJsonPath('provider_reference', self::TWILIO_MESSAGE)
            ->assertJsonMissing(['otp' => '012345']);
        $twilioId = $receipt->json('delivery_id');

        config()->set('sms.kavenegar.enabled', false);
        $selection->update($operator, [], 'kavenegar');
        $this->send($site, $secret, $twilioInput)->assertOk()->assertJsonPath('delivery_id', $twilioId);
        $this->send($site, $secret, ['event_id' => 'test-event-sms-004', 'destination' => '09351234567'])
            ->assertOk()->assertJsonPath('provider', 'kavenegar');
        $selection->update($operator, [], null);
        $this->send($site, $secret, $twilioInput)->assertOk()->assertJsonPath('provider', 'twilio');
        $this->send($site, $secret, ['event_id' => 'test-event-sms-005'])->assertServiceUnavailable()
            ->assertJsonPath('code', 'sms_delivery_disabled');

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::TWILIO_URL
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode(self::TWILIO_API_KEY.':'.self::TWILIO_SECRET))
            && $request->data() === [
                'To' => '+14155550123',
                'MessagingServiceSid' => self::TWILIO_SERVICE,
                'Body' => 'Your verification code is 012345.',
                'ContentRetention' => 'discard',
            ]);
        $this->assertDatabaseHas('shield_sms_deliveries', ['id' => $twilioId, 'provider' => 'twilio', 'provider_reference' => self::TWILIO_MESSAGE]);
    }

    public function test_twilio_normalizes_iranian_numbers_and_an_ambiguous_carrier_reply_cannot_be_resent(): void
    {
        $site = $this->site();
        $secret = $this->configureTwilio($site);
        app(ProviderSelection::class)->update($this->operator(), [], 'twilio');
        Http::fake([self::TWILIO_URL => Http::sequence()
            ->pushFailedConnection('Private carrier details')
            ->push(['code' => 21610, 'message' => 'Private carrier details'], 400)]);

        $this->send($site, $secret, ['destination' => '00989123456789'])->assertConflict()
            ->assertJsonPath('code', 'sms_delivery_unknown');
        $this->send($site, $secret, ['destination' => '+989123456789'])->assertConflict()
            ->assertJsonPath('code', 'sms_delivery_unknown');
        $this->send($site, $secret, ['destination' => '+989123456789', 'otp' => '654321'])->assertConflict()
            ->assertJsonPath('code', 'idempotency_conflict');
        $this->send($site, $secret, ['event_id' => 'test-event-sms-002', 'destination' => '+14155550123'])
            ->assertStatus(502)->assertJsonPath('code', 'sms_provider_rejected')->assertDontSee('Private carrier details');
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::TWILIO_URL && $request->data()['To'] === '+989123456789');
        $this->assertDatabaseHas('shield_sms_deliveries', ['site_id' => $site->id, 'provider' => 'twilio', 'state' => 'unknown']);
        $this->assertDatabaseHas('shield_sms_deliveries', ['site_id' => $site->id, 'provider' => 'twilio', 'state' => 'rejected']);
    }

    public function test_provider_provisioning_encrypts_twilio_credentials_and_keeps_the_shared_key_until_the_last_provider_is_removed(): void
    {
        $site = $this->site();
        $secret = $this->configureTwilio($site);
        $this->assertNull(app(KavenegarSettings::class)->configure($site, 'signup', 'signup-otp', self::FAKE_KEY));
        $encrypted = DB::table(TwilioSettings::SETTINGS_TABLE)->where('site_id', $site->id)->value('credentials');
        $this->assertStringNotContainsString(self::TWILIO_SECRET, $encrypted);
        $this->assertStringNotContainsString(self::TWILIO_API_KEY, $encrypted);
        $this->assertSame(self::TWILIO_SECRET, json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR)['api_key_secret']);

        $key = SiteCredential::query()->where('site_id', $site->id)->where('secret_digest', hash('sha256', $secret));
        $this->artisan('shield:sms:twilio', ['site' => $site->public_key, '--disable' => true])->assertSuccessful();
        $this->assertNull($key->value('revoked_at'));
        $this->assertDatabaseMissing(TwilioSettings::SETTINGS_TABLE, ['site_id' => $site->id]);
        $this->artisan('shield:sms:kavenegar', ['site' => $site->public_key, '--disable' => true])->assertSuccessful();
        $this->assertNotNull($key->value('revoked_at'));
        Http::assertNothingSent();
    }

    public function test_send_route_is_gated_by_credentials_site_and_template(): void
    {
        $site = $this->site();
        $other = $this->site();
        $secret = $this->configure($site);
        $assessmentSecret = app(SiteKeys::class)->issue($site, 'assess');
        Http::fake();

        $this->send($site, $assessmentSecret)->assertUnauthorized();
        $this->send($other, $secret)->assertUnauthorized();
        $this->send($site, $secret, ['action' => 'reset'])->assertForbidden()->assertJsonPath('code', 'context_mismatch');
        $this->send($site, $secret, ['destination' => '+14165550123'])->assertUnprocessable();
        $this->send($site, $secret, ['destination' => '02123456789'])->assertUnprocessable();
        $this->send($site, $secret, ['otp' => 123456])->assertUnprocessable();
        $this->send($site, $secret, ['template' => 'client-choice'])->assertUnprocessable();

        config()->set('sms.kavenegar.templates', []);
        $this->send($site, $secret)->assertStatus(503)->assertJsonPath('code', 'sms_provider_unavailable');
        config()->set('sms.kavenegar.enabled', false);
        $this->send($site, $secret)->assertStatus(503)->assertJsonPath('code', 'sms_delivery_disabled');

        Http::assertNothingSent();
        $this->assertDatabaseEmpty('shield_sms_deliveries');
    }

    public function test_sandbox_and_sites_without_dns_verification_cannot_provision(): void
    {
        $sandbox = $this->site(environment: 'sandbox');
        $unverified = $this->site();
        $unverified->update(['domain_verified_at' => null]);

        foreach ([$sandbox, $unverified] as $site) {
            try {
                $this->configure($site);
                $this->fail('Provisioning must refuse non-production or unverified sites.');
            } catch (ValidationException) {
                $this->assertDatabaseMissing(KavenegarSettings::SETTINGS_TABLE, ['site_id' => $site->id]);
            }
        }

        Http::fake();
        // Delivery authentication still enforces the environment if a bad manual import bypasses SiteKeys.
        $secret = 'sk_sms_send_'.str_repeat('a', 64);
        SiteCredential::create([
            'site_id' => $sandbox->id,
            'scope' => 'sms_send',
            'secret_digest' => hash('sha256', $secret),
            'prefix' => substr($secret, 0, 20),
        ]);
        $this->send($sandbox, $secret)->assertForbidden()->assertJsonPath('code', 'sms_production_required');
        Http::assertNothingSent();
    }

    public function test_provider_rejection_and_timeout_never_open_a_second_send(): void
    {
        $site = $this->site();
        $secret = $this->configure($site);
        Http::fake([self::URL => Http::sequence()
            ->push(['return' => ['status' => 424, 'message' => 'Provider internal details']], 424)
            ->pushFailedConnection('Sensitive URL '.self::URL)]);

        $reject = $this->send($site, $secret)->assertStatus(502)->assertJsonPath('code', 'sms_provider_rejected');
        $this->send($site, $secret)->assertStatus(502)->assertJsonPath('code', 'sms_provider_rejected');
        $this->assertStringNotContainsString('Provider internal details', $reject->getContent());

        $otherEvent = ['event_id' => 'test-event-sms-003', 'destination' => '09351234567'];
        $unknown = $this->send($site, $secret, $otherEvent)->assertConflict()->assertJsonPath('code', 'sms_delivery_unknown');
        $this->send($site, $secret, $otherEvent)->assertConflict()->assertJsonPath('code', 'sms_delivery_unknown');
        $this->assertStringNotContainsString(self::FAKE_KEY, $unknown->getContent());

        Http::assertSentCount(2);
        $this->assertDatabaseHas('shield_sms_deliveries', ['state' => 'rejected', 'site_id' => $site->id]);
        $this->assertDatabaseHas('shield_sms_deliveries', ['state' => 'unknown', 'site_id' => $site->id]);
    }

    public function test_existing_dispatch_claim_cannot_be_reused_before_or_after_timeout(): void
    {
        $site = $this->site();
        $secret = $this->configure($site);
        Http::fake([self::URL => Http::response($this->providerResponse())]);

        $this->send($site, $secret)->assertOk();
        // Simulate interruption immediately after the durable dispatch claim.
        SmsDelivery::query()->firstOrFail()->update(['state' => SmsDelivery::DISPATCHING, 'provider_reference' => null]);
        $this->send($site, $secret)->assertConflict()->assertJsonPath('code', 'sms_dispatch_in_progress');
        $this->travel(21)->seconds();
        $this->send($site, $secret)->assertConflict()->assertJsonPath('code', 'sms_delivery_unknown');
        Http::assertSentCount(1);
    }

    public function test_oversized_provider_response_remains_ambiguous_without_another_dispatch(): void
    {
        $site = $this->site();
        $secret = $this->configure($site);
        $body = json_encode($this->providerResponse(), JSON_THROW_ON_ERROR).str_repeat(' ', 16_384);
        Http::fake([self::URL => Http::response($body)]);

        $this->send($site, $secret)->assertConflict()->assertJsonPath('code', 'sms_delivery_unknown');
        $this->send($site, $secret)->assertConflict()->assertJsonPath('code', 'sms_delivery_unknown');
        $this->assertDatabaseHas('shield_sms_deliveries', ['state' => 'unknown', 'site_id' => $site->id]);
        Http::assertSentCount(1);
    }

    public function test_reconfiguration_and_disable_revoke_old_spend_credentials(): void
    {
        $site = $this->site();
        $first = $this->configure($site);
        $second = $this->configure($site);
        Http::fake();

        $this->send($site, $first)->assertUnauthorized();
        $this->artisan('shield:sms:kavenegar', ['site' => $site->public_key, '--disable' => true])->assertSuccessful();
        $this->send($site, $second)->assertUnauthorized();
        Http::assertNothingSent();
        $this->assertDatabaseMissing(KavenegarSettings::SETTINGS_TABLE, ['site_id' => $site->id]);
    }

    public function test_retention_prunes_old_receipts_and_keeps_recent_dispatch_claims(): void
    {
        $site = $this->site();
        $secret = $this->configure($site);
        Http::fake([self::URL => fn () => Http::response($this->providerResponse())]);

        $oldId = $this->send($site, $secret)->assertOk()->json('delivery_id');
        $this->travel(31)->days();
        $recentId = $this->send($site, $secret, ['event_id' => 'test-event-sms-008'])->assertOk()->json('delivery_id');

        $this->artisan('shield:sms:prune')->assertSuccessful();
        $this->assertDatabaseMissing('shield_sms_deliveries', ['id' => $oldId]);
        $this->assertDatabaseHas('shield_sms_deliveries', ['id' => $recentId]);
        Http::assertSentCount(2);
    }

    private function site(?Organization $organization = null, string $environment = 'production'): Site
    {
        $organization ??= Organization::create([
            'name' => 'SMS tenant',
            'slug' => 'sms-'.bin2hex(random_bytes(6)),
            'is_active' => true,
        ]);

        return $organization->sites()->create([
            'name' => 'SMS site',
            'public_key' => ($environment === 'production' ? 'site_prod_' : 'site_test_').bin2hex(random_bytes(16)),
            'environment' => $environment,
            'origin' => 'https://sms.example.test',
            'domain_verified_at' => $environment === 'production' ? now() : null,
            'actions' => ['signup', 'reset'],
            'is_active' => true,
        ]);
    }

    private function configure(Site $site, string $providerKey = self::FAKE_KEY): string
    {
        return app(KavenegarSettings::class)->configure($site, 'signup', 'signup-otp', $providerKey);
    }

    private function configureTwilio(Site $site): ?string
    {
        return app(TwilioSettings::class)->configure($site, 'signup', self::TWILIO_ACCOUNT, self::TWILIO_SERVICE, self::TWILIO_API_KEY, self::TWILIO_SECRET);
    }

    private function operator(): User
    {
        $operator = User::factory()->create();
        $operator->forceFill(['is_operator' => true, 'app_authentication_secret' => 'TESTOPERATORSECRETFORMFA'])->save();

        return $operator;
    }

    private function twilioResponse(): array
    {
        return [
            'sid' => self::TWILIO_MESSAGE,
            'account_sid' => self::TWILIO_ACCOUNT,
            'messaging_service_sid' => self::TWILIO_SERVICE,
            'status' => 'accepted',
        ];
    }

    private function send(Site $site, string $secret, array $changes = []): TestResponse
    {
        return $this->postJson('/api/v1/sms/send', $changes + [
            'site_key' => $site->public_key,
            'action' => 'signup',
            'event_id' => 'test-event-sms-001',
            'destination' => '09123456789',
            'otp' => '012345',
        ], ['Authorization' => 'Bearer '.$secret, 'Origin' => $site->origin]);
    }

    private function providerResponse(): array
    {
        return [
            'return' => ['status' => 200, 'message' => 'Accepted'],
            'entries' => [[
                'messageid' => 903438271,
                'status' => 5,
                'receptor' => '09123456789',
                'message' => 'Your code is 012345',
            ]],
        ];
    }
}
