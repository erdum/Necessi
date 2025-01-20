<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Post;
use App\Models\PostBid;
use Carbon\Carbon;

class Dashboard
{
    protected static function calculate_percentage_change(
        $current,
        $yesterday
    ) {

        if ($yesterday == 0) {
            return number_format($current > 0 ? 100 : 0);
        }

        return number_format((($current - $yesterday) / $yesterday) * 100);
    }

    protected static function yesterday_date()
    {
        return Carbon::yesterday()->startOfDay();
    }

    public static function users()
    {
        $users_count = User::count();
        $users_count_yesterday = User::where(
            'created_at',
            '<=',
            self::yesterday_date()
        )->count();

        return [
            'value' => number_format($users_count),
            'change_from_yesterday' => self::calculate_percentage_change(
                $users_count,
                $users_count_yesterday
            ),
        ];
    }

    public static function posts()
    {
        $posts_count = Post::count();
        $posts_count_yesterday = Post::where(
            'created_at',
            '<=',
            self::yesterday_date()
        )->count();

        return [
            'value' => number_format($posts_count),
            'change_from_yesterday' => self::calculate_percentage_change(
                $posts_count,
                $posts_count_yesterday
            ),
        ];
    }

    public static function sales_revenue()
    {
        $sales = PostBid::where('status', 'accepted')
            ->withWhereHas('order', function ($query) {
                $query->whereNotNull('transaction_id');
            })
            ->sum('amount');
        $sales_yesterday = PostBid::where(
            'created_at',
            '<=',
            self::yesterday_date()
        )
            ->where('status', 'accepted')
            ->withWhereHas('order', function ($query) {
                $query->whereNotNull('transaction_id');
            })
            ->sum('amount');

        $revenue = config(
            'services.stripe.application_fee'
        ) * $sales;

        $revenue_yesterday = config(
            'services.stripe.application_fee'
        ) * $sales_yesterday;

        return [
            'sales' => [
                'value' => number_format($sales),
                'change_from_yesterday' => self::calculate_percentage_change(
                    $sales,
                    $sales_yesterday
                ),
            ],
            'revenue' => [
                'value' => number_format($revenue),
                'change_from_yesterday' => self::calculate_percentage_change(
                    $revenue,
                    $revenue_yesterday
                ),
            ],
        ];
    }

    public static function revenue_graph()
    {
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
        ];
    }
}
