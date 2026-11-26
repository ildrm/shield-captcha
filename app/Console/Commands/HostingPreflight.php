<?php

namespace App\Console\Commands;

use App\Billing\BillingPlanCatalog;
use App\Billing\StripeGateway;
use App\Billing\ZarinPalGateway;
use App\Settings\ProviderSelection;
use App\Sms\KavenegarSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class HostingPreflight extends Command
{
    protected $signature = 'shield:preflight {--production : Check production configuration}';

    protected $description = 'Check shared-host prerequisites without changing data';

    public function handle(): int
    {
        $production = $this->option('production') || app()->isProduction();
        $key = config('app.key');
        $checks = [
            'PHP 8.3 or later' => PHP_VERSION_ID >= 80300,
            'Valid application key' => is_string($key) && str_starts_with($key, 'base64:')
                && strlen(base64_decode(substr($key, 7), true) ?: '') === 32,
            'Writable runtime directories' => is_writable(storage_path('logs'))
                && is_writable(storage_path('framework/views'))
                && is_writable(base_path('bootstrap/cache')),
            'Published browser and panel assets' => is_file(public_path('assets/captcha/v1/widget.js'))
                && is_file(public_path('css/filament/filament/app.css')),
            'MySQL/MariaDB InnoDB schema' => $this->hasSchema(),
        ];

        if ($production) {
            $checks += [
                'Production mode and HTTPS configuration' => app()->isProduction() && ! config('app.debug')
                    && parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https',
                'Encrypted secure database sessions' => config('session.driver') === 'database'
                    && config('session.secure') && config('session.encrypt'),
                'Durable scheduler locks' => config('cache.default') === 'database',
                'Configured mail delivery' => in_array(config('mail.default'), ['smtp', 'sendmail', 'ses', 'postmark', 'resend'], true),
            ];
        }

        if ($checks['MySQL/MariaDB InnoDB schema']) {
            $providers = app(ProviderSelection::class)->current();
            $payments = $providers['payment_gateways'];
            if (in_array('zarinpal', $payments, true)) {
                $checks['ZarinPal merchant, callback origin, and published offer'] = app(ZarinPalGateway::class)->enabled()
                    && app(BillingPlanCatalog::class)->offersFor('zarinpal') !== [];
            }

            if (in_array('stripe', $payments, true)) {
                $checks['Stripe key, webhook, callback origin, and published offer'] = app(StripeGateway::class)->enabled()
                    && app(BillingPlanCatalog::class)->offersFor('stripe') !== [];
            }

            if ($providers['sms_provider'] === 'kavenegar') {
                $templates = config('sms.kavenegar.templates', []);
                $checks['Kavenegar template allowlist'] = $templates !== []
                    && count(array_filter($templates, KavenegarSettings::templateAllowed(...))) === count($templates);
            }
        }

        foreach ($checks as $label => $passed) {
            $this->line(($passed ? 'OK   ' : 'FAIL ').$label);
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    private function hasSchema(): bool
    {
        if (! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            return false;
        }

        try {
            $tables = [
                'organizations', 'sites', 'site_credentials', 'shield_tokens', 'shield_challenges', 'sessions', 'cache_locks',
                'billing_invoices', 'billing_subscriptions', 'shield_sms_tenant_keys', 'shield_sms_kavenegar_sites', 'shield_sms_deliveries',
                'shield_provider_settings', 'shield_sms_twilio_sites', 'billing_plans', 'shield_team_invitations',
                'shield_protection_policies', 'shield_protection_events', 'shield_scheduler_status',
            ];
            $count = DB::table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->whereIn('TABLE_NAME', $tables)->where('ENGINE', 'InnoDB')->count();

            return $count === count($tables);
        } catch (Throwable) {
            return false;
        }
    }
}
