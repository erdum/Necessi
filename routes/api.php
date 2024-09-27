<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FirebaseAuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['auth:sanctum'])->group(function () {

});

Route::middleware(['throttle:100,1'])->group(function () {

    Route::post('/register', [FirebaseAuthController::class, 'register']);

    Route::post(
        '/verify-email',
        [FirebaseAuthController::class, 'verify_email']
    );

    Route::post('/resend-otp', [FirebaseAuthController::class, 'resend_otp']);

    Route::post('/google/auth', [FirebaseAuthController::class, 'google_auth']);

});


