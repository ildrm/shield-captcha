<?php

namespace Tests\Feature;

use App\Filament\Operator\Pages\ProviderSettings;
use App\Models\User;
use App\Settings\ProviderSelection;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProviderSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_defaults_apply_until_the_operator_saves_an_explicit_selection(): void
    {
        config([
            'billing.zarinpal.enabled' => true,
            'billing.stripe.enabled' => true,
            'sms.kavenegar.enabled' => true,
            'sms.twilio.enabled' => true,
        ]);

        $providers = app(ProviderSelection::class);
        $this->assertSame(['zarinpal'], $providers->paymentGateways());
        $this->assertFalse($providers->paymentEnabled('stripe'));
        $this->assertSame('kavenegar', $providers->smsProvider());

        $operator = $this->operator();
        $providers->update($operator, ['stripe'], 'twilio');
        $this->assertFalse($providers->paymentEnabled('zarinpal'));
        $this->assertTrue($providers->paymentEnabled('stripe'));
        $this->assertSame('twilio', $providers->smsProvider());

        $providers->update($operator, [], null);
        $this->assertSame([], $providers->paymentGateways());
        $this->assertNull($providers->smsProvider());
        $this->assertDatabaseCount('shield_provider_settings', 1);
        $this->assertDatabaseCount('shield_audit_entries', 2);
    }

    public function test_provider_settings_are_in_the_mfa_protected_operator_panel(): void
    {
        $path = '/admin/provider-settings';
        $operator = $this->operator(enrolled: false);

        $this->actingAs($operator, 'web')->get($path)->assertRedirect('/admin/login');
        $this->actingAs($operator, 'operator')->get($path)->assertRedirect('/admin/multi-factor-authentication/set-up');

        $operator->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $this->get('/admin')->assertOk()->assertSee($path, false);
        $this->get($path)->assertOk()->assertSee('Payment gateways')->assertSee('SMS provider');
    }

    public function test_operator_can_save_both_payment_gateways_and_one_sms_provider_after_confirming_their_password(): void
    {
        config(['billing.zarinpal.enabled' => false, 'sms.kavenegar.enabled' => false]);
        $operator = $this->operator();
        Filament::setCurrentPanel(Filament::getPanel('operator'));
        $this->actingAs($operator, 'operator');

        $page = Livewire::test(ProviderSettings::class)
            ->fillForm([
                'payment_gateways' => ['stripe', 'zarinpal'],
                'sms_provider' => 'twilio',
                'current_password' => 'wrong-password',
            ])
            ->call('save')
            ->assertHasFormErrors(['current_password'])
            ->assertSet('data.current_password', null);
        $this->assertDatabaseCount('shield_provider_settings', 0);
        $this->assertDatabaseCount('shield_audit_entries', 0);

        $page->fillForm(['current_password' => 'password'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('data.current_password', null);

        $providers = app(ProviderSelection::class);
        $this->assertSame(['zarinpal', 'stripe'], $providers->paymentGateways());
        $this->assertSame('twilio', $providers->smsProvider());
        $this->assertDatabaseHas('shield_audit_entries', [
            'actor_id' => $operator->id,
            'operation' => 'providers.updated',
            'reason' => 'Payment gateways changed from none to ZarinPal and Stripe. SMS changed from disabled to Twilio.',
        ]);
        $page->fillForm(['current_password' => 'password'])->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseCount('shield_audit_entries', 1);
    }

    public function test_provider_settings_reject_an_unsupported_payment_and_multiple_sms_providers(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('operator'));
        $this->actingAs($this->operator(), 'operator');

        Livewire::test(ProviderSettings::class)
            ->set('data.payment_gateways', ['unsupported'])
            ->set('data.sms_provider', ['kavenegar', 'twilio'])
            ->set('data.current_password', 'password')
            ->call('save')
            ->assertHasFormErrors(['payment_gateways.0', 'sms_provider']);

        $this->assertDatabaseCount('shield_provider_settings', 0);
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
}
