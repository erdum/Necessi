<?php

namespace App\Services\Admin;

use Jimmyjs\ReportGenerator\Facades\PdfReportFacade as PdfReport;
use App\Models\Report;
use App\Models\OrderHistory;
use Illuminate\Pagination\LengthAwarePaginator;

class Pdf
{
    public static function reports()
    {
        $current_page = LengthAwarePaginator::resolveCurrentPage();
        $per_page = 50;

        $title = 'Reports';

        $reports = Report::with(
            ['reporter:id,first_name,last_name,avatar', 'reportable']
        )
            ->offset($per_page * ($current_page - 1))
            ->limit($per_page)
            ->latest();

        $columns = [
            'Reporter Name' => function ($report) {
                return $report->reporter->full_name;
            },
            'Reported Type' => function ($report) {
                $type = explode('\\', $report->reportable_type);
                return end($type);
            },
            'Date & Time' => function ($report) {
                return $report->created_at->format('Y-m-d H:i');
            },
            'Reason' => function ($report) {
                return $report->other_reason ?: $report->reason_type;
            },
        ];

        return PdfReport::of($title, [], $reports, $columns)->download(
            'Reports'
        );
    }

    public static function orders()
    {
        $current_page = LengthAwarePaginator::resolveCurrentPage();
        $per_page = 50;

        $title = 'Orders';

        $orders = OrderHistory::withWhereHas(
            'bid',
            function ($query) {
                $query->where('status', 'accepted')
                    ->withWhereHas('post', function ($query) {
                        $query->withWhereHas('user');
                    })
                    ->with('user');
            }
        )
            ->with(['bid', 'bid.user', 'bid.post', 'bid.post.user'])
            ->whereNotNull('transaction_id')
            ->offset($per_page * ($current_page - 1))
            ->limit($per_page)
            ->latest();

        $columns = [
            'Type' => function ($order) {
                return $order->bid->post->type;
            },
            'Order By' => function ($order) {
                return $order->bid->user->full_name;
            },
            'Listed By' => function ($order) {
                return $order->bid->post->user->full_name;
            },
            'Sale Price' => function ($order) {
                return '$'.$order->bid->post->budget;
            },
            'Date & Time' => function ($order) {
                return $order->created_at->format('Y-m-d H:i');
            },
        ];

        return PdfReport::of($title, [], $orders, $columns)->download(
            'Orders'
        );
    }
}
