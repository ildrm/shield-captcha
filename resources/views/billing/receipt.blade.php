@extends('site.layout')

@section('title', __('billing.invoice').' · '.$organization->name)

@section('content')
<section class="frame text-page">
    <a href="{{ route('billing.index', ['organization' => $organization->slug]) }}">{{ __('billing.title') }}</a>
    <h1>{{ __('billing.invoice') }}</h1>
    <p>{{ $organization->name }}</p>
    <dl class="artifact">
        <dt>{{ __('billing.invoice_number') }}</dt>
        <dd><bdi>{{ $invoice->id }}</bdi></dd>
        <dt>{{ __('billing.payment_status') }}</dt>
        <dd>{{ __('billing.status_'.$invoice->status) }} · {{ __('billing.mode_'.$invoice->environment) }}</dd>
        <dt>{{ __('billing.payment_gateway') }}</dt>
        <dd>{{ __('billing.provider_'.$invoice->provider) }}</dd>
        @if ($invoice->plan_name !== null)
            <dt>{{ __('billing.plan') }}</dt>
            <dd><bdi>{{ $invoice->plan_name }}</bdi></dd>
        @endif
        <dt>{{ __('billing.amount') }}</dt>
        <dd><bdi>{{ $invoice->displayAmount() }}</bdi></dd>
        @if ($invoice->paid_at)
            <dt>{{ __('billing.paid_at') }}</dt>
            <dd>{{ $invoice->paid_at->utc()->format('Y-m-d H:i') }} UTC</dd>
            @if ($invoice->provider_reference)
                <dt>{{ __('billing.reference') }}</dt>
                <dd>{{ $invoice->provider_reference }}</dd>
            @endif
        @endif
        @if ($invoice->subscription)
            <dt>{{ __('billing.access_term') }}</dt>
            <dd>{{ __('billing.term_range', [
                'start' => $invoice->subscription->starts_at->utc()->format('Y-m-d H:i'),
                'end' => $invoice->subscription->expires_at->utc()->format('Y-m-d H:i'),
            ]) }}</dd>
        @endif
    </dl>

    @if ($invoice->environment === 'sandbox')
        <p>{{ __('billing.sandbox') }}</p>
    @endif

    @if ($isPending)
        <p>{{ __('billing.pending_help') }}</p>
    @endif

    <div class="actions">
        @if ($canRecheck)
            <form method="post" action="{{ route('billing.recheck', ['organization' => $organization->slug, 'invoice' => $invoice->id]) }}">
                @csrf
                <button type="submit" class="button primary">{{ __('billing.recheck') }}</button>
            </form>
        @endif
        @if ($canResume)
            <form method="post" action="{{ route('billing.checkout', ['organization' => $organization->slug]) }}">
                @csrf
                <input type="hidden" name="checkout_key" value="{{ $invoice->checkout_key }}">
                <input type="hidden" name="provider" value="{{ $invoice->provider }}">
                @if ($invoice->billing_plan_id !== null)
                    <input type="hidden" name="plan_id" value="{{ $invoice->billing_plan_id }}">
                @endif
                <button type="submit" class="button outline">{{ __('billing.resume') }}</button>
            </form>
        @endif
    </div>
</section>
@endsection
