<?php

namespace App\Settings;

use App\Models\User;
use App\Tenancy\Audit;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use stdClass;

final class ProviderSelection
{
    public const PAYMENT_GATEWAYS = ['zarinpal' => 'ZarinPal', 'stripe' => 'Stripe'];

    public const SMS_PROVIDERS = ['kavenegar' => 'Kavenegar', 'twilio' => 'Twilio'];

    private const KEY = 'providers';

    public function paymentEnabled(string $provider): bool
    {
        return in_array($provider, $this->paymentGateways(), true);
    }

    /** @return list<string> */
    public function paymentGateways(): array
    {
        return $this->current()['payment_gateways'];
    }

    public function smsProvider(): ?string
    {
        return $this->current()['sms_provider'];
    }

    /** @return array{payment_gateways: list<string>, sms_provider: ?string} */
    public function current(): array
    {
        return $this->selectionFrom($this->settingsRow()->first());
    }

    public static function canManage(User $actor): bool
    {
        return $actor->is_operator && $actor->hasVerifiedEmail() && filled($actor->getAppAuthenticationSecret());
    }

    public function update(User $operator, array $paymentGateways, ?string $smsProvider): void
    {
        abort_unless(self::canManage($operator), 403);

        Validator::make(['payment_gateways' => $paymentGateways, 'sms_provider' => $smsProvider], [
            'payment_gateways' => ['present', 'array', 'list', 'max:2'],
            'payment_gateways.*' => ['required', 'string', 'distinct:strict', Rule::in(array_keys(self::PAYMENT_GATEWAYS))],
            'sms_provider' => ['nullable', Rule::in(array_keys(self::SMS_PROVIDERS))],
        ])->validate();

        DB::transaction(function () use ($operator, $paymentGateways, $smsProvider): void {
            $actor = User::query()->findOrFail($operator->getKey());
            abort_unless(self::canManage($actor), 403);
            $row = $this->settingsRow()->lockForUpdate()->first();
            $before = $this->selectionFrom($row);
            $paymentGateways = array_values(array_intersect(array_keys(self::PAYMENT_GATEWAYS), $paymentGateways));

            if ($row !== null && $before === ['payment_gateways' => $paymentGateways, 'sms_provider' => $smsProvider]) {
                return;
            }

            DB::table('shield_provider_settings')->upsert([
                'id' => self::KEY,
                'zarinpal_active' => in_array('zarinpal', $paymentGateways, true),
                'stripe_active' => in_array('stripe', $paymentGateways, true),
                'sms_provider' => $smsProvider,
                'updated_at' => now(),
            ], ['id'], ['zarinpal_active', 'stripe_active', 'sms_provider', 'updated_at']);

            Audit::record($actor, 'providers.updated', reason: sprintf(
                'Payment gateways changed from %s to %s. SMS changed from %s to %s.',
                $this->paymentLabels($before['payment_gateways']),
                $this->paymentLabels($paymentGateways),
                self::SMS_PROVIDERS[$before['sms_provider']] ?? 'disabled',
                self::SMS_PROVIDERS[$smsProvider] ?? 'disabled',
            ));
        }, 3);
    }

    private function settingsRow(): Builder
    {
        return DB::table('shield_provider_settings')->where('id', self::KEY);
    }

    /** @return array{payment_gateways: list<string>, sms_provider: ?string} */
    private function selectionFrom(?stdClass $row): array
    {
        if ($row === null) {
            return [
                'payment_gateways' => config('billing.zarinpal.enabled') === true ? ['zarinpal'] : [],
                'sms_provider' => config('sms.kavenegar.enabled') ? 'kavenegar' : null,
            ];
        }

        return [
            'payment_gateways' => array_values(array_filter(
                array_keys(self::PAYMENT_GATEWAYS),
                fn (string $provider): bool => (bool) $row->{$provider.'_active'},
            )),
            'sms_provider' => $row->sms_provider,
        ];
    }

    /** @param list<string> $providers */
    private function paymentLabels(array $providers): string
    {
        $labels = array_intersect_key(self::PAYMENT_GATEWAYS, array_flip($providers));

        return $labels === [] ? 'none' : implode(' and ', $labels);
    }
}
