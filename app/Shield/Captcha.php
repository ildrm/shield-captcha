<?php

namespace App\Shield;

use App\Billing\BillingEntitlements;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteCredential;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

final class Captcha
{
    public const MAX_NUMBER = 12_000;

    private const PROVIDER = 'sha256-pow-v1';

    private const MAX_ATTEMPTS = 3;

    private const ADAPTIVE_PENDING_THRESHOLD = 12;

    public function __construct(
        private readonly FixedWindows $limits,
        private readonly BillingEntitlements $billing,
    ) {}

    public function admitSource(string $ip): void
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new ApiProblem('service_unavailable', 503);
        }

        $now = now()->toImmutable();
        $source = hash_hmac('sha256', 'shield.ingress:'.$ip, $key);
        $retryAfter = $this->limits->hit('ingress:'.$source, 60, 120, $now);

        if ($retryAfter > 0) {
            throw new ApiProblem('rate_limited', 429, $retryAfter);
        }

        $retryAfter = $this->limits->hit('ingress:global', 60, 3_000, $now);

        if ($retryAfter > 0) {
            throw new ApiProblem('rate_limited', 429, $retryAfter);
        }
    }

    public function config(string $key, string $action, string $origin): array
    {
        $site = $this->findSite($key);
        $this->assertActive($site, Organization::query()->find($site->organization_id));
        $this->assertContext($site, $action, $origin, $site->environment);

        $mode = $site->challenge_mode;
        $resolved = match ($mode) {
            'invisible' => 'invisible',
            'adaptive' => $this->pendingChallenges($site) < self::ADAPTIVE_PENDING_THRESHOLD ? 'invisible' : 'checkbox',
            default => 'checkbox',
        };

        return [
            'site_key' => $site->public_key,
            'action' => $action,
            'environment' => $site->environment,
            'modes' => array_keys(Site::CHALLENGE_MODES),
            'mode' => $resolved,
            'provider' => self::PROVIDER,
        ];
    }

    public function issue(string $key, string $action, string $origin): array
    {
        return $this->forLockedSite($this->findSite($key), function (Site $site) use ($action, $origin): array|ApiProblem {
            $this->assertContext($site, $action, $origin, $site->environment);
            $now = now()->toImmutable();

            $retryAfter = $this->limits->hit('challenge:site:'.$site->id, 60, 30, $now);

            if ($retryAfter === 0) {
                $retryAfter = $this->limits->hit('challenge:org:'.$site->organization_id.':'.$site->environment, 60, 120, $now);
            }

            if ($retryAfter > 0) {
                return new ApiProblem('rate_limited', 429, $retryAfter);
            }

            if ($this->pendingChallenges($site) >= 40) {
                return new ApiProblem('challenge_capacity', 429, 30);
            }

            $id = (string) Str::uuid();
            $salt = bin2hex(random_bytes(16));
            $digest = hash('sha256', $salt.random_int(0, self::MAX_NUMBER));
            $expiresAt = $now->addMinutes(3);

            DB::table('shield_challenges')->insert([
                'id' => $id,
                'site_id' => $site->id,
                'action' => $action,
                'origin' => $origin,
                'environment' => $site->environment,
                'salt' => $salt,
                'challenge_digest' => $digest,
                'max_number' => self::MAX_NUMBER,
                'expires_at' => $expiresAt,
            ]);

            return [
                'id' => $id,
                'salt' => $salt,
                'challenge' => $digest,
                'max_number' => self::MAX_NUMBER,
                'expires_at' => $expiresAt->toIso8601String(),
                'provider' => self::PROVIDER,
            ];
        });
    }

    private function pendingChallenges(Site $site): int
    {
        return DB::table('shield_challenges')->where('site_id', $site->id)->whereNull('solved_at')
            ->where('attempts', '<', self::MAX_ATTEMPTS)->where('expires_at', '>', now())->count();
    }

    public function solve(string $id, string $key, string $action, string $origin, int $number): array
    {
        return $this->forLockedSite($this->findSite($key), function (Site $site) use ($id, $action, $origin, $number): array|ApiProblem {
            $this->assertContext($site, $action, $origin, $site->environment);
            $challenge = DB::table('shield_challenges')->where('site_id', $site->id)->where('id', $id)->lockForUpdate()->first();

            if ($challenge === null || $challenge->action !== $action || $challenge->origin !== $origin || $challenge->environment !== $site->environment) {
                throw new ApiProblem('challenge_invalid', 404);
            }

            $now = now()->toImmutable();

            if ($this->expired($challenge->expires_at, $now)) {
                throw new ApiProblem('challenge_expired', 410);
            }

            if ($challenge->solved_at !== null) {
                throw new ApiProblem('challenge_already_solved', 409);
            }

            if ($challenge->attempts >= self::MAX_ATTEMPTS) {
                throw new ApiProblem('attempt_limit', 429);
            }

            if ($number > $challenge->max_number || ! hash_equals($challenge->challenge_digest, hash('sha256', $challenge->salt.$number))) {
                DB::table('shield_challenges')->where('id', $id)->increment('attempts');

                return $challenge->attempts + 1 >= self::MAX_ATTEMPTS
                    ? new ApiProblem('attempt_limit', 429)
                    : new ApiProblem('solution_invalid', 422);
            }

            $token = bin2hex(random_bytes(32));
            $expiresAt = $now->addMinutes(2);
            DB::table('shield_tokens')->insert([
                'digest' => hash('sha256', $token),
                'challenge_id' => $id,
                'expires_at' => $expiresAt,
            ]);
            DB::table('shield_challenges')->where('id', $id)->update(['solved_at' => $now]);

            return ['token' => $token, 'expires_at' => $expiresAt->toIso8601String()];
        });
    }

    public function verify(
        #[SensitiveParameter] string $secret,
        #[SensitiveParameter] string $token,
        string $key,
        string $action,
        string $origin,
        string $environment,
    ): array {
        [$site, $credential] = $this->authenticate($secret, $key, 'verify');

        return $this->forLockedSite($site, function (Site $site) use ($credential, $token, $action, $origin, $environment): array|ApiProblem {
            $this->assertCurrentCredential($site, $credential);
            $this->assertContext($site, $action, $origin, $environment);
            $digest = hash('sha256', $token);
            $row = DB::table('shield_tokens as token')
                ->join('shield_challenges as challenge', 'challenge.id', '=', 'token.challenge_id')
                ->where('token.digest', $digest)->where('challenge.site_id', $site->id)->lockForUpdate()
                ->first(['token.expires_at', 'token.consumed_at', 'challenge.action', 'challenge.origin', 'challenge.environment']);

            if ($row === null || $row->action !== $action || $row->origin !== $origin || $row->environment !== $environment) {
                throw new ApiProblem('token_invalid', 422);
            }

            $now = now()->toImmutable();

            if ($this->expired($row->expires_at, $now)) {
                throw new ApiProblem('token_expired', 410);
            }

            if ($row->consumed_at !== null) {
                throw new ApiProblem('token_already_used', 409);
            }

            $allowance = $this->billing->dailyVerificationLimit($site->organization_id, $environment);
            $retryAfter = $this->limits->hit('verify:'.$site->organization_id.':'.$environment, 86_400, $allowance, $now);

            if ($retryAfter > 0) {
                return new ApiProblem('quota_exceeded', 429, $retryAfter);
            }

            DB::table('shield_tokens')->where('digest', $digest)->update(['consumed_at' => $now]);
            DB::table('shield_usage_events')->insert([
                'site_id' => $site->id,
                'action' => $action,
                'environment' => $environment,
                'occurred_at' => $now,
            ]);

            return [
                'success' => true,
                'decision' => 'allow',
                'site_key' => $site->public_key,
                'action' => $action,
                'origin' => $origin,
                'environment' => $environment,
                'verified_at' => $now->toIso8601String(),
            ];
        });
    }

    public function assessSms(
        #[SensitiveParameter] string $secret,
        string $key,
        string $action,
        string $eventId,
        #[SensitiveParameter] string $destinationHash,
    ): array {
        return $this->withSmsCredential(
            $secret,
            $key,
            $action,
            'assess',
            fn (Site $site): array|ApiProblem => $this->evaluateSms($site, $action, $eventId, $destinationHash),
        );
    }

    /**
     * Allow the caller to check delivery configuration and claim the dispatch within the SMS assessment transaction.
     *
     * @param  Closure(Site, Closure(string, string): (array|ApiProblem)): (array|ApiProblem)  $claim
     */
    public function assessSmsForDelivery(#[SensitiveParameter] string $secret, string $key, string $action, Closure $claim): array
    {
        return $this->withSmsCredential($secret, $key, $action, 'sms_send', function (Site $site) use ($action, $claim): array|ApiProblem {
            if ($site->environment !== 'production') {
                throw new ApiProblem('sms_production_required', 403);
            }

            return $claim($site, fn (string $eventId, string $destinationHash): array|ApiProblem => $this->evaluateSms($site, $action, $eventId, $destinationHash));
        });
    }

    private function withSmsCredential(#[SensitiveParameter] string $secret, string $key, string $action, string $scope, Closure $operation): array
    {
        [$site, $credential] = $this->authenticate($secret, $key, $scope);

        return $this->forLockedSite($site, function (Site $site) use ($credential, $action, $operation): array|ApiProblem {
            $this->assertCurrentCredential($site, $credential);
            $this->assertContext($site, $action, $site->origin, $site->environment);

            return $operation($site);
        });
    }

    private function evaluateSms(Site $site, string $action, string $eventId, #[SensitiveParameter] string $destinationHash): array|ApiProblem
    {
        $now = now()->toImmutable();
        $eventDigest = hash('sha256', $eventId);
        $requestDigest = hash('sha256', $action.':'.$destinationHash);
        $previous = DB::table('shield_sms_assessments')->where('site_id', $site->id)->where('event_digest', $eventDigest)->first();

        if ($previous !== null) {
            if (! hash_equals($previous->request_digest, $requestDigest)) {
                throw new ApiProblem('idempotency_conflict', 409);
            }

            return $this->smsResult($site, $previous, $now);
        }

        $tenantScope = $site->organization_id.':'.$site->environment;
        $retryAfter = $this->limits->hit('sms:org:'.$tenantScope, 86_400, 3_000, $now);

        if ($retryAfter === 0) {
            $retryAfter = $this->limits->hit('sms:site:'.$site->id, 3_600, 300, $now);
        }

        if ($retryAfter > 0) {
            return new ApiProblem('quota_exceeded', 429, $retryAfter);
        }

        $destination = $tenantScope.':'.$destinationHash;
        $retryAfter = $this->limits->cooldown('sms:cooldown:'.$destination, 60, $now);

        if ($retryAfter === 0) {
            $retryAfter = $this->limits->hit('sms:destination:'.$destination, 3_600, 3, $now);
        }

        $row = [
            'id' => (string) Str::uuid(),
            'site_id' => $site->id,
            'event_digest' => $eventDigest,
            'request_digest' => $requestDigest,
            'action' => $action,
            'environment' => $site->environment,
            'decision' => $retryAfter > 0 ? 'throttle' : 'allow',
            'reason_code' => $retryAfter > 0 ? 'destination_velocity' : 'within_limits',
            'retry_at' => $retryAfter > 0 ? $now->addSeconds($retryAfter)->toDateTimeString() : null,
            'created_at' => $now,
        ];
        DB::table('shield_sms_assessments')->insert($row);

        return $this->smsResult($site, (object) $row, $now);
    }

    private function findSite(string $key): Site
    {
        return Site::query()->where('public_key', $key)->first() ?? throw new ApiProblem('invalid_site', 404);
    }

    private function authenticate(#[SensitiveParameter] string $secret, string $key, string $scope): array
    {
        $digest = hash('sha256', $secret);
        $credential = SiteCredential::query()->where('secret_digest', $digest)->where('scope', $scope)->whereNull('revoked_at')->first();

        if ($credential === null || ! hash_equals($credential->secret_digest, $digest)) {
            throw new ApiProblem('invalid_key', 401);
        }

        $site = Site::query()->whereKey($credential->site_id)->where('public_key', $key)->first();

        if ($site === null) {
            throw new ApiProblem('invalid_key', 401);
        }

        return [$site, $credential];
    }

    private function assertCurrentCredential(Site $site, SiteCredential $credential): void
    {
        $current = SiteCredential::query()->where('site_id', $site->id)->lockForUpdate()->find($credential->id);

        if ($current === null || $current->revoked_at !== null || $current->scope !== $credential->scope || ! hash_equals($current->secret_digest, $credential->secret_digest)) {
            throw new ApiProblem('invalid_key', 401);
        }
    }

    private function forLockedSite(Site $site, Closure $operation): array
    {
        $result = DB::transaction(function () use ($site, $operation): array|ApiProblem {
            $organization = Organization::query()->lockForUpdate()->find($site->organization_id);
            $locked = Site::query()->where('organization_id', $site->organization_id)->lockForUpdate()->find($site->id);
            $this->assertActive($locked, $organization);

            return $operation($locked);
        }, 3);

        if ($result instanceof ApiProblem) {
            throw $result;
        }

        return $result;
    }

    private function assertActive(?Site $site, ?Organization $organization): void
    {
        if (! $organization?->is_active || ! $site?->is_active || ($site->environment === 'production' && $site->domain_verified_at === null)) {
            throw new ApiProblem('site_unavailable', 403);
        }
    }

    private function assertContext(Site $site, string $action, string $origin, string $environment): void
    {
        if ($site->environment !== $environment || $site->origin !== $origin || ! in_array($action, $site->actions, true)) {
            throw new ApiProblem('context_mismatch', 403);
        }
    }

    private function expired(string $expiresAt, CarbonImmutable $now): bool
    {
        return CarbonImmutable::parse($expiresAt, 'UTC')->lessThanOrEqualTo($now);
    }

    private function smsResult(Site $site, object $row, CarbonImmutable $now): array
    {
        $result = [
            'assessment_id' => $row->id,
            'site_key' => $site->public_key,
            'action' => $row->action,
            'environment' => $row->environment,
            'decision' => $row->decision,
            'reason_codes' => [$row->reason_code],
            'risk_score' => $row->decision === 'throttle' ? 60 : null,
            'policy_version' => 1,
        ];

        if ($row->retry_at !== null) {
            $result['retry_after'] = max(0, CarbonImmutable::parse($row->retry_at, 'UTC')->timestamp - $now->timestamp);
        }

        return $result;
    }
}
