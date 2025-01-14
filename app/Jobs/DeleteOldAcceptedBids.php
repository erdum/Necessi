<?php

namespace App\Jobs;

use App\Models\PostBid;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteOldAcceptedBids implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * @param  Factory  $factory
     * @return void
     */
    public function handle()
    {
        try {
            $one_day_ago = Carbon::now()->subDay();
            $bids = PostBid::where('status', 'accepted')
                ->where('updated_at', '<', $one_day_ago)
                ->whereHas('order', function ($query) {
                    $query->whereNull('transaction_id');
                })
                ->update(['status' => 'rejected']);

        } catch (\Exception $e) {
            logger()->error(
                'Error in DeleteOldAcceptedBids job: '.$e->getMessage()
            );
        }
    }
}
