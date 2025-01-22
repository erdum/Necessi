<?php

namespace App\Services\Admin;

use App\Exceptions;
use App\Models\Admin;
use App\Models\User;
use App\Models\PostBid;
use App\Services\StripeService;
use App\Models\OrderHistory;
use Kreait\Firebase\Factory;

class Users
{
    protected static $firestore;

    public static function initializeFirestore()
    {
        if (self::$firestore === null) {
            $factory = app(Factory::class);
            $firebase = $factory->withServiceAccount(
                base_path()
                .DIRECTORY_SEPARATOR
                .config('firebase.projects.app.credentials')
            );

            self::$firestore = $firebase->createFirestore()->database();
        }
    }

    public static function get_users()
    {
        self::initializeFirestore();
        $users = User::paginate();

        $all_users = [];
        $active_users = [];
        $offline_users = [];

        $users->getCollection()->each(
            function ($user) use (&$all_users, &$active_users, &$offline_users,
        ){
            $user_ref = self::$firestore->collection('users')->document($user->uid);
            $user_snapshot = $user_ref->snapshot();
    
            if ($user_snapshot->exists()) {
                $user_data = $user_snapshot->data();
    
                $user_entry = [
                    'user_id' => $user->id,
                    'user_uid' => $user->uid,
                    'user_name' => $user->full_name ?? 'Unknown',
                    'email' => $user->email,
                    'user_avatar' => $user->avatar,
                    'is_online' => $user_data['is_online'] ?? false,
                ];
    
                $all_users[] = $user_entry;
    
                if ($user_data['is_online'] === true) {
                    $active_users[] = $user_entry;
                } else {
                    $offline_users[] = $user_entry;
                }
            }
        });

        $users->setCollection(collect([
            'all_users' => $all_users,
            'active_users' => $active_users,
            'offline_users' => $offline_users,
        ]));

        return $users;
    }

    public static function user_details(string $uid)
    {
        self::initializeFirestore();
        $firebase_user = self::$firestore->collection('users')->document($uid);
        $user = User::where('uid', $uid)->first();
        $snapshot = $firebase_user->snapshot();
        $stripe_service = new StripeService();
        $user_details = [];

        $balance = $stripe_service->get_account_balance(
            $user
        )['available'][0]['amount'] / 100;

        $orders = OrderHistory::withWhereHas(
            'bid',
            function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('status', 'accepted');
            })
            ->whereNotNull('transaction_id')
            ->orderBy('created_at', 'desc')
            ->get();

        $total_revenue_amount = $orders->sum(function ($order) {
            return $order->bid->amount ?? 0;
        });

        $orders = $orders->transform(function ($order) {
            return [
                'order_id' => $order->id,
                'transaction_id' => $order->transaction_id,
                'type' => $order->bid->post->type,
                'created_at' => $order->created_at->format('d F Y'),
                'amount' => $order->bid->amount,
            ];
        });

        $user_post_ids = $user->posts()->pluck('id');
        $user_spent = PostBid::whereIn('post_id',$user_post_ids)
            ->where('status', 'accepted')
            ->WhereHas('order')
            ->sum('amount');

        if ($snapshot->exists()) {
            $user_data = $snapshot->data();
    
            $user_details = [
                'user_id' => $user_data['id'],
                'user_uid' => $user_data['uid'],  
                'user_name' => ($user_data['first_name'] ?? 'Unknown') . ' ' . ($user_data['last_name'] ?? ''),
                'email' => $user_data['email'],
                'user_avatar' => $user_data['avatar'] ?? null,
                'is_online' => $user_data['is_online'] ?? false,
                'balance' => $balance,
                'total_revenue' => $total_revenue_amount,
                'spent_amount' => $user_spent,
            ];
        }      
    
        return [
            'user' => $user_details,
            'user_posts' => $user->posts,
            'user_reviews' => $user->reviews,
            'transaction_history' => $orders,
        ];
    }
}