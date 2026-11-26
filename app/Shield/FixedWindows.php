<?php

namespace App\Shield;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class FixedWindows
{
    /** @return int Seconds until the window resets if the limit is reached, otherwise zero. */
    public function hit(string $scope, int $seconds, int $limit, CarbonImmutable $now): int
    {
        $start = intdiv($now->timestamp, $seconds) * $seconds;
        $end = $start + $seconds;
        $bucket = hash('sha256', $scope.':'.$seconds.':'.$start);

        DB::table('shield_rate_windows')->insertOrIgnore([
            'bucket' => $bucket,
            'expires_at' => CarbonImmutable::createFromTimestampUTC($end),
        ]);

        $counted = DB::table('shield_rate_windows')
            ->where('bucket', $bucket)
            ->where('hits', '<', $limit)
            ->increment('hits');

        return $counted === 1 ? 0 : $end - $now->timestamp;
    }

    public function cooldown(string $scope, int $seconds, CarbonImmutable $now): int
    {
        $bucket = hash('sha256', $scope);
        $expiresAt = $now->addSeconds($seconds);

        $inserted = DB::table('shield_rate_windows')->insertOrIgnore([
            'bucket' => $bucket,
            'expires_at' => $expiresAt,
        ]);

        if ($inserted === 1) {
            return 0;
        }

        $row = DB::table('shield_rate_windows')->where('bucket', $bucket)->lockForUpdate()->first();
        $previousExpiry = CarbonImmutable::parse($row->expires_at, 'UTC');

        if ($previousExpiry->greaterThan($now)) {
            return $previousExpiry->timestamp - $now->timestamp;
        }

        DB::table('shield_rate_windows')->where('bucket', $bucket)->update(['expires_at' => $expiresAt]);

        return 0;
    }
}
