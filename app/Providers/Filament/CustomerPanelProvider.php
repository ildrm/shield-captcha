<?php

namespace App\Providers\Filament;

use App\Billing\BillingAccess;
use App\Filament\Customer\Pages\ProtectionPolicies;
use App\Filament\Customer\RegisterOrganization;
use App\Filament\Customer\Resources\ProtectionEventResource;
use App\Filament\Customer\Resources\SiteResource;
use App\Filament\Customer\Resources\SmsAssessmentResource;
use App\Filament\Customer\Resources\SmsDeliveryResource;
use App\Filament\Customer\Widgets\OrganizationOverview;
use App\Http\Controllers\ShowSiteSecret;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\TeamManagement;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CustomerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('customer')
            ->path('customer')
            ->brandName(fn () => config('app.name'))
            ->colors(['primary' => Color::Indigo])
            ->login()
            ->registration()
            ->emailVerification()
            ->passwordReset()
            ->profile()
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()->codeWindow(1)])
            ->tenant(Organization::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterOrganization::class)
            ->resources([SiteResource::class, SmsAssessmentResource::class, SmsDeliveryResource::class, ProtectionEventResource::class])
            ->navigationItems([
                NavigationItem::make('Billing')
                    ->visible(function (): bool {
                        $tenant = Filament::getTenant();
                        $user = Filament::auth()->user();

                        return $tenant instanceof Organization && $user instanceof User && BillingAccess::canManage($user, $tenant);
                    })
                    ->url(function (): ?string {
                        $tenant = Filament::getTenant();

                        return $tenant instanceof Organization ? route('billing.index', ['organization' => $tenant->slug]) : null;
                    }),
                NavigationItem::make('Team & audit')
                    ->visible(function (): bool {
                        $tenant = Filament::getTenant();
                        $user = Filament::auth()->user();

                        return $tenant instanceof Organization && $user instanceof User && TeamManagement::canManage($user, $tenant);
                    })
                    ->url(function (): ?string {
                        $tenant = Filament::getTenant();

                        return $tenant instanceof Organization ? route('team.index', ['organization' => $tenant->slug]) : null;
                    }),
            ])
            ->pages([Dashboard::class, ProtectionPolicies::class])
            ->widgets([OrganizationOverview::class])
            ->globalSearch(false)
            ->authenticatedTenantRoutes(function (): void {
                Route::get('sites/{site}/credentials/show/{receipt}', ShowSiteSecret::class)
                    ->whereNumber('site')
                    ->where('receipt', '[a-f0-9]{64}')
                    ->name('credentials.show');
            })
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
