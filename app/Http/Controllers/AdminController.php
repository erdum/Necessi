<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Admin;

class AdminController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|exists:admins,email',
            'password' => 'required'
        ]);

        $response = Admin\Auth::login(
            $request->email,
            $request->password,
            $request->fcm_token
        );

        return $response;
    }

    public function forget_password(Request $request)
    {
        $request->validate([
            'email' => 'required|exists:admins,email',
        ]);

        $response = Admin\Auth::forget_password($request->email);

        return $response;
    }

    public function verify_otp(Request $request)
    {
        $request->validate([
            'email' => 'required|exists:admins,email',
            'otp' => 'required|size:4',
        ]);

        $response = Admin\Auth::verify_otp($request->email, $request->otp);

        return $response;
    }

    public function update_password(Request $request)
    {
        $request->validate([
            'email' => 'required|exists:admins,email',
            'otp' => 'required|size:4',
            'new_password' => 'required',
        ]);

        $response = Admin\Auth::update_password(
            $request->email,
            $request->otp,
            $request->new_password
        );

        return $response;
    }

    public function get_dashboard(Request $request)
    {
        $sales_revenue = Admin\Dashboard::sales_revenue();

        $response = [
            'total_users' => Admin\Dashboard::users(),
            'total_posts' => Admin\Dashboard::posts(),
            'total_sales' => $sales_revenue['sales'],
            'total_revenue' => $sales_revenue['revenue'],
            'revenue_graph' => Admin\Dashboard::revenue_graph(),
            'posts_graph' => Admin\Dashboard::posts_graph(),
            'users_growth_graph' => Admin\Dashboard::users_growth_graph(),
            // 'sales_graph' => ,
        ];

        return $response;
    }
}
