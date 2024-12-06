<?php

namespace App\Observers;

use App\Models\PostLike;
use Illuminate\Contracts\Queue\ShouldQueue;
use Kreait\Firebase\Factory;

class PostLikeObserver implements ShouldQueue
{
    /**
     * Handle the PostLike "created" event.
     */
    public function created(PostLike $postLike): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $ref = $db->collection('posts')
            ->document($postLike->post_id)->collection('likes')
            ->document($postLike->id);

        $user_name = $postComment->user->first_name.' '.$postComment->user->last_name;

        $ref->set([
            'id' => $postLike->id,
            'user_id' => $postLike->user_id,
            'post_id' => $postLike->post_id,
            'user_name' => $user_name,
            'avatar' => $postLike->user->avatar,
            'created_at' => $postLike->created_at,
        ]);
    }

    /**
     * Handle the PostLike "updated" event.
     */
    public function updated(PostLike $postLike): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $ref = $db->collection('posts')
            ->document($postLike->post_id)->collection('likes')
            ->document($postLike->id);

        $user_name = $postComment->user->first_name.' '.$postComment->user->last_name;

        $ref->set([
            'id' => $postLike->id,
            'user_id' => $postLike->user_id,
            'post_id' => $postLike->post_id,
            'user_name' => $user_name,
            'avatar' => $postLike->user->avatar,
            'created_at' => $postLike->created_at,
        ]);
    }

    /**
     * Handle the PostLike "deleted" event.
     */
    public function deleted(PostLike $postLike): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

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
