<?php

namespace App\Providers\Filament;

use App\Filament\Operator\Pages\ProviderSettings;
use App\Filament\Operator\Resources\AuditEntryResource;
use App\Filament\Operator\Resources\BillingInvoiceResource;
use App\Filament\Operator\Resources\BillingPlanResource;
use App\Filament\Operator\Resources\OrganizationResource;
use App\Filament\Operator\Widgets\PlatformOverview;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class OperatorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('operator')
            ->path('admin')
            ->authGuard('operator')
            ->brandName(fn () => config('app.name').' Operations')
            ->colors(['primary' => Color::Teal])
            ->login()
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable()->codeWindow(1),
            ], isRequired: true)
            ->resources([OrganizationResource::class, AuditEntryResource::class, BillingInvoiceResource::class, BillingPlanResource::class])
            ->pages([Dashboard::class, ProviderSettings::class])
            ->widgets([PlatformOverview::class])
            ->globalSearch(false)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
