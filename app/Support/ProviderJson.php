<?php

namespace App\Support;

use Illuminate\Http\Client\Response;

final class ProviderJson
{
    private const MAX_BYTES = 16_384;

    public static function read(Response $response, int $deadline): ?array
    {
        $body = $response->toPsrResponse()->getBody();
        try {
            $json = '';
            while (! $body->eof() && strlen($json) <= self::MAX_BYTES && hrtime(true) < $deadline) {
                $chunk = $body->read(min(4096, self::MAX_BYTES + 1 - strlen($json)));
                if ($chunk === '') {
                    break;
                }

                $json .= $chunk;
            }

            if (! $body->eof() || strlen($json) > self::MAX_BYTES) {
                return null;
            }

            $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);

            return is_array($data) ? $data : null;
        } finally {
            $body->close();
        }
    }
}
