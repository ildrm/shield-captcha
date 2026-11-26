<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PruneShieldData extends Command
{
    protected $signature = 'shield:prune {--batch=250 : Maximum rows to remove from each table per run}';

    protected $description = 'Remove expired challenge/token state and age out the Shield pilot usage records';

    public function handle(): int
    {
        $size = filter_var($this->option('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);

        if ($size === false) {
            $this->error('Choose a batch size from 1 to 1000.');

            return self::INVALID;
        }

        $now = now();
        $challenges = DB::table('shield_challenges')->where('expires_at', '<=', $now)
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('shield_tokens')->whereColumn('challenge_id', 'shield_challenges.id'));

        $deleted = [
            'tokens' => $this->deleteBatch(DB::table('shield_tokens')->where('expires_at', '<=', $now), 'digest', 'expires_at', $size),
            'challenges' => $this->deleteBatch($challenges, 'id', 'expires_at', $size),
            'rate windows' => $this->deleteBatch(DB::table('shield_rate_windows')->where('expires_at', '<=', $now->copy()->subMinute()), 'bucket', 'expires_at', $size),
            'usage events' => $this->deleteBatch(DB::table('shield_usage_events')->where('occurred_at', '<=', $now->copy()->subDays(30)), 'id', 'occurred_at', $size),
            'SMS assessments' => $this->deleteBatch(DB::table('shield_sms_assessments')->where('created_at', '<=', $now->copy()->subDays(7)), 'id', 'created_at', $size),
        ];

        foreach ($deleted as $table => $count) {
            $this->line("{$table} removed {$count}");
        }

        return self::SUCCESS;
    }

    private function deleteBatch(Builder $query, string $primaryKey, string $ageField, int $size): int
    {
        $ids = (clone $query)->orderBy($ageField)->orderBy($primaryKey)->limit($size)->pluck($primaryKey);

        return $ids->isEmpty() ? 0 : $query->whereIn($primaryKey, $ids)->delete();
    }
}
