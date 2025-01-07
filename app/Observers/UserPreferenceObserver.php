<?php

namespace App\Observers;

use App\Models\UserPreference;
use Kreait\Firebase\Factory;

class UserPreferenceObserver
{
    /**
     * Handle the UserPreference "created" event.
     */
    public function created(UserPreference $userPreference): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $data = [
            'chat_status' => $userPreference->user->preferences->who_can_send_messages,
        ];

        $db->collection('users')->document($userPreference?->user->uid)->set(
            $data,
            ['merge' => true]
        );
    }

    /**
     * Handle the UserPreference "updated" event.
     */
    public function updated(UserPreference $userPreference): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $data = [
            'chat_status' => $userPreference->user->preferences?->who_can_send_messages,
        ];

        $db->collection('users')->document($userPreference->user->uid)->set(
            $data,
            ['merge' => true]
        );
    }

    /**
     * Handle the UserPreference "deleted" event.
     */
    public function deleted(UserPreference $userPreference): void
    {
        //
    }

    /**
     * Handle the UserPreference "restored" event.
     */
    public function restored(UserPreference $userPreference): void
    {
        //
    }

    /**
     * Handle the UserPreference "force deleted" event.
     */
    public function forceDeleted(UserPreference $userPreference): void
    {
        //
    }
}
