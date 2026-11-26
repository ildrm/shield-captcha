import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import {
    closeSync, copyFileSync, existsSync, mkdirSync, mkdtempSync, openSync, readFileSync, readSync, realpathSync, renameSync, rmSync, statSync,
    utimesSync, writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, dirname, join, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
const downloads = join(root, 'downloads');
const version = '0.1.0';
const browserVersion = '0.2.0';
const applicationVersion = '0.4.0';
const releasedAt = '2026-11-26T00:00:00Z';
const license = 'GPL-2.0-only';
const artifacts = [];

function copy(from, to) {
    mkdirSync(dirname(to), { recursive: true });
    copyFileSync(from, to);
    const date = new Date(releasedAt);
    utimesSync(to, date, date);
}

function stage(stageDir, mapping) {
    for (const [destination, source] of Object.entries(mapping)) {
        copy(join(root, source), join(stageDir, destination));
    }
}

function hashFile(path) {
    const hash = createHash('sha256');
    const bytes = Buffer.alloc(256 * 1024);
    const file = openSync(path, 'r');
    try {
        for (let length = readSync(file, bytes); length > 0; length = readSync(file, bytes)) {
            hash.update(bytes.subarray(0, length));
        }
    } finally {
        closeSync(file);
    }
    return hash.digest('hex');
}

function record(details, source) {
    const target = join(downloads, details.path);
    if (source !== target) {
        copy(source, target + '.tmp');
        renameSync(target + '.tmp', target);
    }
    artifacts.push({
        ...details,
        filename: basename(details.path),
        size_bytes: statSync(target).size,
        sha256: hashFile(target),
        version: details.version ?? version,
        protocol: 'v1',
        license,
        released_at: releasedAt,
        docs_url: '/docs',
        visibility: 'public',
        status: 'built-unverified',
    });
}

function verify() {
    const manifest = JSON.parse(readFileSync(join(downloads, 'manifest.json'), 'utf8'));
    if (manifest.schema_version !== 1 || !Array.isArray(manifest.artifacts) || manifest.artifacts.length < 3) {
        throw new Error('Invalid release manifest.');
    }
    const absoluteDownloads = realpathSync(downloads);
    const ids = new Set();
    const paths = new Set();
    const checksums = [];
    for (const artifact of manifest.artifacts) {
        if (!/^[a-z0-9-]+$/.test(artifact.id) || ids.has(artifact.id) || paths.has(artifact.path)) {
            throw new Error('Duplicate or invalid release identity.');
        }
        const expected = resolve(absoluteDownloads, artifact.path);
        if (
            !expected.startsWith(absoluteDownloads + sep)
            || realpathSync(expected) !== expected || basename(expected) !== artifact.filename
            || !statSync(expected).isFile()
        ) {
            throw new Error('Invalid artifact path for ' + artifact.id);
        }
        const sha256 = hashFile(expected);
        if (statSync(expected).size !== artifact.size_bytes || sha256 !== artifact.sha256) {
            throw new Error('Artifact checksum mismatch for ' + artifact.id);
        }
        ids.add(artifact.id);
        paths.add(artifact.path);
        checksums.push(sha256 + '  ' + artifact.path + '\n');
    }
    if (readFileSync(join(downloads, 'checksums/SHA256SUMS'), 'utf8') !== checksums.join('')) {
        throw new Error('Release checksum index differs from the manifest.');
    }
    process.stdout.write('Verified ' + manifest.artifacts.length + ' release checksums.\n');
}

if (process.argv.includes('--check')) {
    verify();
    process.exit(0);
}

const temp = mkdtempSync(join(tmpdir(), 'shield-release-'));
try {
    const widgetSource = 'public/assets/captcha/v1/';
    const js = join(temp, 'widget');
    stage(js, {
        'widget.js': widgetSource + 'widget.js',
        'widget.css': widgetSource + 'widget.css',
        'widget.d.ts': 'packages/sdk/js/widget.d.ts',
        'package.json': 'packages/sdk/js/package.json',
        'README.md': 'packages/sdk/js/README.md',
        'LICENSE': 'LICENSE',
    });
    const [npmPackage] = JSON.parse(execFileSync('npm', [
        'pack', '--offline', '--ignore-scripts', '--json', '--pack-destination', temp, '--cache', join(temp, 'npm-cache'),
    ], { cwd: js, encoding: 'utf8' }));
    record({
        id: 'widget-js', product: 'Browser widget and TypeScript types', ecosystem: 'npm', category: 'sdk', type: 'source',
        platforms: ['Browsers with Web Workers and Web Crypto'],
        path: 'sdk/javascript/shield-captcha-widget-' + browserVersion + '.tgz', version: browserVersion,
    }, join(temp, npmPackage.filename));

    const serverJs = join(temp, 'verify-node');
    const nodeSdk = 'packages/sdk/node/';
    stage(serverJs, {
        'index.cjs': nodeSdk + 'index.cjs',
        'index.d.ts': nodeSdk + 'index.d.ts',
        'package.json': nodeSdk + 'package.json',
        'README.md': nodeSdk + 'README.md',
        'LICENSE': 'LICENSE',
    });
    const [nodePackage] = JSON.parse(execFileSync('npm', [
        'pack', '--offline', '--ignore-scripts', '--json', '--pack-destination', temp, '--cache', join(temp, 'npm-cache'),
    ], { cwd: serverJs, encoding: 'utf8' }));
    record({
        id: 'verify-node', product: 'Node.js verification SDK', ecosystem: 'npm', category: 'sdk', type: 'source',
        platforms: ['Node.js 22+'], path: 'sdk/node/shield-captcha-verify-' + version + '.tgz',
    }, join(temp, nodePackage.filename));

    const php = join(temp, 'php');
    const sdk = 'packages/sdk/php/';
    const phpFiles = {
        'src/Verification.php': sdk + 'src/Verification.php',
        'src/Verifier.php': sdk + 'src/Verifier.php',
        'composer.json': sdk + 'composer.json',
        'README.md': sdk + 'README.md',
        'LICENSE': 'LICENSE',
    };
    stage(php, phpFiles);
    const phpZip = join(temp, 'php.zip');
    execFileSync('zip', ['-X', '-q', phpZip, ...Object.keys(phpFiles)], { cwd: php });
    record({
        id: 'verify-php', product: 'PHP verification SDK', ecosystem: 'Composer', category: 'sdk', type: 'source',
        platforms: ['PHP 8.3+ with cURL'], path: 'sdk/php/shield-captcha-php-' + version + '.zip',
    }, phpZip);

    const wordpressDir = 'shield-captcha';
    const wordpress = 'packages/cms/wordpress/';
    const wpFiles = {
        'shield-captcha.php': wordpress + 'shield-captcha.php',
        'uninstall.php': wordpress + 'uninstall.php',
        'readme.txt': wordpress + 'readme.txt',
        'src/Verification.php': sdk + 'src/Verification.php',
        'assets/widget.js': widgetSource + 'widget.js',
        'assets/widget.css': widgetSource + 'widget.css',
        'LICENSE': 'LICENSE',
    };
    stage(join(temp, wordpressDir), wpFiles);
    const wpZip = join(temp, 'wordpress.zip');
    execFileSync('zip', ['-X', '-q', wpZip, ...Object.keys(wpFiles).map((file) => wordpressDir + '/' + file)], { cwd: temp });
    record({
        id: 'plugin-wordpress', product: 'WordPress and WooCommerce plugin', ecosystem: 'WordPress', category: 'cms',
        type: 'source', platforms: ['WordPress 6.6+ target', 'Classic WooCommerce login and registration target', 'PHP 8.3+'],
        path: 'cms/wordpress/shield-captcha-wordpress-' + browserVersion + '.zip', version: browserVersion,
    }, wpZip);

    for (const [id, product, source, extension] of [
        ['openapi-v1', 'OpenAPI schema', 'openapi/v1.yaml', 'yaml'],
        ['postman-v1', 'Postman collection', 'openapi/shield-captcha-v1.postman_collection.json', 'json'],
    ]) {
        if (existsSync(join(root, source))) {
            record({
                id, product, ecosystem: id === 'openapi-v1' ? 'OpenAPI' : 'Postman', category: 'examples', type: 'source',
                platforms: ['API v1'], path: 'examples/shield-captcha-' + id + '-' + applicationVersion + '.' + extension,
                version: applicationVersion,
            }, join(root, source));
        }
    }
    const selfHosted = 'self-hosted/shield-captcha-shared-host-' + applicationVersion + '.zip';
    if (existsSync(join(downloads, selfHosted))) {
        record({
            id: 'shared-host', product: 'Shared-hosting application', ecosystem: 'Laravel', category: 'self-hosted',
            version: applicationVersion,
            type: 'distribution', platforms: ['PHP 8.3+', 'MySQL or MariaDB'],
            path: selfHosted,
        }, join(downloads, selfHosted));
    }

    const checksumText = artifacts.map(({ path, sha256 }) => sha256 + '  ' + path + '\n').join('');
    const checksumDir = join(downloads, 'checksums');
    mkdirSync(checksumDir, { recursive: true });
    const checksumPath = join(checksumDir, 'SHA256SUMS');
    writeFileSync(checksumPath + '.tmp', checksumText);
    renameSync(checksumPath + '.tmp', checksumPath);
    const manifestPath = join(downloads, 'manifest.json');
    writeFileSync(manifestPath + '.tmp', JSON.stringify({ schema_version: 1, artifacts }, null, 2) + '\n');
    renameSync(manifestPath + '.tmp', manifestPath);
    verify();
} finally {
    rmSync(temp, { recursive: true, force: true });
}
