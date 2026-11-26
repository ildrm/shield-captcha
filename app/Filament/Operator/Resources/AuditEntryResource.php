<?php

namespace App\Filament\Operator\Resources;

use App\Filament\Operator\Resources\AuditEntryResource\Pages\ListAuditEntries;
use App\Models\AuditEntry;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditEntryResource extends Resource
{
    protected static ?string $model = AuditEntry::class;

    public static function canViewAny(): bool
    {
        $operator = Filament::auth()->user();

        return $operator instanceof User && $operator->is_operator && $operator->hasVerifiedEmail();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return static::canViewAny() ? $query : $query->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime(),
                TextColumn::make('operation')->searchable(),
                TextColumn::make('organization_id')->label('Organization ID')->placeholder('Platform'),
                TextColumn::make('site_id')->label('Site ID')->placeholder('—'),
                TextColumn::make('actor_id')->label('Actor ID')->placeholder('Deleted'),
                TextColumn::make('reason')->placeholder('—'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditEntries::route('/')];
    }
}
