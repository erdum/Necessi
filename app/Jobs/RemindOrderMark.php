<?php

namespace App\Jobs;

use App\Models\PostBid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\FirebaseNotificationService;
use App\Services\NotificationData;

class RemindOrderMark implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * @param  Factory  $factory
     * @return void
     */

	protected $notification_service;

    public function handle()
    {
    	$this->notification_service = app(FirebaseNotificationService::class);
        try {
            $borrower_pickups = PostBid::withWhereHas(
            	'post',
            	function ($query) {
	            	$query->where('start_date', '<=', now())
		            	->withWhereHas('user')->where('type', 'item');
	            }
	        )
	            ->withWhereHas('order', function ($query) {
	            	$query->whereNotNull('transaction_id')
		            	->whereNull('received_by_borrower');
	            })
	            ->where('status', 'accepted')
	            ->with('user')
	            ->get();

	        foreach ($borrower_pickups as $pickup) {
	        	$this->notification_service->push_notification(
	        		...NotificationData::PICKUP_DATE_REMINDER->get(
	        			$pickup->post->user,
	        			$pickup->user,
	        			$pickup->post
	        		)
	        	);
	        }

	        $provider_pickups = PostBid::withWhereHas(
            	'post',
            	function ($query) {
	            	$query->where('end_date', '<=', now())
		            	->withWhereHas('user')->where('type', 'item');
	            }
	        )
	            ->withWhereHas('order', function ($query) {
	            	$query->whereNotNull('transaction_id')
		            	->whereNull('received_by_lender');
	            })
	            ->where('status', 'accepted')
	            ->with('user')
	            ->get();

	        foreach ($provider_pickups as $pickup) {
	        	$this->notification_service->push_notification(
	        		...NotificationData::RETURN_DATE_REMINDER->get(
	        			$pickup->user,
	        			$pickup->post->user,
	        			$pickup->post
	        		)
	        	);
	        }

        } catch (\Exception $e) {
        	logger()->error(
        		'Error executing RemindOrderMark job: '.$e->getMessage()."\n".$e->getTraceAsString(),
        	);
        }
    }
}
