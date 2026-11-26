#!/bin/sh
set -eu

root=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
stage=$(mktemp -d "${TMPDIR:-/tmp}/shield-host.XXXXXX")
trap 'rm -rf "$stage"' EXIT
trap 'exit 1' HUP INT TERM

app="$stage/shield-captcha"
mkdir -p "$app/downloads" "$app/packages/sdk"
cp "$root/downloads/README.md" "$app/downloads/README.md"

for item in app bootstrap config database docs examples lang openapi public resources routes; do
    mkdir -p "$app/$item"
    rsync -r --exclude='.env*' --exclude='*.log' --exclude='*.sqlite*' \
        --exclude='*.key' --exclude='*.pem' --exclude='*.sql' \
        --exclude='/cache/*' "$root/$item/" "$app/$item/"
done

mkdir -p "$app/bootstrap/cache" "$app/storage/logs" "$app/storage/app/private" \
    "$app/storage/framework/cache/data" "$app/storage/framework/sessions" "$app/storage/framework/views"

for file in .env.example artisan composer.json composer.lock LICENSE README.md DEPLOYMENT.md CHANGELOG.md \
    IMPLEMENTATION_PROMPT.md IMPLEMENTATION_STATUS.md REQUIREMENTS_TRACEABILITY.md DECISIONS.md \
    SECURITY.md SECURITY_MODEL.md INCIDENT_RUNBOOK.md PRIVACY_DATA_INVENTORY.md THIRD_PARTY_NOTICES.md; do
    cp "$root/$file" "$app/$file"
done

cp -R "$root/packages/sdk/php" "$app/packages/sdk/"
mkdir -p "$app/packages/sdk/node"
for file in README.md package.json index.cjs index.d.ts; do
    cp "$root/packages/sdk/node/$file" "$app/packages/sdk/node/$file"
done
node "$root/scripts/build-releases.mjs" --check

# Embed a catalog of packages available within this edition.
node -e '
    const fs = require("node:fs");
    const path = require("node:path");
    const [from, to] = process.argv.slice(1);
    const data = JSON.parse(fs.readFileSync(path.join(from, "manifest.json"), "utf8"));
    data.artifacts = data.artifacts.filter((entry) => ["sdk", "cms", "examples"].includes(entry.category));
    for (const entry of data.artifacts) {
        const target = path.join(to, entry.path);
        fs.mkdirSync(path.dirname(target), { recursive: true });
        fs.copyFileSync(path.join(from, entry.path), target);
    }
    fs.writeFileSync(path.join(to, "manifest.json"), JSON.stringify(data, null, 2) + "\n");
    fs.mkdirSync(path.join(to, "checksums"), { recursive: true });
    fs.writeFileSync(path.join(to, "checksums", "SHA256SUMS"),
        data.artifacts.map((entry) => entry.sha256 + "  " + entry.path + "\n").join(""));
' "$root/downloads" "$app/downloads"

(
    cd "$app"
    export COMPOSER_MAX_PARALLEL_HTTP=2 COMPOSER_MAX_PARALLEL_PROCESSES=1
    composer install --no-dev --prefer-dist --no-plugins --no-scripts --no-interaction --no-progress
    php artisan package:discover --no-ansi >/dev/null
    php artisan filament:assets --no-ansi >/dev/null
    composer check-platform-reqs --no-dev
)

mkdir -p "$root/downloads/self-hosted"
(
    cd "$stage"
    zip -1 -q -r "$stage/archive.zip" shield-captcha
)
mv "$stage/archive.zip" "$root/downloads/self-hosted/shield-captcha-shared-host-0.4.0.zip"
