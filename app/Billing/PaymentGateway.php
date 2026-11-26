<?php

namespace App\Billing;

use App\Models\BillingInvoice;

interface PaymentGateway
{
    public function environment(): ?string;

    public function enabled(): bool;

    public function fingerprint(): ?string;

    public function matches(BillingInvoice $invoice): bool;

    public function checkoutUrl(BillingInvoice $invoice): ?string;

    public function start(BillingInvoice $invoice): ?CheckoutSession;

    public function verify(BillingInvoice $invoice): ?PaymentVerification;
}
