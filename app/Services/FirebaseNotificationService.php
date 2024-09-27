<?php

namespace App\Services;

use App\Models\Notification as NotificationModel;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

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
            throw new \Exception(
                'Token is already linked to another user',
                400
            );
        }

        return ['message' => 'FCM token successfully stored'];
    }
}