<?php

namespace App\Billing;

use App\Models\BillingInvoice;
use App\Support\ProviderJson;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use SensitiveParameter;

final class StripeGateway implements PaymentGateway
{
    public const WEBHOOK_MAX_BYTES = 262_144;

    private const API_URL = 'https://api.stripe.com/v1/checkout/sessions';

    private const API_VERSION = '2026-11-18.endive';

    public function environment(): ?string
    {
        $secret = $this->secretKey();

        return $secret === null ? null : (substr($secret, 3, 4) === 'live' ? 'production' : 'sandbox');
    }

    public function enabled(): bool
    {
        return $this->secretKey() !== null && $this->webhookSecret() !== null
            && BillingOrigin::callbackBase($this->environment()) !== null;
    }

    public function fingerprint(): ?string
    {
        $environment = $this->environment();

        // Stripe scopes session retrieval to the API key's account. A replacement key for
        // that account must be able to reconcile the same session after a key rotation.
        return $environment === null ? null : hash('sha256', 'stripe:'.$environment);
    }

    public function matches(BillingInvoice $invoice): bool
    {
        $fingerprint = $this->fingerprint();

        return $invoice->provider === BillingInvoice::STRIPE
            && $this->environment() === $invoice->environment
            && $fingerprint !== null
            && hash_equals($invoice->merchant_fingerprint, $fingerprint);
    }

    public function checkoutUrl(BillingInvoice $invoice): ?string
    {
        if (! $this->matches($invoice) || $invoice->status !== BillingInvoice::PENDING
            || ! $this->validSessionId($invoice->stripe_session_id, $invoice->environment)) {
            return null;
        }

        $url = $invoice->stripe_checkout_url;

        return $this->validCheckoutUrl($url, $invoice->stripe_session_id) ? $url : null;
    }

