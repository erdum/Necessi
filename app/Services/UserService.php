<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Factory;
use App\Jobs\StoreImages;
use Illuminate\Http\UploadedFile;

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

    public function update_profile(
        User $user,
        ?string $about,
        string $gender,
        string $age,
        ?UploadedFile $avatar
    ) {
        $user->about = $about ?? null;
        $user->gender = $gender;
        $user->age = $age;

        if ($avatar) {
            $avatar_name = str()->random(15);
            $user->avatar = "avatars/$avatar_name.webp";

            StoreImages::dispatchAfterResponse(
                $avatar->path(),
                'avatars',
                $avatar_name
            );
        }

        $user->save();
        // UpdateFirestoreProfile::dispatch($user);

        return $user->only([
            'id',
            'first_name',
            'last_name',
            'about',
            'gender',
            'age',
            'uid',
            'avatar',
            'phone_number',
        ]);
    }

    public function get_profile(string $user_uid)
    {
        $user = User::select([
            'id',
            'uid',
            'first_name',
            'last_name',
            'email',
            'gender',
            'age',
            'about',
            'avatar',
            'phone_number',
            'lat',
            'long',
            'location',
        ])->where('uid', $user_uid)->first();

        return $user;
    }
}
