<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use App\Models\PostBid;
use Kreait\Firebase\Factory;

class DeleteOldAcceptedBids implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * @param Factory $factory
     * @return void
     */
    public function handle(Factory $factory)
    {
        try {
            $firebase = $factory->withServiceAccount(
                config('firebase.projects.app.credentials')
            );
            $db = $firebase->createFirestore()->database();

            $one_day_ago = Carbon::now()->subDay();
            $bids = PostBid::where('status', 'accepted')
                ->where('updated_at', '<', $one_day_ago)
                ->with('user')
                ->get();

            if ($bids->isNotEmpty()) {
                foreach ($bids as $bid) 
                {
                    $bid_ref = $db->collection('posts')
                        ->document($bid->post_id)
                        ->collection('bids')
                        ->document($bid->user->uid);
                    $bid_snapshot = $bid_ref->snapshot();

                    if ($bid_snapshot->exists()) {
                        $bid_ref->delete();
                    }

                    $bid->delete();
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error in DeleteOldAcceptedBids job: ' . $e->getMessage(), [
                'stack' => $e->getTraceAsString(),
            ]);
        }
    }
}
