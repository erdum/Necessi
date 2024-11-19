<?php

namespace App\Observers;

use App\Models\User;
use Kreait\Firebase\Factory;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserObserver implements ShouldQueue
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $data = [
            'id' => $user->id,
            'uid' => $user->uid,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar' => $user->avatar,
            'age' => $user->age,
            'about' => $user->about,
        ];

        $db->collection('users')->document($user->uid)->set($data);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $data = [
            'id' => $user->id,
            'uid' => $user->uid,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar' => $user->avatar,
            'age' => $user->age,
            'about' => $user->about,
        ];

        $db->collection('users')->document($user->uid)->set($data);
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $db->collection('users')->document($user->uid)->delete();
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
