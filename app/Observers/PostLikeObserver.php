<?php

namespace App\Observers;

use App\Models\PostLike;
use Illuminate\Contracts\Queue\ShouldQueue;

class PostLikeObserver implements ShouldQueue
{
    /**
     * Handle the PostLike "created" event.
     */
    public function created(PostLike $postLike): void
    {
        $db = app('firebase')->createFirestore()->database();

        $ref = $db->collection('posts')
            ->document($postLike->post_id)->collection('likes')
            ->document($postLike->id);

        $ref->set([
            'id' => $postLike->id,
            'user_id' => $postLike->user_id,
            'post_id' => $postLike->post_id,
            'uid' => $postLike->user->uid,
            'created_at' => $postLike->created_at,
        ]);
    }

    /**
     * Handle the PostLike "updated" event.
     */
    public function updated(PostLike $postLike): void
    {
        $db = app('firebase')->createFirestore()->database();

        $ref = $db->collection('posts')
            ->document($postLike->post_id)->collection('likes')
            ->document($postLike->id);

        $ref->set([
            'id' => $postLike->id,
            'user_id' => $postLike->user_id,
            'post_id' => $postLike->post_id,
            'uid' => $postLike->user->uid,
            'created_at' => $postLike->created_at,
        ]);
    }

    /**
     * Handle the PostLike "deleted" event.
     */
    public function deleted(PostLike $postLike): void
    {
        $db = app('firebase')->createFirestore()->database();

        $ref = $db->collection('posts')
            ->document($postLike->post_id)->collection('likes')
            ->document($postLike->id);

        $ref->delete();
    }

    /**
     * Handle the PostLike "restored" event.
     */
    public function restored(PostLike $postLike): void
    {
        //
    }

    /**
     * Handle the PostLike "force deleted" event.
     */
    public function forceDeleted(PostLike $postLike): void
    {
        //
    }
}
