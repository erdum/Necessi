<?php

namespace App\Services\Admin;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Services\StripeService;
use App\Services\PostService;

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
            $firestore = app('firebase')->createFirestore()->database();
            $user_snap = $firestore->collection('users')
                ->document($reported_entity->uid)
                ->snapshot();

            if ($user_snap->exists()) {
                $user_firestore_data = $user_snap->data();
            }

            $stripe_service = app(StripeService::class);
            $user_balance = $stripe_service->get_account_balance(
                $reported_entity
            )['available'][0]['amount'] / 100;

            $reviews = Review::whereHas(
                'post',
                function ($query) use ($reported_entity) {
                    $query->user_id = $reported_entity->id;
                }
            )
                ->with('user:id,uid,first_name,last_name')
                ->paginate(4);

            $reviews->getCollection()->transform(
                function ($review) {
                    return [
                        'id' => $review->id,
                        'post_id' => $review->post_id,
                        'data' => $review->data,
                        'rating' => $review->rating,
                        'created_at' => $review->created_at->format('Y-m-d'),
                        'user' => [
                            'id' => $review->user->id,
                            'uid' => $review->user->uid,
                            'name' => $review->user->full_name,
                            'avatar' => $review->user->avatar,
                        ],
                    ];
                }
            );

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
                    'reviews' => $reviews,
                ],
            ];
        } elseif ($reported_entity instanceof Post) {

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
        } elseif ($reported_entity instanceof PostComment) {
            // Admin panel doesn't have Report type Post Comment
        }
    }

    public static function deactivate_user(User $user) {
        // Cancel all pending bids
        $pending_bids = $user->bids()->with('post')->where('status', 'pending')
            ->get();
        $post_service = app(PostService::class);

        $pending_bids->each(function ($bid) use ($user, $post_service) {
            $post_service->cancel_placed_bid($user, $bid->post);
        });

        $user->deactivated = true;
        $user->save();

        return ['message' => 'User successfully deactivated.'];
    }

    public static function reactive_user(User $user) {
        $user->deactivated = false;
        $user->save();

        return ['message' => 'User successfully reactivated.'];
    }
}
