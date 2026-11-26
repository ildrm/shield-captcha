<?php

namespace App\Billing;

use App\Models\BillingSubscription;

final class BillingEntitlements
{
    public const FREE_DAILY_LIMIT = 10_000;

    public function dailyVerificationLimit(int $organizationId, string $environment): int
    {
        if ($environment !== 'production') {
            return self::FREE_DAILY_LIMIT;
        }

        $now = now();
        $quota = BillingSubscription::query()->where('organization_id', $organizationId)
            ->where('starts_at', '<=', $now)->where('expires_at', '>', $now)
            ->latest('starts_at')->value('daily_verifications');

        return $quota ?? self::FREE_DAILY_LIMIT;
    }
}
