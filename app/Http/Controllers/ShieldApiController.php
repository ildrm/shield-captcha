<?php

namespace App\Http\Controllers;

use App\Shield\ApiProblem;
use App\Shield\Captcha;
use App\Sms\DeliverSms;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use SensitiveParameter;
use stdClass;

final class ShieldApiController
{
    private const SITE_KEY_PATTERN = '/\Asite_(?:test|prod)_[a-f0-9]{32}\z/';

    private const ACTION_PATTERN = '/\A[a-z][a-z0-9_-]{0,63}\z/';

    private const ORIGIN_PATTERN = '/\Ahttps?:\/\/[a-z0-9.\-\[\]:]{1,247}\z/';

    private const HEX_PATTERN = '/\A[a-f0-9]{64}\z/';

    private const UUID_PATTERN = '/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/';

    public function __construct(private readonly Captcha $captcha) {}

    public function config(Request $request): JsonResponse
    {
        return $this->respond($request, function () use ($request): array {
            $fields = $request->isMethod('POST') ? $this->body($request) : $request->query();
            $input = $this->fields($fields, ['site_key' => self::SITE_KEY_PATTERN, 'action' => self::ACTION_PATTERN]);

            return $this->captcha->config($input['site_key'], $input['action'], $this->origin($request));
        }, browser: true);
    }

    public function issue(Request $request): JsonResponse
    {
        return $this->respond($request, function () use ($request): array {
            $input = $this->fields($this->body($request), ['site_key' => self::SITE_KEY_PATTERN, 'action' => self::ACTION_PATTERN]);

            return $this->captcha->issue($input['site_key'], $input['action'], $this->origin($request));
        }, browser: true, status: 201);
    }

