<?php

namespace App\Filament\Customer\Resources\SiteResource\Pages;

use App\Filament\Customer\Resources\SiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSites extends ListRecords
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
