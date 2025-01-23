<?php

namespace App\Services\Admin;

use App\Models\Withdraw;
use App\Services\StripeService;

class Withdrawals
{
    public static function get()
    {
        $withdrawals = Withdraw::select(
            'id',
            'user_id',
            'amount',
            'created_at',
            'status'
        )
            ->latest()
            ->with('user:id,uid,email,first_name,last_name,avatar')
            ->paginate();

        return $withdrawals;
    }

    public static function details(Withdraw $withdrawal)
    {
        $history = $withdrawal->user->withdraws()
            ->whereNot('id', $withdrawal->id)
            ->paginate();

        $history->getCollection()->transform(function ($withdraw) {
            return [
                'id' => $withdraw->id,
                'request_date' => $withdraw->created_at->format('Y-m-d'),
                'amount' => $withdraw->amount,
                'type' => 'withdrawal',
            ];
        });

        return [
            'user' => [
                'id' => $withdrawal->user->id,
                'uid' => $withdrawal->user->uid,
                'email' => $withdrawal->user->email,
            ],
            'id' => $withdrawal->id,
            'amount' => $withdrawal->amount,
            'status' => $withdrawal->status,
            'request_date' => $withdrawal->created_at->format('Y-m-d'),
            'history' => $history,
        ];
    }

    public static function approve(Withdraw $withdrawal)
    {
        $stripe_service = app(StripeService::class);

        $payout_id = $stripe_service->payout_to_account(
            $withdrawal->user,
            $withdrawal->bank_id,
            $withdrawal->amount
        )['id'];

        $withdrawal->id = (string) $payout_id;
        $withdrawal->status = 'approved';
        $withdrawal->save();

        return ['message' => 'Funds successfully transferred'];
    }

    public static function reject(Withdraw $withdrawal, string $reason)
    {
        $withdrawal->status = 'rejected';
        $withdrawal->rejection_reason = $reason;
        $withdrawal->save();

        return ['message' => 'Request successfully rejected'];
    }
}
