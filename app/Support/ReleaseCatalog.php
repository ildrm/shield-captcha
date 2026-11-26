<?php

namespace App\Support;

final class ReleaseCatalog
{
    public function publicArtifacts(): array
    {
        $manifest = base_path('downloads/manifest.json');
        if (! is_file($manifest) || is_link($manifest) || filesize($manifest) > 262144) {
            return [];
        }

        $data = json_decode((string) file_get_contents($manifest), true, 32);
        if (($data['schema_version'] ?? null) !== 1 || ! is_array($data['artifacts'] ?? null)) {
            return [];
        }

        $result = [];
        foreach ($data['artifacts'] as $entry) {
            if (! is_array($entry)
                || ($entry['visibility'] ?? null) !== 'public'
                || ! in_array($entry['status'] ?? null, ['verified', 'built-unverified', 'source-only'], true)
                || ! is_string($entry['id'] ?? null)
                || ! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,119}$/D', $entry['id'])
                || ! is_string($entry['sha256'] ?? null)
                || ! preg_match('/^[a-f0-9]{64}$/D', $entry['sha256'])
                || ! is_string($entry['category'] ?? null)
                || ! is_string($entry['version'] ?? null)
                || $this->resolve($entry) === null) {
                continue;
            }

            $result[$entry['id']] = $entry;
        }

        return $result;
    }

    public function resolve(array $entry): ?string
    {
        $relative = $entry['path'] ?? null;
        $size = $entry['size_bytes'] ?? null;
        if (! is_string($relative)
            || ! preg_match('~\A(?:[a-zA-Z0-9_-]+/)+[a-zA-Z0-9][a-zA-Z0-9._-]*\z~D', $relative)
            || basename($relative) !== ($entry['filename'] ?? null)
            || ! is_int($size)
            || $size < 1
            || $size > 128 * 1024 * 1024) {
            return null;
        }

        $root = realpath(base_path('downloads'));
        $candidate = $root.DIRECTORY_SEPARATOR.$relative;

        return $root !== false
            && ! is_link(base_path('downloads'))
            && realpath($candidate) === $candidate
            && is_file($candidate)
            && filesize($candidate) === $size
            ? $candidate
            : null;
    }
}
