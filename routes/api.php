<?php

use App\Http\Controllers\FirebaseAuthController;
use App\Http\Controllers\PostController;
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

    // Get user profile
    Route::get(
        '/profile',
        [UserController::class, 'get_user']
    );

    // Update user profile
    Route::post(
        '/profile',
        [UserController::class, 'update_user']
    );

    // Update user FCM token
    Route::post(
        '/user/fcm',
        [UserController::class, 'update_user_fcm']
    );

    // Get all posts
    Route::get(
        '/posts',
        [PostController::class, 'get_all_posts']
    );

    // Get specific post
    Route::get(
        '/posts/{post_id}',
        [PostController::class, 'get_post_details']
    );

    // Get user posts
    Route::get(
        '/user/posts',
        [PostController::class, 'get_user_posts']
    );

    // Get user posts reviews
    Route::get(
        '/user/posts/reviews',
        [PostController::class, 'get_user_reviews']
    );

    // Create post
    Route::post(
        '/posts/create',
        [PostController::class, 'create_post']
    );

    // Edit post
    Route::post(
        '/posts/{post_id}',
        [PostController::class, 'edit_post']
    );

    // Delete post
    Route::delete(
        '/posts/{post_id}',
        [PostController::class, 'delete_post']
    );

    // Place bid on a post
    Route::post(
        '/posts/{post_id}/bid',
        [PostController::class, 'place_bid']
    );

    // Get bids on a post
    Route::get(
        '/posts/{post_id}/bids',
        [PostController::class, 'get_post_bids']
    );

    // Get reviews on a post
    Route::get(
        '/posts/{post_id}/reviews',
        [PostController::class, 'get_post_reviews']
    );

    // Get comments on a post
    Route::get(
        '/posts/{post_id}/comments',
        [PostController::class, 'get_post_comments']
    );

    // Place like on a post
    Route::post(
        '/posts/{post_id}/like',
        [PostController::class, 'post_like']
    );

    // unlike on a post
    Route::post(
        '/posts/{post_id}/unlike',
        [PostController::class, 'post_unlike']
    );

    // Search all posts
    Route::post(
        '/search',
        [PostController::class, 'search_all']
    );

    // Set user location
    Route::post(
        '/user/location',
        [UserController::class, 'set_location']
    );

    // Get nearby users
    Route::get(
        '/nearby/users',
        [UserController::class, 'get_nearby_users']
    );

    // Send connections request
    Route::post(
        '/user/send-connection-request',
        [UserController::class, 'send_connection_request']
    );

    // cancel connection request
    Route::post(
        '/user/cancel-connection-request',
        [UserController::class, 'cancel_connection_request']
    );

    // Get connection requests
    Route::get(
        '/user/connection-requests',
        [UserController::class, 'get_connection_requests']
    );

    // Accept connection request
    Route::post(
        '/users/request-accept',
        [UserController::class, 'make_connection']
    );

    // Decline connection request
    Route::post(
        '/user/request-decline',
        [UserController::class, 'request_decline']
    );

    // Remove user connections
    Route::delete(
        '/user/remove',
        [UserController::class, 'user_remove']
    );

    // Get user connections
    Route::get(
        '/user/connections',
        [UserController::class, 'get_connections']
    );

    // Logout user
    Route::get(
        '/user/logout',
        [FirebaseAuthController::class, 'logout']
    );

});

Route::middleware(['throttle:100,1'])->group(function () {

    // Register user
    Route::post(
        '/register',
        [FirebaseAuthController::class, 'register']
    );

    // Verify user email
    Route::post(
        '/verify-email',
        [FirebaseAuthController::class, 'verify_email']
    );

    // Request resend OTP
    Route::post(
        '/resend-otp',
        [FirebaseAuthController::class, 'resend_otp']
    );

    // Login user
    Route::post(
        '/login',
        [FirebaseAuthController::class, 'login']
    );

    // Login user with social providers
    Route::post(
        '/social/auth',
        [FirebaseAuthController::class, 'social_auth']
    );

    // Request password reset
    Route::post(
        'password/reset',
        [FirebaseAuthController::class, 'reset_password']
    );

    Route::post('/dev-login', [FirebaseAuthController::class, 'dev_login']);

});
