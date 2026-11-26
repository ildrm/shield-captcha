<?php

namespace App\Http\Controllers;

use App\Filament\Customer\Resources\SiteResource;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\CredentialDisplay;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShowSiteSecret
{
    public function __invoke(Request $request, string $tenant, string $site, string $receipt, CredentialDisplay $display): Response
    {
        abort_unless($request->isMethod('GET'), 405);

        $user = Filament::auth()->user();
        $organization = Filament::getTenant();
        abort_unless($user instanceof User && $organization instanceof Organization && $user->canAccessTenant($organization), 403);
        $record = $organization->sites()->findOrFail($site);
        $secret = $display->consume($user, $record, $request->session()->getId(), $receipt);
        abort_if($secret === null, 404);

        return response()
            ->view('filament.customer.secret', [
                'secret' => $secret,
                'backUrl' => SiteResource::getUrl('view', ['record' => $record], tenant: $organization),
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('Content-Security-Policy', "default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'")
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex');
    }
}
