<?php

namespace App\Billing;

use App\Models\BillingInvoice;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\User;
use App\Settings\ProviderSelection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class BillingCheckout
{
    public function __construct(
        private ProviderSelection $providers,
        private ZarinPalGateway $zarinpal,
        private StripeGateway $stripe,
        private BillingPlanCatalog $plans,
    ) {}

    /** @return list<array{provider: string, plan: BillingPlan, environment: string, quote: string}> */
    public function checkoutOptions(Organization $organization, string $key): array
    {
        $options = [];
        foreach ($this->providers->paymentGateways() as $provider) {
            $gateway = $this->gateway($provider);
            if (! $gateway?->enabled()) {
                continue;
            }

            foreach ($this->plans->offersFor($provider) as $plan) {
                $options[] = [
                    'provider' => $provider,
                    'plan' => $plan,
                    'environment' => $gateway->environment(),
                    'quote' => $this->quote($organization, $key, $provider, $gateway, $plan),
                ];
            }
        }

        return $options;
    }

    public function checkoutUrl(BillingInvoice $invoice): ?string
    {
        return $this->providers->paymentEnabled($invoice->provider)
            ? $this->gateway($invoice->provider)?->checkoutUrl($invoice)
            : null;
    }

    public function canReconcile(BillingInvoice $invoice): bool
    {
        return $invoice->status === BillingInvoice::PENDING && $this->gateway($invoice->provider)?->matches($invoice) === true;
    }

    public function create(
        User $user,
        Organization $organization,
        string $key,
        ?string $provider = null,
        ?int $planId = null,
        ?string $quote = null,
    ): BillingInvoice {
        [$invoice, $created] = DB::transaction(function () use ($user, $organization, $key, $provider, $planId, $quote): array {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organization->id);
            abort_unless(BillingAccess::canManage($user, $organization), 403);

            $invoice = BillingInvoice::query()->where('organization_id', $organization->id)
                ->where('checkout_key', $key)->lockForUpdate()->first();
            if ($invoice !== null) {
                abort_if($provider !== null && $provider !== $invoice->provider, 409, 'Checkout already has a payment gateway.');
                abort_if($planId !== $invoice->billing_plan_id, 409, 'Checkout already has a billing plan.');
                if ($invoice->status === BillingInvoice::CREATING && $invoice->created_at->lte(now()->subMinute())) {
                    $invoice->forceFill(['status' => BillingInvoice::CREATION_FAILED])->save();
                }

                return [$invoice, false];
            }

            if ($provider === null) {
                $options = $this->checkoutOptions($organization, $key);
                abort_if(count($options) > 1, 422, 'Choose a payment gateway.');
                $provider = $options[0]['provider'] ?? null;
            }

            abort_unless($provider !== null && $this->providers->paymentEnabled($provider), 503, 'Checkout is unavailable.');
            $gateway = $this->gateway($provider);
            abort_unless($gateway?->enabled(), 503, 'Checkout is unavailable.');
            $plan = $this->plans->checkoutOffer($provider, $planId);
            abort_if($plan === null && ($planId !== null || $quote !== null), 409, 'Refresh billing to see the current offers.');
            abort_unless($plan !== null, 503, 'Checkout is unavailable.');
            abort_if($plan->id !== null && $quote === null, 422, 'A current billing quote is required.');
            abort_if($quote !== null && ! hash_equals($this->quote($organization, $key, $provider, $gateway, $plan), $quote),
                409, 'Refresh billing to see the current offers.');

            return [BillingInvoice::query()->create([
                'id' => (string) Str::uuid(),
                'organization_id' => $organization->id,
                'created_by' => $user->id,
                'checkout_key' => $key,
                'provider' => $provider,
                'environment' => $gateway->environment(),
                'merchant_fingerprint' => $gateway->fingerprint(),
                'amount_irr' => $provider === BillingInvoice::ZARINPAL ? $plan->amountMinor : null,
                'amount_minor' => $plan->amountMinor,
                'currency' => $plan->currency,
                'term_days' => $plan->termDays,
                'daily_verifications' => $plan->dailyVerifications,
                'billing_plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'status' => BillingInvoice::CREATING,
            ]), true];
        }, 3);

        if (! $created) {
            return $invoice;
        }

        $session = $this->gateway($invoice->provider)?->start($invoice);
        $checkout = $session === null ? [
            'status' => BillingInvoice::CREATION_FAILED,
        ] : match ($invoice->provider) {
            BillingInvoice::ZARINPAL => ['status' => BillingInvoice::PENDING, 'authority' => $session->id],
            BillingInvoice::STRIPE => [
                'status' => BillingInvoice::PENDING,
                'stripe_session_id' => $session->id,
                'stripe_checkout_url' => Crypt::encryptString($session->url),
            ],
        };
        try {
            BillingInvoice::query()->whereKey($invoice->id)->where('status', BillingInvoice::CREATING)->update($checkout);
        } catch (UniqueConstraintViolationException) {
            BillingInvoice::query()->whereKey($invoice->id)->where('status', BillingInvoice::CREATING)
                ->update(['status' => BillingInvoice::CREATION_FAILED]);
        }

        return $invoice->refresh();
    }

    public function reconcile(BillingInvoice $invoice): BillingInvoice
    {
        $claimed = $this->claimVerification($invoice);
        if ($claimed === null) {
            return $invoice->refresh();
        }

        $verification = $this->gateway($claimed->provider)?->verify($claimed);
        if ($verification !== null) {
            try {
                $this->settle($claimed, $verification);
            } catch (UniqueConstraintViolationException) {
                Log::warning('Billing verification requires reconciliation', ['invoice_id' => $invoice->id]);
            }
        }

        return $invoice->refresh();
    }

    private function gateway(string $provider): ?PaymentGateway
    {
        return match ($provider) {
            BillingInvoice::ZARINPAL => $this->zarinpal,
            BillingInvoice::STRIPE => $this->stripe,
            default => null,
        };
    }

    private function quote(Organization $organization, string $key, string $provider, PaymentGateway $gateway, BillingPlan $plan): string
    {
        $secret = config('app.key');
        abort_unless(is_string($secret) && $secret !== '', 503, 'Checkout is unavailable.');

        return hash_hmac('sha256', json_encode([
            'shield-billing-quote-v1',
            $organization->id, $key, $provider, $gateway->environment(), $gateway->fingerprint(),
            $plan->id, $plan->name, $plan->amountMinor, $plan->currency, $plan->termDays, $plan->dailyVerifications,
        ], JSON_THROW_ON_ERROR), $secret);
    }

    private function claimVerification(BillingInvoice $invoice): ?BillingInvoice
    {
        return DB::transaction(function () use ($invoice): ?BillingInvoice {
            Organization::query()->lockForUpdate()->findOrFail($invoice->organization_id);
            $invoice = BillingInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $now = now();
            if (! $this->canReconcile($invoice) || $invoice->next_verify_at?->gt($now)) {
                return null;
            }

            $invoice->forceFill(['next_verify_at' => $now->addSeconds(20)])->save();

            return $invoice;
        }, 3);
    }

    private function settle(BillingInvoice $invoice, PaymentVerification $verification): void
    {
        DB::transaction(function () use ($invoice, $verification): void {
            Organization::query()->lockForUpdate()->findOrFail($invoice->organization_id);
            $locked = BillingInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status !== BillingInvoice::PENDING) {
                return;
            }

            $now = now()->toImmutable();
            $locked->forceFill([
                'paid_at' => $now,
                'status' => BillingInvoice::PAID,
                'provider_reference' => $verification->reference,
                'verified_code' => $verification->code,
                'next_verify_at' => null,
            ])->save();

            if ($locked->environment === 'production') {
                $lastTerm = BillingSubscription::query()->where('organization_id', $locked->organization_id)
                    ->where('expires_at', '>', $now)->latest('expires_at')->first();
                $startsAt = $lastTerm?->expires_at ?? $now;

                BillingSubscription::query()->create([
                    'invoice_id' => $locked->id,
                    'organization_id' => $locked->organization_id,
                    'starts_at' => $startsAt,
                    'expires_at' => $startsAt->addDays($locked->term_days),
                    'daily_verifications' => $locked->daily_verifications,
                ]);
            }
        }, 3);
    }
}
