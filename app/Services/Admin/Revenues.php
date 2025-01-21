<?php

namespace App\Services\Admin;

use App\Exceptions;
use App\Models\User;
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
            ->get();
    
        $revenues = [
            'all' => [],
            'received' => [],
            'withdrawn' => [],
        ];
        $total_revenue = 0;
    
        foreach ($order_history as $order) {
            $revenue_data = [
                'user' => $order->bid->user->full_name,
                'transaction_date' => $order->created_at->format('Y-m-d h:i'),
                'total_amount' => $order->bid->amount,
                'platform_fee' => 0,
                'status' => 'received',
                'total_revenue' => $order->bid->amount,
            ];
    
            $total_revenue += $order->bid->amount;
            $revenues['all'][] = $revenue_data;
            $revenues['received'][] = $revenue_data;
        }
    
        $withdraws = Withdraw::with(['user', 'bank'])
            ->orderBy('created_at', 'desc')
            ->get();
    
        foreach ($withdraws as $withdraw) {
            $revenue_data = [
                'user' => $withdraw->user->full_name,
                'transaction_date' => $withdraw->created_at->format('Y-m-d h:i'),
                'total_amount' => $withdraw->amount,
                'platform_fee' => 0,
                'status' => 'withdrawn',
                'total_revenue' => 0,
            ];
    
            $total_revenue -= $withdraw->amount;
            $revenues['all'][] = $revenue_data;
            $revenues['withdrawn'][] = $revenue_data;
        }
    
        usort($revenues['all'], function ($a, $b) {
            return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
        });
    
        return [
            'total_revenue' => $total_revenue,
            'platform_earnings' => 0,
            'revenue_data' => $revenues,
        ];
    }
}