<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Sms\KavenegarSettings;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class ConfigureKavenegar extends Command
{
    protected $signature = 'shield:sms:kavenegar {site : Public site key} {action? : Registered OTP action} {template? : Approved Kavenegar template} {--disable : Remove site credentials for this provider} {--rotate-send-key : Replace shared SMS send keys}';

    protected $description = 'Provision or disable Kavenegar OTP delivery for a production site';

    public function handle(KavenegarSettings $settings): int
    {
        $site = Site::query()->where('public_key', $this->argument('site'))->first();
        if ($site === null) {
            $this->error('Site not found.');

            return self::FAILURE;
        }

        if ($this->option('disable')) {
            $revoked = $settings->disable($site);
            $this->info($revoked
                ? 'Kavenegar delivery disabled and send credentials revoked for this site.'
                : 'Kavenegar delivery disabled. The site send key remains valid with Twilio.');

            return self::SUCCESS;
        }

        $action = $this->argument('action');
        $template = $this->argument('template');
        if (! $this->input->isInteractive() || ! is_string($action) || ! is_string($template)) {
            $this->error('Run this command in an interactive terminal with a site, action, and approved template.');

            return self::FAILURE;
        }

        $apiKey = $this->secret('Kavenegar API key', false);
        if (! is_string($apiKey)) {
            return self::FAILURE;
        }

        try {
            $secret = $settings->configure($site, $action, $template, $apiKey, (bool) $this->option('rotate-send-key'));
        } catch (ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($secret !== null) {
            $this->info('Save this SMS send key in the customer backend. It is shown once.');
            $this->line($secret);
        } else {
            $this->info('Kavenegar configured. Continue using the existing SMS send key.');
        }

        return self::SUCCESS;
    }
}
