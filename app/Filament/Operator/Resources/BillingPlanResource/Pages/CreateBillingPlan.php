<?php

namespace App\Filament\Operator\Resources\BillingPlanResource\Pages;

use App\Billing\BillingPlanSettings;
use App\Filament\Operator\Resources\BillingPlanResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Components\Attributes\Exposed;
use Illuminate\Database\Eloquent\Model;

class CreateBillingPlan extends CreateRecord
{
    protected static string $resource = BillingPlanResource::class;

    protected static bool $canCreateAnother = false;

    #[Exposed]
    public function create(bool $another = false): void
    {
        try {
            parent::create($another);
        } finally {
            $this->data['current_password'] = null;
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(BillingPlanSettings::class)->save(Filament::auth()->user(), $data);
    }
}
