<?php

namespace App\Observers;

use App\Models\PostComment;
use Illuminate\Contracts\Queue\ShouldQueue;

class PostCommentObserver implements ShouldQueue
{
    /**
     * Handle the PostComment "created" event.
     */
    public function created(PostComment $postComment): void
    {
        $db = app('firebase')->createFirestore()->database();

        $ref = $db->collection('posts')
            ->document($postComment->post_id)->collection('comments')
            ->document($postComment->id);

        $ref->set([
            'id' => $postComment->id,
            'post_id' => $postComment->post_id,
            'user_id' => $postComment->user_id,
            'uid' => $postComment->user->uid,
            'comment' => $postComment->data,
            'created_at' => $postComment->created_at,
        ]);
    }

    /**
     * Handle the PostComment "updated" event.
     */
    public function updated(PostComment $postComment): void
    {
        $db = app('firebase')->createFirestore()->database();

        $ref = $db->collection('posts')
            ->document($postComment->post_id)->collection('comments')
            ->document($postComment->id);

        $ref->set([
            'id' => $postComment->id,
            'post_id' => $postComment->post_id,
            'user_id' => $postComment->user_id,
            'uid' => $postComment->user->uid,
            'comment' => $postComment->data,
            'created_at' => $postComment->created_at,
        ]);
    }

    /**
     * Handle the PostComment "deleted" event.
     */
    public function deleted(PostComment $postComment): void
    {
        $db = app('firebase')->createFirestore()->database();

        $ref = $db->collection('posts')
            ->document($postComment->post_id)->collection('comments')
            ->document($postComment->id);

        $ref->delete();
    }

    /**
     * Handle the PostComment "restored" event.
     */
    public function restored(PostComment $postComment): void
    {
        //
    }

    /**
     * Handle the PostComment "force deleted" event.
     */
    public function forceDeleted(PostComment $postComment): void
    {
        //
    }
}
