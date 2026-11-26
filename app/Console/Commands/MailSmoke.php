<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Arr;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class MailSmoke extends Command
{
    protected $signature = 'shield:mail-smoke {address : A single email address whose inbox you can check}';

    protected $description = 'Send one delivery check through the configured mail service';

    public function handle(): int
    {
        if (! $this->laravel->runningInConsole()) {
            $this->error('Run the mail delivery check from the CLI.');

            return self::FAILURE;
        }

        $address = $this->argument('address');
        if (! $this->isAddress($address)) {
            $this->error('Enter one valid recipient email address.');

            return self::FAILURE;
        }

        try {
            $name = config('mail.default');
            $settings = is_string($name) ? config('mail.mailers.'.$name) : null;
            if (! is_array($settings) || config('mail.driver')) {
                $this->error('Configure a supported default mail delivery transport.');

                return self::FAILURE;
            }

            if (isset($settings['url'])) {
                $settings = array_replace($settings, (new ConfigurationUrlParser)->parseConfiguration($settings));
                $settings['transport'] = $settings['driver'] ?? null;
            }

            if (! in_array($settings['transport'] ?? null, ['smtp', 'sendmail', 'ses', 'ses-v2', 'mailgun', 'postmark', 'resend'], true)) {
                $this->error('Configure a supported default mail delivery transport.');

                return self::FAILURE;
            }

            $from = Arr::get($settings, 'from', config('mail.from'));
            $to = Arr::get($settings, 'to', config('mail.to'));
            if (! $this->isAddress($from['address'] ?? null) || isset($to['address'])) {
                $this->error('Configure a valid sender address and remove any global recipient override.');

                return self::FAILURE;
            }

            $sent = Mail::mailer($name)->raw(
                'This message checks the configured mail delivery path for Shield CAPTCHA.',
                fn (Message $message) => $message->to($address)->subject('Shield CAPTCHA mail delivery check'),
            );
        } catch (Throwable) {
            $this->error('The configured mail transport failed to send the test message.');

            return self::FAILURE;
        }

        if ($sent === null) {
            $this->error('The configured mail transport did not send a test message.');

            return self::FAILURE;
        }

        $this->info('Mail transport accepted one test message. Check the recipient inbox.');

        return self::SUCCESS;
    }

    private function isAddress(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 254
            && ! preg_match('/[\x00-\x20\x7f,;<>]/D', $value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}
