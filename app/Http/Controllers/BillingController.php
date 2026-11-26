<?php

namespace App\Http\Controllers;

use App\Billing\BillingAccess;
use App\Billing\BillingCheckout;
use App\Billing\BillingEntitlements;
use App\Billing\StripeGateway;
use App\Models\BillingInvoice;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    private const PRIVATE_HEADERS = ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer'];

    public function __construct(
        private BillingCheckout $billing,
        private StripeGateway $stripe,
    ) {}

    public function index(Request $request, Organization $organization, BillingEntitlements $entitlements): Response
    {
        $this->authorizeOrganization($request, $organization);
        $checkoutKey = (string) Str::uuid();

        return $this->render($request, 'index', [
            'organization' => $organization,
            'options' => $this->billing->checkoutOptions($organization, $checkoutKey),
            'checkoutKey' => $checkoutKey,
            'currentQuota' => $entitlements->dailyVerificationLimit($organization->id, 'production'),
            'paidThrough' => BillingSubscription::query()->where('organization_id', $organization->id)
                ->where('expires_at', '>', now())->latest('expires_at')->first()?->expires_at,
            'invoices' => BillingInvoice::query()->where('organization_id', $organization->id)
                ->latest()->orderByDesc('id')->simplePaginate(20),
        ]);
    }

    public function checkout(Request $request, Organization $organization): RedirectResponse
    {
        $user = $this->authorizeOrganization($request, $organization);
        $fields = $request->validate([
            'checkout_key' => ['required', 'uuid:4'],
            'provider' => ['sometimes', 'required', 'string', Rule::in([BillingInvoice::ZARINPAL, BillingInvoice::STRIPE])],
            'plan_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'quote' => ['sometimes', 'required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);
        $invoice = $this->billing->create(
            $user,
            $organization,
            strtolower($fields['checkout_key']),
            $fields['provider'] ?? null,
            isset($fields['plan_id']) ? (int) $fields['plan_id'] : null,
            $fields['quote'] ?? null,
        );
        $url = $this->billing->checkoutUrl($invoice);

        return $url !== null ? redirect()->away($url, 303, self::PRIVATE_HEADERS) : $this->redirectToInvoice($invoice, $organization);
    }

    public function receipt(Request $request, Organization $organization, string $invoice): Response
    {
        $this->authorizeOrganization($request, $organization);
        $record = $this->findInvoice($organization, $invoice);

        return $this->render($request, 'receipt', [
            'organization' => $organization,
            'invoice' => $record,
            'isPending' => $record->status === BillingInvoice::PENDING,
            'canResume' => $this->billing->checkoutUrl($record) !== null,
            'canRecheck' => $this->billing->canReconcile($record),
        ]);
    }

    public function export(Request $request, Organization $organization): StreamedResponse
    {
        $this->authorizeOrganization($request, $organization);
        $exportedAt = now();

        return response()->streamDownload(function () use ($organization, $exportedAt): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'invoice_id', 'created_at_utc', 'paid_at_utc', 'gateway', 'environment', 'plan_name',
                'currency', 'amount_minor', 'term_days', 'verifications_per_utc_day', 'status',
            ], escape: '');

            $invoices = BillingInvoice::query()->where('organization_id', $organization->id)
                ->where('created_at', '<=', $exportedAt)->lazyById(200);
            foreach ($invoices as $invoice) {
                $planName = $invoice->plan_name ?? '';
                if (preg_match('/\A(?:\s*[=+\-@]|[\t\r\n])/u', $planName)) {
                    $planName = "'".$planName;
                }

                fputcsv($output, [
                    $invoice->id, $invoice->created_at->utc()->toIso8601String(), $invoice->paid_at?->utc()->toIso8601String(),
                    $invoice->provider, $invoice->environment, $planName, $invoice->currency, $invoice->minorAmount(),
                    $invoice->term_days, $invoice->daily_verifications, $invoice->status,
                ], escape: '');
            }
            fclose($output);
        }, 'billing-invoices-'.$exportedAt->format('Y-m-d').'.csv', self::PRIVATE_HEADERS + ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function recheck(Request $request, Organization $organization, string $invoice): RedirectResponse
    {
        $this->authorizeOrganization($request, $organization);
        $record = $this->billing->reconcile($this->findInvoice($organization, $invoice));

        return $this->redirectToInvoice($record, $organization);
    }

    public function callback(Request $request, string $invoice): RedirectResponse
    {
        abort_unless($request->isMethod('GET'), 405);
        $authority = $request->query('Authority');
        $status = $request->query('Status');
        abort_unless(is_string($authority) && strlen($authority) === 36 && in_array($status, ['OK', 'NOK'], true), 404);

        $record = BillingInvoice::query()->findOrFail($invoice);
        abort_unless($record->provider === BillingInvoice::ZARINPAL
            && $record->authority !== null && hash_equals($record->authority, $authority), 404);

        if ($status === 'OK') {
            $this->billing->reconcile($record);
        }

        return redirect()->route('billing.return', ['invoice' => $invoice], 303, self::PRIVATE_HEADERS);
    }

    public function stripeCallback(Request $request, string $invoice): RedirectResponse
    {
        abort_unless($request->isMethod('GET'), 405);
        $record = BillingInvoice::query()->findOrFail($invoice);
        abort_unless($this->stripe->matchesSession($record, $request->query('session_id')), 404);
        $this->billing->reconcile($record);

        return redirect()->route('billing.return', ['invoice' => $invoice], 303, self::PRIVATE_HEADERS);
    }

    public function stripeWebhook(Request $request): Response
    {
        $payload = stream_get_contents($request->getContent(true), StripeGateway::WEBHOOK_MAX_BYTES + 1);
        $event = $payload === false ? null : $this->stripe->webhookEvent($payload, $request->header('Stripe-Signature'));
        if ($event === null) {
            return response('', 400, self::PRIVATE_HEADERS);
        }

        if ($event !== []) {
            $record = BillingInvoice::query()->find($event['invoice']);
            if ($record !== null && $record->environment === $event['environment']
                && $this->stripe->matchesSession($record, $event['session'])) {
                $record = $this->billing->reconcile($record);
                if ($record->status !== BillingInvoice::PAID) {
                    return response('', 503, ['Retry-After' => '20'] + self::PRIVATE_HEADERS);
                }
            }
        }

        return response()->noContent(204, self::PRIVATE_HEADERS);
    }

    public function returnToCustomer(Request $request, string $invoice): Response|RedirectResponse
    {
        $user = $request->user('web');
        if ($user instanceof User) {
            $record = BillingInvoice::query()->with('organization')->find($invoice);
            if ($record !== null && BillingAccess::canManage($user, $record->organization)) {
                return $this->redirectToInvoice($record, $record->organization);
            }
        }

        return $this->render($request, 'return');
    }

    private function authorizeOrganization(Request $request, Organization $organization): User
    {
        $user = $request->user('web');
        abort_unless($user instanceof User && BillingAccess::canManage($user, $organization), 403);

        return $user;
    }

    private function findInvoice(Organization $organization, string $invoice): BillingInvoice
    {
        return BillingInvoice::query()->where('organization_id', $organization->id)->findOrFail($invoice);
    }

    private function redirectToInvoice(BillingInvoice $invoice, Organization $organization): RedirectResponse
    {
        return redirect()->route('billing.receipt', ['organization' => $organization->slug, 'invoice' => $invoice->id], 303, self::PRIVATE_HEADERS);
    }

    private function render(Request $request, string $view, array $data = []): Response
    {
        $locale = $request->session()->get('locale');
        app()->setLocale(in_array($locale, ['en', 'fa'], true) ? $locale : 'en');

        return response()->view('billing.'.$view, $data, 200, self::PRIVATE_HEADERS);
    }
}
