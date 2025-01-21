<?php

namespace App\Services\Admin;

use App\Exceptions;
use App\Models\User;
use App\Models\OrderHistory;

class Orders
{
    public static function get_orders()
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

        $orders = [
            'all_orders' => [],
            'item_orders' => [],
            'service_orders' => [],
        ];

        foreach($order_history as $order)
        {
            $order_data = [
                'type' => $order->bid->post->type,
                'order_by' => $order->bid->post->user->full_name,
                'listed_by' => $order->bid->user->full_name,
                'sale_price' => $order->bid->amount,
                'created_at' => $order->created_at->format('Y-m-d h:i'),
            ];

            $orders['all_orders'][] = $order_data;

            if ($order->bid->post->type == 'item') {
                $orders['item_orders'][] = $order_data;
            } else {
                $orders['service_orders'][] = $order_data;
            }
        }

        return $orders;
    }
}