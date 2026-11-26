<?php

namespace App\Filament\Operator\Resources\BillingPlanResource\Pages;

use App\Filament\Operator\Resources\BillingPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBillingPlans extends ListRecords
{
    protected static string $resource = BillingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
