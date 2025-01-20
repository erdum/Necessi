<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Post;
use App\Models\PostBid;

class Dashboard
{
    protected static function calculate_percentage_change(
        $current,
        $yesterday,
        $decimals = 0
    ) {

        if ($yesterday == 0) {
            return number_format($current > 0 ? 100 : 0, $decimals);
        }

        return number_format(
            (($current - $yesterday) / $yesterday) * 100,
            $decimals
        );
    }

    protected static function today_date()
    {
        return now()->startOfDay()->format('Y-m-d');
    }

    protected static function yesterday_date()
    {
        return now()->subDay()->startOfDay()->format('Y-m-d');
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
                'value' => number_format($sales, 2),
                'change_from_yesterday' => self::calculate_percentage_change(
                    $sales,
                    $sales_yesterday,
                    2
                ),
            ],
            'revenue' => [
                'value' => number_format($revenue, 2),
                'change_from_yesterday' => self::calculate_percentage_change(
                    $revenue,
                    $revenue_yesterday,
                    2
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
            ->groupByRaw('MONTH(post_bids.created_at)')
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
                [now()->startOfWeek(), now()->endOfWeek()]
            )
            ->where('post_bids.status', 'accepted')
            ->whereNotNull('order_histories.transaction_id')
            ->groupByRaw('WEEKDAY(post_bids.created_at)')
            ->get();

        $platform_fee = $revenue = config('services.stripe.application_fee');

        $revenue_max_yearly = 0;
        $revenue_max_monthly = 0;
        $revenue_max_weekly = 0;

        $revenue_graph_yearly->transform(
            function ($point) use (&$revenue_max_yearly, $platform_fee) {
                $val = $platform_fee * $point->value;

                if ($val > $revenue_max_yearly) {
                    $revenue_max_yearly = number_format($val, 2);
                }

                return [
                    'value' => number_format($val, 2),
                    'month' => $point->month,
                ];
            }
        );
        $revenue_graph_monthly->transform(
            function ($point) use (&$revenue_max_monthly, $platform_fee) {
                $val = $platform_fee * $point->value;

                if ($val > $revenue_max_monthly) {
                    $revenue_max_monthly = number_format($val, 2);
                }

                return [
                    'value' => number_format($val, 2),
                    'day' => $point->day,
                ];
            }
        );
        $revenue_graph_weekly->transform(
            function ($point) use (&$revenue_max_weekly, $platform_fee) {
                $val = $platform_fee * $point->value;

                if ($val > $revenue_max_weekly) {
                    $revenue_max_weekly = number_format($val, 2);
                }

                return [
                    'value' => number_format($val, 2),
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

    public static function posts_graph()
    {
        $posts_max_yearly = 0;
        $posts_graph_yearly = Post::selectRaw(
            'COUNT(id) as value,
            type'
        )
            ->whereYear('created_at', date('Y'))
            ->groupBy('type')
            ->get();

        $posts_max_last_month = 0;
        $posts_graph_last_month = Post::selectRaw(
            'COUNT(id) as value,
            type'
        )
            ->whereYear('created_at', now()->subMonth()->format('Y'))
            ->whereMonth('created_at', now()->subMonth()->format('m'))
            ->groupBy('type')
            ->get();

        $posts_max_monthly = 0;
        $posts_graph_monthly = Post::selectRaw(
            'COUNT(id) as value,
            type'
        )
            ->whereYear('created_at', now()->format('Y'))
            ->whereMonth('created_at', now()->format('m'))
            ->groupBy('type')
            ->get();

        $posts_max_weekly = 0;
        $posts_graph_weekly = Post::selectRaw(
            'COUNT(id) as value,
            type'
        )
            ->whereBetween(
                'created_at',
                [now()->startOfWeek(), now()->endOfWeek()]
            )
            ->groupBy('type')
            ->get();

        $posts_max_today = 0;
        $posts_graph_today = Post::selectRaw(
            'COUNT(id) as value,
            type'
        )
            ->whereDate('created_at', self::today_date())
            ->groupBy('type')
            ->get();

        $transformer = function ($point, &$max_val, &$points) {

            if ($point->value > $max_val) {
                $max_val = $point->value;
            }

            if ($point->type == 'service') {
                $points['services'] = [
                    'value' => $point->value
                ];
            } else if ($point->type == 'item') {
                $points['items'] = [
                    'value' => $point->value
                ];
            }
        };

        $yearly = [];
        $last_month = [];
        $monthly = [];
        $weekly = [];
        $today = [];

        foreach ($posts_graph_yearly as $point) {
            $transformer($point, $posts_max_yearly, $yearly);
        }

        foreach ($posts_graph_last_month as $point) {
            $transformer($point, $posts_max_last_month, $last_month);
        }

        foreach ($posts_graph_monthly as $point) {
            $transformer($point, $posts_max_monthly, $monthly);
        }

        foreach ($posts_graph_weekly as $point) {
            $transformer($point, $posts_max_weekly, $weekly);
        }

        foreach ($posts_graph_today as $point) {
            $transformer($point, $posts_max_today, $today);
        }

        return [
            'yearly' => [
                'max_value' => $posts_max_yearly,
                'points' => $yearly,
            ],
            'last_month' => [
                'max_value' => $posts_max_last_month,
                'points' => $last_month,
            ],
            'monthly' => [
                'max_value' => $posts_max_monthly,
                'points' => $monthly,
            ],
            'weekly' => [
                'max_value' => $posts_max_weekly,
                'points' => $weekly,
            ],
            'today' => [
                'max_value' => $posts_max_today,
                'points' => $today,
            ],
        ];
    }

    public static function users_growth_graph()
    {
        $yearly_max = 0;
        $users_graph_yearly = User::selectRaw(
            'COUNT(id) as value,
            MONTH(created_at) as month'
        )
            ->whereYear('created_at', now()->format('Y'))
            ->groupByRaw('MONTH(created_at)')
            ->get();

        $last_month_max = 0;
        $users_graph_last_month = User::selectRaw(
            'COUNT(id) as value,
            DAY(created_at) as day'
        )
            ->whereYear('created_at', now()->subMonth()->format('Y'))
            ->whereMonth('created_at', now()->subMonth()->format('m'))
            ->groupByRaw('DAY(created_at)')
            ->get();

        $monthly_max = 0;
        $users_graph_monthly = User::selectRaw(
            'COUNT(id) as value,
            DAY(created_at) as day'
        )
            ->whereYear('created_at', now()->format('Y'))
            ->whereMonth('created_at', now()->format('m'))
            ->groupByRaw('DAY(created_at)')
            ->get();

        $weekly_max = 0;
        $users_graph_weekly = User::selectRaw('
            COUNT(id) as value,
            WEEKDAY(created_at) as week_day'
        )
            ->whereBetween(
                'created_at',
                [now()->startOfWeek(), now()->endOfWeek()]
            )
            ->groupByRaw('WEEKDAY(created_at)')
            ->get();

        $today_max = 0;
        $users_graph_today = User::selectRaw(
            'COUNT(id) as value,
            HOUR(created_at) as hour'
        )
            ->whereDate('created_at', self::today_date())
            ->groupByRaw('HOUR(created_at)')
            ->get();

        foreach ($users_graph_yearly as $point) {
            if ($point->value > $yearly_max) $yearly_max = $point->value;
        }

        foreach ($users_graph_last_month as $point) {
            if ($point->value > $last_month_max)
                $last_month_max = $point->value;
        }

        foreach ($users_graph_monthly as $point) {
            if ($point->value > $monthly_max) $monthly_max = $point->value;
        }

        foreach ($users_graph_weekly as $point) {
            if ($point->value > $weekly_max) $weekly_max = $point->value;
        }

        foreach ($users_graph_today as $point) {
            if ($point->value > $today_max) $today_max = $point->value;
        }

        return [
            'yearly' => [
                'max_value' => $yearly_max,
                'points' => $users_graph_yearly,
            ],
            'last_month' => [
                'max_value' => $last_month_max,
                'points' => $users_graph_last_month,
            ],
            'monthly' => [
                'max_value' => $monthly_max,
                'points' => $users_graph_monthly,
            ],
            'weekly' => [
                'max_value' => $weekly_max,
                'points' => $users_graph_weekly,
            ],
            'today' => [
                'max_value' => $today_max,
                'points' => $users_graph_today,
            ],
        ];
    }

    public static function sales_graph()
    {}
}
