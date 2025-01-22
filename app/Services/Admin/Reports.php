<?php

namespace App\Services\Admin;

use App\Models\Report;
use App\Models\User;
use App\Models\Post;
use App\Models\PostComment;
use App\Services\StripeService;
use Kreait\Firebase\Factory;
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
                'report_date' => $report->created_at->format('Y-m-d H:i'),
                'report_reason' => $report->reason_type,
                'other_reason' => $report->other_reason,
            ];
        });

        // Admin panel doesn't have Report type Post Comment
        unset($items['postcomment']);

        $reports->setCollection(collect($items));

        return $reports;
    }

    public static function details(Report $report)
    {
        $reporter = $report->reporter;
        $reported_entity = $report->reportable;

        if ($reported_entity instanceof User) {
            $factory = app(Factory::class);
            $firebase = $factory->withServiceAccount(
                base_path()
                .DIRECTORY_SEPARATOR
                .config('firebase.projects.app.credentials')
            );

            $firestore = $firebase->createFirestore()->database();
            $user_snap = $firestore->collection('users')
                ->document($reported_entity->uid)
                ->snapshot();

            if ($user_snap->exists()) $user_firestore_data = $user_snap->data();

            $stripe_service = app(StripeService::class);
            $user_balance = $stripe_service->get_account_balance(
                $reported_entity
            )['available'][0]['amount'] / 100;;

            return [
                'reporter' => [
                    'id' => $reporter->id,
                    'name' => $reporter->full_name,
                    'avatar' => $reporter->avatar,
                ],
                'report_date' => $report->created_at->format('Y-m-d'),
                'report_reason' => $report->reason_type,
                'other_reason' => $report->other_reason,
                'type' => 'user',
                'reported_user' => [
                    'id' => $reported_entity->id,
                    'uid' => $reported_entity->uid,
                    'name' => $reported_entity->full_name,
                    'avatar' => $reported_entity->avatar,
                    'email' => $reported_entity->email,
                    'is_online' => $user_firestore_data['is_online'] ?? false,
                    'wallet_balance' => $user_balance,
                ],
            ];
        } else if ($reported_entity instanceof Post) {

            return [
                'reporter' => [
                    'id' => $reporter->id,
                    'name' => $reporter->full_name,
                    'avatar' => $reporter->avatar,
                ],
                'report_date' => $report->created_at->format('Y-m-d'),
                'report_reason' => $report->reason_type,
                'other_reason' => $report->other_reason,
                'type' => 'post',
                'reported_post' => [
                    'post_user' => [
                        'id' => $reported_entity->user->id,
                        'uid' => $reported_entity->user->uid,
                        'email' => $reported_entity->user->email,
                        'name' => $reported_entity->user->full_name,
                        'avatar' => $reported_entity->user->avatar,
                    ],
                    'id' => $reported_entity->id,
                    'title' => $reported_entity->title,
                    'description' => $reported_entity->description,
                    'budget' => $reported_entity->budget,
                    'start_date' => $reported_entity->start_date,
                    'end_date' => $reported_entity->end_date,
                    'start_time' => $reported_entity?->start_time,
                    'end_time' => $reported_entity?->end_time,
                    'bids_count' => $reported_entity->bids()->count(),
                    'likes_count' => $reported_entity->likes()->count(),
                ],
            ];
        } else if ($reported_entity instanceof PostComment) {
            // Admin panel doesn't have Report type Post Comment
        }
    }

    public static function deactivate_user(User $user)
    {}
}
