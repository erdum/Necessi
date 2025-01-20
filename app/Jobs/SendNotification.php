<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fcm_token;
    protected $title;
    protected $body;
    protected $image;
    protected $additional_data;

    public function __construct(
    	$fcm_token,
    	$title,
    	$body,
    	$image,
    	$additional_data
    )
    {
    	$this->fcm_token = $fcm_token;
    	$this->title = $title;
    	$this->body = $body;
    	$this->image = $image;
    	$this->additional_data = $additional_data;
    }

    /**
     * Execute the job.
     *
     * @param  Factory  $factory
     * @return void
     */
    public function handle()
    {
        try {
        	$factory = app(Factory::class);
        	$firebase = $factory->withServiceAccount(
	            base_path()
	            .DIRECTORY_SEPARATOR
	            .config('firebase.projects.app.credentials')
	        );
	        $messaging = $firebase->createMessaging();

        	$firebaseNotification = FirebaseNotification::create(
	            $this->title,
	            $this->body,
	            $this->image
	        );

	        $message = CloudMessage::new()
	            ->withNotification($firebaseNotification)
	            ->withData($this->additional_data)
	            ->withDefaultSounds();

	        $send_report = $messaging->sendMulticast(
	            $message,
	            [$this->fcm_token]
	        );

	        if ($send_report->hasFailures()) {
	            $messages = [];
	            foreach ($send_report->failures()->getItems() as $failure) {
	                $messages[] = $failure->error()->getMessage();
	            }
	            logger()->warning('Failed to send notifications: ', $messages);
	        }
        } catch (\Exception $e) {
            logger()->error(
                'Error in SendNotification job: '.$e->getMessage()."\n".$e->getTraceAsString()
            );
        }
    }
}
