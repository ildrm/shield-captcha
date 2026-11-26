<?php

namespace App\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class Audit
{
    public static function record(
        User $actor,
        string $operation,
        ?int $organizationId = null,
        ?int $siteId = null,
        ?string $reason = null,
    ): void {
        DB::table('shield_audit_entries')->insert([
            'actor_id' => $actor->id,
            'organization_id' => $organizationId,
            'site_id' => $siteId,
            'operation' => $operation,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
