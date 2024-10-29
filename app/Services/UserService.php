<?php

namespace App\Services;

use App\Exceptions;
use App\Jobs\StoreImages;
use App\Models\ConnectionRequest;
use App\Models\PostLike;
use App\Models\Review;
use App\Models\User;
use App\Models\UserPreference;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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

    public function update_preferences(
        User $user,
        ?bool $general_notification,
        ?bool $biding_notification,
        ?bool $transaction_notification,
        ?bool $activity_notification,
        ?bool $receive_message_notification,
        ?string $who_can_see_connection,
    ) {

        if (! $user->preferences) {
            $preferences = new UserPreference;
            $preferences->user_id = $user->id;
            $preferences->general_notifications = $general_notification
            ?? true;
            $preferences->biding_notifications = $biding_notification
            ?? true;
            $preferences->transaction_notifications =
            $transaction_notification
            ?? true;
            $preferences->activity_notifications = $activity_notification
            ?? true;
            $preferences->messages_notifications = $receive_message_notification
            ?? true;
            $preferences->who_can_see_connections = $who_can_see_connection
                ?? 'public';

            $user->preferences()->save($preferences);

            return $preferences;
        }

        $user->preferences->general_notifications = $general_notification
            ?? $user->preferences->general_notifications;
        $user->preferences->biding_notifications = $biding_notification
            ?? $user->preferences->biding_notifications;
        $user->preferences->transaction_notifications =
            $transaction_notification
            ?? $user->preferences->transaction_notifications;
        $user->preferences->activity_notifications = $activity_notification
            ?? $user->preferences->activity_notifications;
        $user->preferences->messages_notifications = $receive_message_notification
            ?? $user->preferences->messages_notifications;
        $user->preferences->who_can_see_connections = $who_can_see_connection
            ?? $user->preferences->who_can_see_connections;

        $user->preferences->save();

        return $user->preferences;
    }

    public function get_profile(User $user)
    {
        $current_user = \Auth::user();

        $post_ids = $user->posts->pluck('id');
        $post_reviews = Review::where('user_id', $user->id)->with('user')->get();
        $average_rating = round($post_reviews->avg('rating'), 1);
        $recent_post = $user->posts()->latest()->first();
        $current_user_like = PostLike::where('user_id', $user->id)
            ->where('post_id', $recent_post->id)->exists();
        $connection_request = ConnectionRequest::where('sender_id', $current_user->id)
            ->where('receiver_id', $user->id)->first();
        $is_connection = $current_user->connections()->where(
            'connection_id', $user->id)->exists();
        $reviews = [];
        $connections = [];
        $distance = null;
        $connection_request_status = 'not send';

        if ($connection_request) {
            $connection_request_status = $connection_request->status;
        }

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
            $reviews[] = [
                'user_id' => $review->user->id,
                'user_name' => $review->user->first_name.' '.$review->user->last_name,
                'avatar' => $review->user->avatar,
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
            'connection_request_status' => $connection_request_status,
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

    public function accept_connection_request(User $user, int $user_id)
    {
        $connection_request = ConnectionRequest::where('receiver_id', $user->id)
            ->where('sender_id', $user_id)->first();

        if (! $connection_request) {
            throw new Exceptions\ConnectionRequestNotFound;
        }

        $connection_request->status = 'accepted';
        $connection_request->save();

        return ['message' => 'Connections successfully created'];
    }

    public function decline_connection_request(User $current_user, int $user_id)
    {
        $connection_request = ConnectionRequest::where('receiver_id', $user->id)
            ->where('sender_id', $user_id)->first();

        if (! $connection_request) {
            throw new Exceptions\ConnectionRequestNotFound;
        }

        $connection_request->status = 'rejected';
        $connection_request->save();

        return [
            'message' => 'Connection Decline successfully',
        ];
    }

    public function remove_connection(User $user, int $user_id)
    {
        $connection = ConnectionRequest::where([
            ['sender_id', '=', $user->id],
            ['receiver_id', '=', $user_id],
        ])
            ->orWhere([
                ['sender_id', '=', $user_id],
                ['receiver_id', '=', $user->id],
            ])
            ->where('status', 'accepted')
            ->first();

        if (! $connection) {
            throw new Exceptions\UserNotConnected;
        }

        $connection->delete();

        return [
            'message' => 'user Disconnected Successfully',
        ];
    }

    public function get_connections(User $user)
    {
        $connections = ConnectionRequest::where('receiver_id', $user->id)
            ->orWhere('sender_id', $user->id)
            ->where('status', 'accepted')
            ->with([
                'sender',
                'receiver',
            ])
            ->get();

        $connection_list = [];

        foreach ($connections as $con) {
            $sender_user = $con->sender['id'] == $user->id
                ? $con->receiver : $con->sender;

            $connection_list[] = [
                'id' => $con->id,
                'status' => $con->status,
                'created_at' => $con->created_at,
                'user' => $sender_user,
            ];
        }

        return $connection_list;
    }

    private function send_request(int $sender_id, int $receiver_id)
    {
        $existing_request = ConnectionRequest::where('sender_id', $sender_id)
            ->where('receiver_id', $receiver_id)->first();

        if ($existing_request) {
            return [
                'message' => 'Connection request already sent!',
            ];
        }

        $connection_request = new ConnectionRequest;
        $connection_request->sender_id = $sender_id;
        $connection_request->receiver_id = $receiver_id;
        $connection_request->save();
    }

    public function send_connection_requests(User $user, array $user_ids)
    {
        foreach ($user_ids as $id) {
            $response = $this->send_request(
                $user->id,
                $id
            );
        }

        return [
            'message' => 'Connection request sent successfully!',
        ];
    }

    public function cancel_connection_request(User $user, int $user_id)
    {
        $connection_request = ConnectionRequest::where('receiver_id', $user_id)
            ->where('sender_id', $user->id)->first();

        if (! $connection_request) {
            throw new Exceptions\ConnectionRequestNotFound;
        }

        $connection_request->delete();

        return [
            'message' => 'Canceled Connection request',
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
        $connection_requests = ConnectionRequest::where(
            'receiver_id',
            $user->id
        )
            ->whereNot('status', 'rejected')
            ->with('sender:id,first_name,last_name,avatar')
            ->get();

        $requests = [];

        foreach ($connection_requests as $req) {
            $requests[] = [
                'user_id' => $req->sender->id,
                'user_name' => $req->sender->first_name.' '.$req->sender->last_name,
                'avatar' => $req->sender->avatar,
                'status' => $req->status,
                'request_id' => $req->id,
            ];
        }

        return $requests;
    }

    public function update_password(
        User $user,
        string $old_password,
        string $new_password
    ) {
        if (! $user) {
            throw new Exceptions\UserNotFound;
        }

        if (! Hash::check($old_password, $user->password)) {
            throw new Exceptions\WrongPassword;
        }

        if (Hash::check($new_password, $user->password)) {
            throw new Exceptions\SameAsOldPassword;
        }

        $user->password = Hash::make($new_password);
        $user->save();

        return [
            'message' => 'Password Update Succesfully',
        ];
    }
}
