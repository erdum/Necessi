<?php

namespace App\Observers;

use App\Models\UserPreference;

class UserPreferenceObserver
{
    /**
     * Handle the UserPreference "created" event.
     */
    public function created(UserPreference $user_preference): void
    {
        $db = app('firebase')->createFirestore()->database();

        $data = [
            'chat_status' => $user_preference->user->preferences->who_can_send_messages,
        ];

        $db->collection('users')->document($user_preference?->user->uid)->set(
            $data,
            ['merge' => true]
        );

        $user_preference->user->fire_updated_observer();
    }

    /**
     * Handle the UserPreference "updated" event.
     */
    public function updated(UserPreference $user_preference): void
    {
        $db = app('firebase')->createFirestore()->database();

        $data = [
            'chat_status' => $user_preference->user->preferences?->who_can_send_messages,
        ];

        $db->collection('users')->document($user_preference->user->uid)->set(
            $data,
            ['merge' => true]
        );

        $user_preference->user->fire_updated_observer();
    }

    /**
     * Handle the UserPreference "deleted" event.
     */
    public function deleted(UserPreference $user_preference): void
    {
        //
    }

    /**
     * Handle the UserPreference "restored" event.
     */
    public function restored(UserPreference $user_preference): void
    {
        //
    }

    /**
     * Handle the UserPreference "force deleted" event.
     */
    public function forceDeleted(UserPreference $user_preference): void
    {
        //
    }
}
