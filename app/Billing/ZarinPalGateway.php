<?php

namespace App\Billing;

use App\Models\BillingInvoice;
use App\Support\ProviderJson;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

final class ZarinPalGateway implements PaymentGateway
{
    private const HOSTS = [
        'sandbox' => 'https://sandbox.zarinpal.com',
        'production' => 'https://payment.zarinpal.com',
    ];

    public function environment(): ?string
    {
        $environment = config('billing.zarinpal.environment');

        return is_string($environment) && isset(self::HOSTS[$environment]) ? $environment : null;
    }

    public function enabled(): bool
    {
        return $this->fingerprint() !== null && $this->callbackBase() !== null;
    }

    public function fingerprint(): ?string
    {
        $merchant = $this->merchantId();

        return $merchant !== null && $this->environment() !== null ? hash('sha256', $merchant) : null;
    }

    public function matches(BillingInvoice $invoice): bool
    {
        $fingerprint = $this->fingerprint();

        return $invoice->provider === BillingInvoice::ZARINPAL
            && $this->environment() === $invoice->environment
            && $fingerprint !== null
            && hash_equals($invoice->merchant_fingerprint, $fingerprint);
    }

    public function checkoutUrl(BillingInvoice $invoice): ?string
    {
        if (! $this->enabled() || ! $this->matches($invoice) || $invoice->status !== BillingInvoice::PENDING
            || ! $this->validAuthority($invoice->authority, $invoice->environment)) {
            return null;
        }

        return self::HOSTS[$invoice->environment].'/pg/StartPay/'.$invoice->authority;
    }

    public function start(BillingInvoice $invoice): ?CheckoutSession
    {
        $callbackBase = $this->callbackBase();
        if ($callbackBase === null || ! $this->matches($invoice)) {
            return null;
        }

        $data = $this->post($invoice->environment, 'request', [
            'merchant_id' => $this->merchantId(),
            'amount' => $invoice->minorAmount(),
            'currency' => 'IRR',
            'description' => 'Shield CAPTCHA access',
            'callback_url' => $callbackBase.route('billing.zarinpal.callback', ['invoice' => $invoice->id], false),
            'metadata' => ['order_id' => $invoice->id, 'auto_verify' => false],
        ]);
        $authority = $data['authority'] ?? null;

        return ($data['code'] ?? null) === 100 && $this->validAuthority($authority, $invoice->environment)
            ? new CheckoutSession($authority)
            : null;
    }

    public function verify(BillingInvoice $invoice): ?PaymentVerification
    {
        if (! $this->matches($invoice) || ! $this->validAuthority($invoice->authority, $invoice->environment)) {
            return null;
        }

        $data = $this->post($invoice->environment, 'verify', [
            'merchant_id' => $this->merchantId(),
            'amount' => $invoice->minorAmount(),
            'authority' => $invoice->authority,
        ]);
        $code = $data['code'] ?? null;

        if (! in_array($code, [100, 101], true)
            || (array_key_exists('amount', $data) && $data['amount'] !== $invoice->minorAmount())
            || (array_key_exists('authority', $data) && $data['authority'] !== $invoice->authority)
            || (array_key_exists('currency', $data) && $data['currency'] !== 'IRR')) {
            return null;
        }

        $reference = $data['ref_id'] ?? null;
        if ($reference === null && $code === 101) {
            return new PaymentVerification($code, null);
        }

        if ((! is_int($reference) && ! is_string($reference)) || ! preg_match('/\A[1-9][0-9]{0,23}\z/', (string) $reference)) {
            return null;
        }

        return new PaymentVerification($code, (string) $reference);
    }

    private function merchantId(): ?string
    {
        $merchant = config('billing.zarinpal.merchant_id');

        return is_string($merchant)
            && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $merchant)
            ? $merchant
            : null;
    }

    private function validAuthority(mixed $authority, string $environment): bool
    {
        $prefix = $environment === 'production' ? 'A' : 'S';

        return is_string($authority) && preg_match('/\A'.$prefix.'[0-9A-Za-z]{35}\z/', $authority) === 1;
    }

    private function callbackBase(): ?string
    {
        return BillingOrigin::callbackBase($this->environment());
    }

    private function post(string $environment, string $operation, array $payload): ?array
    {
        $deadline = hrtime(true) + 8_000_000_000;
        try {
            $response = Http::asJson()->acceptJson()->withoutRedirecting()
                ->connectTimeout(3)->timeout(8)->withOptions(['stream' => true, 'read_timeout' => 2])
                ->post(self::HOSTS[$environment].'/pg/v4/payment/'.$operation.'.json', $payload);
            if (! $response->successful()) {
                $response->close();

                return null;
            }

            $data = ProviderJson::read($response, $deadline);

            return is_array($data['data'] ?? null) && ($data['errors'] ?? null) === [] ? $data['data'] : null;
        } catch (ConnectionException|JsonException|RuntimeException) {
            return null;
        }
    }
}
