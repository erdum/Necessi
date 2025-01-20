<?php

namespace App\Services\Admin;

use App\Models\Notification;

class Notifications
{
    public static function get()
    {
        $notifications = Notification::whereNull('user_id')->paginate();

        return $notifications;
    }

    public static function push_admin_notification()
    {}
}
