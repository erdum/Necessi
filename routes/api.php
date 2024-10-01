<?php

use App\Http\Controllers\FirebaseAuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

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
    
    Route::post(
        '/profile', 
        [UserController::class, 'update_user']
    );

});

Route::middleware(['throttle:100,1'])->group(function () {

    Route::post(
        '/register',
        [FirebaseAuthController::class, 'register']
    );

    Route::post(
        '/verify-email',
        [FirebaseAuthController::class, 'verify_email']
    );

    Route::post(
        '/resend-otp',
        [FirebaseAuthController::class, 'resend_otp']
    );

    Route::post(
        '/login',
        [FirebaseAuthController::class, 'login']
    );

    Route::post(
        '/google/auth',
        [FirebaseAuthController::class, 'google_auth']
    );

    Route::post(
        'password/reset',
        [FirebaseAuthController::class, 'reset_password']
    );

});
