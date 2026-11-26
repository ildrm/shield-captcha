<?php

namespace App\Filament\Operator\Pages;

use App\Models\User;
use App\Settings\ProviderSelection;
use App\Tenancy\ConfirmPassword;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProviderSettings extends Page
{
    protected static ?string $title = 'Provider settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.operator.pages.provider-settings';

    public array $data = [];

    public static function canAccess(): bool
    {
        return self::currentOperator() !== null;
    }

    public function mount(ProviderSelection $providers): void
    {
        abort_unless(static::canAccess(), 403);
        $selection = $providers->current();

        $this->form->fill([
            'payment_gateways' => $selection['payment_gateways'],
            'sms_provider' => $selection['sms_provider'] ?? 'disabled',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            CheckboxList::make('payment_gateways')
                ->label('Payment gateways')
                ->options(ProviderSelection::PAYMENT_GATEWAYS)
                ->helperText('Customers can choose from selected gateways with valid deployment credentials and pricing.')
                ->rules(['array', 'list']),
            Radio::make('sms_provider')
                ->label('SMS provider')
                ->options(['disabled' => 'Disabled', ...ProviderSelection::SMS_PROVIDERS])
                ->helperText('New OTP requests use the selected provider. Each site needs its own credentials. Twilio blocks SMS to Iranian numbers.')
                ->required()
                ->rules(['string', Rule::in(['disabled', ...array_keys(ProviderSelection::SMS_PROVIDERS)])]),
            TextInput::make('current_password')
                ->label('Current password')
                ->password()
                ->autocomplete('current-password')
                ->required()
                ->rule(new ConfirmPassword),
        ])->statePath('data');
    }

    public function save(ProviderSelection $providers): void
    {
        $operator = self::currentOperator();
        abort_unless($operator !== null, 403);

        try {
            $data = $this->form->getState();
        } finally {
            $this->data['current_password'] = null;
        }

        $providers->update(
            $operator,
            $data['payment_gateways'] ?? [],
            $data['sms_provider'] === 'disabled' ? null : $data['sms_provider'],
        );

        Notification::make()->title('Provider settings saved')->success()->send();
    }

    private static function currentOperator(): ?User
    {
        $user = Auth::guard('operator')->user();

        return $user instanceof User && ProviderSelection::canManage($user) ? $user : null;
    }
}
