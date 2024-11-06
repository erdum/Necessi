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

    // Get uses notifications list
    Route::get(
        '/user/notifications',
        [UserController::class, 'get_notifications']
    );

    // Get user preferences
    Route::get(
        '/user/preferences',
        [UserController::class, 'get_user_preferences']
    );

    // update user preferences
    Route::post(
        '/user/preferences',
        [UserController::class, 'update_preferences']
    );

    // update user Password
    Route::post(
        '/user/update-password',
        [UserController::class, 'update_password']
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
        [PostController::class, 'get_user_posts_reviews']
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

    // cancel user palced bid on a post
    Route::delete(
        '/posts/{post_id}/bid',
        [PostController::class, 'cancel_placed_bid']
    );

    // Get bids on a post
    Route::get(
        '/posts/{post_id}/bids',
        [PostController::class, 'get_post_bids']
    );

    // Get user palced bids on a post
    Route::get(
        '/user/bids/placed',
        [PostController::class, 'get_placed_bids']
    );

    // Get user Received bids on a post
    Route::get(
        '/user/bids/received',
        [PostController::class, 'get_received_bids']
    );

    // Get user palced bid status on a post
    Route::get(
        '/posts/{post_id}/user/bid/status',
        [PostController::class, 'get_placed_bid_status']
    );

    // Accept bid on a post
    Route::get(
        '/posts/bid/{bid_id}/accept',
        [PostController::class, 'accept_post_bid']
    );

    // Decline bid on a post
    Route::get(
        '/posts/bid/{bid_id}/decline',
        [PostController::class, 'decline_post_bid']
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
        [PostController::class, 'toggle_like']
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

    // Send connections requests
    Route::post(
        '/user/send-connection-requests',
        [UserController::class, 'send_connection_requests']
    );

    // Cancel connection request
    Route::post(
        '/user/cancel-connection-request',
        [UserController::class, 'cancel_connection_request']
    );

    // Accept connection request
    Route::post(
        '/users/accept-connection-request',
        [UserController::class, 'accept_connection_request']
    );

    // Decline connection request
    Route::post(
        '/user/decline-connection-request',
        [UserController::class, 'decline_connection_request']
    );

    // Get connection requests
    Route::get(
        '/user/connection-requests',
        [UserController::class, 'get_connection_requests']
    );

    // Remove user connection
    Route::delete(
        '/user/remove-connection',
        [UserController::class, 'remove_connection']
    );

    // Get user connections
    Route::get(
        '/user/connections',
        [UserController::class, 'get_connections']
    );

    // Block user from chat
    Route::post(
        '/user/block/{uid}',
        [UserController::class, 'block_user']
    );

    // Unblock user from chat
    Route::get(
        '/user/unblock/{uid}',
        [UserController::class, 'unblock_user']
    );

    // Get blocked users list
    Route::get(
        '/user/blocked/users',
        [UserController::class, 'get_blocked_users']
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
