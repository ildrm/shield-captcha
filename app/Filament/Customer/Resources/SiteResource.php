<?php

namespace App\Filament\Customer\Resources;

use App\Filament\Customer\Resources\SiteResource\Pages\CreateSite;
use App\Filament\Customer\Resources\SiteResource\Pages\EditSite;
use App\Filament\Customer\Resources\SiteResource\Pages\ListSites;
use App\Filament\Customer\Resources\SiteResource\Pages\ViewSite;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Tenancy\Origin;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();
        $user = Filament::auth()->user();
        $query = parent::getEloquentQuery();

        if (! $tenant instanceof Organization || ! $user instanceof User || ! $user->canAccessTenant($tenant)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('sites.organization_id', $tenant->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            Select::make('environment')->required()->options([
                'sandbox' => 'Sandbox',
                'production' => 'Production',
            ])->default('sandbox')->disabledOn('edit'),
            TextInput::make('origin')
                ->required()->maxLength(300)->disabledOn('edit')
                ->helperText('The full browser origin, including a port when needed. Production requires HTTPS and DNS proof.'),
            TagsInput::make('actions')->required()->default(['contact'])
                ->helperText('Use lowercase letters, numbers, dashes or underscores. Start with a letter.'),
            Select::make('challenge_mode')->required()->options(Site::CHALLENGE_MODES)->default('checkbox')
                ->helperText('Automatic and adaptive processing start when a form with a managed embed is submitted.'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('public_key')->label('Public site key')->copyable(),
            TextEntry::make('environment')->badge(),
            TextEntry::make('origin'),
            TextEntry::make('actions')->badge(),
            TextEntry::make('challenge_mode')->label('Challenge mode')
                ->formatStateUsing(fn (string $state): string => Site::CHALLENGE_MODES[$state] ?? Site::CHALLENGE_MODES['checkbox']),
            TextEntry::make('domain_verified_at')->label('Domain verification')->dateTime()
                ->placeholder('DNS proof required')->visible(fn (Site $record): bool => $record->environment === 'production'),
            TextEntry::make('dns_name')->label('TXT record name')->state(fn (Site $record): string => Origin::dnsName($record->origin))
                ->copyable()->visible(fn (Site $record): bool => $record->environment === 'production' && $record->domain_verified_at === null),
            TextEntry::make('domain_verification_token')->label('TXT record value')->copyable()
                ->visible(fn (Site $record): bool => $record->environment === 'production' && $record->domain_verified_at === null),
            RepeatableEntry::make('key_history')->label('Recent credentials')
                ->state(fn (Site $record) => $record->credentials()->latest('id')->limit(20)->get(['prefix', 'scope', 'created_at', 'revoked_at']))
                ->schema([
                    TextEntry::make('prefix')->label('Key prefix'),
                    TextEntry::make('scope')->badge(),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('revoked_at')->dateTime()->placeholder('Active'),
                ])->columns(4)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('origin')->searchable(),
                TextColumn::make('environment')->badge(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([SelectFilter::make('environment')->options(['sandbox' => 'Sandbox', 'production' => 'Production'])])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSites::route('/'),
            'create' => CreateSite::route('/create'),
            'view' => ViewSite::route('/{record}'),
            'edit' => EditSite::route('/{record}/edit'),
        ];
    }
}
