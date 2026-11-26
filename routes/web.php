<?php

use App\Http\Controllers\PublicSiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicSiteController::class, 'home'])->name('home');
Route::get('/docs', [PublicSiteController::class, 'docs'])->name('docs');
Route::get('/docs/v1', [PublicSiteController::class, 'docs'])->name('docs.v1');
Route::get('/api-reference', [PublicSiteController::class, 'contract'])->name('api-reference');
Route::get('/playground', [PublicSiteController::class, 'playground'])->name('playground');
Route::redirect('/demo', '/playground');
Route::get('/downloads', [PublicSiteController::class, 'downloads'])->name('downloads');
Route::get('/downloads/{artifact}', [PublicSiteController::class, 'download'])
    ->where('artifact', '[a-zA-Z0-9][a-zA-Z0-9._-]{0,119}')->middleware('throttle:30,1')->name('download');
Route::get('/status', [PublicSiteController::class, 'status'])->name('status');
Route::get('/health/live', fn () => response()->json(['status' => 'ok'])->header('Cache-Control', 'no-store'));
Route::get('/health/ready', [PublicSiteController::class, 'ready'])->name('ready');
Route::get('/security', [PublicSiteController::class, 'security'])->name('security');
Route::redirect('/login', '/customer/login')->name('login');
Route::redirect('/register', '/customer/register');

Route::post('/language', function (Request $request) {
    $request->session()->put('locale', $request->validate(['locale' => 'required|in:en,fa'])['locale']);

    return back();
})->name('locale');

require __DIR__.'/billing.php';
require __DIR__.'/teams.php';
