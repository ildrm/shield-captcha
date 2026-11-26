<?php

namespace Tests\Feature;

use App\Models\BillingInvoice;
use App\Models\Organization;
use App\Models\User;
use App\Settings\ProviderSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StripeBillingTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.stripe.com/v1/checkout/sessions';

    private const SECRET = 'sk_live_fixtureabcdefghijklmnop';

    private const SIGNING_SECRET = 'whsec_fixtureabcdefghijklmnop';

    private const LIVE_SESSION = 'cs_live_a123456789012345';

    private const TEST_SESSION = 'cs_test_a123456789012345';

    private const PAYMENT = 'pi_a123456789012345678901234';

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'app.url' => 'https://billing.example.test',
            'billing.zarinpal.enabled' => false,
            'billing.zarinpal.environment' => 'production',
            'billing.zarinpal.merchant_id' => 'cd1ba656-41a5-45cf-b8d5-4a9dd0c1a24a',
            'billing.plan.amount_irr' => '700000',
            'billing.plan.term_days' => 30,
            'billing.plan.daily_verifications' => 45000,
            'billing.stripe.secret_key' => self::SECRET,
            'billing.stripe.webhook_secret' => self::SIGNING_SECRET,
            'billing.stripe.currency' => 'USD',
            'billing.stripe.amount_minor' => 1299,
        ]);
        $this->operator = User::factory()->create();
        $this->operator->forceFill([
            'is_operator' => true,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ])->save();
    }

    public function test_multiple_available_gateways_are_a_customer_choice_and_checkout_stays_bound_to_it(): void
    {
        $organization = $this->loginOwner();
        $this->select(['zarinpal', 'stripe']);
        $this->mockCheckout();
        $key = (string) Str::uuid();

        $this->get($this->path('billing.index', $organization))->assertOk()->assertSee('Pay with ZarinPal')
            ->assertSee('Pay with Stripe')->assertSee('700,000 IRR')->assertSee('12.99 USD');
        $this->post($this->path('billing.checkout', $organization), ['checkout_key' => $key])->assertStatus(422);
        $this->checkout($organization, $key)->assertStatus(303)->assertRedirect($this->url(self::LIVE_SESSION));
        $this->post($this->path('billing.checkout', $organization), [
            'checkout_key' => $key,
            'provider' => 'zarinpal',
        ])->assertStatus(409);

        config(['billing.stripe.amount_minor' => 1500, 'billing.stripe.currency' => 'EUR']);
        $this->checkout($organization, $key)->assertRedirect($this->url(self::LIVE_SESSION));
        $invoice = BillingInvoice::query()->sole();
        $this->assertSame('stripe', $invoice->provider);
        $this->assertSame(1299, $invoice->minorAmount());
        $this->assertSame('USD', $invoice->currency);
        $this->assertNull($invoice->amount_irr);
        $this->assertSame(self::LIVE_SESSION, $invoice->stripe_session_id);
        $this->assertSame($this->url(self::LIVE_SESSION), $invoice->stripe_checkout_url);
        $this->assertNotSame($invoice->stripe_checkout_url, $invoice->getRawOriginal('stripe_checkout_url'));
        $this->assertArrayNotHasKey('stripe_session_id', $invoice->toArray());
        $this->assertArrayNotHasKey('stripe_checkout_url', $invoice->toArray());
        $this->assertDatabaseCount('billing_invoices', 1);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer '.self::SECRET)
            && $request->hasHeader('Idempotency-Key', 'shield-invoice-'.$invoice->id)
            && $request->data()['client_reference_id'] === $invoice->id
            && (int) $request->data()['line_items'][0]['price_data']['unit_amount'] === 1299
            && $request->data()['line_items'][0]['price_data']['currency'] === 'usd'
            && $request->data()['success_url'] === 'https://billing.example.test/billing/stripe/callback/'.$invoice->id.'?session_id={CHECKOUT_SESSION_ID}');
    }

    public function test_deselecting_stripe_stops_new_checkouts_and_its_signed_webhook_still_settles_an_existing_invoice_once(): void
    {
        $this->freezeTime();
        $organization = $this->loginOwner();
        $this->select(['stripe']);
        $invoice = $this->openStripeInvoice($organization);
        $this->mockVerification($invoice);
        $this->select(['zarinpal']);

        $this->get($this->path('billing.index', $organization))->assertOk()->assertSee('Pay with ZarinPal')->assertDontSee('Pay with Stripe');
        $this->checkout($organization)->assertStatus(503);
        $this->checkout($organization, $invoice->checkout_key)->assertRedirect($this->path('billing.receipt', $organization, $invoice));
        $this->get($this->path('billing.receipt', $organization, $invoice))->assertOk()->assertDontSee('Continue checkout');
        $this->webhook($invoice)->assertNoContent();
        $this->webhook($invoice)->assertNoContent();
        $this->get($this->callbackUrl($invoice))->assertStatus(303)->assertHeader('Referrer-Policy', 'no-referrer');

        $invoice->refresh();
        $this->assertSame(BillingInvoice::PAID, $invoice->status);
        $this->assertSame(self::PAYMENT, $invoice->provider_reference);
        $this->assertNull($invoice->verified_code);
        $this->assertDatabaseCount('billing_subscriptions', 1);
        $this->assertSame(45000, $invoice->subscription->daily_verifications);
        $this->get($this->path('billing.receipt', $organization, $invoice))->assertOk()->assertSee('12.99 USD')
            ->assertSee(self::PAYMENT)->assertDontSee(self::LIVE_SESSION);
        Http::assertSentCount(1);
    }

    public function test_verification_survives_a_same_mode_secret_rotation(): void
    {
        $organization = $this->loginOwner();
        $this->select(['stripe']);
        $invoice = $this->openStripeInvoice($organization);
        $this->mockVerification($invoice);
        $rotated = 'sk_live_rotatedfixtureabcdef';
        config(['billing.stripe.secret_key' => $rotated]);

        $this->webhook($invoice)->assertNoContent();
        $this->assertSame(BillingInvoice::PAID, $invoice->refresh()->status);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === self::API.'/'.$invoice->stripe_session_id
            && $request->hasHeader('Authorization', 'Bearer '.$rotated));
    }

    public function test_webhook_checks_signature_timestamp_and_saved_session_before_requesting_verification(): void
    {
        $this->freezeTime();
        $organization = $this->loginOwner();
        $this->select(['stripe']);
        $invoice = $this->openStripeInvoice($organization);
        $event = $this->event($invoice);

        $this->call('POST', '/billing/stripe/webhook', server: ['CONTENT_TYPE' => 'application/json'], content: $event)->assertStatus(400);
        $this->sendWebhook($event, now()->timestamp - 301)->assertStatus(400);
        $this->sendWebhook($event.str_repeat(' ', 262_144))->assertStatus(400);
        $this->sendWebhook(json_encode(array_replace_recursive(json_decode($event, true), [
            'data' => ['object' => ['id' => 'cs_live_d123456789012345']],
        ]), JSON_THROW_ON_ERROR))->assertNoContent();
        $this->get($this->callbackUrl($invoice, 'cs_live_d123456789012345'))->assertNotFound();
        Http::assertSentCount(1);
        $this->assertSame(BillingInvoice::PENDING, $invoice->refresh()->status);
        $this->assertDatabaseCount('billing_subscriptions', 0);
    }

    public function test_webhook_and_browser_return_require_a_paid_matching_stripe_api_session(): void
    {
        $this->freezeTime();
        $organization = $this->loginOwner();
        $this->select(['stripe']);
        $invoice = $this->openStripeInvoice($organization);
        Http::fakeSequence(self::API.'/'.$invoice->stripe_session_id)
            ->push($this->stripeSession($invoice, ['amount_total' => 1]))
            ->push($this->stripeSession($invoice, ['payment_status' => 'unpaid']))
            ->push($this->stripeSession($invoice, ['currency' => 'eur']))
            ->push($this->stripeSession($invoice));

        $this->webhook($invoice)->assertStatus(503);
        $this->get($this->callbackUrl($invoice))->assertStatus(303);
        Http::assertSentCount(1);
        $this->travel(21)->seconds();
        $this->get($this->callbackUrl($invoice))->assertStatus(303);
        $this->travel(21)->seconds();
        $this->webhook($invoice)->assertStatus(503);
        $this->assertSame(BillingInvoice::PENDING, $invoice->refresh()->status);
        $this->assertDatabaseCount('billing_subscriptions', 0);

        $this->travel(21)->seconds();
        $this->webhook($invoice)->assertNoContent();
        $this->assertDatabaseCount('billing_subscriptions', 1);
    }

    public function test_stripe_test_mode_never_enables_a_production_subscription(): void
    {
        config(['billing.stripe.secret_key' => 'sk_test_fixtureabcdefghijklmnop']);
        $organization = $this->loginOwner();
        $this->select(['stripe']);
        $invoice = $this->openStripeInvoice($organization, self::TEST_SESSION);
        $this->mockVerification($invoice);

        $this->get($this->callbackUrl($invoice))->assertStatus(303);
        $this->assertSame('sandbox', $invoice->environment);
        $this->assertSame(BillingInvoice::PAID, $invoice->refresh()->status);
        $this->assertDatabaseCount('billing_subscriptions', 0);
        Http::assertSentCount(1);
    }

    public function test_checkout_requires_admin_selection_and_complete_provider_configuration(): void
    {
        $organization = $this->loginOwner();
        $this->checkout($organization)->assertStatus(503);
        $this->select(['stripe']);
        config(['billing.stripe.webhook_secret' => null]);
        $this->get($this->path('billing.index', $organization))->assertOk()->assertDontSee('Pay with Stripe');
        $this->checkout($organization)->assertStatus(503);
        Http::assertNothingSent();
        $this->assertDatabaseCount('billing_invoices', 0);
    }

    private function loginOwner(): Organization
    {
        $organization = Organization::query()->create([
            'name' => 'Stripe Team',
            'slug' => 'stripe-team',
            'is_active' => true,
        ]);
        $owner = User::factory()->create();
        $organization->members()->attach($owner, ['role' => 'owner']);
        $this->actingAs($owner, 'web');

        return $organization;
    }

    private function select(array $providers): void
    {
        app(ProviderSelection::class)->update($this->operator, $providers, null);
    }

    private function openStripeInvoice(Organization $organization, string $session = self::LIVE_SESSION): BillingInvoice
    {
        $this->mockCheckout($session);
        $this->checkout($organization)->assertRedirect($this->url($session));

        return BillingInvoice::query()->sole();
    }

    private function checkout(Organization $organization, ?string $key = null): TestResponse
    {
        return $this->post($this->path('billing.checkout', $organization), [
            'checkout_key' => $key ?? (string) Str::uuid(),
            'provider' => 'stripe',
        ]);
    }

    private function mockCheckout(string $session = self::LIVE_SESSION): void
    {
        Http::fake([self::API => fn (Request $request) => Http::response($this->stripeSession(
            BillingInvoice::query()->findOrFail($request->data()['client_reference_id']),
            ['id' => $session, 'status' => 'open', 'payment_status' => 'unpaid', 'url' => $this->url($session)],
        ))]);
    }

    private function mockVerification(BillingInvoice $invoice): void
    {
        Http::fake([self::API.'/'.$invoice->stripe_session_id => fn () => Http::response($this->stripeSession($invoice))]);
    }

    private function stripeSession(BillingInvoice $invoice, array $changes = []): array
    {
        return array_replace([
            'id' => $invoice->stripe_session_id,
            'object' => 'checkout.session',
            'status' => 'complete',
            'mode' => 'payment',
            'payment_status' => 'paid',
            'livemode' => $invoice->environment === 'production',
            'client_reference_id' => $invoice->id,
            'metadata' => ['invoice_id' => $invoice->id],
            'amount_total' => $invoice->minorAmount(),
            'currency' => strtolower($invoice->currency),
            'payment_intent' => self::PAYMENT,
        ], $changes);
    }

    private function event(BillingInvoice $invoice): string
    {
        return json_encode([
            'id' => 'evt_testabcdefghijkl',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'livemode' => $invoice->environment === 'production',
            'data' => ['object' => $this->stripeSession($invoice)],
        ], JSON_THROW_ON_ERROR);
    }

    private function webhook(BillingInvoice $invoice): TestResponse
    {
        return $this->sendWebhook($this->event($invoice));
    }

    private function sendWebhook(string $event, ?int $time = null): TestResponse
    {
        $time ??= now()->timestamp;
        $signature = hash_hmac('sha256', $time.'.'.$event, self::SIGNING_SECRET);

        return $this->call('POST', '/billing/stripe/webhook', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.$signature,
        ], content: $event);
    }

    private function path(string $name, Organization $organization, ?BillingInvoice $invoice = null): string
    {
        return route($name, array_filter(['organization' => $organization->slug, 'invoice' => $invoice?->id]), false);
    }

    private function callbackUrl(BillingInvoice $invoice, ?string $session = null): string
    {
        return route('billing.stripe.callback', ['invoice' => $invoice->id, 'session_id' => $session ?? $invoice->stripe_session_id], false);
    }

    private function url(string $session): string
    {
        return 'https://checkout.stripe.com/c/pay/'.$session.'#arbitrary%2Ffragment';
    }
}
