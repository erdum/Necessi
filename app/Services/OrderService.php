<?php

namespace App\Services;

use App\Exceptions;
use App\Models\OrderHistory;
use App\Models\Post;
use App\Models\PostBid;
use App\Models\ConnectionRequest;
use App\Models\User;
use Carbon\Carbon;

class OrderService
{
    protected $stripe_service;

    protected $post_service;

    public function __construct(
        StripeService $stripe_service,
        PostService $post_service,
    ){
        $this->stripe_service = $stripe_service;
        $this->post_service = $post_service;
    }

    public function get_all(User $user)
    {
        $posts = Post::query()
            ->with('user:id,uid,first_name,last_name,avatar')
            ->withWhereHas('bids', function ($query) {
                $query->where('status', 'accepted')
                    ->withWhereHas('order')
                    ->with('user:id,uid,first_name,last_name,avatar', 'reviews');
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

        $posts->getCollection()->each(
            function ($post) use ($user, &$items, &$services) {
                $status = $post->bids[0]->order->received_by_borrower == null
                    ? 'upcoming' : '';

                $status = $post->bids[0]->order?->received_by_borrower?->isPast()
                    && $post->bids[0]->order->received_by_lender == null
                    && $post->end_date->isFuture()
                    ? 'underway' : $status;

                $status = $post->bids[0]->order?->received_by_borrower?->isPast()
                    && $post->bids[0]->order->received_by_lender == null
                    && $post->end_date->isPast()
                    ? 'past due' : $status;

                $status = $post->bids[0]->order?->received_by_borrower?->isPast()
                    && $post->bids[0]->order?->received_by_lender?->isPast()
                    && $post->bids[0]->order?->received_by_lender <= $post->end_date
                    ? 'completed' : $status;

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
                        'post_user_name' => $post->user->first_name.' '.$post->user->last_name,
                        'post_user_avatar' => $post->user->avatar,
                        'is_provided' => $post->user_id == $user->id,
                        'status' => $status,
                        'is_feedback' => $post->reviews->isNotEmpty(),
                        'is_borrower' =>  $post->bids[0]->order?->received_by_borrower,
                        'is_lender' =>  $post->bids[0]->order?->received_by_lender,
                        'transaction_id' => $post->bids[0]->order?->transaction_id,
                    ]);
                } else {
                    array_push($services, [
                        'title' => $post->title,
                        'description' => $post->description,
                        'start_date' => $post->start_date->format('j M'),
                        'end_date' => $post->end_date->format('j M y'),
                        'post_user_id' => $post->user->id,
                        'post_user_uid' => $post->user->uid,
                        'post_user_name' => $post->user->first_name.' '.$post->user->last_name,
                        'post_user_avatar' => $post->user->avatar,
                        'is_provided' => $post->user_id == $user->id,
                        'status' => $status,
                        'is_feedback' => $post->reviews->isNotEmpty(),
                        'transaction_id' => $post->bids[0]->order?->transaction_id,
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

        if ($order->bid->user_id !== $user->id) 
        {
            $order->received_by_lender = now();
        } 
        else {
            $order->received_by_borrower = now();
        }
    
        $order->save();
    
        return [
            'message' => 'Order marked as received successfully!'
        ];
    }    

    public function get_transaction_details(User $user, string $transaction_id)
    {
        $order = OrderHistory::with('bid')->where('transaction_id', $transaction_id)
          ->first();
    
        if (! $order || ! $order->bid) {
            throw new Exceptions\BaseException('Order or bid not found!', 404);
        }
    
        $post = Post::with('user:id,first_name,last_name,avatar')
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
            'user_name' => $post->user->first_name . ' ' . $post->user->last_name,
            'avatar' => $post->user->avatar,
            'location' => $post->city,
            'distance' => $distance,
            'transaction_id' => $order->transaction_id,
            'title' => $post->title,
            'type' => $post->type,
            'description' => $post->description,
            'duration' => Carbon::parse($post->start_date)->format('d M') . ' - ' . Carbon::parse($post->end_date)->format('d M Y'),
            'return_date' => Carbon::parse($post->end_date)->format('d M Y'),
            'chat_id' => $chat_id,
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

        if ($bid->status != 'accepted') {
            throw new Exceptions\BaseException(
                'Bid is not accepted',
                400
            );
        }

        $receipt = $this->stripe_service->charge_card(
            $payment_method_id,
            $this->stripe_service->get_customer_id($user),
            $bid->amount
        );

        $transaction = new Transaction;
        $transaction->id = $receipt->id;
        $transaction->user_id = $user->id;
        $transaction->save();

        $order = new OrderHistory;
        $order->bid_id = $bid_id;
        $order->transaction_id = $transaction_id;
        $order->save();
    }

    public function get_revenue(User $user, ?string $year, ?string $month)
    {
        $orders = OrderHistory::withWhereHas(
            'bid',
            function ($query) use ($user) {
                $query->where('user_id', $user->id)->where('status', 'accepted')
                    ->withWhereHas('post');
            }
        )
            ->whereNotNull('transaction_id')
            ->paginate();

        $orders->getCollection()->transform(function ($order) {
            return [
                'type' => $order->bid->post->type,
                'created_at' => $order->created_at->format('d F Y'),
                'amount' => $order->bid->amount,
            ];
        });

        return $orders;
    }
}
