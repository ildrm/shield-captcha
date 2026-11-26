<?php

use App\Http\Controllers\ProtectionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->controller(ProtectionController::class)->group(function (): void {
    foreach ([
        'assessments' => 'generic',
        'accounts/assess' => 'account',
        'api-protection/assess' => 'api',
        'fraud/assess' => 'fraud',
        'bots/assess' => 'bot',
    ] as $path => $product) {
        Route::post('/'.$path, 'assess')->defaults('product', $product)->name('protection.'.$product);
    }

    Route::post('/feedback', 'feedback')->name('protection.feedback');
});