    public function solve(#[SensitiveParameter] Request $request, string $id): JsonResponse
    {
        return $this->respond($request, function () use ($request, $id): array {
            if (! preg_match(self::UUID_PATTERN, $id)) {
                throw new ApiProblem('challenge_invalid', 404);
            }

            $input = $this->fields($this->body($request), [
                'site_key' => self::SITE_KEY_PATTERN,
                'action' => self::ACTION_PATTERN,
                'number' => null,
            ]);

            return $this->captcha->solve($id, $input['site_key'], $input['action'], $this->origin($request), $input['number']);
        }, browser: true);
    }

    public function verify(#[SensitiveParameter] Request $request): JsonResponse
    {
        return $this->respond($request, function () use ($request): array {
            $secret = $this->secret($request, 'verify');
            $input = $this->fields($this->body($request), [
                'token' => self::HEX_PATTERN,
                'site_key' => self::SITE_KEY_PATTERN,
                'expected_action' => self::ACTION_PATTERN,
                'expected_origin' => self::ORIGIN_PATTERN,
                'expected_environment' => '/\A(?:production|sandbox)\z/',
            ]);

            return $this->captcha->verify($secret, $input['token'], $input['site_key'], $input['expected_action'], $input['expected_origin'], $input['expected_environment']);
        });
    }

    public function assessSms(#[SensitiveParameter] Request $request): JsonResponse
    {
        return $this->respond($request, function () use ($request): array {
            $secret = $this->secret($request, 'assess');
            $input = $this->fields($this->body($request), [
                'site_key' => self::SITE_KEY_PATTERN,
                'action' => self::ACTION_PATTERN,
                'event_id' => '/\A[a-zA-Z0-9_-]{16,64}\z/',
                'destination_hash' => self::HEX_PATTERN,
            ]);

            return $this->captcha->assessSms($secret, $input['site_key'], $input['action'], $input['event_id'], $input['destination_hash']);
        });
    }

    public function sendSms(#[SensitiveParameter] Request $request, DeliverSms $sender): JsonResponse
    {
        return $this->respond($request, function () use ($request, $sender): array {
            $secret = $this->secret($request, 'sms_send');
            $input = $this->fields($this->body($request), [
                'site_key' => self::SITE_KEY_PATTERN,
                'action' => self::ACTION_PATTERN,
                'event_id' => '/\A[a-zA-Z0-9_-]{16,64}\z/',
                'destination' => DeliverSms::DESTINATION,
                'otp' => '/\A[0-9]{4,10}\z/',
            ]);

            return $sender->send($secret, $input['site_key'], $input['action'], $input['event_id'], $input['destination'], $input['otp']);
        });
    }

    public function preflightConfig(Request $request): Response
    {
        return $this->preflight($request, $request->header('Access-Control-Request-Method') === 'POST' ? 'POST' : 'GET');
    }

    public function preflightWrite(Request $request): Response
    {
        return $this->preflight($request, 'POST');
    }

    private function preflight(Request $request, string $method): Response
    {
        $origin = (string) $request->header('Origin');
        $requestedHeaders = array_filter(array_map('trim', explode(',', strtolower((string) $request->header('Access-Control-Request-Headers')))));
        $headers = [
            'Cache-Control' => 'no-store',
            'Vary' => 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers',
        ];

        if ($request->header('Access-Control-Request-Method') !== $method || array_diff($requestedHeaders, ['content-type']) !== []) {
            return response('', 403, $headers);
        }

        try {
            $this->captcha->admitSource((string) $request->ip());

            if (! $this->registeredOrigin($origin)) {
                return response('', 403, $headers);
            }
        } catch (ApiProblem $error) {
            if ($error->retryAfter !== null) {
                $headers['Retry-After'] = (string) $error->retryAfter;
            }

            return response('', $error->status, $headers);
        } catch (QueryException $error) {
            Log::warning('Shield preflight data operation unavailable', ['code' => (string) $error->getCode()]);

            return response('', 503, $headers);
        }

        return response()->noContent(204, $headers + [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => $method,
            'Access-Control-Allow-Headers' => 'Content-Type',
            'Access-Control-Max-Age' => '300',
        ]);
    }

    private function respond(#[SensitiveParameter] Request $request, Closure $operation, bool $browser = false, int $status = 200): JsonResponse
    {
        $requestId = (string) Str::uuid();
        $headers = [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Request-ID' => $requestId,
        ];

        if ($browser) {
            $headers['Vary'] = 'Origin';
        }

        try {
            $this->captcha->admitSource((string) $request->ip());
            $origin = (string) $request->header('Origin');

            if ($browser && $this->registeredOrigin($origin)) {
                $headers['Access-Control-Allow-Origin'] = $origin;
            }

            $body = $operation();
        } catch (ApiProblem $error) {
            $status = $error->status;
            $body = ['success' => false, 'code' => $error->errorCode];

            if ($status === 401) {
                $headers['WWW-Authenticate'] = 'Bearer';
            }

            if ($error->retryAfter !== null) {
                $body['retry_after'] = $error->retryAfter;
                $headers['Retry-After'] = (string) $error->retryAfter;
            }
        } catch (QueryException $error) {
            Log::warning('Shield data operation unavailable', ['request_id' => $requestId, 'code' => (string) $error->getCode()]);
            $status = 503;
            $body = ['success' => false, 'code' => 'service_unavailable'];
        }

        return response()->json($body + ['request_id' => $requestId], $status, $headers);
    }

    private function registeredOrigin(string $origin): bool
    {
        if (strlen($origin) > 255 || ! preg_match(self::ORIGIN_PATTERN, $origin)) {
            return false;
        }

        return DB::table('sites')->join('organizations', 'organizations.id', '=', 'sites.organization_id')
            ->where('sites.origin', $origin)->where('sites.is_active', true)->where('organizations.is_active', true)
            ->where(fn ($query) => $query->where('sites.environment', 'sandbox')->orWhereNotNull('sites.domain_verified_at'))
            ->value('sites.origin') === $origin;
    }

    private function origin(Request $request): string
    {
        $origin = (string) $request->header('Origin');

        if (strlen($origin) > 255 || ! preg_match(self::ORIGIN_PATTERN, $origin)) {
            throw new ApiProblem('context_mismatch', 403);
        }

        return $origin;
    }

    private function secret(#[SensitiveParameter] Request $request, string $scope): string
    {
        $authorization = (string) $request->header('Authorization');

        if (strlen($authorization) > 90 || ! preg_match('/\A(?i:Bearer) (sk_'.$scope.'_[a-f0-9]{64})\z/', $authorization, $matches)) {
            throw new ApiProblem('invalid_key', 401);
        }

        return $matches[1];
    }

    private function body(#[SensitiveParameter] Request $request): array
    {
        $mediaType = strtolower(trim(explode(';', (string) $request->header('Content-Type'), 2)[0]));

        if ($mediaType !== 'application/json') {
            throw new ApiProblem('unsupported_media_type', 415);
        }

        $content = $request->getContent();

        if (strlen($content) > 4_096) {
            throw new ApiProblem('body_too_large', 413);
        }

        try {
            $decoded = json_decode($content, depth: 8, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiProblem('invalid_json', 400);
        }

        if (! $decoded instanceof stdClass) {
            throw new ApiProblem('invalid_request', 422);
        }

        return (array) $decoded;
    }

    /** @param array<string, string|null> $schema A null pattern requires a bounded integer. */
    private function fields(#[SensitiveParameter] array $input, array $schema): array
    {
        if (count($input) !== count($schema)) {
            throw new ApiProblem('invalid_request', 422);
        }

        foreach ($schema as $key => $pattern) {
            $value = $input[$key] ?? null;
            $valid = $pattern === null
                ? is_int($value) && $value >= 0 && $value <= Captcha::MAX_NUMBER
                : is_string($value) && strlen($value) <= 255 && preg_match($pattern, $value) === 1;

            if (! $valid) {
                throw new ApiProblem('invalid_request', 422);
            }
        }

        return $input;
    }
}
