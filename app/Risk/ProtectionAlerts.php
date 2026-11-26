<?php

namespace App\Risk;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

final class ProtectionAlerts
{
    public const WINDOW_MINUTES = 60;

    public const MAX_ASSESSMENTS = 250;

    public const VELOCITY_THRESHOLD = 5;

    public const ABUSE_THRESHOLD = 3;

    public function forOrganization(Organization $organization): array
    {
        $events = DB::table('shield_protection_events as events')
            ->join('sites', 'sites.id', '=', 'events.site_id')
            ->where('sites.organization_id', $organization->id)
            ->where('events.created_at', '>=', now()->subMinutes(self::WINDOW_MINUTES))
            ->orderByDesc('events.created_at')->orderByDesc('events.id')->limit(self::MAX_ASSESSMENTS)
            ->get([
                'sites.name as site_name', 'events.site_id', 'events.action', 'events.environment',
                'events.mode', 'events.reason_codes', 'events.feedback_label',
            ]);
        $alerts = [];

        foreach ($events->groupBy(fn ($event): string => "{$event->site_id}:{$event->action}:{$event->environment}") as $group) {
            $velocity = ['observe' => 0, 'enforce' => 0];
            $abusive = 0;
            foreach ($group as $event) {
                if ($event->feedback_label === 'abusive') {
                    $abusive++;
                }
                if (in_array('unusual_velocity', json_decode($event->reason_codes, true, flags: JSON_THROW_ON_ERROR), true)) {
                    $velocity[$event->mode]++;
                }
            }

            if (array_sum($velocity) >= self::VELOCITY_THRESHOLD || $abusive >= self::ABUSE_THRESHOLD) {
                $context = $group->first();
                $alerts[] = [
                    'site' => $context->site_name,
                    'action' => $context->action,
                    'environment' => $context->environment,
                    'observed' => $velocity['observe'],
                    'enforced' => $velocity['enforce'],
                    'abusive' => $abusive,
                ];
            }
        }

        return $alerts;
    }
}
