<?php

namespace App\Observers;

use App\Models\ConnectionRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Kreait\Firebase\Factory;

class ConnectionRequestObserver implements ShouldQueue
{
    /**
     * Handle the ConnectionRequest "created" event.
     */
    public function created(ConnectionRequest $req): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $db->runTransaction(function ($trx) use ($db, $req) {
            $sender_ref = $db->collection('users')
                ->document($req->sender->uid)
                ->collection('connection_requests')
                ->document($req->id);

            $receiver_ref = $db->collection('users')
                ->document($req->receiver->uid)
                ->collection('connection_requests')
                ->document($req->id);

            $sender_data = [
                'id' => $req->id,
                'status' => $req->status,
                'created_at' => $req->created_at,
                'user_uid' => $req->receiver->uid,
                'chat_id' => $req->chat_id,
            ];

            $receiver_data = [
                'id' => $req->id,
                'status' => $req->status,
                'created_at' => $req->created_at,
                'user_uid' => $req->sender->uid,
                'chat_id' => $req->chat_id,
            ];

            $trx->set($sender_ref, $sender_data);
            $trx->set($receiver_ref, $receiver_data);
        });

    }

    /**
     * Handle the ConnectionRequest "updated" event.
     */
    public function updated(ConnectionRequest $req): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $db->runTransaction(function ($trx) use ($db, $req) {
            $sender_ref = $db->collection('users')
                ->document($req->sender->uid)
                ->collection('connection_requests')
                ->document($req->id);

            $receiver_ref = $db->collection('users')
                ->document($req->receiver->uid)
                ->collection('connection_requests')
                ->document($req->id);

            $trx->set(
                $sender_ref,
                ['status' => $req->status],
                ['merge' => true]
            );

            $trx->set(
                $receiver_ref,
                ['status' => $req->status],
                ['merge' => true]
            );
        });
    }

    /**
     * Handle the ConnectionRequest "deleted" event.
     */
    public function deleted(ConnectionRequest $req): void
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $db->runTransaction(function ($trx) use ($db, $req) {
            $sender_ref = $db->collection('users')
                ->document($req->sender->uid)
                ->collection('connection_requests')
                ->document($req->id);

            $receiver_ref = $db->collection('users')
                ->document($req->receiver->uid)
                ->collection('connection_requests')
                ->document($req->id);

            $trx->delete($sender_ref);
            $trx->delete($receiver_ref);
        });
    }

    /**
     * Handle the ConnectionRequest "restored" event.
     */
    public function restored(ConnectionRequest $req): void
    {
        //
    }

    /**
     * Handle the ConnectionRequest "force deleted" event.
     */
    public function forceDeleted(ConnectionRequest $req): void
    {
        //
    }
}
