<?php

namespace App\Filament\Operator\Resources\BillingPlanResource\Pages;

use App\Billing\BillingPlanSettings;
use App\Filament\Operator\Resources\BillingPlanResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Components\Attributes\Exposed;
use Illuminate\Database\Eloquent\Model;

class EditBillingPlan extends EditRecord
{
    protected static string $resource = BillingPlanResource::class;

    #[Exposed]
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        } finally {
            $this->data['current_password'] = null;
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(BillingPlanSettings::class)->save(Filament::auth()->user(), $data, $record);
    }
}
