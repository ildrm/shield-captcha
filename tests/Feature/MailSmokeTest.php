<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

final class MailSmokeTest extends TestCase
{
    public function test_sends_one_message_through_the_configured_mailer(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'shield@example.test']);
        $transport = new ArrayTransport;
        Mail::extend('smtp', fn () => $transport);

        $this->artisan('shield:mail-smoke', ['address' => 'inbox@example.test'])
            ->expectsOutput('Mail transport accepted one test message. Check the recipient inbox.')
            ->assertExitCode(Command::SUCCESS);

        $message = $transport->messages()->sole();
        $this->assertSame('shield@example.test', $message->getEnvelope()->getSender()->getAddress());
        $this->assertSame(['inbox@example.test'], array_map(fn ($recipient) => $recipient->getAddress(), $message->getEnvelope()->getRecipients()));
        $this->assertSame('Shield CAPTCHA mail delivery check', $message->getOriginalMessage()->getSubject());
    }

    public function test_rejects_recipient_lists_and_non_delivery_transports(): void
    {
        config(['mail.default' => 'array']);

        foreach (["one@example.test\r\nBcc: two@example.test", 'one@example.test,two@example.test', 'Name <one@example.test>'] as $input) {
            $this->artisan('shield:mail-smoke', ['address' => $input])
                ->expectsOutput('Enter one valid recipient email address.')
                ->assertExitCode(Command::FAILURE);
        }

        $this->artisan('shield:mail-smoke', ['address' => 'inbox@example.test'])
            ->expectsOutput('Configure a supported default mail delivery transport.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_refuses_global_recipient_redirects_and_hides_transport_errors(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'shield@example.test',
            'mail.to.address' => 'redirect@example.test',
        ]);
        Mail::extend('smtp', function (): never {
            throw new RuntimeException('Unable to access smtp://username:password@example.test');
        });

        $this->artisan('shield:mail-smoke', ['address' => 'inbox@example.test'])
            ->expectsOutput('Configure a valid sender address and remove any global recipient override.')
            ->assertExitCode(Command::FAILURE);

        config([
            'mail.to' => null,
            'mail.mailers.smtp.url' => 'smtp://mail.example.test?to%5Baddress%5D=redirect%40example.test',
        ]);
        $this->artisan('shield:mail-smoke', ['address' => 'inbox@example.test'])
            ->expectsOutput('Configure a valid sender address and remove any global recipient override.')
            ->assertExitCode(Command::FAILURE);

        config(['mail.mailers.smtp.url' => 'smtp://mail.example.test']);
        $this->artisan('shield:mail-smoke', ['address' => 'inbox@example.test'])
            ->expectsOutput('The configured mail transport failed to send the test message.')
            ->doesntExpectOutputToContain('username:password')
            ->assertExitCode(Command::FAILURE);
    }
}
