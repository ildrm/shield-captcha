<?php

namespace App\Filament\Customer\Widgets;

use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class OrganizationOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $organization = Filament::getTenant();
        $user = Filament::auth()->user();
        abort_unless($organization instanceof Organization && $user instanceof User && $user->canAccessTenant($organization), 403);
        $siteIds = $organization->sites()->select('sites.id');
        $since = now()->subDay();
        $validations = DB::table('shield_usage_events')->whereIn('site_id', $siteIds)->where('occurred_at', '>=', $since);
        $assessments = DB::table('shield_sms_assessments')->whereIn('site_id', $siteIds)->where('created_at', '>=', $since);

        return [
            Stat::make('Active sites', $organization->sites()->where('is_active', true)->count()),
            Stat::make('Production verifications', (clone $validations)->where('environment', 'production')->count())->description('Successful consumes in the past 24 hours'),
            Stat::make('Sandbox verifications', (clone $validations)->where('environment', 'sandbox')->count())->description('Successful consumes in the past 24 hours'),
            Stat::make('Production SMS assessments', $assessments->where('environment', 'production')->count())->description('Past 24 hours'),
        ];
    }
}
