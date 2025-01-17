<?php

namespace App\Services;

use App\Exceptions;
use Illuminate\Support\Facades\DB;
use App\Models\Admin;
use App\Models\User;
use App\Models\Post;
use App\Models\PostBid;
use App\Services\OtpService;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Jobs\SendEmails;

class AdminService
{
    public function register(string $name, string $email, string $password)
    {}

    public function login(string $email, string $password, ?string $fcm_token)
    {
        $admin = Admin::where('email', $email)->firstOrFail();

        if (! Hash::check($password, $admin->password)) {
            throw new Exceptions\BaseException('Incorrect password', 400);
        }

        if ($fcm_token) {
            $admin->fcm_token = $fcm_token;
            $admin->save();
        }

        $admin->tokens()->delete();
        $token = $admin->createToken($admin->email)->plainTextToken;

        return [
            'message' => 'Login successful',
            'token' => $token,
            'admin_details' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'avatar' => $admin?->avatar,
            ],
        ];
    }

    public function forget_password(string $email)
    {
        $admin = Admin::where('email', $email)->firstOrFail();

        OtpService::send(
            $admin->email,
            mt_rand(1000, 9999),
            function ($otp) use ($admin) {
                $subject = 'OTP | '.config('app.name');
                $content = "Hello {$admin->name},\n\nHere is your One-Time Password (OTP) for authentication:\n\n{$otp}.\n\nPlease use this code to complete your action.\n\nThank you,\n".config('app.name');

                SendEmails::dispatchAfterResponse(
                    $subject,
                    $content,
                    [$admin->email]
                );
            }
        );

        return [
            'message' => 'OTP has been successfully sent',
            'retry_duration' => config('otp.expiry_duration'),
        ];
    }

    public function verify_otp(string $email, string $otp)
    {
        $admin = Admin::where('email', $email)->firstOrFail();

        $verified = OtpService::verify($otp);

        return ['is_verified' => $verified ? true : false];
    }

    public function update_password(
        string $email,
        string $otp,
        string $new_password
    )
    {
        $admin = Admin::where('email', $email)->firstOrFail();

        $verified = OtpService::verify($otp);

        if (! $verified) {
            throw new Exceptions\InvalidOtp;
        }

        $admin->password = Hash::make($new_password);
        $admin->save();

        OtpService::clear_otp($email);

        return [
            'message' => 'Password has been successfully reset'
        ];
    }

    public function get_dashboard()
    {
        $user_count = User::count();
        $post_count = Post::count();
        $total_sales = PostBid::where('status', 'accepted')
            ->withWhereHas('order', function ($query) {
                $query->whereNotNull('transaction_id');
            })
            ->sum('amount');
        $total_revenue = config(
            'services.stripe.application_fee'
        ) * $total_sales;

        $revenue_graph_yearly = DB::table('post_bids')
            ->join(
                'order_histories',
                'order_histories.bid_id',
                '=',
                'post_bids.id'
            )
            ->selectRaw(
                'SUM(post_bids.amount) as value,
                MONTH(post_bids.created_at) as month'
            )
            ->whereYear('post_bids.created_at', date('Y'))
            ->where('post_bids.status', 'accepted')
            ->whereNotNull('order_histories.transaction_id')
            ->groupByRaw('YEAR(post_bids.created_at)')
            ->get();

        $revenue_graph_monthly = DB::table('post_bids')
            ->join(
                'order_histories',
                'order_histories.bid_id',
                '=',
                'post_bids.id'
            )
            ->selectRaw(
                'SUM(post_bids.amount) as value,
                DAY(post_bids.created_at) as day'
            )
            ->whereMonth('post_bids.created_at', date('n'))
            ->where('post_bids.status', 'accepted')
            ->whereNotNull('order_histories.transaction_id')
            ->groupByRaw('DAY(post_bids.created_at)')
            ->get();

        $revenue_graph_weekly = DB::table('post_bids')
            ->join(
                'order_histories',
                'order_histories.bid_id',
                '=',
                'post_bids.id'
            )
            ->selectRaw(
                'SUM(post_bids.amount) as value,
                WEEKDAY(post_bids.created_at) as week_day'
            )
            ->whereBetween(
                'post_bids.created_at',
                [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]
            )
            ->where('post_bids.status', 'accepted')
            ->whereNotNull('order_histories.transaction_id')
            ->groupByRaw('DAY(post_bids.created_at)')
            ->get();

        $revenue_max_yearly = 0;
        $revenue_max_monthly = 0;
        $revenue_max_weekly = 0;

        $revenue_graph_yearly->transform(
            function ($point) use (&$revenue_max_yearly) {

                if ($point->value > $revenue_max_yearly) {
                    $revenue_max_yearly = $point->value;
                }

                return [
                    'value' => $point->value,
                    'month' => $point->month,
                ];
            }
        );
        $revenue_graph_monthly->transform(
            function ($point) use (&$revenue_max_monthly) {

                if ($point->value > $revenue_max_monthly) {
                    $revenue_max_monthly = $point->value;
                }

                return [
                    'value' => $point->value,
                    'day' => $point->day,
                ];
            }
        );
        $revenue_graph_weekly->transform(
            function ($point) use (&$revenue_max_weekly) {

                if ($point->value > $revenue_max_weekly) {
                    $revenue_max_weekly = $point->value;
                }

                return [
                    'value' => $point->value,
                    'week_day' => $point->week_day,
                ];
            }
        );

        return [
            'total_users' => [
                'value' => $user_count,
                'change_from_yesterday' => null,
            ],
            'total_posts' => [
                'value' => $post_count,
                'change_from_yesterday' => null,
            ],
            'total_sales' => number_format($total_sales),
            'total_revenue' => number_format($total_revenue),
            'revenue_graph' => [
                'yearly' => [
                    'max_value' => $revenue_max_yearly,
                    'points' => $revenue_graph_yearly,
                ],
                'monthly' => [
                    'max_value' => $revenue_max_monthly,
                    'points' => $revenue_graph_monthly,
                ],
                'weekly' => [
                    'max_value' => $revenue_max_weekly,
                    'points' => $revenue_graph_weekly,
                ],
            ],
        //     'posts_graph' => ,
        //     'users_growth_graph' => ,
        //     'sales_graph' => ,
        ];
    }
}
