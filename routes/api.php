<?php

use App\Http\Controllers\FirebaseAuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PostController;
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

    Route::get(
        '/profile', 
        [UserController::class, 'get_user']
    );

    Route::post(
        '/create-post', 
        [PostController::class, 'create_post']
    );

    Route::post(
        '/post-biding', 
        [PostController::class, 'post_biding']
    );

    // Get user posts
    Route::get(
        '/user/post', 
        [PostController::class, 'get_user_posts']
    );

    // Get all posts 
    Route::get(
        '/posts', 
        [PostController::class, 'get_all_posts']
    );

    Route::post(
        '/post/like', 
        [PostController::class, 'post_like']
    );

    Route::post(
        '/location',
        [UserController::class, 'set_location']
    );

    Route::get(
        '/nearby/users',
        [UserController::class, 'get_nearby_users']
    );

    Route::post(
        '/users/connect',
        [UserController::class, 'make_connections']
    );

    Route::get(
        '/user/connections',
        [UserController::class, 'get_connections']
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
        '/social/auth',
        [FirebaseAuthController::class, 'social_auth']
    );

    Route::post(
        'password/reset',
        [FirebaseAuthController::class, 'reset_password']
    );

    Route::post('/dev-login', [FirebaseAuthController::class, 'dev_login']);

});
