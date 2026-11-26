<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SmsDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderPanelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_link_uses_the_tenant_billing_permission(): void
    {
        $organization = $this->organization('Tenant with billing');
        $manager = User::factory()->create();
        $viewer = User::factory()->create();
        $organization->members()->attach($manager, ['role' => 'billing_manager']);
        $organization->members()->attach($viewer, ['role' => 'viewer']);
        $panelPath = "/customer/{$organization->slug}";
        $billingUrl = route('billing.index', ['organization' => $organization->slug]);

        $this->actingAs($manager)->get($panelPath)->assertOk()->assertSee($billingUrl, false);
        $this->actingAs($viewer)->get($panelPath)->assertOk()->assertDontSee($billingUrl, false);
    }

    public function test_customer_sms_delivery_history_uses_the_current_organization_and_redacts_digests(): void
    {
        $first = $this->organization('SMS customer');
        $second = $this->organization('Other SMS customer');
        $viewer = User::factory()->create();
        $first->members()->attach($viewer, ['role' => 'viewer']);

        foreach ([$first, $second] as $index => $organization) {
            $site = $organization->sites()->create([
                'name' => 'Site '.$index,
                'public_key' => 'site_prod_'.Str::random(32),
                'environment' => 'production',
                'origin' => "https://site-{$index}.example.test",
                'domain_verified_at' => now(),
                'actions' => ['otp'],
                'is_active' => true,
            ]);

            DB::table('shield_sms_deliveries')->insert([
                'id' => Str::uuid()->toString(),
                'site_id' => $site->id,
                'action' => 'otp',
                'assessment_id' => Str::uuid()->toString(),
                'event_digest' => str_repeat('a', 64),
                'request_digest' => str_repeat('b', 64),
                'state' => SmsDelivery::ACCEPTED,
                'provider_reference' => (string) (10000871 + $index),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($index === 0) {
                DB::table('shield_sms_deliveries')->insert([
                    'id' => Str::uuid()->toString(),
                    'site_id' => $site->id,
                    'action' => 'otp',
                    'assessment_id' => Str::uuid()->toString(),
                    'event_digest' => str_repeat('c', 64),
                    'request_digest' => str_repeat('d', 64),
                    'state' => SmsDelivery::DISPATCHING,
                    'created_at' => now()->subMinute(),
                    'updated_at' => now()->subMinute(),
                ]);
            }
        }

        $this->actingAs($viewer)->get("/customer/{$first->slug}/sms-deliveries")
            ->assertOk()
            ->assertSee('Accepted by provider')
            ->assertSee('Outcome unknown')
            ->assertSee('10000871')
            ->assertDontSee('10000872')
            ->assertDontSee(str_repeat('a', 64))
            ->assertDontSee(str_repeat('b', 64));

        $this->get("/customer/{$second->slug}/sms-deliveries")->assertNotFound();
    }

    public function test_operator_invoice_listing_uses_separate_mfa_guard_and_omits_gateway_authority(): void
    {
        $organization = $this->organization('Invoiced organization');
        $operator = User::factory()->create();
        $operator->forceFill(['is_operator' => true])->save();
        $authority = 'A'.str_repeat('8', 35);
        $merchantFingerprint = str_repeat('f', 64);
        DB::table('billing_invoices')->insert([
            'id' => Str::uuid()->toString(),
            'organization_id' => $organization->id,
            'checkout_key' => Str::uuid()->toString(),
            'environment' => 'production',
            'merchant_fingerprint' => $merchantFingerprint,
            'amount_irr' => 1200000,
            'term_days' => 30,
            'daily_verifications' => 25000,
            'status' => 'paid',
            'authority' => $authority,
            'provider_reference' => '893201608',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $path = '/admin/billing-invoices';
        $this->actingAs($operator, 'web')->get($path)->assertRedirect('/admin/login');
        $this->actingAs($operator, 'operator')->get($path)->assertRedirect('/admin/multi-factor-authentication/set-up');

        $operator->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $this->get($path)
            ->assertOk()
            ->assertSee('Payment verified')
            ->assertSee('893201608')
            ->assertDontSee($authority)
            ->assertDontSee($merchantFingerprint);
    }

    private function organization(string $name): Organization
    {
        return Organization::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
        ]);
    }
}
