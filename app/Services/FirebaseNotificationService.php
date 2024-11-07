<?php

namespace App\Services;

use App\Exceptions;
use App\Models\User;
use Kreait\Firebase\Factory;
use App\Models\Notification;
use App\Models\UserNotificationDevice;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class FirebaseNotificationService
{
    protected $messaging;

    public function __construct(Factory $factory)
    {
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $this->messaging = $firebase->createMessaging();
    }

    public function store_fcm_token(User $user, string $token)
    {
        try {
            $user->notification_device()->updateOrCreate(
                ['user_id' => $user->id],
                ['fcm_token' => $token]
            );
        } catch (\Exception $error) {
            throw new Exceptions\TokenAlreadyLinked;
        }

        return ['message' => 'FCM token successfully stored'];
    }

    public function push_notification(
        User $user,
        string $title,
        string $body,
        ?string $image = null,
        array $additional_data = []
    ) {
        $notification_device = UserNotificationDevice::where('user_id', $user->id)->first();
    
        if (!$notification_device) {
            throw new Exceptions\FcmTokenNotFound;
        }
        
        $notification = new Notification;
        $notification->title = $title;
        $notification->body = $body;
        $notification->image = $image;
        $user->notifications()->save($notification);

        $firebaseNotification = FirebaseNotification::create($title, $body, $image);

        $message = CloudMessage::new()
            ->withNotification($firebaseNotification)
            ->withData($additional_data)
            ->withDefaultSounds();
    
        $send_report = $this->messaging->sendMulticast(
            $message,
            [$notification_device->fcm_token]
        );
    
        if ($send_report->hasFailures()) {
            $messages = [];
            foreach ($send_report->failures()->getItems() as $failure) {
                $messages[] = $failure->error()->getMessage();
            }
            Log::warning('Failed to send notifications: ', $messages);
        }
    
        return [
            'message' => 'Notifications successfully sent',
            'send_report' => $send_report ?? null,
        ];
    }
}
