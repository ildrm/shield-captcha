<?php

namespace App\Filament\Customer\Resources\ProtectionEventResource\Pages;

use App\Filament\Customer\Resources\ProtectionEventResource;
use App\Filament\Customer\Widgets\ProtectionAlertsWidget;
use Filament\Resources\Pages\ListRecords;

class ListProtectionEvents extends ListRecords
{
    protected static string $resource = ProtectionEventResource::class;

    protected function getHeaderWidgets(): array
    {
        return [ProtectionAlertsWidget::class];
    }
}
