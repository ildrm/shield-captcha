<?php

namespace App\Risk;

use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteCredential;
use App\Shield\ApiProblem;
use App\Shield\FixedWindows;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;
use stdClass;

final class Protection
{
    public const PRODUCT_DECISIONS = [
        'generic' => 'challenge',
        'account' => 'step_up',
        'api' => 'throttle',
        'fraud' => 'review',
        'bot' => 'throttle',
    ];

    public function __construct(
        private readonly FixedWindows $limits,
        private readonly ProtectionPolicies $policies,
    ) {}

    public function assess(#[SensitiveParameter] string $secret, string $scope, string $product, #[SensitiveParameter] array $input): array
    {
        return $this->withSite($secret, $scope, $input, function (Site $site) use ($scope, $product, $input): array|ApiProblem {
            $now = now()->toImmutable();
            $context = 'protection:v1:'.$site->id.':'.$site->environment;
            $eventDigest = $this->digest($context.':event:'.$input['event_id']);
            $requestDigest = $this->digest($context.':request:'.json_encode([
                $input['action'], $product, $scope, $input['subject_hash'] ?? null,
                $input['declares_automation'] ?? false,
            ], JSON_THROW_ON_ERROR));
            $existing = DB::table('shield_protection_events')->where('site_id', $site->id)->where('event_digest', $eventDigest)->first();
            if ($existing !== null) {
                if (! hash_equals($existing->request_digest, $requestDigest)) {
                    throw new ApiProblem('idempotency_conflict', 409);
                }

                return $this->result($existing, $site, $now);
            }

            $retryAfter = $this->limits->hit('protection:v1:org:'.$site->organization_id.':'.$site->environment, 86_400, 10_000, $now);
            if ($retryAfter === 0) {
                $retryAfter = $this->limits->hit($context.':daily', 86_400, 3_000, $now);
            }
            if ($retryAfter > 0) {
                return new ApiProblem('quota_exceeded', 429, $retryAfter);
            }

            $policy = $this->policies->current($site, $input['action']);
            $actionScope = $context.':'.$product.':'.$input['action'];
            $subject = isset($input['subject_hash']) ? $this->digest($context.':subject:'.$input['subject_hash']) : null;
            $retryAfter = $this->limits->hit($actionScope, $policy['window_seconds'], $policy['site_limit'], $now);
            if ($subject !== null) {
                $retryAfter = max($retryAfter, $this->limits->hit($actionScope.':'.$subject, $policy['window_seconds'], $policy['subject_limit'], $now));
            }

            $classification = match (true) {
                $scope === 'automation' => 'registered_automation',
                $input['declares_automation'] ?? false => 'declared_automation',
                default => 'unknown',
            };
            [$wouldDecide, $score, $reason] = match (true) {
                $product === 'bot' && $policy['require_registered_automation'] && $scope !== 'automation' => ['deny', 85, 'registered_automation_required'],
                $retryAfter > 0 => [
                    $policy['velocity_decision'] === 'default' ? self::PRODUCT_DECISIONS[$product] : $policy['velocity_decision'],
                    70,
                    'unusual_velocity',
                ],
                $scope === 'automation' => ['allow', 0, 'registered_automation'],
                $subject !== null => ['allow', 0, 'within_policy'],
                default => ['allow', null, 'insufficient_context'],
            };
            $decision = $policy['mode'] === 'enforce' ? $wouldDecide : 'allow';

            $event = (object) [
                'id' => (string) Str::uuid(),
                'site_id' => $site->id,
                'event_digest' => $eventDigest,
                'request_digest' => $requestDigest,
                'action' => $input['action'],
                'product' => $product,
                'environment' => $site->environment,
                'policy_version' => $policy['version'],
                'mode' => $policy['mode'],
                'decision' => $decision,
                'would_decision' => $wouldDecide,
                'risk_score' => $score,
                'reason_codes' => json_encode([$reason], JSON_THROW_ON_ERROR),
                'classification' => $classification,
                'retry_at' => $decision === 'throttle' && $retryAfter > 0 ? $now->addSeconds($retryAfter)->toDateTimeString() : null,
                'created_at' => $now,
            ];
            DB::table('shield_protection_events')->insert((array) $event);

            return $this->result($event, $site, $now);
        });
    }

    public function feedback(#[SensitiveParameter] string $secret, array $input): array
    {
        return $this->withSite($secret, 'protect', $input, function (Site $site) use ($input): array {
            $query = DB::table('shield_protection_events')->where('site_id', $site->id)->where('action', $input['action'])
                ->where('environment', $site->environment)->where('id', $input['assessment_id']);
            $event = $query->lockForUpdate()->first();
            if ($event === null) {
                throw new ApiProblem('assessment_not_found', 404);
            }
            if ($event->feedback_label !== null && $event->feedback_label !== $input['label']) {
                throw new ApiProblem('idempotency_conflict', 409);
            }
            if ($event->feedback_label === null) {
                $query->update(['feedback_label' => $input['label'], 'feedback_at' => now()]);
            }

            return ['assessment_id' => $event->id, 'label' => $input['label']];
        });
    }

    private function withSite(#[SensitiveParameter] string $secret, string $scope, #[SensitiveParameter] array $input, Closure $operation): array
    {
        $hash = hash('sha256', $secret);
        $credential = SiteCredential::query()->where('secret_digest', $hash)
            ->where('scope', $scope)->whereNull('revoked_at')->first();
        $site = $credential?->site;
        if ($site === null || ! hash_equals($site->public_key, $input['site_key'])) {
            throw new ApiProblem('invalid_key', 401);
        }

        $result = DB::transaction(function () use ($site, $credential, $hash, $scope, $input, $operation): array|ApiProblem {
            $organization = Organization::query()->lockForUpdate()->find($site->organization_id);
            $site = Site::query()->where('organization_id', $site->organization_id)->lockForUpdate()->find($site->id);
            if ($organization === null || $site === null || ! $organization->is_active || ! $site->is_active
                || ($site->environment === 'production' && $site->domain_verified_at === null)) {
                throw new ApiProblem('site_unavailable', 403);
            }
            if (! $site->credentials()->whereKey($credential->id)->where('secret_digest', $hash)
                ->where('scope', $scope)->whereNull('revoked_at')->lockForUpdate()->first(['id'])) {
                throw new ApiProblem('invalid_key', 401);
            }
            if ($site->environment !== $input['environment'] || ! in_array($input['action'], $site->actions, true)) {
                throw new ApiProblem('context_mismatch', 403);
            }

            return $operation($site);
        }, 3);

        if ($result instanceof ApiProblem) {
            throw $result;
        }

        return $result;
    }

    private function digest(#[SensitiveParameter] string $context): string
    {
        return hash_hmac('sha256', $context, (string) config('app.key'));
    }

    private function result(stdClass $event, Site $site, CarbonImmutable $now): array
    {
        $result = [
            'assessment_id' => $event->id,
            'decision' => $event->decision,
            'would_decision' => $event->would_decision,
            'mode' => $event->mode,
            'risk_score' => $event->risk_score === null ? null : (int) $event->risk_score,
            'reason_codes' => json_decode($event->reason_codes, true, flags: JSON_THROW_ON_ERROR),
            'classification' => $event->classification,
            'policy_version' => (int) $event->policy_version,
            'site_key' => $site->public_key,
            'action' => $event->action,
            'environment' => $event->environment,
        ];
        if ($event->retry_at !== null) {
            $result['retry_after'] = max(0, CarbonImmutable::parse($event->retry_at, 'UTC')->timestamp - $now->timestamp);
        }

        return $result;
    }
}
