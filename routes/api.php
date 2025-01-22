<?php

use App\Http\Controllers\FirebaseAuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
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

Route::prefix('admin')->group(function () {

    // Login
    Route::post('/login', [AdminController::class, 'login']);

    // Forget password
    Route::post(
        '/forget-password',
        [AdminController::class, 'forget_password']
    );

    // Resend OTP
    Route::post(
        'resend-otp',
        [AdminController::class, 'forget_password']
    );

    // Verify OTP
    Route::post(
        '/verify-otp',
        [AdminController::class, 'verify_otp']
    );

    // Update password
    Route::post(
        '/update-password',
        [AdminController::class, 'update_password']
    );

    Route::middleware('auth:sanctum')->group(function () {

        // Get dashboard data
        Route::get(
            '/dashboard', 
            [AdminController::class, 'get_dashboard']
        );

        // Get notifications data
        Route::get(
            '/notifications',
            [AdminController::class, 'get_notifications']
        );

        // Send global notification
        Route::post(
            '/notifications',
            [AdminController::class, 'push_admin_notification']
        );

        // Get Users
        Route::get(
            '/users', 
            [AdminController::class, 'get_users']
        );

        // Get User Details
        Route::get(
            '/user-details/{uid}', 
            [AdminController::class, 'user_details']
        );

        // Get posts
        Route::get(
            '/posts',
            [AdminController::class, 'get_posts']
        );

        // Get reports
        Route::get(
            '/reports',
            [AdminController::class, 'get_reports']
        );

        // Get withdrawal requests
        Route::get(
            '/withdrawals',
            [AdminController::class 'get_withdrawals']
        );

        // Get Revenues
        Route::get(
            '/revenues', 
            [AdminController::class, 'get_revenues']
        );

        // Get Orders
        Route::get(
            '/orders', 
            [AdminController::class, 'get_orders']
        );

        // Logout
        Route::get(
            '/logout',
            [AdminController::class, 'logout']
        );
    });

});

Route::middleware('auth:sanctum')->group(function () {

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

    // Delete user account
    Route::delete(
        '/user',
        [UserController::class, 'delete_user_account']
    );

    // Get user payment details
    Route::get(
        '/user/payment-methods',
        [UserController::class, 'get_payment_details']
    );

    // Add user payment card
    Route::post(
        '/user/payment-card',
        [UserController::class, 'add_payment_card']
    );

    // Update user payment card
    Route::post(
        '/user/payment-card/{card_id}',
        [UserController::class, 'update_payment_card']
    );

    // Delete user payment card
    Route::delete(
        '/user/payment-card/{card_id}',
        [UserController::class, 'delete_payment_card']
    );

    // Add user bank account
    Route::post(
        '/user/bank-details',
        [UserController::class, 'add_bank_details']
    );

    // Update user bank account
    // Route::post(
    //     '/user/bank-details/{bank_id}',
    //     [UserController::class, 'update_bank_details']
    // );

    // Delete user bank account
    Route::delete(
        '/user/bank-details/{bank_id}',
        [UserController::class, 'delete_bank_account']
    );

    // Get Stripe account onboarding link
    Route::get(
        '/user/connect/onboarding/link',
        [UserController::class, 'get_onboarding_link']
    );

    // Withdraw funds to bank account
    Route::post(
        '/user/funds/withdraw',
        [UserController::class, 'withdraw_funds']
    );

    // Get user account funds
    Route::get(
        '/user/funds',
        [UserController::class, 'get_user_funds']
    );

    // Get uses notifications list
    Route::get(
        '/user/notifications',
        [UserController::class, 'get_notifications']
    );

    // Clear user notifications
    Route::get(
        '/user/notifications/clear',
        [UserController::class, 'clear_user_notifications']
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

    // Get post preview link
    Route::get(
        '/posts/{post_id}/preview',
        [PostController::class, 'get_post_preview']
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
    )->withoutMiddleware(['throttle:api']);

    // Get user posts reviews
    Route::get(
        '/user/posts/reviews',
        [PostController::class, 'get_user_posts_reviews']
    );

    // Get specific user post reviews
    Route::get(
        '/user/review/{post_id}',
        [PostController::class, 'get_user_review']
    );

    // Place user posts reviews
    Route::post(
        '/user/posts/review',
        [PostController::class, 'place_post_review']
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
        '/user/bids/placed/{bid_id?}',
        [PostController::class, 'get_placed_bids']
    );

    // Remove user rejected bid on a post
    Route::delete(
        '/user/bid/{bid_id}',
        [PostController::class, 'remove_rejected_bid']
    );

    // Get user Received bids on a post
    Route::get(
        '/user/bids/received',
        [PostController::class, 'get_received_bids']
    );

    // Get user placed bid status on a post
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

    // Delete comment on a post
    Route::delete(
        '/posts/comments/{comment_id}',
        [PostController::class, 'delete_post_comment']
    );

    // Report comment on a post
    Route::post(
        '/posts/report/{comment_id}',
        [PostController::class, 'report_post_comment']
    );

    // Report Post
    Route::post(
        '/posts/{post_id}/report',
        [PostController::class, 'report_post']
    );

    // Place like on a post
    Route::post(
        '/posts/{post_id}/like',
        [PostController::class, 'toggle_like']
    )->withoutMiddleware(['throttle:api']);

    // Place comment on a post
    Route::post(
        '/posts/place/comment',
        [PostController::class, 'place_comment']
    );

    // Search all posts
    Route::post(
        '/search',
        [PostController::class, 'search_all']
    );

    // Get all transactions
    Route::get(
        '/transactions',
        [OrderController::class, 'get_all']
    );

    // Mark as received
    Route::post(
        '/user/mark-as-received/{bid_id}',
        [OrderController::class, 'mark_as_received']
    );

    // Get specific transaction details
    Route::get(
        '/transactions/{transaction_id}',
        [OrderController::class, 'get_transaction_details']
    );

    // Get revenue
    Route::get(
        '/revenue',
        [OrderController::class, 'get_revenue']
    );

    // Get specific revenue Details
    Route::get(
        '/user/revenue-details/{order_id}',
        [OrderController::class, 'get_revenue_details']
    );

    // Make bid payment
    Route::post(
        '/bids/{bid_id}/payment',
        [OrderController::class, 'make_bid_payment']
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

    // Get chat users
    Route::get(
        '/user/chats',
        [UserController::class, 'get_chat_users']
    );

    // Initiate chat
    Route::get(
        '/user/initiate-chat/{uid}',
        [UserController::class, 'initiate_chat']
    );

    // Create chat
    Route::post(
        '/chat',
        [UserController::class, 'create_chat']
    ); //------- We're creating chat upon connection request being accepted

    // Upload document to Firebase cloud storage
    Route::post(
        '/uploads',
        [UserController::class, 'handle_uploads']
    );

    // Send message notification
    Route::post(
        '/chat/notification',
        [UserController::class, 'send_message_notificatfion']
    );

    // Block user from chat
    Route::post(
        '/user/block/{user_uid}',
        [UserController::class, 'block_user']
    );

    // Report user from chat
    Route::post(
        '/users/report/{user_id}',
        [UserController::class, 'report_user']
    );

    // Report user from chat
    Route::post(
        '/user/report/{chat_id}',
        [UserController::class, 'report_chat']
    );

    // Unblock user from chat
    Route::get(
        '/user/unblock/{user_uid}',
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

    // Get all posts
    Route::get(
        '/posts',
        [PostController::class, 'get_all_posts']
    );

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

// Stripe webhook
Route::post('/stripe-webhook', [StripeWebhookController::class, 'handle']);
