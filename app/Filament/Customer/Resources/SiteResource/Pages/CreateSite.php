<?php

namespace App\Filament\Customer\Resources\SiteResource\Pages;

use App\Filament\Customer\Resources\SiteResource;
use App\Tenancy\SiteManagement;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SiteManagement::class)->create(Filament::auth()->user(), Filament::getTenant(), $data);
    }
}
