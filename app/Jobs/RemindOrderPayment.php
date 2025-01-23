<?php

namespace App\Jobs;

use App\Models\PostBid;
use App\Services\FirebaseNotificationService;
use App\Services\NotificationData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RemindOrderPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * @param  Factory  $factory
     * @return void
     */
    public function handle(FirebaseNotificationService $notification_service)
    {
        try {
            $payment_reminders = PostBid::where('status', 'accepted')
                ->whereDoesntHave('order')->get();

            foreach ($payment_reminders as $reminder) {
                $notification_service->push_notification(
                    ...NotificationData::ORDER_PAYMENT_REMINDER->get(
                        $reminder->post->user,
                        $reminder->user,
                        $reminder->post
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
