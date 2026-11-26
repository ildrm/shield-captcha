<?php

namespace App\Console\Commands;

use App\Models\TeamInvitation;
use Illuminate\Console\Command;

final class PruneTeamInvitations extends Command
{
    protected $signature = 'shield:prune-team-invitations';

    protected $description = 'Delete up to 1,000 expired team invitations';

    public function handle(): int
    {
        $cutoff = now();
        $ids = TeamInvitation::query()->where('expires_at', '<=', $cutoff)
            ->orderBy('expires_at')->limit(1000)->pluck('id');
        TeamInvitation::query()->whereKey($ids)->where('expires_at', '<=', $cutoff)->delete();

        return self::SUCCESS;
    }
}
