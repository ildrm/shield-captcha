<?php

namespace App\Filament\Customer\Resources;

use App\Filament\Customer\Resources\SmsDeliveryResource\Pages\ListSmsDeliveries;
use App\Models\Organization;
use App\Models\SmsDelivery;
use App\Models\User;
use App\Settings\ProviderSelection;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SmsDeliveryResource extends Resource
{
    protected static ?string $model = SmsDelivery::class;

    protected static ?string $navigationLabel = 'SMS deliveries';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();
        $user = Filament::auth()->user();

        return $tenant instanceof Organization && $user instanceof User && $user->canAccessTenant($tenant);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->select([
            'id',
            'site_id',
            'action',
            'assessment_id',
            'state',
            'provider',
            'provider_reference',
            'created_at',
            'updated_at',
        ]);

        if (! static::canViewAny()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('site_id', Filament::getTenant()->sites()->select('sites.id'));
    }

    public static function table(Table $table): Table
    {
        $states = [
            SmsDelivery::DISPATCHING => 'Awaiting confirmation',
            SmsDelivery::ACCEPTED => 'Accepted by provider',
            SmsDelivery::REJECTED => 'Rejected by provider',
            SmsDelivery::UNKNOWN => 'Outcome unknown',
        ];

        return $table
            ->columns([
                TextColumn::make('created_at')->label('Submitted')->dateTime(),
                TextColumn::make('site.name')->label('Site'),
                TextColumn::make('action'),
                TextColumn::make('provider')->badge()
                    ->formatStateUsing(fn (string $state): string => ProviderSelection::SMS_PROVIDERS[$state] ?? $state),
                TextColumn::make('state')->label('Send status')->badge()
                    ->formatStateUsing(function (string $state, SmsDelivery $record) use ($states): string {
                        if ($state === SmsDelivery::DISPATCHING
                            && $record->updated_at?->lte(now()->subSeconds(SmsDelivery::DISPATCH_TIMEOUT_SECONDS))) {
                            return $states[SmsDelivery::UNKNOWN];
                        }

                        return $states[$state] ?? $states[SmsDelivery::UNKNOWN];
                    }),
                TextColumn::make('provider_reference')->label('Provider reference')->placeholder('—')->copyable(),
                TextColumn::make('assessment_id')->label('Assessment ID')->placeholder('—')->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateHeading('No SMS deliveries')
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListSmsDeliveries::route('/')];
    }
}
