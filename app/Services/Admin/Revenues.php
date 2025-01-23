<?php

namespace App\Services\Admin;

use App\Models\OrderHistory;
use App\Models\Withdraw;

class Revenues
{
    public static function get_revenues()
    {
        $order_history = OrderHistory::withWhereHas(
            'bid',
            function ($query) {
                $query->where('status', 'accepted')
                    ->withWhereHas('post');
            })
            ->whereNotNull('transaction_id')
            ->orderBy('created_at', 'desc')
            ->paginate();

        $all = [];
        $received = [];
        $withdrawn = [];
        $total_revenue = 0;

        $order_history->getCollection()->each(
            function ($order) use (
                &$all,
                &$received,
                &$total_revenue
            ) {
                $revenue_data = [
                    'user' => $order->bid->user->full_name,
                    'transaction_date' => $order->created_at->format('Y-m-d h:i'),
                    'total_amount' => $order->bid->amount,
                    'platform_fee' => 0,
                    'status' => 'received',
                    'total_revenue' => $order->bid->amount,
                ];

                $total_revenue += $order->bid->amount;

                $all[] = $revenue_data;
                $received[] = $revenue_data;
            }
        );

        $withdraws = Withdraw::with(['user', 'bank'])
            ->orderBy('created_at', 'desc')
            ->paginate();

        $withdraws->getCollection()->each(
            function ($withdraw) use (
                &$all,
                &$withdrawn,
                &$total_revenue
            ) {
                $revenue_data = [
                    'user' => $withdraw->user->full_name,
                    'transaction_date' => $withdraw->created_at->format('Y-m-d h:i'),
                    'total_amount' => $withdraw->amount,
                    'platform_fee' => 0,
                    'status' => 'withdrawn',
                    'total_revenue' => $total_revenue,
                ];

                $all[] = $revenue_data;
                $withdrawn[] = $revenue_data;
            }
        );

        usort($all, function ($a, $b) {
            return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
        });

        $order_history->setCollection(collect([
            'total_revenue' => $total_revenue,
            'platform_earnings' => 0,
            'all' => $all,
            'received' => $received,
            'withdrawn' => $withdrawn,
        ]));

        return $order_history;
    }
}
