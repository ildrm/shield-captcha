<?php

namespace App\Http\Controllers;

use App\Risk\Protection;
use App\Shield\ApiProblem;
use App\Shield\Captcha;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use SensitiveParameter;
use stdClass;

final class ProtectionController
{
    private const CONTEXT = [
        'site_key' => '/\Asite_(?:prod|test)_[a-f0-9]{32}\z/',
        'action' => '/\A[a-z][a-z0-9_-]{0,63}\z/',
        'environment' => '/\A(?:sandbox|production)\z/',
    ];

    public function assess(#[SensitiveParameter] Request $request, Protection $protection): JsonResponse
    {
        return $this->respond($request, function () use ($request, $protection): array {
            $product = (string) $request->route('product');
            [$scope, $secret] = $this->credential($request, $product === 'bot');
            $schema = self::CONTEXT + ['event_id' => '/\A[a-zA-Z0-9_-]{16,64}\z/'];
            $optional = ['subject_hash' => '/\A[a-f0-9]{64}\z/'];
            if (in_array($product, ['account', 'api', 'fraud'], true)) {
                $schema += $optional;
                $optional = [];
            }
            if ($product === 'bot') {
                $optional['declares_automation'] = null;
            }
            $input = $this->fields($request, $schema, $optional);

            return $protection->assess($secret, $scope, $product, $input);
        });
    }

    public function feedback(#[SensitiveParameter] Request $request, Protection $protection): JsonResponse
    {
        return $this->respond($request, function () use ($request, $protection): array {
            [, $secret] = $this->credential($request);
            $input = $this->fields($request, self::CONTEXT + [
                'assessment_id' => '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/',
                'label' => '/\A(?:legitimate|abusive|unknown)\z/',
            ]);

            return $protection->feedback($secret, $input);
        });
    }

    private function respond(#[SensitiveParameter] Request $request, Closure $operation): JsonResponse
    {
        $id = (string) Str::uuid();
        $headers = ['Cache-Control' => 'no-store', 'X-Request-ID' => $id, 'X-Content-Type-Options' => 'nosniff'];
        $status = 200;
        try {
            app(Captcha::class)->admitSource((string) $request->ip());
            $result = $operation();
        } catch (ApiProblem $error) {
            $status = $error->status;
            $result = ['success' => false, 'code' => $error->errorCode];
            if ($status === 401) {
                $headers['WWW-Authenticate'] = 'Bearer';
            }
            if ($error->retryAfter !== null) {
                $headers['Retry-After'] = (string) $error->retryAfter;
                $result['retry_after'] = $error->retryAfter;
            }
        } catch (QueryException $error) {
            Log::warning('Protection data operation unavailable', ['request_id' => $id, 'code' => (string) $error->getCode()]);
            $status = 503;
            $result = ['success' => false, 'code' => 'service_unavailable'];
        }

        return response()->json($result + ['request_id' => $id], $status, $headers);
    }

    private function credential(#[SensitiveParameter] Request $request, bool $allowAutomation = false): array
    {
        $header = (string) $request->header('Authorization');
        if (strlen($header) > 90 || ! preg_match('/\A(?i:Bearer) (sk_(protect|automation)_[a-f0-9]{64})\z/', $header, $matches)
            || ($matches[2] === 'automation' && ! $allowAutomation)) {
            throw new ApiProblem('invalid_key', 401);
        }

        return [$matches[2], $matches[1]];
    }

    private function fields(#[SensitiveParameter] Request $request, array $required, array $optional = []): array
    {
        $mediaType = strtolower(trim(explode(';', (string) $request->header('Content-Type'), 2)[0]));
        if ($mediaType !== 'application/json') {
            throw new ApiProblem('unsupported_media_type', 415);
        }

        $stream = $request->getContent(true);
        $body = stream_get_contents($stream, 4_097);
        if ($body === false || strlen($body) > 4_096) {
            throw new ApiProblem('body_too_large', 413);
        }
        try {
            $document = json_decode($body, depth: 4, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiProblem('invalid_json', 400);
        }
        if (! $document instanceof stdClass) {
            throw new ApiProblem('invalid_request', 422);
        }
        $values = (array) $document;
        $all = $required + $optional;
        if (array_diff_key($values, $all) !== [] || array_diff_key($required, $values) !== []) {
            throw new ApiProblem('invalid_request', 422);
        }
        foreach ($values as $key => $value) {
            $pattern = $all[$key];
            if ($pattern === null ? ! is_bool($value) : (! is_string($value) || preg_match($pattern, $value) !== 1)) {
                throw new ApiProblem('invalid_request', 422);
            }
        }

        return $values;
    }
}
