<?php

use App\Http\Controllers\ShieldApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->controller(ShieldApiController::class)->group(function (): void {
    Route::options('/widget/config', 'preflightConfig');
    Route::get('/widget/config', 'config');
    Route::post('/widget/config', 'config');
    Route::options('/challenges', 'preflightWrite');
    Route::post('/challenges', 'issue');
    Route::options('/challenges/{id}/solve', 'preflightWrite');
    Route::post('/challenges/{id}/solve', 'solve');
    Route::post('/verify', 'verify');
    Route::post('/sms/assess', 'assessSms');
});

require __DIR__.'/sms-provider.php';
require __DIR__.'/protection.php';
