<?php

use App\Http\Controllers\BillingController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/billing/zarinpal/callback/{invoice}', [BillingController::class, 'callback'])
    ->whereUuid('invoice')
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])
    ->middleware('throttle:30,1')
    ->name('billing.zarinpal.callback');

Route::get('/billing/stripe/callback/{invoice}', [BillingController::class, 'stripeCallback'])
    ->whereUuid('invoice')
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])
    ->middleware('throttle:30,1')
    ->name('billing.stripe.callback');

Route::post('/billing/stripe/webhook', [BillingController::class, 'stripeWebhook'])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])
    ->name('billing.stripe.webhook');

Route::get('/billing/return/{invoice}', [BillingController::class, 'returnToCustomer'])
    ->whereUuid('invoice')
    ->name('billing.return');

Route::middleware('auth:web')->prefix('customer/{organization:slug}/billing')->group(function (): void {
    Route::get('/', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/checkout', [BillingController::class, 'checkout'])
        ->middleware('throttle:10,1')->name('billing.checkout');
    Route::get('/invoices.csv', [BillingController::class, 'export'])
        ->middleware('throttle:6,1')->name('billing.export');
    Route::get('/invoices/{invoice}', [BillingController::class, 'receipt'])
        ->whereUuid('invoice')->name('billing.receipt');
    Route::post('/invoices/{invoice}/recheck', [BillingController::class, 'recheck'])
        ->whereUuid('invoice')->middleware('throttle:10,1')->name('billing.recheck');
});
