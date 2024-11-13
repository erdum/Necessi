<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OrderService;

class OrderController extends Controller
{
    public function get_all(Request $request, OrderService $order_service)
    {
        $response = $order_service->get_all($request->user());

        return response()->json($response);
    }

    public function make_bid_payment(
        int $bid_id,
        Request $request,
        OrderService $order_service
    ) {
        $request->validate(['payment_method_id' => 'required']);

        $response = $order_service->make_bid_payment(
            $request->user(),
            $bid_id,
            $request->payment_method_id
        );

        return response()->json($response);
    }
}
