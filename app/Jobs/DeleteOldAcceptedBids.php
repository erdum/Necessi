<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use App\Models\PostBid;

class DeleteOldAcceptedBids implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * @param Factory $factory
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
            ->with('user:id,uid,first_name,last_name,avatar')
            ->get();

            if ($bids->isNotEmpty()) {
                foreach ($bids as $bid) 
                {
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
