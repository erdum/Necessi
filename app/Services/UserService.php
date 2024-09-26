<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use Carbon\Carbon;
use Kreait\Firebase\Factory;

class UserService
{
    protected $db;

    protected $auth;


    public function __construct(
        Factory $factory,
    ) {
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $this->db = $firebase->createFirestore()->database();
        $this->auth = $firebase->createAuth();
    }

    public function update_firestore_profile(User $user)
    {
        $user_ref = $this->db->collection('users')->document($user->uid);
        
        $user_ref->set([
            'first_name' => $user->first_name,
            'uid' => $user->uid,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'phone_number' => $user->phone_number,
            'is_online' => true,
        ]);
    }

}