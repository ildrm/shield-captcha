@extends('site.layout')

@section('title', __('billing.title').' · '.$organization->name)

@section('content')
<section class="frame text-page">
    <h1>{{ __('billing.title') }}</h1>
    <p class="lede">{{ $organization->name }}</p>
    <p>{{ __('billing.current_quota', ['count' => number_format($currentQuota)]) }}</p>
    @if ($paidThrough)
        <p>{{ __('billing.paid_through', ['date' => $paidThrough->utc()->format('Y-m-d H:i')]) }}</p>
    @endif

    @forelse (collect($options)->groupBy(fn ($option) => $option['plan']->id ?? 0) as $planOptions)
        @php $planName = $planOptions->first()['plan']->name; @endphp
        <article class="artifact">
            @if ($planName !== null)
                <h2>{{ $planName }}</h2>
            @endif
            @foreach ($planOptions as $option)
                @php
                    $provider = $option['provider'];
                    $plan = $option['plan'];
                    $providerName = __('billing.provider_'.$provider);
                @endphp
                @if ($planName === null)
                    <h2>{{ $providerName }}</h2>
                @else
                    <h3>{{ $providerName }}</h3>
                @endif
                <p>{{ __('billing.plan_details', [
                    'amount' => $plan->displayAmount(),
                    'days' => $plan->termDays,
                    'count' => number_format($plan->dailyVerifications),
                ]) }}</p>
                @if ($option['environment'] === 'sandbox')
                    <p>{{ __('billing.sandbox') }}</p>
                @endif
                <form method="post" action="{{ route('billing.checkout', ['organization' => $organization->slug]) }}">
                    @csrf
                    <input type="hidden" name="checkout_key" value="{{ $checkoutKey }}">
                    <input type="hidden" name="provider" value="{{ $provider }}">
                    <input type="hidden" name="quote" value="{{ $option['quote'] }}">
                    @if ($plan->id !== null)
                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    @endif
                    <button type="submit" class="button primary">{{ __('billing.pay', ['provider' => $providerName]) }}</button>
                </form>
            @endforeach
        </article>
    @empty
        <p>{{ __('billing.unavailable') }}</p>
    @endforelse

    <h2>{{ __('billing.invoices') }}</h2>
    @if ($invoices->isNotEmpty())
        <a class="button outline" href="{{ route('billing.export', ['organization' => $organization->slug]) }}">{{ __('billing.export') }}</a>
    @endif
    @forelse ($invoices as $invoice)
        <article class="artifact">
            <h3><a href="{{ route('billing.receipt', ['organization' => $organization->slug, 'invoice' => $invoice->id]) }}">
                {{ __('billing.invoice_link', ['date' => $invoice->created_at->utc()->format('Y-m-d H:i')]) }}
            </a></h3>
            <p>
                @if ($invoice->plan_name !== null)
                    <bdi>{{ $invoice->plan_name }}</bdi> ·
                @endif
                <bdi>{{ $invoice->displayAmount() }}</bdi> · {{ __('billing.provider_'.$invoice->provider) }}
            </p>
            <p>{{ __('billing.status_'.$invoice->status) }} · {{ __('billing.mode_'.$invoice->environment) }}</p>
        </article>
    @empty
        <p>{{ __('billing.no_invoices') }}</p>
    @endforelse

    @if ($invoices->hasPages())
        <nav class="actions" aria-label="{{ __('billing.invoices') }}">
            @if ($invoices->previousPageUrl())
                <a class="button outline" href="{{ $invoices->previousPageUrl() }}">{{ __('billing.previous') }}</a>
            @endif
            @if ($invoices->nextPageUrl())
                <a class="button outline" href="{{ $invoices->nextPageUrl() }}">{{ __('billing.next') }}</a>
            @endif
        </nav>
    @endif
</section>
@endsection
