<?php

namespace App\Filament\Operator\Resources\BillingInvoiceResource\Pages;

use App\Filament\Operator\Resources\BillingInvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListBillingInvoices extends ListRecords
{
    protected static string $resource = BillingInvoiceResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(BillingInvoiceResource::canViewAny(), 403);
    }
}
