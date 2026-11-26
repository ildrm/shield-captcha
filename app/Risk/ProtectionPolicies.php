<?php

namespace App\Risk;

use App\Models\Site;
use App\Models\User;
use App\Tenancy\Audit;
use App\Tenancy\SiteManagement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use stdClass;

final class ProtectionPolicies
{
    public const DECISIONS = [
        'default' => 'Product default',
        'challenge' => 'Require CAPTCHA',
        'step_up' => 'Require stronger authentication',
        'throttle' => 'Throttle requests',
        'review' => 'Send to review',
        'deny' => 'Deny',
    ];

    public const DEFAULTS = [
        'version' => 0,
        'mode' => 'observe',
        'window_seconds' => 60,
        'subject_limit' => 10,
        'site_limit' => 120,
        'velocity_decision' => 'default',
        'require_registered_automation' => false,
    ];

    public function current(Site $site, string $action): array
    {
        $record = DB::table('shield_protection_policies')->where('site_id', $site->id)
            ->where('action', $action)->latest('version')->first();

        return $record === null ? self::DEFAULTS : $this->settings($record);
    }

    /** @return list<int> */
    public function recentVersions(Site $site, string $action): array
    {
        return DB::table('shield_protection_policies')->where('site_id', $site->id)
            ->where('action', $action)->latest('version')->limit(20)->pluck('version')->map(fn ($value): int => (int) $value)->all();
    }

    public function version(Site $site, string $action, int $version): ?array
    {
        if ($version === 0) {
            return self::DEFAULTS;
        }

        $record = DB::table('shield_protection_policies')->where('site_id', $site->id)
            ->where('action', $action)->where('version', $version)->first();

        return $record === null ? null : $this->settings($record);
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['observe', 'enforce'])],
            'window_seconds' => ['required', 'integer', 'between:10,3600'],
            'subject_limit' => ['required', 'integer', 'between:1,1000'],
            'site_limit' => ['required', 'integer', 'between:1,3000'],
            'velocity_decision' => ['required', Rule::in(array_keys(self::DECISIONS))],
            'require_registered_automation' => ['required', 'boolean'],
        ];
    }

    public function publish(User $actor, Site $site, string $action, array $policy): int
    {
        $data = Validator::make($policy, $this->rules())->validate();

        return DB::transaction(function () use ($actor, $site, $action, $data): int {
            $site = app(SiteManagement::class)->lock($site);
            Gate::forUser($actor)->authorize('update', $site);
            if (! in_array($action, $site->actions, true)) {
                throw ValidationException::withMessages(['action' => __('Select a registered action.')]);
            }

            $version = $this->current($site, $action)['version'] + 1;
            DB::table('shield_protection_policies')->insert($data + [
                'site_id' => $site->id,
                'action' => $action,
                'version' => $version,
                'created_at' => now(),
            ]);
            Audit::record($actor, 'protection_policy.published', $site->organization_id, $site->id, "{$action} v{$version}");

            return $version;
        }, 3);
    }

    private function settings(stdClass $record): array
    {
        return [
            'version' => (int) $record->version,
            'mode' => $record->mode,
            'window_seconds' => (int) $record->window_seconds,
            'subject_limit' => (int) $record->subject_limit,
            'site_limit' => (int) $record->site_limit,
            'velocity_decision' => $record->velocity_decision,
            'require_registered_automation' => (bool) $record->require_registered_automation,
        ];
    }
}
