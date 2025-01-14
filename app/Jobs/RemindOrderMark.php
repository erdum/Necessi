<?php

namespace App\Jobs;

use App\Models\PostBid;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\FirebaseNotificationService;
use App\Services\NotificationType;

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

	public function __construct()
	{
	}
	
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
	        	$user = $pickup->post->user;

	        	$this->notification_service->push_notification(
	                $user,
	                NotificationType::TRANSACTION,
	                $user->full_name,
	                "Your order pickup date as arrived, please mark it if you have received the item",
	                $pickup->user->avatar ?? '',
	                [
	                    'description' => $user->about,
	                    'sender_id' => $user->id,
	                    'post_id' => $pickup->post_id,
	                    'notification_type' => 'bid_canceled',
	                ]
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
	        	$user = $pickup->user;

	        	$this->notification_service->push_notification(
	                $user,
	                NotificationType::TRANSACTION,
	                $user->full_name,
	                "Your order return date as arrived, please mark it if you have received the item",
	                $pickup->post->user->avatar ?? '',
	                [
	                    'description' => $user->about,
	                    'sender_id' => $user->id,
	                    'post_id' => $pickup->post_id,
	                    'notification_type' => 'bid_canceled',
	                ]
	            );
	        }

        } catch (\Exception $e) {
        	logger()->error(
        		'Error executing RemindOrderMark job: '.$e->getMessage(),
        	);
        }
    }
}
