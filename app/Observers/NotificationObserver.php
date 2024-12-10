<?php

namespace App\Observers;

use App\Models\ConnectionRequest;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Kreait\Firebase\Factory;

class NotificationObserver implements ShouldQueue
{
    /**
     * Handle the Notification "created" event.
     */
    public function created(Notification $notification): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $connection_request = ConnectionRequest::withTrashed()->find(
            $notification->additional_data['connection_request_id'] ?? 0
        );

        $is_request_accepted = $connection_request?->status == 'accepted';
        $is_request_rejected = $connection_request?->status == 'rejected';
        $is_connection_request = (! ($is_request_accepted || $is_request_rejected)) && str_contains(
            $notification?->body,
            'has sent you a connection request'
        );

        $data = [
            'title' => $notification->title,
            'body' => $notification->body,
            'image' => $notification->image,
            'created_at' => $notification->created_at,
            'is_connection_request' => $is_connection_request,
            'is_connection_request_accepted' => $is_request_accepted,
            'is_connection_request_rejected' => $is_request_rejected,
            'sender_id' => $notification->additional_data['sender_id'] ?? null,
            'is_read' => false,
        ];

        $db->collection('users')->document($notification->user->uid)
            ->collection('notifications')->document($notification->id)
            ->set($data);
    }

    /**
     * Handle the Notification "updated" event.
     */
    public function updated(Notification $notification): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $connection_request = ConnectionRequest::withTrashed()->find(
            $notification->additional_data['connection_request_id'] ?? 0
        );

        $is_request_accepted = $connection_request?->status == 'accepted';
        $is_request_rejected = $connection_request?->status == 'rejected';
        $is_connection_request = (! ($is_request_accepted || $is_request_rejected)) && str_contains(
            $notification?->body,
            'has sent you a connection request'
        );

        $data = [
            'title' => $notification->title,
            'body' => $notification->body,
            'image' => $notification->image,
            'created_at' => $notification->created_at,
            'is_connection_request' => $is_connection_request,
            'is_connection_request_accepted' => $is_request_accepted,
            'is_connection_request_rejected' => $is_request_rejected,
            'sender_id' => $notification->additional_data['sender_id'] ?? null,
        ];

        $db->collection('users')->document($notification->user->uid)
            ->collection('notifications')->document($notification->id)
            ->set($data);
    }

    /**
     * Handle the Notification "deleted" event.
     */
    public function deleted(Notification $notification): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $db->collection('users')->document($notification->user->uid)
            ->collection('notifications')->document($notification->id)
            ->delete();
    }

    /**
     * Handle the Notification "restored" event.
     */
    public function restored(Notification $notification): void
    {
        //
    }

    /**
     * Handle the Notification "force deleted" event.
     */
    public function forceDeleted(Notification $notification): void
    {
        //
    }
}
