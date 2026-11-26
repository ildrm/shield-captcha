<?php

namespace App\Console\Commands;

use App\Models\SmsDelivery;
use Illuminate\Console\Command;

final class PruneSmsDelivery extends Command
{
    protected $signature = 'shield:sms:prune';

    protected $description = 'Remove up to 1000 SMS delivery records older than 30 days';

    public function handle(): int
    {
        $ids = SmsDelivery::query()->where('created_at', '<', now()->subDays(30))
            ->orderBy('created_at')->orderBy('id')->limit(1000)->pluck('id');
        $count = SmsDelivery::query()->whereKey($ids)->delete();

        $this->info("Removed $count expired SMS delivery records.");

        return self::SUCCESS;
    }
}
