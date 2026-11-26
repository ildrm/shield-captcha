<?php

namespace App\Billing;

use App\Models\BillingCatalogPlan;
use App\Models\User;
use App\Settings\ProviderSelection;
use App\Tenancy\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class BillingPlanSettings
{
    public static function rules(string $prefix = ''): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'term_days' => ['required', 'integer', 'between:1,365'],
            'daily_verifications' => ['required', 'integer', 'between:'.(BillingEntitlements::FREE_DAILY_LIMIT + 1).',2147483647'],
            'zarinpal_amount_irr' => ['required_without:'.$prefix.'stripe_amount_minor', 'nullable', 'integer', 'between:1,1000000000'],
            'stripe_amount_minor' => ['required_without:'.$prefix.'zarinpal_amount_irr', 'nullable', 'integer', 'between:1,999999999'],
            'stripe_currency' => ['required_with:'.$prefix.'stripe_amount_minor', 'nullable', 'string', 'regex:/\A(?!IRR\z)[A-Z]{3}\z/i'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function save(User $operator, array $input, ?BillingCatalogPlan $plan = null): BillingCatalogPlan
    {
        abort_unless(ProviderSelection::canManage($operator), 403);

        $data = Validator::make($input, self::rules())->validate();
        $data['zarinpal_amount_irr'] = filled($data['zarinpal_amount_irr'] ?? null) ? $data['zarinpal_amount_irr'] : null;
        $data['stripe_amount_minor'] = filled($data['stripe_amount_minor'] ?? null) ? $data['stripe_amount_minor'] : null;
        $data['stripe_currency'] = $data['stripe_amount_minor'] !== null ? strtoupper($data['stripe_currency']) : null;

        return DB::transaction(function () use ($operator, $data, $plan): BillingCatalogPlan {
            $actor = User::query()->lockForUpdate()->findOrFail($operator->id);
            abort_unless(ProviderSelection::canManage($actor), 403);

            $record = $plan === null
                ? new BillingCatalogPlan
                : BillingCatalogPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $record->fill($data);
            if ($record->is_active && $record->published_at === null) {
                $record->published_at = now();
            }
            if ($record->isDirty()) {
                $record->save();
                Audit::record($actor, $plan === null ? 'billing_plan.created' : 'billing_plan.updated', reason: sprintf(
                    'Billing plan #%d saved as %s.',
                    $record->id,
                    $record->is_active ? 'active' : 'inactive',
                ));
            }

            return $record;
        }, 3);
    }
}
