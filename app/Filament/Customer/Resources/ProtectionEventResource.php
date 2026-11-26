<?php

namespace App\Filament\Customer\Resources;

use App\Filament\Customer\Resources\ProtectionEventResource\Pages\ListProtectionEvents;
use App\Models\Organization;
use App\Models\User;
use App\Risk\ProtectionEvent;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProtectionEventResource extends Resource
{
    private const PRODUCTS = [
        'generic' => 'Generic',
        'account' => 'Account Shield',
        'api' => 'API Shield',
        'fraud' => 'Fraud protection',
        'bot' => 'Bot management',
    ];

    protected static ?string $model = ProtectionEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

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
            'id', 'site_id', 'action', 'product', 'environment', 'mode', 'decision', 'would_decision',
            'risk_score', 'reason_codes', 'classification', 'policy_version', 'feedback_label', 'created_at',
        ]);
        if (! static::canViewAny()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('site_id', Filament::getTenant()->sites()->select('sites.id'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Assessed')->dateTime(),
                TextColumn::make('site.name')->label('Site'),
                TextColumn::make('product')->formatStateUsing(fn (string $state): string => self::PRODUCTS[$state] ?? $state),
                TextColumn::make('environment')->badge(),
                TextColumn::make('action'),
                TextColumn::make('mode')->badge(),
                TextColumn::make('decision')->label('Returned decision')->badge(),
                TextColumn::make('would_decision')->label('Policy recommendation')->badge(),
                TextColumn::make('reason_codes')->label('Reasons')->badge()->toggleable(),
                TextColumn::make('risk_score')->label('Risk indicator')->placeholder('Unavailable')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('classification')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('policy_version')->label('Policy revision')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('feedback_label')->label('Feedback')->placeholder('Pending')->toggleable(),
                TextColumn::make('id')->label('Assessment ID')->copyable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product')->options(self::PRODUCTS),
                SelectFilter::make('environment')->options(['sandbox' => 'Sandbox', 'production' => 'Production']),
                SelectFilter::make('mode')->options(['observe' => 'Observe', 'enforce' => 'Enforce']),
                SelectFilter::make('feedback_label')->label('Feedback')->options([
                    'legitimate' => 'Legitimate',
                    'abusive' => 'Abusive',
                    'unknown' => 'Unknown',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return ['index' => ListProtectionEvents::route('/')];
    }
}
