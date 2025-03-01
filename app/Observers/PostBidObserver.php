<?php

namespace App\Observers;

use App\Models\PostBid;
use Google\Cloud\Firestore\FieldValue;
use Illuminate\Contracts\Queue\ShouldQueue;

class PostBidObserver implements ShouldQueue
{
    /**
     * Handle the PostBid "created" event.
     */
    public function created(PostBid $postBid): void
    {
        $db = app('firebase')->createFirestore()->database();

        $lowest_ref = $db->collection('posts')
            ->document($postBid->post->id)->collection('bids')
            ->document('lowest_bid');

        $bid_ref = $db->collection('posts')->document($postBid->post->id)
            ->collection('bids')->document($postBid->user->uid);

        $db->runTransaction(
            function ($trx) use ($postBid, $lowest_ref, $bid_ref) {
                $trx->set($lowest_ref, [
                    'bid_id' => $postBid->user->uid,
                ]);

                $trx->set($bid_ref, [
                    'user_id' => $postBid->user_id,
                    'post_id' => $postBid->post_id,
                    'amount' => $postBid->amount,
                    'status' => 'pending',
                    'user_uid' => $postBid->user->uid,
                    // 'user_avatar' => $postBid->user->avatar,
                    // 'user_name' => $postBid->user->full_name,
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
        $db = app('firebase')->createFirestore()->database();

        $lowest_ref = $db->collection('posts')
            ->document($postBid->post->id)->collection('bids')
            ->document('lowest_bid');

        $bid_ref = $db->collection('posts')->document($postBid->post->id)
            ->collection('bids')->document($postBid->user->uid);

        $db->runTransaction(
            function ($trx) use ($postBid, $lowest_ref, $bid_ref) {
                $trx->set($lowest_ref, [
                    'bid_id' => $postBid->user->uid,
                ]);

                $trx->set($bid_ref, [
                    'amount' => $postBid->amount,
                    'status' => $postBid->status,
                    'user_uid' => $postBid->user->uid,
                    // 'user_avatar' => $postBid->user->avatar,
                    // 'user_name' => $postBid->user->full_name,
                    'created_at' => FieldValue::serverTimestamp(),
                ], ['merge' => true]);
            }
        );
    }

    /**
     * Handle the PostBid "deleted" event.
     */
    public function deleted(PostBid $postBid): void
    {
        $db = app('firebase')->createFirestore()->database();

        $lowest_ref = $db->collection('posts')
            ->document($postBid->post->id)->collection('bids')
            ->document('lowest_bid');

        $bid_ref = $db->collection('posts')->document($postBid->post->id)
            ->collection('bids')->document($postBid->user->uid);

        $db->runTransaction(
            function ($trx) use ($postBid, $lowest_ref, $bid_ref) {
                $trx->set($lowest_ref, [
                    'bid_id' => ($postBid->id - 1) > 0 ? ($postBid->id - 1) : null,
                ]);

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
