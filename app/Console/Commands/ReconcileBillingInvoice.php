<?php

namespace App\Console\Commands;

use App\Billing\BillingCheckout;
use App\Models\BillingInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReconcileBillingInvoice extends Command
{
    protected $signature = 'shield:billing:reconcile {invoice : Invoice UUID}';

    protected $description = 'Verify an existing provider payment and apply its access term once';

    public function handle(BillingCheckout $billing): int
    {
        $id = $this->argument('invoice');
        $invoice = Str::isUuid($id) ? BillingInvoice::query()->find($id) : null;
        if ($invoice === null) {
            $this->error('Invoice not found.');

            return self::FAILURE;
        }

        $verified = $billing->reconcile($invoice);
        if ($verified->status !== BillingInvoice::PAID) {
            $this->warn('Payment remains unverified. Check the invoice, merchant configuration, and provider dashboard.');

            return self::FAILURE;
        }

        $this->info('Payment verified.');

        return self::SUCCESS;
    }
}
