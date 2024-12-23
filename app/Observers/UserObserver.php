<?php

namespace App\Observers;

use App\Models\User;
use App\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Kreait\Firebase\Factory;

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
            'chat_unread' => 0,
            'is_online' => true,
            'has_active_stripe_connect' => false,
            'has_active_bank' => false,
            'has_active_card' => false,
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
        $stripe_service = app(StripeService::class);

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
            'has_active_stripe_connect' => $stripe_service->is_account_active($user),
            'has_active_bank' => $user->banks->count() > 0,
            'has_active_card' => $user->cards->count() > 0,
        ];

        $db->collection('users')->document($user->uid)->set(
            $data,
            ['merge' => true]
        );
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

        $ref = $db->collection('users')->document($user->uid);

        foreach ($ref->collection('notifications')->listDocuments() as $doc) {
            $doc->delete();
        }

        foreach (
            $ref->collection('connection_requests')->listDocuments() as $doc
        ) {
            $doc->delete();
        }

        $ref->delete();
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
