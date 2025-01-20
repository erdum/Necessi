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

class SendAdminNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $title;
    protected $body;
    protected $image;
    protected $additional_data;

    public function __construct(
        $title,
        $body,
        $image,
        $additional_data
    )
    {
        $this->title = $title;
        $this->body = $body;
        $this->image = $image;
        $this->additional_data = $additional_data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
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
                ->withDefaultSounds()
                ->toTopic(config('app.admin.notification.fcm_topic'));

            $messaging->send($message);
        } catch (\Exception $e) {
            logger()->error(
                'Error in SendAdminNotification job: '.$e->getMessage()."\n".$e->getTraceAsString()
            );
        }
    }
}
