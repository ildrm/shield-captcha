import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
const temp = mkdtempSync(join(tmpdir(), 'shield-package-test-'));

try {
    const js = join(temp, 'js');
    mkdirSync(js);
    writeFileSync(join(js, 'package.json'), JSON.stringify({ name: 'widget-test', version: '1.0.0', private: true }));
    execFileSync('npm', [
        'install', '--offline', '--no-audit', '--ignore-scripts', '--cache', join(temp, 'npm-cache'),
        join(root, 'downloads/sdk/javascript/shield-captcha-widget-0.2.0.tgz'),
    ], { cwd: js, stdio: 'inherit' });
    const widget = join(js, 'node_modules/@shield-captcha/widget');
    execFileSync(process.execPath, ['--check', join(widget, 'widget.js')], { stdio: 'inherit' });
    for (const file of ['widget.js', 'widget.css']) {
        assert.deepEqual(readFileSync(join(widget, file)), readFileSync(join(root, 'public/assets/captcha/v1', file)));
    }

    const php = join(temp, 'php');
    mkdirSync(php);
    writeFileSync(join(php, 'composer.json'), JSON.stringify({
        name: 'shield-captcha/package-smoke',
        repositories: {
            shield: { type: 'artifact', url: join(root, 'downloads/sdk/php') },
            'packagist.org': false,
        },
        require: { 'shield-captcha/verify': '0.1.0' },
    }));
    execFileSync('composer', ['install', '--no-scripts', '--no-plugins', '--no-interaction', '--no-progress'], {
        cwd: php,
        env: { ...process.env, COMPOSER_HOME: join(temp, 'composer'), COMPOSER_DISABLE_NETWORK: '1' },
        stdio: 'inherit',
    });
    const smoke = [
        "require 'vendor/autoload.php';",
        "$site = 'site_test_' . str_repeat('a', 32);",
        "$request = \\ShieldCaptcha\\Verification::request(str_repeat('f', 64), $site, 'contact', 'https://shop.example.test', 'sandbox');",
        'if ($request === null) exit(1);',
        "$valid = ['success' => true, 'decision' => 'allow', 'site_key' => $site, 'action' => 'contact',",
        "'origin' => 'https://shop.example.test', 'environment' => 'sandbox'];",
        'if (! \\ShieldCaptcha\\Verification::passed(200, json_encode($valid), $request)) exit(2);',
        "$valid['origin'] = 'https://other.example.test';",
        'if (\\ShieldCaptcha\\Verification::passed(200, json_encode($valid), $request)) exit(3);',
        "if (! (new \\ShieldCaptcha\\Verifier('https://shield.example.test', 'sk_verify_' . str_repeat('b', 64)) instanceof \\ShieldCaptcha\\Verifier)) exit(4);",
    ].join('\n');
    execFileSync('php', ['-r', smoke], { cwd: php, stdio: 'inherit' });
    console.log('npm and Composer installed the built archives offline and passed package smoke tests.');
} finally {
    rmSync(temp, { recursive: true, force: true });
}
