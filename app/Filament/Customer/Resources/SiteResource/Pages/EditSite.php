<?php

namespace App\Filament\Customer\Resources\SiteResource\Pages;

use App\Filament\Customer\Resources\SiteResource;
use App\Tenancy\SiteManagement;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSite extends EditRecord
{
    protected static string $resource = SiteResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['domain_verification_token']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(SiteManagement::class)->update(Filament::auth()->user(), $record, $data);
    }
}
