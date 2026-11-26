<?php

namespace App\Filament\Operator\Widgets;

use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PlatformOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $operator = Filament::auth()->user();
        abort_unless($operator instanceof User && $operator->is_operator, 403);

        $since = now()->subDay();
        $validations = DB::table('shield_usage_events')->where('environment', 'production')->where('occurred_at', '>=', $since);
        $heartbeat = DB::table('shield_scheduler_status')->where('id', 1)->value('last_run_at');
        $lastRun = $heartbeat === null ? null : Carbon::parse($heartbeat);
        $working = $lastRun?->greaterThanOrEqualTo(now()->subMinutes(5)) ?? false;

        return [
            Stat::make('Organizations', Organization::query()->count()),
            Stat::make('Suspended organizations', Organization::query()->where('is_active', false)->count()),
            Stat::make('Sites', Site::query()->count()),
            Stat::make('Production verifications', $validations->count())->description('Successful consumes in the past 24 hours'),
            Stat::make('Scheduled jobs', $lastRun === null ? 'Awaiting first run' : ($working ? 'Scheduler seen' : 'Heartbeat delayed'))
                ->description($lastRun === null ? 'Set up the host cron job' : 'Last heartbeat '.$lastRun->diffForHumans())
                ->color($working ? 'success' : 'warning'),
        ];
    }
}
