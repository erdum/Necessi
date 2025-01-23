<?php

namespace App\Observers;

use App\Models\UserNotificationDevice;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserNotificationDeviceObserver implements ShouldQueue
{
    /**
     * Handle the UserNotificationDevice "created" event.
     */
    public function created(UserNotificationDevice $userNotificationDevice): void
    {
        $db = app('firebase')->createFirestore()->database();

        $db->collection('users')->document($userNotificationDevice->user->uid)
            ->set(
                ['fcm_token' => $userNotificationDevice->fcm_token],
                ['merge' => true]
            );
    }

    /**
     * Handle the UserNotificationDevice "updated" event.
     */
    public function updated(UserNotificationDevice $userNotificationDevice): void
    {
        $db = app('firebase')->createFirestore()->database();

        $db->collection('users')->document($userNotificationDevice->user->uid)
            ->set(
                ['fcm_token' => $userNotificationDevice->fcm_token],
                ['merge' => true]
            );
    }

    /**
     * Handle the UserNotificationDevice "deleted" event.
     */
    public function deleted(UserNotificationDevice $userNotificationDevice): void
    {
        $db = app('firebase')->createFirestore()->database();

        $db->collection('users')->document($userNotificationDevice->user->uid)
            ->set(
                ['fcm_token' => null],
                ['merge' => true]
            );
    }

    /**
     * Handle the UserNotificationDevice "restored" event.
     */
    public function restored(UserNotificationDevice $userNotificationDevice): void
    {
        //
    }

    /**
     * Handle the UserNotificationDevice "force deleted" event.
     */
    public function forceDeleted(UserNotificationDevice $userNotificationDevice): void
    {
        //
    }
}
