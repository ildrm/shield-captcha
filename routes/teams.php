<?php

use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:web')->group(function (): void {
    Route::get('/team-invitations', [TeamController::class, 'join'])->name('team-invitations.show');
    Route::post('/team-invitations', [TeamController::class, 'accept'])
        ->middleware('throttle:10,1')->name('team-invitations.accept');

    Route::prefix('customer/{organization:slug}/team')->name('team.')->group(function (): void {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::get('/export', [TeamController::class, 'export'])
            ->middleware('throttle:10,1')->name('export');
        Route::middleware('throttle:20,1')->group(function (): void {
            Route::post('/invitations', [TeamController::class, 'issue'])->name('issue');
            Route::delete('/invitations/{invitation}', [TeamController::class, 'revoke'])
                ->whereUuid('invitation')->name('revoke');
            Route::patch('/members/{member}', [TeamController::class, 'updateRole'])
                ->whereNumber('member')->name('update-role');
            Route::delete('/members/{member}', [TeamController::class, 'remove'])
                ->whereNumber('member')->name('remove');
            Route::post('/ownership', [TeamController::class, 'transfer'])->name('transfer');
        });
    });
});
