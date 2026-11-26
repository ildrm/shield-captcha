<?php

use App\Http\Controllers\ShieldApiController;
use Illuminate\Support\Facades\Route;

Route::post('v1/sms/send', [ShieldApiController::class, 'sendSms']);
