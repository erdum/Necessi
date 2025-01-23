<?php

namespace App\Services\Admin;

use App\Models\Notification;
use App\Services\FirebaseNotificationService;

class Notifications
{
    public static function get()
    {
        $notifications = Notification::whereNull('user_id')->paginate();

        return $notifications;
    }

    public static function push_admin_notification(
        string $title,
        string $body,
        ?string $image = null,
        array $additional_data = []
    ) {
        $firebase_notification_service = app(
            FirebaseNotificationService::class
        );

        return $firebase_notification_service->push_admin_notification(
            $title,
            $body,
            $image,
            $additional_data
        );
    }
}
