<?php

namespace App\Services\Admin;

use App\Models\Post;
use App\Models\PostBid;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

        $platform_fee = config('services.stripe.application_fee');

        $revenue = $platform_fee * $sales;

        $revenue_yesterday = $platform_fee * $sales_yesterday;

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
        $platform_fee = config('services.stripe.application_fee');

        $sales_yearly_data = DB::table('post_bids')
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
            ->whereYear('post_bids.created_at', now()->format('Y'))
            ->where('post_bids.status', 'accepted')
            ->whereNotNull('order_histories.transaction_id')
            ->groupByRaw('MONTH(post_bids.created_at)')
            ->get()
            ->keyBy('month');

        $yearly_revenue_labels = [];
        $yearly_revenue_points = [];
        $revenue_max_yearly = 0;
        collect(range(1, 12))->each(
            function ($month) use (
                $platform_fee,
                $sales_yearly_data,
                &$yearly_revenue_labels,
                &$yearly_revenue_points,
                &$revenue_max_yearly
            ) {
                $yearly_revenue_labels[] = $month;

                if ($sales_yearly_data->has($month)) {
                    $value = $platform_fee * $sales_yearly_data[$month]->value;
                    $revenue_max_yearly = max($revenue_max_yearly, $value);

                    return $yearly_revenue_points[] = $value;
                }

                return $yearly_revenue_points[] = '0.00';
            }
        );

        $sales_monthly_data = DB::table('post_bids')
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
            ->whereMonth('post_bids.created_at', now()->format('m'))
            ->where('post_bids.status', 'accepted')
            ->whereNotNull('order_histories.transaction_id')
            ->groupByRaw('DAY(post_bids.created_at)')
            ->get()
            ->keyBy('day');

        $monthly_revenue_labels = [];
        $monthly_revenue_points = [];
        $revenue_max_monthly = 0;
        collect(range(1, date('t')))->each(
            function ($day) use (
                $sales_monthly_data,
                $platform_fee,
                &$monthly_revenue_labels,
                &$monthly_revenue_points,
                &$revenue_max_monthly
            ) {
                $monthly_revenue_labels[] = $day;

                if ($sales_monthly_data->has($day)) {
                    $value = $platform_fee * $sales_monthly_data[$day]->value;
                    $revenue_max_monthly = max($revenue_max_monthly, $value);

                    return $monthly_revenue_points[] = $value;
                }

                return $monthly_revenue_points[] = '0.00';
            }
        );

        $sales_weekly_data = DB::table('post_bids')
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
            ->get()
            ->keyBy('week_day');

        $weekly_revenue_labels = [];
        $weekly_revenue_points = [];
        $revenue_max_weekly = 0;
        collect(range(0, 6))->each(
            function ($week_day) use (
                $sales_weekly_data,
                $platform_fee,
                &$weekly_revenue_labels,
                &$weekly_revenue_points,
                &$revenue_max_weekly
            ) {
                $weekly_revenue_labels[] = $week_day;

                if ($sales_weekly_data->has($week_day)) {
                    $value = $platform_fee * $sales_weekly_data[$week_day]->value;
                    $revenue_max_weekly = max($revenue_max_weekly, $value);

                    return $weekly_revenue_points[] = $value;
                }

                return $weekly_revenue_points[] = '0.00';
            }
        );

        return [
            'yearly' => [
                'max_value' => $revenue_max_yearly,
                'labels' => $yearly_revenue_labels,
                'points' => $yearly_revenue_points,
            ],
            'monthly' => [
                'max_value' => $revenue_max_monthly,
                'labels' => $monthly_revenue_labels,
                'points' => $monthly_revenue_points,
            ],
            'weekly' => [
                'max_value' => $revenue_max_weekly,
                'labels' => $weekly_revenue_labels,
                'points' => $weekly_revenue_points,
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
                    'value' => $point->value,
                ];
            } elseif ($point->type == 'item') {
                $points['items'] = [
                    'value' => $point->value,
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
        $users_yearly_data = User::selectRaw(
            'COUNT(id) as value,
            MONTH(created_at) as month'
        )
            ->whereYear('created_at', now()->format('Y'))
            ->groupByRaw('MONTH(created_at)')
            ->get()
            ->keyBy('month');

        $yearly_users_labels = [];
        $yearly_users_points = [];
        $yearly_max = 0;
        collect(range(1, 12))->each(
            function ($month) use (
                $users_yearly_data,
                &$yearly_users_labels,
                &$yearly_users_points,
                &$yearly_max
            ) {
                $yearly_users_labels[] = $month;

                if ($users_yearly_data->has($month)) {
                    $value = $users_yearly_data[$month]->value;
                    $yearly_max = max($yearly_max, $value);

                    return $yearly_users_points[] = $value;
                }

                return $yearly_users_points[] = '0.00';
            }
        );

        $users_last_month_data = User::selectRaw(
            'COUNT(id) as value,
            DAY(created_at) as day'
        )
            ->whereYear('created_at', now()->subMonth()->format('Y'))
            ->whereMonth('created_at', now()->subMonth()->format('m'))
            ->groupByRaw('DAY(created_at)')
            ->get()
            ->keyBy('day');

        $last_month_users_labels = [];
        $last_month_users_points = [];
        $last_month_max = 0;
        collect(range(1, date('t')))->each(
            function ($day) use (
                $users_last_month_data,
                &$last_month_users_labels,
                &$last_month_users_points,
                &$last_month_max
            ) {
                $last_month_users_labels[] = $day;

                if ($users_last_month_data->has($day)) {
                    $value = $users_last_month_data[$day]->value;
                    $last_month_max = max($last_month_max, $value);

                    return $last_month_users_points[] = $value;
                }

                return $last_month_users_points[] = '0.00';
            }
        );

        $users_monthly_data = User::selectRaw(
            'COUNT(id) as value,
            DAY(created_at) as day'
        )
            ->whereYear('created_at', now()->format('Y'))
            ->whereMonth('created_at', now()->format('m'))
            ->groupByRaw('DAY(created_at)')
            ->get()
            ->keyBy('day');

        $monthly_users_labels = [];
        $monthly_users_points = [];
        $monthly_max = 0;
        collect(range(1, date('t')))->each(
            function ($day) use (
                $users_monthly_data,
                &$monthly_users_labels,
                &$monthly_users_points,
                &$monthly_max
            ) {
                $monthly_users_labels[] = $day;

                if ($users_monthly_data->has($day)) {
                    $value = $users_monthly_data[$day]->value;
                    $monthly_max = max($monthly_max, $value);

                    return $monthly_users_points[] = $value;
                }

                return $monthly_users_points[] = '0.00';
            }
        );

        $users_weekly_data = User::selectRaw('
            COUNT(id) as value,
            WEEKDAY(created_at) as week_day'
        )
            ->whereBetween(
                'created_at',
                [now()->startOfWeek(), now()->endOfWeek()]
            )
            ->groupByRaw('WEEKDAY(created_at)')
            ->get()
            ->keyBy('week_day');

        $weekly_users_labels = [];
        $weekly_users_points = [];
        $weekly_max = 0;
        collect(range(0, 6))->each(
            function ($week_day) use (
                $users_weekly_data,
                &$weekly_users_labels,
                &$weekly_users_points,
                &$weekly_max
            ) {
                $weekly_users_labels[] = $week_day;

                if ($users_weekly_data->has($week_day)) {
                    $value = $users_weekly_data[$week_day]->value;
                    $weekly_max = max($weekly_max, $value);

                    return $weekly_users_points[] = $value;
                }

                return $weekly_users_points[] = '0.00';
            }
        );

        $users_today_data = User::selectRaw(
            'COUNT(id) as value,
            HOUR(created_at) as hour'
        )
            ->whereDate('created_at', self::today_date())
            ->groupByRaw('HOUR(created_at)')
            ->get()
            ->keyBy('hour');

        $today_users_labels = [];
        $today_users_points = [];
        $today_max = 0;
        collect(range(0, 23))->each(
            function ($hour) use (
                $users_today_data,
                &$today_users_labels,
                &$today_users_points,
                &$today_max
            ) {
                $today_users_labels[] = $hour;

                if ($users_today_data->has($hour)) {
                    $value = $users_today_data[$hour]->value;
                    $today_max = max($today_max, $value);

                    return $today_users_points[] = $value;
                }

                return $today_users_points[] = '0.00';
            }
        );

        return [
            'yearly' => [
                'max_value' => $yearly_max,
                'labels' => $yearly_users_labels,
                'points' => $yearly_users_points,
            ],
            'last_month' => [
                'max_value' => $last_month_max,
                'labels' => $last_month_users_labels,
                'points' => $last_month_users_points,
            ],
            'monthly' => [
                'max_value' => $monthly_max,
                'labels' => $monthly_users_labels,
                'points' => $monthly_users_points,
            ],
            'weekly' => [
                'max_value' => $weekly_max,
                'labels' => $weekly_users_labels,
                'points' => $weekly_users_points,
            ],
            'today' => [
                'max_value' => $today_max,
                'labels' => $today_users_labels,
                'points' => $today_users_points,
            ],
        ];
    }

    public static function sales_graph()
    {
        $sales_yearly_data = DB::table('post_bids')
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
            ->whereYear('post_bids.created_at', now()->format('Y'))
            ->where('post_bids.status', 'accepted')
            ->whereNotNull('order_histories.transaction_id')
            ->groupByRaw('MONTH(post_bids.created_at)')
            ->get()
            ->keyBy('month');

        $yearly_sales_labels = [];
        $yearly_sales_points = [];
        $sales_max_yearly = 0;
        collect(range(1, 12))->each(
            function ($month) use (
                $sales_yearly_data,
                &$yearly_sales_labels,
                &$yearly_sales_points,
                &$sales_max_yearly
            ) {
                $yearly_sales_labels[] = $month;

                if ($sales_yearly_data->has($month)) {
                    $value = $sales_yearly_data[$month]->value;
                    $sales_max_yearly = max($sales_max_yearly, $value);

                    return $yearly_sales_points[] = $value;
                }

                return $yearly_sales_points[] = '0.00';
            }
        );

        $sales_last_month_data = DB::table('post_bids')
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
            ->whereYear('post_bids.created_at', now()->subMonth()->format('Y'))
            ->whereMonth('post_bids.created_at', now()->subMonth()->format('m'))
            ->where('post_bids.status', 'accepted')
            ->whereNotNull('order_histories.transaction_id')
            ->groupByRaw('DAY(post_bids.created_at)')
            ->get()
            ->keyBy('day');

        $last_month_sales_labels = [];
        $last_month_sales_points = [];
        $sales_max_last_month = 0;
        collect(range(1, date('t')))->each(
            function ($month) use (
                $sales_last_month_data,
                &$last_month_sales_labels,
                &$last_month_sales_points,
                &$sales_max_last_month
            ) {
                $last_month_sales_labels[] = $month;

                if ($sales_last_month_data->has($month)) {
                    $value = $sales_last_month_data[$month]->value;
                    $sales_max_last_month = max($sales_max_last_month, $value);

                    return $last_month_sales_points[] = $value;
                }

                return $last_month_sales_points[] = '0.00';
            }
        );

        $sales_monthly_data = DB::table('post_bids')
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
            ->whereYear('post_bids.created_at', now()->format('Y'))
            ->whereMonth('post_bids.created_at', now()->format('m'))
            ->where('post_bids.status', 'accepted')
            ->whereNotNull('order_histories.transaction_id')
            ->groupByRaw('DAY(post_bids.created_at)')
            ->get()
            ->keyBy('day');

        $monthly_sales_labels = [];
        $monthly_sales_points = [];
        $sales_max_monthly = 0;
        collect(range(1, date('t')))->each(
            function ($month) use (
                $sales_monthly_data,
                &$monthly_sales_labels,
                &$monthly_sales_points,
                &$sales_max_monthly
            ) {
                $monthly_sales_labels[] = $month;

                if ($sales_monthly_data->has($month)) {
                    $value = $sales_monthly_data[$month]->value;
                    $sales_max_monthly = max($sales_max_monthly, $value);

                    return $monthly_sales_points[] = $value;
                }

                return $monthly_sales_points[] = '0.00';
            }
        );

        $sales_weekly_data = DB::table('post_bids')
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
            ->get()
            ->keyBy('week_day');

        $weekly_sales_labels = [];
        $weekly_sales_points = [];
        $sales_max_weekly = 0;
        collect(range(0, 6))->each(
            function ($month) use (
                $sales_weekly_data,
                &$weekly_sales_labels,
                &$weekly_sales_points,
                &$sales_max_weekly
            ) {
                $weekly_sales_labels[] = $month;

                if ($sales_weekly_data->has($month)) {
                    $value = $sales_weekly_data[$month]->value;
                    $sales_max_weekly = max($sales_max_weekly, $value);

                    return $weekly_sales_points[] = $value;
                }

                return $weekly_sales_points[] = '0.00';
            }
        );

        $sales_today_data = DB::table('post_bids')
            ->join(
                'order_histories',
                'order_histories.bid_id',
                '=',
                'post_bids.id'
            )
            ->selectRaw(
                'SUM(post_bids.amount) as value,
                HOUR(post_bids.created_at) as hour'
            )
            ->whereDate('post_bids.created_at', self::today_date())
            ->where('post_bids.status', 'accepted')
            ->whereNotNull('order_histories.transaction_id')
            ->groupByRaw('HOUR(post_bids.created_at)')
            ->get()
            ->keyBy('hour');

        $today_sales_labels = [];
        $today_sales_points = [];
        $sales_max_today = 0;
        collect(range(0, 23))->each(
            function ($month) use (
                $sales_today_data,
                &$today_sales_labels,
                &$today_sales_points,
                &$sales_max_today
            ) {
                $today_sales_labels[] = $month;

                if ($sales_today_data->has($month)) {
                    $value = $sales_today_data[$month]->value;
                    $sales_max_today = max($sales_max_today, $value);

                    return $today_sales_points[] = $value;
                }

                return $today_sales_points[] = '0.00';
            }
        );

        return [
            'yearly' => [
                'max_value' => $sales_max_yearly,
                'labels' => $yearly_sales_labels,
                'points' => $yearly_sales_points,
            ],
            'last_month' => [
                'max_value' => $sales_max_last_month,
                'labels' => $last_month_sales_labels,
                'points' => $last_month_sales_points,
            ],
            'monthly' => [
                'max_value' => $sales_max_monthly,
                'labels' => $monthly_sales_labels,
                'points' => $monthly_sales_points,
            ],
            'weekly' => [
                'max_value' => $sales_max_weekly,
                'labels' => $weekly_sales_labels,
                'points' => $weekly_sales_points,
            ],
            'today' => [
                'max_value' => $sales_max_today,
                'labels' => $today_sales_labels,
                'points' => $today_sales_points,
            ],
        ];
    }
}
