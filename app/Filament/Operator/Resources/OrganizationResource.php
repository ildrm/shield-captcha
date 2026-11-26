<?php

namespace App\Filament\Operator\Resources;

use App\Filament\Operator\Resources\OrganizationResource\Pages\ListOrganizations;
use App\Models\Organization;
use App\Tenancy\ConfirmPassword;
use App\Tenancy\SiteManagement;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('sites_count')->counts('sites')->label('Sites'),
                TextColumn::make('members_count')->counts('members')->label('Members'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->recordActions([
                Action::make('status')
                    ->label(fn (Organization $record): string => $record->is_active ? 'Suspend' : 'Reactivate')
                    ->color(fn (Organization $record): string => $record->is_active ? 'danger' : 'success')
                    ->authorize('update')
                    ->modalDescription('Suspension blocks all sites and members. Still-valid tokens may work after reactivation.')
                    ->schema([
                        Select::make('reason')->required()->options(static::reasons()),
                        TextInput::make('password')->label('Current password')->password()->autocomplete('current-password')
                            ->required()->rule(new ConfirmPassword),
                    ])
                    ->action(function (Organization $record, array $data, SiteManagement $sites): void {
                        $operator = Filament::auth()->user();
                        abort_unless(array_key_exists($data['reason'], static::reasons()), 422);
                        $sites->setOrganizationStatus($operator, $record, ! $record->is_active, $data['reason']);
                        Notification::make()->title('Organization status updated')->success()->send();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListOrganizations::route('/')];
    }

    private static function reasons(): array
    {
        return [
            'incident_response' => 'Incident response',
            'abuse_review' => 'Abuse review',
            'owner_request' => 'Organization owner request',
            'review_completed' => 'Review completed',
        ];
    }
}
