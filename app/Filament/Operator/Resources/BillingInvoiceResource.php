<?php

namespace App\Filament\Operator\Resources;

use App\Filament\Operator\Resources\BillingInvoiceResource\Pages\ListBillingInvoices;
use App\Models\BillingInvoice;
use App\Models\User;
use App\Settings\ProviderSelection;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BillingInvoiceResource extends Resource
{
    protected static ?string $model = BillingInvoice::class;

    protected static ?string $navigationLabel = 'Billing invoices';

    public static function canViewAny(): bool
    {
        $operator = Filament::auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'operator'
            && $operator instanceof User
            && $operator->is_operator
            && $operator->hasVerifiedEmail();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->select([
            'id',
            'organization_id',
            'provider',
            'environment',
            'amount_irr',
            'amount_minor',
            'currency',
            'status',
            'provider_reference',
            'paid_at',
            'created_at',
        ]);

        return static::canViewAny() ? $query : $query->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        $statuses = [
            BillingInvoice::CREATING => 'Creating checkout',
            BillingInvoice::PENDING => 'Awaiting payment verification',
            BillingInvoice::CREATION_FAILED => 'Checkout unavailable',
            BillingInvoice::PAID => 'Payment verified',
        ];

        return $table
            ->columns([
                TextColumn::make('created_at')->label('Opened')->dateTime(),
                TextColumn::make('id')->label('Invoice ID')->copyable()->searchable(),
                TextColumn::make('organization_id')->label('Organization ID'),
                TextColumn::make('provider')->label('Gateway')->badge()
                    ->formatStateUsing(fn (string $state): string => ProviderSelection::PAYMENT_GATEWAYS[$state] ?? $state),
                TextColumn::make('environment')->badge(),
                TextColumn::make('amount_minor')->label('Amount')
                    ->state(fn (BillingInvoice $record): string => $record->displayAmount()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => $statuses[$state] ?? $state),
                TextColumn::make('provider_reference')->label('Payment reference')->placeholder('—')->copyable()->searchable(),
                TextColumn::make('paid_at')->label('Verified at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('provider')->options(ProviderSelection::PAYMENT_GATEWAYS),
                SelectFilter::make('environment')->options(['sandbox' => 'Sandbox', 'production' => 'Production']),
                SelectFilter::make('status')->options($statuses),
            ])
            ->emptyStateHeading('No billing invoices')
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListBillingInvoices::route('/')];
    }
}
