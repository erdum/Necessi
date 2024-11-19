<?php

namespace App\Observers;

use App\Models\PostBid;
use Kreait\Firebase\Factory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Google\Cloud\Firestore\FieldValue;

class PostBidObserver implements ShouldQueue
{
    /**
     * Handle the PostBid "created" event.
     */
    public function created(PostBid $postBid): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $lowest_ref = $db->collection('posts')
            ->document($postBid->post->id)->collection('bids')
            ->document('lowest_bid');

        $bid_ref = $db->collection('posts')->document($postBid->post->id)
            ->collection('bids')->document($postBid->user->uid);

        $db->runTransaction(
            function ($trx) use ($postBid, $lowest_ref, $bid_ref) {
                $trx->set($lowest_ref, [
                    'bid_id' => $postBid->id,
                ], ['merge' => true]);

                $user_name = $postBid->user->first_name.' '.$postBid->user->last_name;
                $trx->set($bid_ref, [
                    'user_id' => $postBid->user_id,
                    'post_id' => $postBid->post_id,
                    'amount' => $postBid->amount,
                    'status' => 'pending',
                    'user_avatar' => $postBid->user->avatar,
                    'user_name' => $user_name,
                    'created_at' => FieldValue::serverTimestamp(),
                ]);
            }
        );
    }

    /**
     * Handle the PostBid "updated" event.
     */
    public function updated(PostBid $postBid): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $lowest_ref = $db->collection('posts')
            ->document($postBid->post->id)->collection('bids')
            ->document('lowest_bid');

        $bid_ref = $db->collection('posts')->document($postBid->post->id)
            ->collection('bids')->document($postBid->user->uid);

        $db->runTransaction(
            function ($trx) use ($postBid, $lowest_ref, $bid_ref) {
                $trx->set($lowest_ref, [
                    'bid_id' => $postBid->id,
                ], ['merge' => true]);

                $user_name = $postBid->user->first_name.' '.$postBid->user->last_name;
                $trx->update($bid_ref, [
                    ['path' => 'amount', 'value' => $postBid->amount],
                    ['path' => 'status', 'value' => $postBid->status],
                    [
                        'path' => 'user_avatar',
                        'value' => $postBid->user->avatar
                    ],
                    [
                        'path' => 'user_name',
                        'value' => $user_name
                    ],
                    [
                        'path' => 'created_at',
                        'value' => FieldValue::serverTimestamp()
                    ],
                ]);
            }
        );
    }

    /**
     * Handle the PostBid "deleted" event.
     */
    public function deleted(PostBid $postBid): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $lowest_ref = $db->collection('posts')
            ->document($postBid->post->id)->collection('bids')
            ->document('lowest_bid');

        $bid_ref = $db->collection('posts')->document($postBid->post->id)
            ->collection('bids')->document($postBid->user->uid);

        $db->runTransaction(
            function ($trx) use ($postBid, $lowest_ref, $bid_ref) {
                $trx->set($lowest_ref, [
                    'bid_id' => ($postBid->id - 1) > 0 ? ($postBid->id - 1) : null,
                ], ['merge' => true]);

                $trx->delete($bid_ref);
            }
        );
    }

    /**
     * Handle the PostBid "restored" event.
     */
    public function restored(PostBid $postBid): void
    {
        //
    }

    /**
     * Handle the PostBid "force deleted" event.
     */
    public function forceDeleted(PostBid $postBid): void
    {
        //
    }
}
