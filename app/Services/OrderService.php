<?php

namespace App\Services;

use App\Exceptions;
use App\Models\ConnectionRequest;
use App\Models\OrderHistory;
use App\Models\Post;
use App\Models\PostBid;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

class OrderService
{
    protected $stripe_service;

    protected $post_service;

    public function __construct(
        StripeService $stripe_service,
        PostService $post_service,
    ) {
        $this->stripe_service = $stripe_service;
        $this->post_service = $post_service;
    }

    protected function make_order_status(Post $post)
    {

        if ($post->type == 'item') {
            $status = $post->start_date->isPast() && $post->bids[0]->order?->received_by_borrower ? 'underway'
                : 'upcoming';

            $status = $post->end_date->isPast()
                && $post->bids[0]->order->received_by_lender == null
                ? 'past due' : $status;

            $status =
                $post->bids[0]->order?->received_by_borrower
                && $post->bids[0]->order?->received_by_lender
                    ? 'completed' : $status;
        } else {
            $status =
                $post->bids[0]->order?->received_by_borrower != null
                    ? 'completed' : 'upcoming';
        }

        return $status;
    }

    public function get_all(User $user)
    {
        $posts = Post::query()
            ->with('user:id,uid,first_name,last_name,avatar')
            ->withWhereHas('bids', function ($query) {
                $query->where('status', 'accepted')
                    ->withWhereHas('order')
                    ->with(
                        'user:id,uid,first_name,last_name,avatar',
                        'reviews'
                    );
            })
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('bids', function ($query) use ($user) {
                        $query->where('user_id', $user->id)
                            ->where('status', 'accepted');
                    });
            })
            ->paginate();

        $items = [
        ];

        $services = [
        ];

        $is_started = false;

        $posts->getCollection()->each(
            function ($post) use ($user, &$items, &$services, $is_started) {

                if ($post->start_date->isPast()) {
                    $is_started = true;
                }

                $chat_id = ConnectionRequest::where([
                    ['sender_id', '=', $user->id],
                    ['receiver_id', '=', $post->user->id],
                ])
                    ->orWhere([
                        ['sender_id', '=', $post->user->id],
                        ['receiver_id', '=', $user->id],
                    ])
                    ->value('chat_id');

                if ($post->type == 'item') {
                    array_push($items, [
                        'post_id' => $post->id,
                        'bid_id' => $post->bids[0]->id,
                        'title' => $post->title,
                        'description' => $post->description,
                        'start_date' => $post->start_date->format('j M'),
                        'end_date' => $post->end_date->format('j M y'),
                        'post_user_id' => $post->user->id,
                        'post_user_uid' => $post->user->uid,
                        'post_user_name' => $post->user->full_name,
                        'post_user_avatar' => $post->user->avatar,
                        'is_provided' => $post->user_id != $user->id,
                        'status' => $this->make_order_status($post),
                        'transaction_id' => $post->bids[0]->order?->transaction_id,
                        'is_marked' => $is_started,
                        'chat_id' => $chat_id,
                        'is_feedback' => !$post->reviews->isEmpty(),
                    ]);
                } else {
                    array_push($services, [
                        'post_id' => $post->id,
                        'bid_id' => $post->bids[0]->id,
                        'title' => $post->title,
                        'description' => $post->description,
                        'start_date' => $post->start_date->format('j M'),
                        'end_date' => $post->end_date->format('j M y'),
                        'post_user_id' => $post->user->id,
                        'post_user_uid' => $post->user->uid,
                        'post_user_name' => $post->user->full_name,
                        'post_user_avatar' => $post->user->avatar,
                        'is_provided' => $post->user_id != $user->id,
                        'status' => $this->make_order_status($post),
                        'transaction_id' => $post->bids[0]->order?->transaction_id,
                        'is_marked' => $is_started,
                        'is_feedback' => !$post->reviews->isEmpty(),
                    ]);
                }
            }
        );

        $posts->setCollection(collect([
            'items' => $items,
            'services' => $services,
        ]));

        return $posts;
    }

    public function mark_as_received(User $user, int $bid_id)
    {
        // if (! $user->bids->contains('id', $bid_id)) {
        //     throw new Exceptions\BidNotFound;
        // }

        $order = OrderHistory::where('bid_id', $bid_id)->first();

        if (! $order) {
            throw new Exceptions\BaseException(
                'Order not found!',
                400
            );
        }

        if ($order->bid->post->user_id == $user->id) {
            $order->received_by_borrower = now();
        } else {
            $order->received_by_lender = now();
        }

        $order->save();

        return [
            'message' => 'Order marked as received successfully!',
        ];
    }

    public function get_transaction_details(User $user, string $transaction_id)
    {
        $order = OrderHistory::with('bid')->where(
            'transaction_id',
            $transaction_id
        )->first();

        if (! $order || ! $order->bid) {
            throw new Exceptions\BaseException('Order or bid not found!', 404);
        }

        $post = Post::with('user:id,uid,first_name,last_name,avatar')
            ->where('id', $order->bid->post_id)
            ->first();

        if (! $post) {
            throw new Exceptions\BaseException(
                'Post or user not found!', 404
            );
        }

        $calculatedDistance = $this->post_service->calculateDistance(
            $user->lat,
            $user->long,
            $post->lat,
            $post->long,
        );

        $distance = round($calculatedDistance, 2).' miles away';
        $is_started = false;

        if ($post->start_date->isPast()) {
            $is_started = true;
        }

        $chat_id = ConnectionRequest::where([
            ['sender_id', '=', $user->id],
            ['receiver_id', '=', $order->bid->user_id],
        ])
            ->orWhere([
                ['sender_id', '=', $order->bid->user_id],
                ['receiver_id', '=', $user->id],
            ])
            ->value('chat_id');

        return [
            'post_id' => $post->id,
            'bid_id' => $order->bid->id,
            'post_user_name' => $post->user->full_name,
            'post_user_uid' => $post->user->uid,
            'post_user_id' => $post->user->id,
            'avatar' => $post->user->avatar,
            'location' => $post->location,
            'distance' => $distance,
            'transaction_id' => $order->transaction_id,
            'title' => $post->title,
            'type' => $post->type,
            'start_date' => $post->start_date->format('d M Y H:i:s A'),
            'end_date' => $post->end_date->format('d M Y H:i:s A'),
            'description' => $post->description,
            'duration' => Carbon::parse($post->start_date)->format('d M').' - '.Carbon::parse($post->end_date)->format('d M Y'),
            'return_date' => Carbon::parse($post->end_date)->format('d M Y'),
            'chat_id' => $chat_id,
            'is_marked' => $is_started,
            'received_by_borrower' => $order->received_by_borrower != null,
            'received_by_lender' => $order->received_by_lender != null,
            'is_provided' => $post->user_id != $user->id,
            'status' => $this->make_order_status($post),
            'is_feedback' => !$post->reviews->isEmpty(),
        ];
    }

    public function make_bid_payment(
        User $user,
        int $bid_id,
        string $payment_method_id
    ) {
        $bid = PostBid::find($bid_id);

        if (! $bid) {
            throw new Exceptions\BidNotFound;
        }

        if ($bid->post->user_id != $user->id) {
            throw new Exceptions\AccessForbidden;
        }

        if ($bid->status != 'accepted') {
            throw new Exceptions\BaseException(
                'Bid is not accepted',
                400
            );
        }

        if (
            PostBid::where('post_id', $bid->post->id)
                ->where('status', 'accepted')
                ->whereHas('order', function ($query) {
                    $query->whereNotNull('transaction_id');
                })
                ->exists()
        ) {
            throw new Exceptions\BaseException(
                'Payment has been already made on this post.',
                400
            );
        }

        $receipt = $this->stripe_service->charge_card_on_behalf(
            $user,
            $payment_method_id,
            $bid->user,
            $bid->amount
        );

        $transaction = new Transaction;
        $transaction->id = $receipt->id;
        $transaction->user_id = $user->id;
        $transaction->save();

        $order = new OrderHistory;
        $order->bid_id = $bid_id;
        $order->transaction_id = $transaction->id;
        $order->save();

        return ['message' => 'Payment successful'];
    }

    public function get_revenue(User $user, string $year, ?string $month)
    {
        $orders = OrderHistory::withWhereHas(
            'bid',
            function ($query) use ($user) {
                $query->where('user_id', $user->id)->where('status', 'accepted')
                    ->withWhereHas('post');
            }
        )
            ->whereYear('created_at', $year)
            ->when($month, function ($query) use ($month) {
                $query->whereMonth('created_at', $month);
            })
            ->whereNotNull('transaction_id')
            ->paginate();

        $orders->getCollection()->transform(function ($order) {
            return [
                'order_id' => $order->id,
                'transaction_id' => $order->transaction_id,
                'type' => $order->bid->post->type,
                'created_at' => $order->created_at->format('d F Y'),
                'amount' => $order->bid->amount,
            ];
        });

        if (! $month) {
            $points = PostBid::withWhereHas('post:id,type')->selectRaw(
                'SUM(amount) as value,
                YEAR(created_at) as year,
                MONTH(created_at) as month,
                DAY(created_at) as day,
                post_id'
            )
                ->whereHas('order', function ($query) {
                    $query->whereNotNull('transaction_id');
                })
                ->where('user_id', $user->id)
                ->where('status', 'accepted')
                ->whereYear('created_at', $year)
                ->groupByRaw('MONTH(`created_at`)')
                ->orderByRaw('MONTH(`created_at`)')
                ->get();
        } else {
            $points = PostBid::withWhereHas('post:id,type')->selectRaw(
                'SUM(amount) as value,
                YEAR(created_at) as year,
                MONTH(created_at) as month,
                DAY(created_at) as day,
                post_id'
            )
                ->whereHas('order', function ($query) {
                    $query->whereNotNull('transaction_id');
                })
                ->where('user_id', $user->id)
                ->where('status', 'accepted')
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->groupByRaw('DAY(`created_at`)')
                ->orderByRaw('DAY(`created_at`)')
                ->get();
        }

        $items_points = [];
        $services_points = [];

        foreach ($points as $point) {

            if ($point->post->type == 'item') {
                array_push(
                    $items_points,
                    [
                        'value' => $point->value,
                        'year' => $point->year,
                        'month' => $point->month,
                        'day' => $point->day,
                    ]
                );
            }

            if ($point->post->type == 'service') {
                array_push(
                    $services_points,
                    [
                        'value' => $point->value,
                        'year' => $point->year,
                        'month' => $point->month,
                        'day' => $point->day,
                    ]
                );
            }
        }

        return [
            'orders' => $orders,
            'graph' => [
                'view' => $month ? 'monthly' : 'yearly',
                'items' => $items_points,
                'services' => $services_points,
            ],
        ];
    }

    public function get_revenue_details(User $user, int $order_id)
    {
        $order = OrderHistory::with(['bid.post'])
            ->whereHas('bid', function ($query) {
                $query->whereHas('post');
            })
            ->where('id', $order_id)
            ->first();

        if (! $order) {
            throw new Exceptions\BaseException(
                'Order Not Found',
                400
            );
        }

        return [
            'created_at' => $order->created_at->format('d F Y'),
            'order_id' => $order->id,
            'post_id' => $order->bid->post->id,
            'post_title' => $order->bid->post->title,
            'post_user_name' => $order->bid->post->user->full_name,
            'post_user_avatar' => $order->bid->post->user->avatar,
            'post_created_at' => $order->bid->post->created_at->format('d M Y'),
            'received_amount' => $order->bid->amount,
        ];
    }
}
