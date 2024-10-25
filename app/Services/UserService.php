<?php

namespace App\Services;

use App\Exceptions;
use App\Jobs\StoreImages;
use App\Models\ConnectionRequest;
use App\Models\Review;
use App\Models\User;
use App\Models\PostLike;
use App\Models\UserPreference;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Kreait\Firebase\Factory;

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

        if (! $avatar) {
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
        $current_user = \Auth::user();

        $post_ids = $user->posts->pluck('id');
        $post_reviews = Review::whereIn('post_id', $post_ids)->get();
        $average_rating = round($post_reviews->avg('rating'), 1);
        $recent_post = $user->posts()->latest()->first();
        $current_user_like = PostLike::where('user_id', $user->id)
                          ->where('post_id', $recent_post->id)->exists();
        $is_connection = $current_user->connections()->where(
            'connection_id', $user->id)->exists();
        $reviews = [];
        $connections = [];
        $distance = null;

        if (! is_null($user->lat) && ! is_null($user->long)) {
            if ($recent_post) {
                $calculatedDistance = $this->post_service->calculateDistance(
                    $user->lat,
                    $user->long,
                    $recent_post->lat,
                    $recent_post->long,
                );

                $distance = round($calculatedDistance, 2).' miles away';
            }
        }

        foreach ($user->connections->take(3) as $connection) {
            $user_connection = User::find($connection->id);
            $connections[] = [
                'id' => $connection->id,
                'user_name' => $user_connection->first_name.' '.$user_connection->last_name,
                'avatar' => $user_connection->avatar,
            ];
        }

        foreach ($post_reviews->take(3) as $review) {
            $users = User::find($review->user_id);
            $reviews[] = [
                'user_id' => $users->id,
                'user_name' => $users->first_name.' '.$users->last_name,
                'avatar' => $users->avatar,
                'post_id' => $review->post_id,
                'rating' => $review->rating,
                'description' => $review->data,
                'created_at' => $review->created_at->diffForHumans(),
            ];
        }

        return [
            'user_id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'uid' => $user->uid,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'phone_number' => $user->phone_number,
            'avatar' => $user->avatar,
            'rating' => $average_rating,
            'age' => $user->age,
            'about' => $user->about,
            'city' => $user->city,
            'state' => $user->state,
            'location' => $user->city,
            'lat' => $user->lat,
            'long' => $user->long,
            'is_connection' => $is_connection,
            'connection_count' => $user->connections->count(),
            'connections' => $connections,
            'recent_post' => $recent_post ? [[
                'post_id' => $recent_post->id,
                'user_id' => $recent_post->user_id,
                'type' => $recent_post->type,
                'title' => $recent_post->title,
                'description' => $recent_post->description,
                'location' => $recent_post->location,
                'distance' => $distance,
                'budget' => $recent_post->budget,
                'duration' => Carbon::parse($recent_post->start_date)->format('d M').' - '.
                              Carbon::parse($recent_post->end_date)->format('d M y'),
                'created_at' => $recent_post->created_at->diffForHumans(),
                'bids' => $recent_post->bids->count(),
                'current_user_like' => $current_user_like,
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

    public function make_connection(User $user, int $user_id)
    {
        $connection_request = ConnectionRequest::where('receiver_id', $user->id)
            ->where('sender_id', $user_id)->first();

        $this->connect_users_mutually(
            $user->id,
            $user_id
        );

        $connection_request->delete();

        return ['message' => 'Connections successfully created'];
    }

    public function request_decline(User $current_user, int $user_id)
    {
        $user = User::find($user_id);

        if(!$user){
            throw new Exceptions\UserNotFound;
        }

        $connection_request = ConnectionRequest::where(
            'sender_id', $user_id)->where('receiver_id', $current_user->id)->first();
        
        if(!$connection_request){
            throw new Exceptions\ConnectionRequestNotFound;
        }

        $connection_request->status = 'rejected';
        $connection_request->save();

        return [
            'message' => 'Connection Decline successfully',
        ];
    }

    public function user_remove(User $user, $user_id)
    {
        if (! $user->connections->contains('id', $user_id)) {
            throw new Exceptions\UserNotConnected;
        }

        $user1 = User::findOrFail($user->id);
        $user2 = User::findOrFail($user_id);

        $user1->connections()->detach($user2->id);
        $user2->connections()->detach($user1->id);

        return [
            'message' => 'user Disconnected Successfully',
        ];
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

    public function send_requests(int $user1_id, int $user2_id)
    {
        $existing_request = ConnectionRequest::where('sender_id', $user1_id)
            ->where('receiver_id', $user2_id)->first();

        if ($existing_request) {
            return [
                'message' => 'Connection request already sent!',
            ];
        }

        $connection_request = new ConnectionRequest;
        $connection_request->sender_id = $user1_id;
        $connection_request->receiver_id = $user2_id;
        $connection_request->save();
    }

    public function send_connection_request(User $user, array $user_ids)
    {
        foreach ($user_ids as $id) {
            $response = $this->send_requests(
                $user->id,
                $id
            );
        }

        return [
            'message' => 'Connection request sent successfully!',
        ];
    }

    public function store_fcm(string $fcm_token, User $user)
    {
        $user->notification_device()->updateOrCreate(
            ['user_id' => $user->id],
            ['fcm_token' => $fcm_token]
        );

        return ['message' => 'FCM token successfully stored'];
    }

    public function get_connection_requests(User $user)
    {
        $connection_requests = ConnectionRequest::where('receiver_id', $user->id)
                ->where('status', '!=', 'rejected')->get();
        $requests=[];

        foreach($connection_requests as $connection_request)
        {
            $user = User::find($connection_request->sender_id);
            $requests[] = [
                'user_id' => $user->id,
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'avatar' => $user->avatar,
                'status' => $connection_request->status,
                'request_id' => $connection_request->id
            ];
        }

        return $requests;
    }
}
