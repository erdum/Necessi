<?php

namespace App\Observers;

use App\Models\UserBank;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserBankObserver implements ShouldQueue
{
    /**
     * Handle the UserBank "created" event.
     */
    public function created(UserBank $userBank): void
    {
        // $stripe_service = app(\App\Services\StripeService::class);
        // $stripe_service->add_bank($userBank->user, $userBank->id);
    }

    /**
     * Handle the UserBank "updated" event.
     */
    public function updated(UserBank $userBank): void
    {
        //
    }

    /**
     * Handle the UserBank "deleted" event.
     */
    public function deleted(UserBank $userBank): void
    {
        $stripe_service = app(\App\Services\StripeService::class);
        $stripe_service->detach_bank($userBank->user, $userBank->id);
    }

    /**
     * Handle the UserBank "restored" event.
     */
    public function restored(UserBank $userBank): void
    {
        //
    }

    /**
     * Handle the UserBank "force deleted" event.
     */
    public function forceDeleted(UserBank $userBank): void
    {
        //
    }
}
