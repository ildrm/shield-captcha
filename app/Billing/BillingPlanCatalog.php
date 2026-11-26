<?php

namespace App\Billing;

use App\Models\BillingCatalogPlan;

final class BillingPlanCatalog
{
    /** @return list<BillingPlan> */
    public function offersFor(string $provider): array
    {
        if (! $this->hasPublishedPlan()) {
            $legacy = BillingPlan::published($provider);

            return $legacy === null ? [] : [$legacy];
        }

        return BillingCatalogPlan::query()->where('is_active', true)->whereNotNull('published_at')->orderBy('daily_verifications')->orderBy('id')
            ->get()->map(fn (BillingCatalogPlan $plan) => BillingPlan::fromCatalog($plan, $provider))->filter()->values()->all();
    }

    public function checkoutOffer(string $provider, ?int $planId): ?BillingPlan
    {
        if ($planId === null) {
            return $this->hasPublishedPlan() ? null : BillingPlan::published($provider);
        }

        $plan = BillingCatalogPlan::query()->where('is_active', true)->whereNotNull('published_at')->lockForUpdate()->find($planId);

        return $plan === null ? null : BillingPlan::fromCatalog($plan, $provider);
    }

    public function hasPublishedPlan(): bool
    {
        return BillingCatalogPlan::query()->whereNotNull('published_at')->exists();
    }
}
