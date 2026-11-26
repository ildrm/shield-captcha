<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Sms\TwilioSettings;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class ConfigureTwilio extends Command
{
    protected $signature = 'shield:sms:twilio {site : Public site key} {action? : Registered OTP action} {account_sid? : Twilio account SID} {messaging_service_sid? : Twilio Messaging Service SID} {--disable : Remove site credentials for this provider} {--rotate-send-key : Replace shared SMS send keys}';

    protected $description = 'Provision or disable Twilio OTP delivery for a production site';

    public function handle(TwilioSettings $settings): int
    {
        $site = Site::query()->where('public_key', $this->argument('site'))->first();
        if ($site === null) {
            $this->error('Site not found.');

            return self::FAILURE;
        }

        if ($this->option('disable')) {
            $revoked = $settings->disable($site);
            $this->info($revoked
                ? 'Twilio delivery disabled and send credentials revoked for this site.'
                : 'Twilio delivery disabled. The site send key remains valid with Kavenegar.');

            return self::SUCCESS;
        }

        $action = $this->argument('action');
        $accountSid = $this->argument('account_sid');
        $messagingServiceSid = $this->argument('messaging_service_sid');
        if (! $this->input->isInteractive() || ! is_string($action) || ! is_string($accountSid) || ! is_string($messagingServiceSid)) {
            $this->error('Run this command in an interactive terminal with a site, action, account SID, and Messaging Service SID.');

            return self::FAILURE;
        }

        $apiKeySid = $this->secret('Twilio API key SID', false);
        $apiKeySecret = $this->secret('Twilio API key secret', false);
        if (! is_string($apiKeySid) || ! is_string($apiKeySecret)) {
            return self::FAILURE;
        }

        try {
            $secret = $settings->configure($site, $action, $accountSid, $messagingServiceSid, $apiKeySid, $apiKeySecret, (bool) $this->option('rotate-send-key'));
        } catch (ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($secret !== null) {
            $this->info('Save this SMS send key in the customer backend. It is shown once.');
            $this->line($secret);
        } else {
            $this->info('Twilio configured. Continue using the existing SMS send key.');
        }

        return self::SUCCESS;
    }
}