    public function start(BillingInvoice $invoice): ?CheckoutSession
    {
        $base = BillingOrigin::callbackBase($invoice->environment);
        if ($base === null || ! $this->matches($invoice)) {
            return null;
        }

        $route = ['invoice' => $invoice->id];
        $data = $this->request('POST', self::API_URL, [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'adaptive_pricing' => ['enabled' => 'false'],
            'client_reference_id' => $invoice->id,
            'metadata' => ['invoice_id' => $invoice->id],
            'success_url' => $base.route('billing.stripe.callback', $route, false).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $base.route('billing.return', $route, false),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($invoice->currency),
                    'unit_amount' => $invoice->minorAmount(),
                    'product_data' => ['name' => 'Shield CAPTCHA access'],
                ],
            ]],
        ], 'shield-invoice-'.$invoice->id);
        $id = $data['id'] ?? null;
        $url = $data['url'] ?? null;

        return $data !== null && $this->validSessionId($id, $invoice->environment)
            && $this->matchesCheckout($data, $invoice) && ($data['status'] ?? null) === 'open'
            && $this->validCheckoutUrl($url, $id)
            ? new CheckoutSession($id, $url)
            : null;
    }

    public function verify(BillingInvoice $invoice): ?PaymentVerification
    {
        if (! $this->matches($invoice) || ! $this->validSessionId($invoice->stripe_session_id, $invoice->environment)) {
            return null;
        }

        $data = $this->request('GET', self::API_URL.'/'.$invoice->stripe_session_id);
        $reference = $data['payment_intent'] ?? null;

        return $data !== null && ($data['id'] ?? null) === $invoice->stripe_session_id
            && $this->matchesCheckout($data, $invoice) && ($data['status'] ?? null) === 'complete'
            && ($data['payment_status'] ?? null) === 'paid'
            && is_string($reference) && preg_match('/\Api_[A-Za-z0-9]{8,180}\z/', $reference)
            ? new PaymentVerification(null, $reference)
            : null;
    }

    public function matchesSession(BillingInvoice $invoice, mixed $sessionId): bool
    {
        return $invoice->provider === BillingInvoice::STRIPE
            && $invoice->stripe_session_id !== null
            && $this->validSessionId($sessionId, $invoice->environment)
            && hash_equals($invoice->stripe_session_id, $sessionId);
    }

    /** @return array{invoice: string, session: string, environment: string}|array{}|null */
    public function webhookEvent(#[SensitiveParameter] string $payload, #[SensitiveParameter] ?string $header): ?array
    {
        $secret = $this->webhookSecret();
        if ($secret === null || $header === null || strlen($header) > 1024 || strlen($payload) > self::WEBHOOK_MAX_BYTES) {
            return null;
        }

        $timestamps = [];
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamps[] = $value;
            } elseif ($key === 'v1' && preg_match('/\A[a-f0-9]{64}\z/', $value)) {
                $signatures[] = $value;
            }
        }

        if (count($timestamps) !== 1 || ! preg_match('/\A[0-9]{1,12}\z/', $timestamps[0])
            || abs(now()->timestamp - (int) $timestamps[0]) > 300) {
            return null;
        }

        $expected = hash_hmac('sha256', $timestamps[0].'.'.$payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return $this->parseEvent($payload);
            }
        }

        return null;
    }

    private function parseEvent(#[SensitiveParameter] string $payload): ?array
    {
        try {
            $event = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (($event['object'] ?? null) !== 'event' || ! is_bool($event['livemode'] ?? null)) {
            return null;
        }

        if (! in_array($event['type'] ?? null, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            return [];
        }

        $data = $event['data']['object'] ?? null;
        $environment = $event['livemode'] ? 'production' : 'sandbox';
        $session = $data['id'] ?? null;
        $invoice = $data['client_reference_id'] ?? null;
        if (($data['object'] ?? null) !== 'checkout.session' || ($data['livemode'] ?? null) !== $event['livemode']
            || ($data['payment_status'] ?? null) !== 'paid' || ! Str::isUuid($invoice)
            || ! $this->validSessionId($session, $environment)) {
            return [];
        }

        return ['invoice' => $invoice, 'session' => $session, 'environment' => $environment];
    }

    private function matchesCheckout(array $data, BillingInvoice $invoice): bool
    {
        return ($data['object'] ?? null) === 'checkout.session'
            && ($data['mode'] ?? null) === 'payment'
            && ($data['livemode'] ?? null) === ($invoice->environment === 'production')
            && ($data['client_reference_id'] ?? null) === $invoice->id
            && ($data['metadata']['invoice_id'] ?? null) === $invoice->id
            && ($data['amount_total'] ?? null) === $invoice->minorAmount()
            && ($data['currency'] ?? null) === strtolower($invoice->currency);
    }

    private function validSessionId(mixed $id, string $environment): bool
    {
        $mode = $environment === 'production' ? 'live' : 'test';

        return is_string($id) && preg_match('/\Acs_'.$mode.'_[A-Za-z0-9]{8,180}\z/', $id) === 1;
    }

    private function validCheckoutUrl(mixed $url, string $id): bool
    {
        return is_string($url) && strlen($url) <= 2048 && preg_match(
            '~\Ahttps://checkout\.stripe\.com/c/pay/'.preg_quote($id, '~').'(?:#[A-Za-z0-9%._\x7E+-]+)?\z~',
            $url,
        ) === 1;
    }

    private function secretKey(): ?string
    {
        $key = config('billing.stripe.secret_key');

        return is_string($key) && preg_match('/\A(?:sk|rk)_(?:live|test)_[A-Za-z0-9_-]{16,220}\z/', $key) ? $key : null;
    }

    private function webhookSecret(): ?string
    {
        $secret = config('billing.stripe.webhook_secret');

        return is_string($secret) && preg_match('/\Awhsec_[A-Za-z0-9_-]{16,220}\z/', $secret) ? $secret : null;
    }

    private function request(string $method, string $url, array $payload = [], ?string $idempotencyKey = null): ?array
    {
        $key = $this->secretKey();
        if ($key === null) {
            return null;
        }

        $deadline = hrtime(true) + 8_000_000_000;
        try {
            $request = Http::asForm()->acceptJson()->withToken($key)->withoutRedirecting()
                ->withHeader('Stripe-Version', self::API_VERSION)
                ->connectTimeout(3)->timeout(8)->withOptions(['stream' => true, 'read_timeout' => 2]);
            $response = $method === 'POST'
                ? $request->withHeader('Idempotency-Key', $idempotencyKey)->post($url, $payload)
                : $request->get($url);

            if (! $response->successful()) {
                $response->close();

                return null;
            }

            return ProviderJson::read($response, $deadline);
        } catch (ConnectionException|JsonException|RuntimeException) {
            return null;
        }
    }
}
