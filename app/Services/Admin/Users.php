<?php

namespace App\Services\Admin;

use App\Models\OrderHistory;
use App\Models\PostBid;
use App\Models\User;
use App\Services\StripeService;
use Carbon\Carbon;

class Users
{
    public static function get_users()
    {
        $users = User::paginate();

        $all_users = [];
        $active_users = [];
        $offline_users = [];

        $users->getCollection()->each(
            function ($user) use (
                &$all_users,
                &$active_users,
                &$offline_users
            ) {
                $firestore = app('firebase')->createFirestore()->database();
                $user_ref = $firestore->collection('users')
                    ->document($user->uid);
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
                        'is_deactivate' => (bool) $user->deactivated,
                    ];

                    $all_users[] = $user_entry;

                    if (($user_data['is_online'] ?? false) === true) {
                        $active_users[] = $user_entry;
                    } else {
                        $offline_users[] = $user_entry;
                    }
                }
            }
        );

        $users->setCollection(collect([
            'all_users' => $all_users,
            'active_users' => $active_users,
            'offline_users' => $offline_users,
        ]));

        return $users;
    }

    public static function user_details(string $uid)
    {
        $firestore = app('firebase')->createFirestore()->database();
        $firebase_user = $firestore->collection('users')->document($uid);
        $user = User::where('uid', $uid)->first();
        $snapshot = $firebase_user->snapshot();
        $stripe_service = app(StripeService::class);
        $user_details = [];
        $posts = [];

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
        $user_spent = PostBid::whereIn('post_id', $user_post_ids)
            ->where('status', 'accepted')
            ->WhereHas('order')
            ->sum('amount');

        if ($snapshot->exists()) {
            $user_data = $snapshot->data();

            $user_details = [
                'user_id' => $user_data['id'],
                'user_uid' => $user_data['uid'],
                'user_name' => ($user_data['first_name'] ?? 'Unknown').' '.($user_data['last_name'] ?? ''),
                'email' => $user_data['email'],
                'user_avatar' => $user_data['avatar'] ?? null,
                'is_online' => $user_data['is_online'] ?? false,
                'balance' => $balance,
                'total_revenue' => $total_revenue_amount,
                'spent_amount' => $user_spent,
                'is_deactivate' => (bool) $user->deactivated
            ];
        }

        if($user->posts){
            foreach($user->posts as $post)
            {
                $posts[] = [
                    'post_id' => $post->id,
                    'type' => $post->type,
                    'title' => $post->title,
                    'description' => $post->description,
                    'location' => $post->location,
                    'lat' => $post->lat,
                    'long' => $post->long,
                    'city' => $post->city,
                    'state' => $post->state,
                    'budget' => $post->budget,
                    'duration' => ($post->start_time && $post->end_time)
                        ? Carbon::parse($post->start_time)->format('h:i A').' - '.Carbon::parse($post->end_time)->format('h:i A')
                        : null,
                    'date' => Carbon::parse($post->start_date)->format('d M').' - '.
                            Carbon::parse($post->end_date)->format('d M y'),
                    'start_date' => $post->start_date,
                    'end_date' => $post->end_date,
                    'start_time' => $post->start_time,
                    'end_time' => $post->end_time,
                    'delivery_requested' =>(bool) $post->delivery_requested,
                    'created_at' => $post->created_at,
                    'bids_count' => $post->bids->count(),
                    'likes_count' => $post->likes->count(),
                    'user' => [
                        'user_id' => $post->user->id,
                        'user_uid' => $post->user->uid,
                        'user_name' => $post->user->full_name,
                        'user_avatar' => $post->user->avatar,
                    ],
                ]; 
            }
        }

        return [
            'user' => $user_details,
            'user_posts' => $posts,
            'user_reviews' => $user->reviews,
            'transaction_history' => $orders,
        ];
    }
}
