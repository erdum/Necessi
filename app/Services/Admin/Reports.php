<?php

namespace App\Services\Admin;

use App\Models\Report;
use Illuminate\Pagination\LengthAwarePaginator;

class Reports
{
    public static function get()
    {
        $reports = Report::with(
            ['reporter:id,first_name,last_name,avatar', 'reportable']
        )->paginate();

        $items = [];

        $reports->getCollection()->each(function ($report) use (&$items) {
            $type = explode('\\', $report->reportable_type);
            $type = end($type);

            $items[strtolower($type)][] = [
                'report_id' => $report->id,
                'reporter_user' => [
                    'id' => $report->reporter->id,
                    'name' => $report->reporter->full_name,
                    'avatar' => $report->reporter->avatar,
                ],
                'reported_entity' => [
                    'id' => $report->reportable_id,
                    'type' => $type,
                ],
                'report_date' => $report->created_at,
                'report_reason' => $report->reason_type,
                'other_reason' => $report->other_reason,
            ];
        });

        $reports->setCollection(collect($items));

        return $reports;
    }
}
