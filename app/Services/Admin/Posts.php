<?php

namespace App\Services\Admin;

use App\Models\Post;
use Carbon\Carbon;

class Posts
{
    public static function get()
    {
        $posts = Post::select(
            'id',
            'user_id',
            'type',
            'title',
            'description',
            'budget',
            'state',
            'city',
            'start_date',
            'end_date',
            'start_time',
            'end_time',
            'created_at',
        )
            ->with(['user:id,uid,first_name,last_name,avatar'])
            ->withCount('likes', 'bids')
            ->latest()
            ->paginate();

        $items = [];
        $services = [];

        $posts->getCollection()->each(
            function ($post) use (&$items, &$services) {

                if ($post->type == 'item') {
                    array_push(
                        $items,
                        [
                            'post_id' => $post->id,
                            'type' => $post->type,
                            'title' => $post->title,
                            'description' => $post->description,
                            'budget' => $post->budget,
                            'state' => $post->state,
                            'city' => $post->city,
                            'duration' => ($post->start_time && $post->end_time)
                                ? Carbon::parse($post->start_time)->format('h:i A').' - '.Carbon::parse($post->end_time)->format('h:i A')
                                : null,
                            'date' => Carbon::parse($post->start_date)->format('d M').' - '.
                                    Carbon::parse($post->end_date)->format('d M y'),
                            'start_date' => $post->start_date,
                            'end_date' => $post->end_date,
                            'start_time' => $post->start_time,
                            'end_time' => $post->end_time,
                            'created_at' => $post->created_at,
                            'user' => [
                                'user_id' => $post->user->id,
                                'user_uid' => $post->user->uid,
                                'user_name' => $post->user->full_name,
                                'user_avatar' => $post->user->avatar,
                            ],
                            'likes_count' => $post->likes_count,
                            'bids_count' => $post->bids_count,
                        ]
                    );
                } else {
                    array_push(
                        $services,
                        [
                            'post_id' => $post->id,
                            'type' => $post->type,
                            'title' => $post->title,
                            'description' => $post->description,
                            'budget' => $post->budget,
                            'state' => $post->state,
                            'city' => $post->city,
                            'duration' => ($post->start_time && $post->end_time)
                                ? Carbon::parse($post->start_time)->format('h:i A').' - '.Carbon::parse($post->end_time)->format('h:i A')
                                : null,
                            'date' => Carbon::parse($post->start_date)->format('d M').' - '.
                                      Carbon::parse($post->end_date)->format('d M y'),
                            'start_date' => $post->start_date,
                            'end_date' => $post->end_date,
                            'start_time' => $post->start_time,
                            'end_time' => $post->end_time,
                            'created_at' => $post->created_at,
                            'user' => [
                                'user_id' => $post->user->id,
                                'user_uid' => $post->user->uid,
                                'user_name' => $post->user->full_name,
                                'user_avatar' => $post->user->avatar,
                            ],
                            'likes_count' => $post->likes_count,
                            'bids_count' => $post->bids_count,
                        ]
                    );
                }
            }
        );

        $posts->setCollection(collect([
            'items' => $items,
            'services' => $services,
        ]));

        return $posts;
    }
}
