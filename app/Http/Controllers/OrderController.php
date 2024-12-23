<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function get_all(Request $request, OrderService $order_service)
    {
        $response = $order_service->get_all($request->user());

        return response()->json($response);
    }

    public function get_transaction_details(
        string $transaction_id,
        Request $request,
        OrderService $order_service
    ) {
        $response = $order_service->get_transaction_details(
            $request->user(),
            $transaction_id
        );

        return response()->json($response);
    }

    public function make_bid_payment(
        int $bid_id,
        Request $request,
        OrderService $order_service
    ) {
        $request->validate([
            'payment_method_id' => 'required|exists:user_cards,id',
        ]);

        $response = $order_service->make_bid_payment(
            $request->user(),
            $bid_id,
            $request->payment_method_id
        );

        return response()->json($response);
    }

    public function get_revenue(Request $request, OrderService $order_service)
    {
        $request->validate([
            'year' => 'nullable|date_format:"Y"',
            'month' => 'nullable|date_format:"n"',
        ]);

        $response = $order_service->get_revenue(
            $request->user(),
            $request->year ?? date('Y'),
            $request->month
        );

        return response()->json($response);
    }

    public function get_revenue_details(
        int $order_id,
        Request $request,
        OrderService $order_service
    ) {
        $response = $order_service->get_revenue_details(
            $request->user(),
            $order_id,
        );

        return response()->json($response);
    }

    public function mark_as_received(
        int $bid_id,
        Request $request,
        OrderService $order_service
    ) {
        $response = $order_service->mark_as_received(
            $request->user(),
            $bid_id,
        );

        return response()->json($response);
    }
}
