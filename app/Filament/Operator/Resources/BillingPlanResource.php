<?php

namespace App\Filament\Operator\Resources;

use App\Billing\BillingAmount;
use App\Billing\BillingPlanSettings;
use App\Filament\Operator\Resources\BillingPlanResource\Pages\CreateBillingPlan;
use App\Filament\Operator\Resources\BillingPlanResource\Pages\EditBillingPlan;
use App\Filament\Operator\Resources\BillingPlanResource\Pages\ListBillingPlans;
use App\Models\BillingCatalogPlan;
use App\Models\User;
use App\Settings\ProviderSelection;
use App\Tenancy\ConfirmPassword;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BillingPlanResource extends Resource
{
    protected static ?string $model = BillingCatalogPlan::class;

    protected static ?string $slug = 'billing-plans';

    protected static ?string $modelLabel = 'billing plan';

    public static function canViewAny(): bool
    {
        $operator = Filament::auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'operator'
            && $operator instanceof User
            && ProviderSelection::canManage($operator);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $rules = BillingPlanSettings::rules('data.');

        return $schema->components([
            TextInput::make('name')->label('Plan name')->required()->maxLength(100)->rules($rules['name'])->columnSpanFull(),
            TextInput::make('term_days')->label('Access term (days)')->required()->integer()->rules($rules['term_days']),
            TextInput::make('daily_verifications')->label('Verifications per UTC day')->required()->integer()->rules($rules['daily_verifications']),
            TextInput::make('zarinpal_amount_irr')->label('ZarinPal amount in IRR')->integer()->rules($rules['zarinpal_amount_irr']),
            TextInput::make('stripe_amount_minor')->label('Stripe amount in minor units')->integer()->rules($rules['stripe_amount_minor'])
                ->helperText('For example, 2500 USD minor units is $25.00. Leave a gateway amount blank to exclude it from this plan.'),
            TextInput::make('stripe_currency')->label('Stripe currency code')->maxLength(3)->rules($rules['stripe_currency']),
            Toggle::make('is_active')->label('Publish for checkout')->default(false)
                ->helperText('The first publication replaces the deployment’s configured offer. Turn off publication to retire a plan.'),
            TextInput::make('current_password')->label('Current password')->password()->autocomplete('current-password')
                ->required()->rule(new ConfirmPassword)->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('term_days')->label('Term (days)')->sortable(),
                TextColumn::make('daily_verifications')->label('Daily verifications')->numeric()->sortable(),
                TextColumn::make('zarinpal_amount_irr')->label('ZarinPal')
                    ->formatStateUsing(fn (int $state): string => BillingAmount::format($state, 'IRR'))->placeholder('—'),
                TextColumn::make('stripe_amount_minor')->label('Stripe')
                    ->formatStateUsing(fn (int $state, BillingCatalogPlan $record): string => BillingAmount::format($state, $record->stripe_currency))
                    ->placeholder('—'),
                TextColumn::make('is_active')->label('Status')->badge()
                    ->state(fn (BillingCatalogPlan $record): string => match (true) {
                        $record->is_active => 'Active',
                        $record->published_at !== null => 'Retired',
                        default => 'Draft',
                    }),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBillingPlans::route('/'),
            'create' => CreateBillingPlan::route('/create'),
            'edit' => EditBillingPlan::route('/{record}/edit'),
        ];
    }
}
