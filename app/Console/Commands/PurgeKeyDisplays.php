<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeKeyDisplays extends Command
{
    protected $signature = 'shield:purge-key-displays';

    protected $description = 'Remove one bounded batch of expired credential display receipts';

    public function handle(): int
    {
        $expired = DB::table('shield_key_displays')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit(250)
            ->pluck('id');

        $deleted = DB::table('shield_key_displays')->whereIn('id', $expired)->delete();
        $this->info($deleted.' expired credential display receipts removed.');

        return self::SUCCESS;
    }
}
