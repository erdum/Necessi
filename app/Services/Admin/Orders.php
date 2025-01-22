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
        ->paginate();
    
        $all_orders = [];
        $item_orders = [];
        $service_orders = [];
    
        $order_history->getCollection()->each(
            function ($order) use (&$all_orders, &$item_orders, &$service_orders) {
                $order_data = [
                    'type' => $order->bid->post->type,
                    'order_by' => $order->bid->post->user->full_name,
                    'listed_by' => $order->bid->user->full_name,
                    'sale_price' => $order->bid->amount,
                    'created_at' => $order->created_at->format('Y-m-d h:i'),
                ];
    
                $all_orders[] = $order_data;
    
                if ($order->bid->post->type == 'item') {
                    $item_orders[] = $order_data;
                } elseif ($order->bid->post->type == 'service') {
                    $service_orders[] = $order_data;
                }
            }
        );
    
        $order_history->setCollection(collect([
            'all_orders' => $all_orders,
            'item_orders' => $item_orders,
            'service_orders' => $service_orders,
        ]));
    
        return $order_history;
    }    
}