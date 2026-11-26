<?php

namespace Tests\Feature;

use App\Billing\BillingCheckout;
use App\Billing\BillingEntitlements;
use App\Models\BillingInvoice;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingFlowTest extends TestCase
{
    use RefreshDatabase;

    private const MERCHANT = '4a434457-41d0-4cf6-9458-50aa67de497a';

    private const LIVE_HOST = 'https://payment.zarinpal.com';

    private const SANDBOX_HOST = 'https://sandbox.zarinpal.com';

    private const API_PATH = '/pg/v4/payment/';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'app.url' => 'https://billing.example.test',
            'billing.zarinpal.enabled' => true,
            'billing.zarinpal.environment' => 'production',
            'billing.zarinpal.merchant_id' => self::MERCHANT,
            'billing.plan.amount_irr' => '1250000',
            'billing.plan.term_days' => 30,
            'billing.plan.daily_verifications' => 50000,
        ]);
    }

    public function test_checkout_uses_saved_terms_and_reuses_same_session_for_form_replay(): void
    {
        $organization = $this->organization();
        $authority = $this->authority();
        $this->mockRequest($authority);
        $key = (string) Str::uuid();

        $this->get($this->billingUrl('billing.index', $organization))
            ->assertOk()->assertSee('1,250,000 IRR')->assertSee('name="_token"', false);
        $response = $this->post('http://host-header.example.test'.$this->billingUrl('billing.checkout', $organization), [
            'checkout_key' => $key,
            'amount_irr' => 1,
            'environment' => 'sandbox',
        ]);
        $response->assertStatus(303)->assertRedirect(self::LIVE_HOST.'/pg/StartPay/'.$authority);

        $invoice = BillingInvoice::query()->sole();
        $this->assertSame($organization->id, $invoice->organization_id);
        $this->assertSame(1_250_000, $invoice->amount_irr);
        $this->assertSame('production', $invoice->environment);
        $this->assertSame(30, $invoice->term_days);
        $this->assertSame(50000, $invoice->daily_verifications);
        $this->assertSame(BillingInvoice::PENDING, $invoice->status);
        $this->assertArrayNotHasKey('authority', $invoice->toArray());

        config(['billing.plan.amount_irr' => 2_000_000]);
        $this->post($this->billingUrl('billing.checkout', $organization), ['checkout_key' => strtoupper($key)])->assertStatus(303)
            ->assertRedirect(self::LIVE_HOST.'/pg/StartPay/'.$authority);
        $this->assertDatabaseCount('billing_invoices', 1);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === self::LIVE_HOST.self::API_PATH.'request.json'
            && $request->data() === [
                'merchant_id' => self::MERCHANT,
                'amount' => 1_250_000,
                'currency' => 'IRR',
                'description' => 'Shield CAPTCHA access',
                'callback_url' => 'https://billing.example.test/billing/zarinpal/callback/'.$invoice->id,
                'metadata' => ['order_id' => $invoice->id, 'auto_verify' => false],
            ]);
    }

    public function test_paid_callback_verifies_once_and_keeps_card_metadata_out_of_the_invoice(): void
    {
        $this->freezeTime();
        $organization = $this->organization();
        $authority = $this->authority();
        $this->mockRequest($authority);
        $this->mockVerification()->push([
            'data' => ['code' => 100, 'ref_id' => 381, 'card_pan' => 'raw-or-masked', 'card_hash' => 'unused'],
            'errors' => [],
        ]);
        $invoice = $this->checkout($organization);

        $this->get($this->callbackUrl($invoice, $authority))->assertStatus(303)
            ->assertRedirect(route('billing.return', ['invoice' => $invoice->id]))
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get($this->callbackUrl($invoice, $authority))->assertStatus(303);
        $this->get(route('billing.return', ['invoice' => $invoice->id], false))->assertRedirect(
            $this->billingUrl('billing.receipt', $organization, $invoice),
        );

        $invoice->refresh();
        $this->assertSame(BillingInvoice::PAID, $invoice->status);
        $this->assertSame('381', $invoice->provider_reference);
        $this->assertSame(100, $invoice->verified_code);
        $this->assertSame(now()->addDays(30)->toDateTimeString(), $invoice->subscription->expires_at->toDateTimeString());
        $this->assertSame(50000, app(BillingEntitlements::class)->dailyVerificationLimit($organization->id, 'production'));
        $this->assertSame(10000, app(BillingEntitlements::class)->dailyVerificationLimit($organization->id, 'sandbox'));
        $this->assertDatabaseCount('billing_subscriptions', 1);
        $this->get($this->billingUrl('billing.receipt', $organization, $invoice))->assertOk()
            ->assertSee('Paid and verified')->assertSee('381')->assertDontSee('raw-or-masked');

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->url() === self::LIVE_HOST.self::API_PATH.'verify.json'
            && $request->data() === ['merchant_id' => self::MERCHANT, 'amount' => 1_250_000, 'authority' => $authority]);
    }

    public function test_callback_must_match_invoice_and_verification_amount_and_cancellation_is_untrusted(): void
    {
        $organization = $this->organization();
        $authority = $this->authority();
        $this->mockRequest($authority);
        $this->mockVerification()->push(['data' => ['code' => 100, 'ref_id' => 381, 'amount' => 1], 'errors' => []]);
        $invoice = $this->checkout($organization);

        $this->get($this->callbackUrl($invoice, $this->authority(4)))->assertNotFound();
        $this->get($this->callbackUrl($invoice, $authority, 'NOK'))->assertStatus(303);
        $this->assertSame(BillingInvoice::PENDING, $invoice->refresh()->status);
        Http::assertSentCount(1);

        $this->get($this->callbackUrl($invoice, $authority))->assertStatus(303);
        $this->assertSame(BillingInvoice::PENDING, $invoice->refresh()->status);
        $this->assertNotNull($invoice->next_verify_at);
        $this->assertDatabaseCount('billing_subscriptions', 0);
        Http::assertSentCount(2);
    }

    public function test_lost_verification_response_can_recover_once_through_reconciliation(): void
    {
        $this->freezeTime();
        $organization = $this->organization();
        $authority = $this->authority();
        $this->mockRequest($authority);
        $this->mockVerification()->pushFailedConnection()
            ->push(['data' => ['code' => 101], 'errors' => []]);
        $invoice = $this->checkout($organization);

        $this->get($this->callbackUrl($invoice, $authority))->assertStatus(303);
        $this->assertSame(BillingInvoice::PENDING, $invoice->refresh()->status);
        $this->artisan('shield:billing:reconcile', ['invoice' => $invoice->id])->assertFailed();
        $this->assertDatabaseCount('billing_subscriptions', 0);

        $this->travel(21)->seconds();
        $this->artisan('shield:billing:reconcile', ['invoice' => $invoice->id])->assertSuccessful();
        $this->artisan('shield:billing:reconcile', ['invoice' => $invoice->id])->assertSuccessful();
        $this->assertSame(BillingInvoice::PAID, $invoice->refresh()->status);
        $this->assertSame(101, $invoice->verified_code);
        $this->assertNull($invoice->provider_reference);
        $this->assertDatabaseCount('billing_subscriptions', 1);
    }

    public function test_sandbox_is_separate_from_production_and_merchant_changes_fail_closed(): void
    {
        config(['billing.zarinpal.environment' => 'sandbox']);
        $organization = $this->organization();
        $authority = $this->authority(prefix: 'S');
        $this->mockRequest($authority, self::SANDBOX_HOST);
        $this->mockVerification(self::SANDBOX_HOST)->push(['data' => ['code' => 100, 'ref_id' => 381], 'errors' => []]);
        $invoice = $this->checkout($organization);

        config(['billing.zarinpal.environment' => 'production']);
        $this->get($this->callbackUrl($invoice, $authority))->assertStatus(303);
        $this->assertSame(BillingInvoice::PENDING, $invoice->refresh()->status);
        Http::assertSentCount(1);

        config(['billing.zarinpal.environment' => 'sandbox', 'billing.zarinpal.merchant_id' => (string) Str::uuid()]);
        $this->get($this->callbackUrl($invoice, $authority))->assertStatus(303);
        $this->assertSame(BillingInvoice::PENDING, $invoice->refresh()->status);
        Http::assertSentCount(1);

        config(['billing.zarinpal.merchant_id' => self::MERCHANT]);
        $this->get($this->callbackUrl($invoice, $authority))->assertStatus(303);
        $this->assertSame('sandbox', $invoice->environment);
        $this->assertSame(BillingInvoice::PAID, $invoice->refresh()->status);
        $this->assertDatabaseCount('billing_subscriptions', 0);
        $this->assertSame(10000, app(BillingEntitlements::class)->dailyVerificationLimit($organization->id, 'production'));
        Http::assertSentCount(2);
    }

    public function test_duplicate_verification_respects_the_durable_lease(): void
    {
        $organization = $this->organization();
        $authority = $this->authority();
        $this->mockRequest($authority);
        $invoice = $this->checkout($organization);
        $requests = 0;
        Http::fake([self::LIVE_HOST.self::API_PATH.'verify.json' => function () use ($invoice, &$requests) {
            $this->assertSame(1, ++$requests);
            $result = app(BillingCheckout::class)->reconcile($invoice);
            $this->assertSame(BillingInvoice::PENDING, $result->status);
            $this->assertNotNull($result->next_verify_at);

            return Http::response(['data' => ['code' => 100, 'ref_id' => 381], 'errors' => []]);
        }]);

        $this->get($this->callbackUrl($invoice, $authority))->assertStatus(303);
        $this->assertSame(BillingInvoice::PAID, $invoice->refresh()->status);
        $this->assertDatabaseCount('billing_subscriptions', 1);
        Http::assertSentCount(1);
    }

    public function test_a_provider_reference_cannot_settle_two_invoices(): void
    {
        $organization = $this->organization();
        $firstAuthority = $this->authority();
        $secondAuthority = $this->authority(4);
        $this->mockRequest($firstAuthority)->push(['data' => ['code' => 100, 'authority' => $secondAuthority], 'errors' => []]);
        $this->mockVerification()->push(['data' => ['code' => 100, 'ref_id' => 381], 'errors' => []])
            ->push(['data' => ['code' => 100, 'ref_id' => 381], 'errors' => []]);
        $first = $this->checkout($organization);
        $second = $this->checkout($organization);

        $this->get($this->callbackUrl($first, $firstAuthority))->assertStatus(303);
        $this->get($this->callbackUrl($second, $secondAuthority))->assertStatus(303);
        $this->assertSame(BillingInvoice::PAID, $first->refresh()->status);
        $this->assertSame(BillingInvoice::PENDING, $second->refresh()->status);
        $this->assertDatabaseCount('billing_subscriptions', 1);
    }

    public function test_renewal_preserves_old_terms_and_entitlement_expires(): void
    {
        $this->freezeTime();
        $organization = $this->organization();
        $firstAuthority = $this->authority();
        $secondAuthority = $this->authority(4);
        $request = $this->mockRequest($firstAuthority);
        $request->push(['data' => ['code' => 100, 'authority' => $secondAuthority], 'errors' => []]);
        $this->mockVerification()
            ->push(['data' => ['code' => 100, 'ref_id' => 381], 'errors' => []])
            ->push(['data' => ['code' => 100, 'ref_id' => 382], 'errors' => []]);
        $first = $this->checkout($organization);
        $this->get($this->callbackUrl($first, $firstAuthority))->assertStatus(303);
        config(['billing.plan.daily_verifications' => 60000, 'billing.plan.term_days' => 45]);
        $second = $this->checkout($organization);
        $this->get($this->callbackUrl($second, $secondAuthority))->assertStatus(303);

        $terms = BillingSubscription::query()->orderBy('starts_at')->get();
        $this->assertCount(2, $terms);
        $this->assertEquals($terms[0]->expires_at, $terms[1]->starts_at);
        $this->assertEquals(now()->addDays(75)->toDateTimeString(), $terms[1]->expires_at->toDateTimeString());
        $this->assertSame(50000, app(BillingEntitlements::class)->dailyVerificationLimit($organization->id, 'production'));

        $this->travel(31)->days();
        $this->assertSame(60000, app(BillingEntitlements::class)->dailyVerificationLimit($organization->id, 'production'));
        $this->travel(45)->days();
        $this->assertSame(10000, app(BillingEntitlements::class)->dailyVerificationLimit($organization->id, 'production'));
    }

    public function test_receipts_and_payments_are_tenant_and_role_scoped(): void
    {
        $organization = $this->organization();
        $this->mockRequest($this->authority());
        $invoice = $this->checkout($organization);
        $outsideOrganization = $this->organization();

        $this->get($this->billingUrl('billing.index', $organization))->assertForbidden();
        $this->get($this->billingUrl('billing.receipt', $outsideOrganization, $invoice))->assertNotFound();
        $this->post($this->billingUrl('billing.recheck', $outsideOrganization, $invoice))->assertNotFound();
        $this->get(route('billing.return', ['invoice' => $invoice->id], false))->assertOk()->assertDontSee($organization->name);

        $viewer = User::factory()->create();
        $organization->members()->attach($viewer, ['role' => 'viewer']);
        $this->actingAs($viewer)->get($this->billingUrl('billing.index', $organization))->assertForbidden();
        $this->post($this->billingUrl('billing.checkout', $organization), ['checkout_key' => (string) Str::uuid()])->assertForbidden();
        Http::assertSentCount(1);
    }

    public function test_checkout_requires_operator_configuration_and_https_for_production(): void
    {
        $organization = $this->organization();
        config(['billing.zarinpal.enabled' => false]);
        $this->get($this->billingUrl('billing.index', $organization))->assertOk()->assertSee('Checkout is currently unavailable.');
        $this->post($this->billingUrl('billing.checkout', $organization), ['checkout_key' => (string) Str::uuid()])->assertStatus(503);
        config(['billing.zarinpal.enabled' => true, 'app.url' => 'http://billing.example.test']);
        $this->post($this->billingUrl('billing.checkout', $organization), ['checkout_key' => (string) Str::uuid()])->assertStatus(503);
        $this->assertDatabaseCount('billing_invoices', 0);
        Http::assertNothingSent();
    }

    private function checkout(Organization $organization): BillingInvoice
    {
        $key = (string) Str::uuid();
        $this->post($this->billingUrl('billing.checkout', $organization), ['checkout_key' => $key])->assertStatus(303);

        return BillingInvoice::query()->where('checkout_key', $key)->sole();
    }

    private function organization(): Organization
    {
        $slug = 'org-'.Str::random(16);
        $organization = Organization::query()->create(['name' => $slug, 'slug' => $slug, 'is_active' => true]);
        $owner = User::factory()->create();
        $organization->members()->attach($owner, ['role' => 'owner']);
        $this->actingAs($owner, 'web');

        return $organization;
    }

    private function authority(int $digit = 3, string $prefix = 'A'): string
    {
        return $prefix.str_repeat((string) $digit, 35);
    }

    private function mockRequest(string $authority, string $host = self::LIVE_HOST): ResponseSequence
    {
        return Http::fakeSequence($host.self::API_PATH.'request.json')
            ->push(['data' => ['code' => 100, 'authority' => $authority], 'errors' => []]);
    }

    private function mockVerification(string $host = self::LIVE_HOST): ResponseSequence
    {
        return Http::fakeSequence($host.self::API_PATH.'verify.json');
    }

    private function callbackUrl(BillingInvoice $invoice, string $authority, string $status = 'OK'): string
    {
        return route('billing.zarinpal.callback', ['invoice' => $invoice->id, 'Authority' => $authority, 'Status' => $status], false);
    }

    private function billingUrl(string $route, Organization $organization, ?BillingInvoice $invoice = null): string
    {
        return route($route, array_filter(['organization' => $organization->slug, 'invoice' => $invoice?->id]), false);
    }
}
