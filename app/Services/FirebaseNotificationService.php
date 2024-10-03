<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Factory;
use App\Exceptions;

class FirebaseNotificationService
{
    protected $messaging;

    public function __construct(Factory $factory)
    {
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config("firebase.projects.app.credentials")
        );
        $this->messaging = $firebase->createMessaging();
    }

    public function store_fcm_token(User $user, string $token)
    {
        try {
            $user->notification_device()->updateOrCreate(
                ["user_id" => $user->id],
                ["fcm_token" => $token]
            );
        } catch (\Exception $error) {
            throw new Exceptions\TokenAlreadyLinked();
        }

        return ["message" => "FCM token successfully stored"];
    }
}
