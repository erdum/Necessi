<?php

namespace App\Observers;

use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\UserCard;

class UserCardObserver implements ShouldQueue
{
    /**
     * Handle the UserCard "created" event.
     */
    public function created(UserCard $userCard): void
    {
        // $stripe_service = app(App\Services\StripeService::class);
        // $stripe_service->add_card($userCard->user, $userCard->id);
    }

    /**
     * Handle the UserCard "updated" event.
     */
    public function updated(UserCard $userCard): void
    {
        //
    }

    /**
     * Handle the UserCard "deleted" event.
     */
    public function deleted(UserCard $userCard): void
    {
        $stripe_service = app(App\Services\StripeService::class);
        $stripe_service->detach_bank($userCard->user, $userCard->id);
    }

    /**
     * Handle the UserCard "restored" event.
     */
    public function restored(UserCard $userCard): void
    {
        //
    }

    /**
     * Handle the UserCard "force deleted" event.
     */
    public function forceDeleted(UserCard $userCard): void
    {
        //
    }
}
