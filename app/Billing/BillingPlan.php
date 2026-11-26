<?php

namespace App\Billing;

use App\Models\BillingCatalogPlan;
use App\Models\BillingInvoice;

final readonly class BillingPlan
{
    private function __construct(
        public int $amountMinor,
        public string $currency,
        public int $termDays,
        public int $dailyVerifications,
        public ?int $id = null,
        public ?string $name = null,
    ) {}

    public static function published(string $provider = BillingInvoice::ZARINPAL): ?self
    {
        $amount = match ($provider) {
            BillingInvoice::ZARINPAL => self::integer('billing.plan.amount_irr', 1, 1_000_000_000),
            BillingInvoice::STRIPE => self::integer('billing.stripe.amount_minor', 1, 999_999_999),
            default => false,
        };
        $currency = strtoupper((string) ($provider === BillingInvoice::STRIPE ? config('billing.stripe.currency') : 'IRR'));
        $term = self::integer('billing.plan.term_days', 1, 365);
        $limit = self::integer('billing.plan.daily_verifications', BillingEntitlements::FREE_DAILY_LIMIT + 1, 2_147_483_647);

        return $amount !== false && $term !== false && $limit !== false
            && preg_match('/\A[A-Z]{3}\z/', $currency)
            && ($provider !== BillingInvoice::STRIPE || $currency !== 'IRR')
            ? new self($amount, $currency, $term, $limit)
            : null;
    }

    public function displayAmount(): string
    {
        return BillingAmount::format($this->amountMinor, $this->currency);
    }

    public static function fromCatalog(BillingCatalogPlan $plan, string $provider): ?self
    {
        $amount = match ($provider) {
            BillingInvoice::ZARINPAL => $plan->zarinpal_amount_irr,
            BillingInvoice::STRIPE => $plan->stripe_amount_minor,
            default => null,
        };

        $currency = $provider === BillingInvoice::ZARINPAL ? 'IRR' : $plan->stripe_currency;
        if ($amount === null || $currency === null) {
            return null;
        }

        return new self(
            $amount,
            $currency,
            $plan->term_days,
            $plan->daily_verifications,
            $plan->id,
            $plan->name,
        );
    }

    private static function integer(string $key, int $min, int $max): int|false
    {
        return filter_var(config($key), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]);
    }
}
