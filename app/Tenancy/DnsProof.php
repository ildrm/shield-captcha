<?php

namespace App\Tenancy;

class DnsProof
{
    public function matches(string $name, string $value): bool
    {
        set_error_handler(static fn (): bool => true, E_WARNING);
        try {
            $records = dns_get_record($name.'.', DNS_TXT);
        } finally {
            restore_error_handler();
        }

        foreach ($records ?: [] as $record) {
            if (strcasecmp(rtrim($record['host'] ?? '', '.'), $name) === 0
                && is_string($record['txt'] ?? null)
                && hash_equals($value, $record['txt'])) {
                return true;
            }
        }

        return false;
    }
}
