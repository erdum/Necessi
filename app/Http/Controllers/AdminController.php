<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Admin;
use App\Models\Report;

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
            'sales_graph' => Admin\Dashboard::sales_graph(),
        ];

        return $response;
    }

    public function get_notifications(Request $request)
    {
        $response = Admin\Notifications::get();

        return $response;
    }

    public function push_admin_notification(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'body' => 'required',
            'image' => 'nullable|image',
        ]);

        $response = Admin\Notifications::push_admin_notification(
            $request->title,
            $request->body,
            $request->image
        );

        return $response;
    }

    public function get_users(Request $request)
    {
        $response = Admin\Users::get_users();

        return $response;
    }

    public function user_details(Request $request, string $uid)
    {
        $response = Admin\Users::user_details($uid);

        return $response;
    }

    public function get_posts(Request $request)
    {
        $response = Admin\Posts::get();

        return $response;
    }

    public function get_reports(Request $request)
    {
        $response = Admin\Reports::get();

        return $response;
    }

    public function get_report_details(int $report_id, Request $request)
    {
        $report = Report::findOrFail($report_id);

        $response = Admin\Reports::details($report);

        return $response;
    }

    public function deactivate_user(int $user_id, Request $request)
    {
        $user = User::findOrFail($user_id);

        $response = Admin\Reports::deactivate_user($user);

        return $response;
    }
    public function get_revenues(Request $request)
    {
        $response = Admin\Revenues::get_revenues();

        return $response;
    }

    public function get_orders(Request $request)
    {
        $response = Admin\Orders::get_orders();

        return $response;
    }

    public function logout(Request $request)
    {
        $response = Admin\Auth::logout($request->user());

        return $response;
    }
}
