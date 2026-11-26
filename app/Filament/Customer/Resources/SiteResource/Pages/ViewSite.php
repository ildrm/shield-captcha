<?php

namespace App\Filament\Customer\Resources\SiteResource\Pages;

use App\Filament\Customer\Resources\SiteResource;
use App\Models\Site;
use App\Tenancy\ConfirmPassword;
use App\Tenancy\CredentialDisplay;
use App\Tenancy\DnsProof;
use App\Tenancy\SiteManagement;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ViewSite extends ViewRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('integration')->label('Integration')
                ->modalHeading('Protect your form')
                ->modalContent(fn (Site $record) => view('filament.customer.integration', ['site' => $record]))
                ->modalSubmitAction(false)->modalCancelActionLabel('Close'),
            Action::make('verifyOrigin')->label('Verify DNS')
                ->visible(fn (Site $record): bool => $record->environment === 'production' && $record->domain_verified_at === null)
                ->authorize('update')
                ->requiresConfirmation()
                ->action(function (Site $record, SiteManagement $sites, DnsProof $dns): void {
                    $verified = $sites->verifyOrigin(Filament::auth()->user(), $record, $dns);
                    Notification::make()->title($verified ? 'Origin verified' : 'No matching TXT record found')
                        ->color($verified ? 'success' : 'warning')->send();
                }),
            Action::make('issueCredential')->label('Create private key')->authorize('manageCredentials')
                ->visible(fn (Site $record): bool => $record->is_active && ($record->environment === 'sandbox' || $record->domain_verified_at !== null))
                ->schema([
                    Select::make('scope')->required()->options([
                        'verify' => 'Token verification',
                        'assess' => 'SMS assessments',
                        'protect' => 'Risk assessments and feedback',
                        'automation' => 'Declared automation',
                    ]),
                    $this->currentPassword(),
                ])
                ->action(fn (Site $record, array $data) => $this->changeCredential($record, 'issued', $data['scope'])),
            Action::make('rotateCredential')->label('Rotate key')->authorize('manageCredentials')
                ->visible(fn (Site $record): bool => $record->is_active && $record->credentials()->whereNull('revoked_at')->exists())
                ->modalDescription('The selected private key will stop working immediately. Update your backend with the new key.')
                ->schema([$this->credentialSelect(), $this->currentPassword()])
                ->action(fn (Site $record, array $data) => $this->changeCredential($record, 'rotated', (int) $data['credential'])),
            Action::make('revokeCredential')->label('Revoke key')->color('danger')->authorize('manageCredentials')
                ->visible(fn (Site $record): bool => $record->credentials()->whereNull('revoked_at')->exists())
                ->modalDescription('Requests authenticated with the selected private key will stop working immediately.')
                ->schema([$this->credentialSelect(), $this->currentPassword()])
                ->action(fn (Site $record, array $data) => $this->changeCredential($record, 'revoked', (int) $data['credential'])),
        ];
    }

    private function currentPassword(): TextInput
    {
        return TextInput::make('password')->label('Current password')->password()->autocomplete('current-password')
            ->required()->rule(new ConfirmPassword);
    }

    private function credentialSelect(): Select
    {
        return Select::make('credential')->label('Active credential')->required()
            ->options(fn (Site $record): array => $record->credentials()->whereNull('revoked_at')
                ->latest('id')->limit(100)->get(['id', 'prefix', 'scope'])
                ->mapWithKeys(fn ($credential): array => [$credential->id => $credential->prefix.' ('.$credential->scope.')'])->all());
    }

    private function changeCredential(Site $record, string $operation, string|int $subject): void
    {
        $user = Filament::auth()->user();
        Gate::forUser($user)->authorize('manageCredentials', $record);
        if ($operation === 'issued' && ! in_array($subject, ['verify', 'assess', 'protect', 'automation'], true)) {
            throw ValidationException::withMessages(['scope' => 'Select a known credential scope.']);
        }

        $receipt = app(CredentialDisplay::class)->change($user, $record, session()->getId(), $operation, $subject);
        if ($receipt === null) {
            Notification::make()->title('Private key revoked')->success()->send();

            return;
        }

        $this->redirect(Filament::getCurrentPanel()->route('credentials.show', [
            'tenant' => Filament::getTenant()->slug,
            'site' => $record->id,
            'receipt' => $receipt,
        ]));
    }
}
