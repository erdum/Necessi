<?php

namespace App\Services;

use App\Jobs\StoreImages;
use App\Models\User;
use App\Models\Review;
use Illuminate\Http\UploadedFile;
use Kreait\Firebase\Factory;
use Carbon\Carbon;

class UserService
{
    protected $db;

    protected $auth;

    protected $post_service;

    public function __construct(
        Factory $factory,
        PostService $post_service,
    ) {
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $this->db = $firebase->createFirestore()->database();
        $this->auth = $firebase->createAuth();
        $this->post_service = $post_service;
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
        ?string $age,
        ?UploadedFile $avatar,
        ?string $phone_number,
        ?float $lat,
        ?float $long
    ) {
        $user->about = $about ?? $user->about ?? null;
        $user->age = $age ?? $user->age ?? null;
        $user->phone_number = $phone_number ?? $user->phone_number ?? null;
        $user->lat = $lat ?? $user->lat ?? null;
        $user->long = $long ?? $user->long ?? null;

        if(!$avatar){
            $user->avatar = $avatar ?? $user->avatar ?? null;
        }

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

        return $user->only([
            'id',
            'first_name',
            'last_name',
            'about',
            'age',
            'avatar',
            'phone_number',
            'lat',
            'long',
        ]);
    }

    public function get_profile(User $user)
    {
        $user = User::where('id', $user->id)->first();

        $post_ids = $user->posts->pluck('id');
        $post_reviews = Review::whereIn('post_id', $post_ids)->get();
        $average_rating = round($post_reviews->avg('rating'), 1);
        $recent_post = $user->posts()->latest()->first();
        $reviews = [];
        $connections = [];
        $distance = null;


        if (!is_null($user->lat) && !is_null($user->long))
        {
            if($recent_post)
            {
                $calculatedDistance = $this->post_service->calculateDistance(
                    $user->lat,
                    $user->long,
                    $recent_post->lat,
                    $recent_post->long,
                );
    
                $distance = round($calculatedDistance, 2) . ' miles away';
            }
        }
        
        foreach($user->connections->take(3) as $connection)
        {
            $user_connection = User::find($connection->id);
            $connections[] = [
                'id' => $connection->id,
                'user_name' => $user_connection->first_name . ' ' . $user_connection->last_name,
                'avatar' => $user_connection->avatar,
            ];
        }

        foreach($post_reviews->take(3) as $review){
            $users = User::find($review->user_id);
            $reviews[] = [
                'user_id' => $users->id,
                'user_name' => $users->first_name . ' ' . $users->last_name,
                'avatar' => $users->avatar,
                'post_id' => $review->post_id,
                'rating' => $review->rating,
                'description' => $review->data,
                'created_at' => $review->created_at->diffForHumans(),
            ];
        }
        
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'uid' => $user->uid,
            'email' => $user->email,
            'email_verified_at'=> $user->email_verified_at,
            'phone_number' => $user->phone_number,
            'avatar' => $user->avatar,
            'rating' => $average_rating,
            'age' => $user->age,
            'about' => $user->about,
            'city' => $user->city,
            'state' => $user->state,
            'location' => $user->city,
            'connection_count' => $user->connections->count(),
            'connections' => $connections,
            'recent_post' => $recent_post ? [[
                'id' => $recent_post->id,
                'user_id' => $recent_post->user_id,
                'type' => $recent_post->type,
                'title' => $recent_post->title,
                'description' => $recent_post->description,
                'location' => $recent_post->location,
                'distance' => $distance,
                'budget' => $recent_post->budget,
                'duration' => Carbon::parse($recent_post->start_date)->format('d M') . ' - ' .
                              Carbon::parse($recent_post->end_date)->format('d M y'),
                'created_at' => $recent_post->created_at->diffForHumans(),
                'bids' => $recent_post->bids->count(),
                'likes' => $recent_post->likes->count(),
            ]] : [],
            'reviews' => $reviews,
        ];
    }

    public function set_location(
        User $user,
        float $lat,
        float $long,
        string $location,
        string $city,
        string $state,
    ) {
        $user->lat = $lat;
        $user->long = $long;
        $user->location = $location;
        $user->city = $city;
        $user->state = $state;

        $user->save();

        return $user->only([
            'id',
            'uid',
            'first_name',
            'last_name',
            'email',
            'age',
            'about',
            'avatar',
            'phone_number',
            'lat',
            'long',
            'location',
            'city',
            'state',
        ]);
    }

    public function connect_users_mutually(int $user1_id, int $user2_id)
    {
        $user1 = User::findOrFail($user1_id);
        $user2 = User::findOrFail($user2_id);

        $user1->connections()->syncWithoutDetaching([$user2->id]);
        $user2->connections()->syncWithoutDetaching([$user1->id]);
    }

    public function are_connected(User $user1_id, User $user2_id)
    {
        $user1 = User::findOrFail($user1_id);

        return $user1->connections()->where(
            'connection_id',
            $user2_id
        )->exists();
    }

    public function get_nearby_users(User $current_user)
    {
        $users = User::select(
            'id',
            'first_name',
            'last_name',
            'uid',
            'email',
            'phone_number',
            'avatar',
            'age',
            'about',
            'lat',
            'long',
            'location'
        )->whereNot('id', $current_user->id)
         ->where('city', $current_user->city)
         ->where('state', $current_user->state) 
         ->get();
    
        $nearby_users = [];
    
        foreach ($users as $user) {
            $nearby_users[] = $user;
            if (count($nearby_users) >= 9) {
                return $nearby_users; 
            }
        }
    
        if (count($nearby_users) < 9) {
            $remaining_users = User::select(
                'id',
                'first_name',
                'last_name',
                'uid',
                'email',
                'phone_number',
                'avatar',
                'age',
                'about',
                'lat',
                'long',
                'location'
            )->whereNot('id', $current_user->id
            )->where('city', '!=', $current_user->city
            )->orWhere('state', '!=', $current_user->state)->get();
    
            foreach ($remaining_users as $user) {
                $distance = $this->haversineDistance(
                    $current_user->lat,
                    $current_user->long,
                    $user->lat,
                    $user->long
                );
    
                if ($distance <= 50) {
                    $nearby_users[] = $user;
                }
    
                if (count($nearby_users) >= 9) {
                    break;
                }
            }
        }
    
        return $nearby_users; 
    }
    

    private function haversineDistance(
        float $lat1,
        float $long1,
        float $lat2,
        float $long2
    ) {
        $earth_radius = 6371;

        $lat1 = deg2rad($lat1);
        $long1 = deg2rad($long1);
        $lat2 = deg2rad($lat2);
        $long2 = deg2rad($long2);

        // Haversine formula
        $d_lat = $lat2 - $lat1;
        $d_long = $long2 - $long1;

        $a = sin($d_lat / 2) * sin($d_lat / 2) + cos($lat1) * cos($lat2) * sin($d_long / 2) * sin($d_long / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earth_radius * $c;

        return $distance;
    }

    public function make_connections(User $user, array $user_ids)
    {
        foreach ($user_ids as $id) {
            $this->connect_users_mutually(
                $user->id,
                $id
            );
        }

        return ['message' => 'Connections successfully created'];
    }

    public function get_connections(User $user)
    {
        return $user->connections()->select(
            'users.id',
            'users.first_name',
            'users.last_name',
            'users.uid',
            'users.email',
            'users.phone_number',
            'users.avatar',
            'users.age',
            'users.about',
            'users.city',
            'users.state',
            'users.lat',
            'users.long',
        )->get();
    }
}
