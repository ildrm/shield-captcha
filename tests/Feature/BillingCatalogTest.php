<?php

namespace Tests\Feature;

use App\Billing\BillingEntitlements;
use App\Billing\BillingPlanSettings;
use App\Filament\Operator\Resources\BillingPlanResource\Pages\CreateBillingPlan;
use App\Filament\Operator\Resources\BillingPlanResource\Pages\EditBillingPlan;
use App\Models\BillingCatalogPlan;
use App\Models\BillingInvoice;
use App\Models\Organization;
use App\Models\User;
use App\Settings\ProviderSelection;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BillingCatalogTest extends TestCase
{
    use RefreshDatabase;

    private const ZARINPAL = 'https://payment.zarinpal.com';

    private const AUTHORITY = 'A33333333333333333333333333333333333';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'app.url' => 'https://billing.example.test',
            'billing.zarinpal.enabled' => true,
            'billing.zarinpal.environment' => 'production',
            'billing.zarinpal.merchant_id' => '4a434457-41d0-4cf6-9458-50aa67de497a',
            'billing.plan.amount_irr' => 1_250_000,
            'billing.plan.term_days' => 30,
            'billing.plan.daily_verifications' => 50_000,
            'billing.stripe.secret_key' => 'sk_test_'.str_repeat('T', 32),
            'billing.stripe.webhook_secret' => 'whsec_'.str_repeat('W', 32),
            'billing.stripe.currency' => 'USD',
            'billing.stripe.amount_minor' => 1_299,
        ]);
    }

    public function test_publication_replaces_the_legacy_offer_and_gateway_selection_is_respected(): void
    {
        $operator = $this->operator();
        app(ProviderSelection::class)->update($operator, ['zarinpal', 'stripe'], null);
        $organization = $this->organization();
        $index = $this->path('billing.index', $organization);
        $catalog = app(BillingPlanSettings::class);

        $this->get($index)->assertOk()->assertSee('1,250,000 IRR')->assertSee('12.99 USD');
        $draft = $catalog->save($operator, $this->terms(['name' => 'Growth', 'is_active' => false]));
        $this->get($index)->assertOk()->assertSee('1,250,000 IRR')->assertDontSee('Growth');

        $catalog->save($operator, $this->terms(['name' => 'Growth', 'stripe_amount_minor' => 2_500, 'stripe_currency' => 'usd']), $draft);
        $scale = $catalog->save($operator, $this->terms([
            'name' => 'Scale', 'zarinpal_amount_irr' => null, 'stripe_amount_minor' => 9_500, 'stripe_currency' => 'EUR',
        ]));

        $response = $this->get($index)->assertOk()->assertSee('Growth')->assertSee('Scale')
            ->assertSee('Pay with Stripe')->assertSee('Pay with ZarinPal')
            ->assertSee('25.00 USD')->assertSee('95.00 EUR')->assertDontSee('1,250,000 IRR');
        $this->assertSame([
            ['zarinpal', $draft->id],
            ['stripe', $draft->id],
            ['stripe', $scale->id],
        ], array_map(fn (array $offer): array => [$offer['provider'], $offer['plan']->id], $response->viewData('options')));

        app(ProviderSelection::class)->update($operator, ['zarinpal'], null);
        $this->get($index)->assertOk()->assertSee('Growth')->assertDontSee('Scale')->assertDontSee('Pay with Stripe');
        $catalog->save($operator, $this->terms(['name' => 'Growth', 'is_active' => false]), $draft);
        $this->get($index)->assertOk()->assertSee('Checkout is currently unavailable.')->assertDontSee('1,250,000 IRR');
        $this->assertNotNull($draft->refresh()->published_at);
    }

    public function test_checkout_rejects_stale_and_cross_organization_quotes_and_settles_the_original_terms(): void
    {
        $this->freezeTime();
        $organization = $this->organization();
        $owner = auth('web')->user();
        $other = $this->organization();
        $other->members()->attach($owner, ['role' => 'owner']);
        $this->actingAs($owner, 'web');
        $operator = $this->operator();
        $catalog = app(BillingPlanSettings::class);
        $plan = $catalog->save($operator, $this->terms());
        $catalog->save($operator, $this->terms(['name' => 'Another plan']));
        $index = $this->path('billing.index', $organization);
        $checkout = $this->path('billing.checkout', $organization);

        $oldQuote = $this->quotes($index)[0];
        $terms = $this->terms(['name' => 'Business', 'zarinpal_amount_irr' => 4_000_000, 'daily_verifications' => 65_000]);
        $catalog->save($operator, $terms, $plan);
        $this->post($checkout, $oldQuote)->assertConflict();
        $this->assertDatabaseCount('billing_invoices', 0);

        [$quote, $otherPlanQuote] = $this->quotes($index);
        if ($quote['plan_id'] !== $plan->id) {
            [$quote, $otherPlanQuote] = [$otherPlanQuote, $quote];
        }
        $foreignQuote = $this->quotes($this->path('billing.index', $other))[0];
        $this->post($checkout, $foreignQuote)->assertConflict();
        $this->post($checkout, array_diff_key($quote, ['quote' => '']))->assertUnprocessable();
        Http::assertNothingSent();

        Http::fake([
            self::ZARINPAL.'/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => self::AUTHORITY], 'errors' => []]),
            self::ZARINPAL.'/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 381], 'errors' => []]),
        ]);

        $this->post($checkout, $quote + ['amount_irr' => 1, 'daily_verifications' => 900_000])
            ->assertStatus(303)->assertRedirect(self::ZARINPAL.'/pg/StartPay/'.self::AUTHORITY);
        $invoice = BillingInvoice::query()->sole();
        $this->assertSame($plan->id, $invoice->billing_plan_id);
        $this->assertSame('Business', $invoice->plan_name);
        $this->assertSame(4_000_000, $invoice->minorAmount());

        $catalog->save($operator, $this->terms(['name' => 'Retired offer', 'is_active' => false, 'term_days' => 60]), $plan);
        $this->post($checkout, $quote)->assertRedirect(self::ZARINPAL.'/pg/StartPay/'.self::AUTHORITY);
        $this->post($checkout, ['checkout_key' => $quote['checkout_key']] + $otherPlanQuote)->assertConflict();
        $this->get($this->path('billing.receipt', $organization, $invoice))->assertOk()->assertSee('Business')
            ->assertSee('name="plan_id" value="'.$plan->id.'"', false);
        Http::assertSentCount(1);

        $this->get(route('billing.zarinpal.callback', ['invoice' => $invoice->id, 'Authority' => self::AUTHORITY, 'Status' => 'OK'], false))
            ->assertStatus(303);
        $invoice->refresh();
        $this->assertSame(BillingInvoice::PAID, $invoice->status);
        $this->assertSame(now()->addDays(45)->toDateTimeString(), $invoice->subscription->expires_at->toDateTimeString());
        $this->assertSame(65_000, app(BillingEntitlements::class)->dailyVerificationLimit($organization->id, 'production'));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/verify.json') && $request->data()['amount'] === 4_000_000);
        Http::assertSentCount(2);
    }

    public function test_operator_creates_and_retires_a_plan_with_mfa_and_current_password(): void
    {
        $operator = $this->operator(enrolled: false);
        $this->actingAs($operator, 'web')->get('/admin/billing-plans')->assertRedirect('/admin/login');
        $this->actingAs($operator, 'operator')->get('/admin/billing-plans')->assertRedirect('/admin/multi-factor-authentication/set-up');

        $operator->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $this->get('/admin/billing-plans/create')->assertOk()->assertSee('Current password');
        Filament::setCurrentPanel(Filament::getPanel('operator'));
        $form = Livewire::test(CreateBillingPlan::class)
            ->fillForm($this->terms(['current_password' => 'incorrect']))
            ->call('create')->assertHasFormErrors(['current_password'])->assertSet('data.current_password', null);
        $this->assertDatabaseCount('billing_plans', 0);

        $form->fillForm(['current_password' => 'password'])->call('create')->assertHasNoFormErrors();
        $plan = BillingCatalogPlan::query()->sole();
        $this->assertTrue($plan->is_active);
        $this->assertNotNull($plan->published_at);
        $this->assertDatabaseHas('shield_audit_entries', ['actor_id' => $operator->id, 'operation' => 'billing_plan.created']);
        $this->get('/admin/billing-plans')->assertOk()->assertSee('Growth');

        Livewire::test(EditBillingPlan::class, ['record' => $plan->id])
            ->fillForm(['is_active' => false, 'current_password' => 'password'])
            ->call('save')->assertHasNoFormErrors()->assertSet('data.current_password', null);
        $this->assertFalse($plan->refresh()->is_active);
        $this->assertNotNull($plan->published_at);
        $this->assertDatabaseHas('shield_audit_entries', ['actor_id' => $operator->id, 'operation' => 'billing_plan.updated']);
    }

    public function test_csv_export_has_only_authorized_invoice_snapshots_and_protects_spreadsheets(): void
    {
        $organization = $this->organization();
        $other = $this->organization();
        $otherOwner = auth('web')->user();
        $owner = $organization->members()->first();
        $operator = $this->operator();
        app(BillingPlanSettings::class)->save($operator, $this->terms(['name' => '=1+1']));
        Http::fake([
            self::ZARINPAL.'/pg/v4/payment/request.json' => Http::sequence()
                ->push(['data' => ['code' => 100, 'authority' => self::AUTHORITY], 'errors' => []])
                ->push(['data' => ['code' => 100, 'authority' => 'A44444444444444444444444444444444444'], 'errors' => []]),
        ]);
        $this->actingAs($owner, 'web');
        $this->post($this->path('billing.checkout', $organization), $this->quotes($this->path('billing.index', $organization))[0])->assertStatus(303);
        $this->actingAs($otherOwner, 'web');
        $this->post($this->path('billing.checkout', $other), $this->quotes($this->path('billing.index', $other))[0])->assertStatus(303);
        $this->get($this->path('billing.export', $organization))->assertForbidden();

        $this->actingAs($owner, 'web');
        $export = $this->get($this->path('billing.export', $organization))->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('no-store', $export->headers->get('Cache-Control'));
        $csv = $export->streamedContent();
        $invoice = BillingInvoice::query()->where('organization_id', $organization->id)->sole();
        $this->assertStringContainsString($invoice->id, $csv);
        $this->assertStringContainsString(",'=1+1,IRR,2500000,45,60000,pending", $csv);
        $this->assertStringNotContainsString(BillingInvoice::query()->where('organization_id', $other->id)->sole()->id, $csv);
        $this->assertStringNotContainsString(self::AUTHORITY, $csv);
        $this->assertStringNotContainsString($invoice->checkout_key, $csv);
    }

    private function operator(bool $enrolled = true): User
    {
        $operator = User::factory()->create();
        $operator->forceFill(['is_operator' => true])->save();
        if ($enrolled) {
            $operator->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        }

        return $operator;
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

    private function terms(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Growth',
            'term_days' => 45,
            'daily_verifications' => 60_000,
            'zarinpal_amount_irr' => 2_500_000,
            'stripe_amount_minor' => null,
            'stripe_currency' => null,
            'is_active' => true,
        ];
    }

    private function quotes(string $index): array
    {
        $page = $this->get($index)->assertOk();

        return array_map(fn (array $offer): array => [
            'checkout_key' => $page->viewData('checkoutKey'),
            'provider' => $offer['provider'],
            'plan_id' => $offer['plan']->id,
            'quote' => $offer['quote'],
        ], $page->viewData('options'));
    }

    private function path(string $route, Organization $organization, ?BillingInvoice $invoice = null): string
    {
        return route($route, array_filter(['organization' => $organization->slug, 'invoice' => $invoice?->id]), false);
    }
}
