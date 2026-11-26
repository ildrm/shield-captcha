<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneProtection extends Command
{
    protected $signature = 'shield:prune-protection {--batch=250 : Maximum assessment records to remove per run}';

    protected $description = 'Remove protection assessments and feedback after their 30-day retention period';

    public function handle(): int
    {
        $size = filter_var($this->option('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($size === false) {
            $this->error('Choose a batch size from 1 to 1000.');

            return self::INVALID;
        }

        $expired = DB::table('shield_protection_events')->where('created_at', '<=', now()->subDays(30));
        $ids = (clone $expired)->orderBy('created_at')->orderBy('id')->limit($size)->pluck('id');
        $deleted = $ids->isEmpty() ? 0 : $expired->whereIn('id', $ids)->delete();
        $this->line("Protection assessments removed {$deleted}");

        return self::SUCCESS;
    }
}
