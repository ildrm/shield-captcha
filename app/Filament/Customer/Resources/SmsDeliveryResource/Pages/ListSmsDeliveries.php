<?php

namespace App\Filament\Customer\Resources\SmsDeliveryResource\Pages;

use App\Filament\Customer\Resources\SmsDeliveryResource;
use Filament\Resources\Pages\ListRecords;

class ListSmsDeliveries extends ListRecords
{
    protected static string $resource = SmsDeliveryResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(SmsDeliveryResource::canViewAny(), 403);
    }
}
