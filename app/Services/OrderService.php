<?php

namespace App\Services;

use App\Exceptions;
use App\Models\OrderHistory;
use App\Models\Post;
use App\Models\User;

class OrderService
{
    protected $stripe_service;

    public function __construct(StripeService $stripe_service)
    {
        $this->stripe_service = $stripe_service;
    }

    public function get_all(User $user)
    {
        $posts = Post::query()
            ->with('user:id,uid,first_name,last_name,avatar')
            ->withWhereHas('bids', function ($query) {
                $query->where('status', 'accepted')
                    ->withWhereHas('order')
                    ->with('user:id,uid,first_name,last_name,avatar');
            })
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('bids', function ($query) use ($user) {
                        $query->where('user_id', $user->id)
                            ->where('status', 'accepted');
                    });
            })
            ->paginate(2);

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
                        'title' => $post->title,
                        'description' => $post->description,
                        'start_date' => $post->start_date->format('j M'),
                        'end_date' => $post->end_date->format('j M y'),
                        'post_user_id' => $post->user->id,
                        'post_user_uid' => $post->user->uid,
                        'post_user_name' => $post->user->first_name.' '.$post->user->last_name,
                        'post_user_avatar' => $post->user->avatar,
                        'is_provided' => $post->user_id != $user->id,
                        'status' => $status,
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
                        'is_provided' => $post->user_id != $user->id,
                        'status' => $status,
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
