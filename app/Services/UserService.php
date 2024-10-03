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
            .config("firebase.projects.app.credentials")
        );
        $this->db = $firebase->createFirestore()->database();
        $this->auth = $firebase->createAuth();
    }

    public function update_firestore_profile(User $user)
    {
        $user_ref = $this->db->collection("users")->document($user->uid);

        $user_ref->set([
            "first_name" => $user->first_name,
            "uid" => $user->uid,
            "last_name" => $user->last_name,
            "email" => $user->email,
            "email_verified_at" => $user->email_verified_at,
            "phone_number" => $user->phone_number,
            "is_online" => true,
        ]);
    }

    public function update_profile(
        User $user,
        ?string $about,
        ?string $gender,
        ?string $age,
        ?UploadedFile $avatar,
        ?string $phone_number,
    ) {
        $user->about = $about ?? $user->about ?? null;
        $user->gender = $gender ?? $user->gender ?? null;
        $user->age = $age ?? $user->age ?? null;
        $user->phone_number = $phone_number ?? $user->phone_number ?? null;

        if ($avatar) {
            $avatar_name = str()->random(15);
            $user->avatar = "avatars/$avatar_name.webp";

            StoreImages::dispatchAfterResponse(
                $avatar->path(),
                "avatars",
                $avatar_name
            );
        }
        $user->save();

        return $user->only([
            "id",
            "first_name",
            "last_name",
            "about",
            "gender",
            "age",
            "uid",
            "avatar",
            "phone_number",
        ]);
    }

    public function get_profile(string $user_uid)
    {
        $user = User::select([
            "id",
            "uid",
            "first_name",
            "last_name",
            "email",
            "gender",
            "age",
            "about",
            "avatar",
            "phone_number",
            "lat",
            "long",
            "location",
        ])->where("uid", $user_uid)->first();

        return $user;
    }

    public function set_location(
        User $user, 
        float $lat, 
        float $long,
        string $location
    ) {
        $user->lat = $lat;
        $user->long = $long;
        $user->location = $location;
        $user->save();

        return $user->only([
            "id",
            "uid",
            "first_name",
            "last_name",
            "email",
            "gender",
            "age",
            "about",
            "avatar",
            "phone_number",
            "lat",
            "long",
            "location",
        ]);
    }

    public function connect_users_mutually($user1_id, $user2_id)
    {
        $user1 = User::findOrFail($user1_id);
        $user2 = User::findOrFail($user2_id);

        $user1->connections()->syncWithoutDetaching([$user2->id]);
        $user2->connections()->syncWithoutDetaching([$user1->id]);
    }

    public function are_connected($user1_id, $user2_id)
    {
        $user1 = User::findOrFail($user1_id);

        return $user1->connections()->where(
            'connection_id',
            $user2_id
        )->exists();
    }
}
