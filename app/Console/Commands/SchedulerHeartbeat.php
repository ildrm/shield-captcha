<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SchedulerHeartbeat extends Command
{
    protected $signature = 'shield:heartbeat';

    protected $description = 'Record that the scheduler reached the application database';

    public function handle(): int
    {
        DB::table('shield_scheduler_status')->upsert(
            ['id' => 1, 'last_run_at' => now()],
            ['id'],
            ['last_run_at'],
        );

        return self::SUCCESS;
    }
}
