<?php

namespace App\Services;

use App\Models\User;
use App\Exceptions;

class OrderService
{
    protected $stripe_service;

    public function __construct(StripeService $stripe_service) {
        $this->stripe_service = $stripe_service;
    }

    public function get_all(User $user)
    {}

    public function make_bid_payment(
        User $user,
        int $bid_id,
        string $payment_method_id
    )
    {
        $bid = PostBid::find($bid_id);

        if (! $bid) throw new Exceptions\BidNotFound;

        if ($bid->status != 'accepted') throw new Exceptions\BaseException(
            'Bid is not accepted',
            400
        );

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
}
