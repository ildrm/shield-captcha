<?php

namespace App\Filament\Customer\Resources;

use App\Filament\Customer\Resources\SmsAssessmentResource\Pages\ListSmsAssessments;
use App\Models\Organization;
use App\Models\SmsAssessment;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SmsAssessmentResource extends Resource
{
    protected static ?string $model = SmsAssessment::class;

    protected static ?string $navigationLabel = 'SMS assessments';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();
        $user = Filament::auth()->user();

        return $tenant instanceof Organization && $user instanceof User && $user->canAccessTenant($tenant);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->select(['id', 'site_id', 'action', 'environment', 'decision', 'reason_code', 'created_at']);
        if (! static::canViewAny()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('site_id', Filament::getTenant()->sites()->select('sites.id'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime(),
                TextColumn::make('site.name')->label('Site'),
                TextColumn::make('environment')->badge(),
                TextColumn::make('action'),
                TextColumn::make('decision')->badge(),
                TextColumn::make('reason_code')->label('Reason'),
                TextColumn::make('id')->label('Assessment ID')->copyable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([SelectFilter::make('environment')->options(['sandbox' => 'Sandbox', 'production' => 'Production'])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListSmsAssessments::route('/')];
    }
}
