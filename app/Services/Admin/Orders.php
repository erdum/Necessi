<?php

namespace App\Services\Admin;

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

        $item_orders = [];
        $service_orders = [];

        $order_history->getCollection()->each(
            function ($order) use (
                &$all_orders,
                &$item_orders,
                &$service_orders
            ) {
                $order_data = [
                    'type' => $order->bid->post->type,
                    'order_by' => $order->bid->post->user->full_name,
                    'listed_by' => $order->bid->user->full_name,
                    'sale_price' => $order->bid->amount,
                    'created_at' => $order->created_at->format('Y-m-d h:i'),
                ];

                if ($order->bid->post->type == 'item') {
                    $item_orders[] = $order_data;
                } elseif ($order->bid->post->type == 'service') {
                    $service_orders[] = $order_data;
                }
            }
        );

        $query_params = request()->query();
        $pdf_url = route('orders-pdf') . '?' . http_build_query($query_params);

        $order_history->setCollection(collect([
            'item_orders' => $item_orders,
            'service_orders' => $service_orders,
            'pdf_url' => $pdf_url,
        ]));

        return $order_history;
    }
}
