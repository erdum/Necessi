<?php

namespace App\Services;

use App\Exceptions;
use App\Jobs\StoreImages;
use App\Models\ConnectionRequest;
use App\Models\Notification;
use App\Models\PostLike;
use App\Models\Review;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\Otp;
use Carbon\Carbon;
use Google\Cloud\Firestore\FieldValue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Storage;

class UserService
{
    protected $post_service;

    protected $notification_service;

    protected $stripe_service;

    public function __construct(
        PostService $post_service,
        FirebaseNotificationService $notification_service,
        StripeService $stripe_service
    ) {
        $this->post_service = $post_service;
        $this->notification_service = $notification_service;
        $this->stripe_service = $stripe_service;
    }

    private function is_connected(User $current_user, User $target_user)
    {
        $is_connection = ConnectionRequest::where([
            ['sender_id', '=', $current_user->id],
            ['receiver_id', '=', $target_user->id],
        ])
            ->orWhere([
                ['sender_id', '=', $target_user->id],
                ['receiver_id', '=', $current_user->id],
            ])->first();

        if ($is_connection) {
            if ($is_connection->status == 'accepted') {
                return true;
            } else {
                return false;
            }
        }
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

    public function delete_user_account(User $user)
    {
        if ($user->avatar ?? false) {
            Storage::delete($user->avatar);
        }

        Otp::where('identifier', $user->email)->first()?->delete();

        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $chat_ids = $user->connections->pluck('chat_id')->toArray();
        $post_ids = $user->bids()->with('post')->get()->pluck('post.id')
            ->toArray();
        $notification_ids = $user->notifications->pluck('id')->toArray();

        $db->runTransaction(
            function ($trx) use (
                $db, $chat_ids, $post_ids, $notification_ids, $user
            ) {
                foreach ($chat_ids as $chat_id) {
                    $ref = $db->collection('chats')->document($chat_id);
                    $messages = $ref->collection('messages')->listDocuments();

                    foreach ($messages as $msg) {
                        $trx->delete($msg);
                    }

                    $trx->delete($ref);
                }

                foreach ($post_ids as $post_id) {
                    $ref = $db->collection('posts')->document($post_id)
                        ->collection('bids');
                    $bid_ref = $ref->document($user->uid);
                    $lowest_bid_ref = $ref->document('lowest_bid');

                    if (
                        $lowest_bid_ref->snapshot()
                            ->data()['bid_id'] == $user->uid
                    ) {
                        $lowest_bids = $ref->orderBy('amount')->limit(2)
                            ->documents()->rows();
                        $next_value = null;

                        foreach ($lowest_bids as $lowest) {
                            $id = $lowest->id();

                            if ($id != $user->uid) $next_value = $id;
                        }
                        $trx->update(
                            $lowest_bid_ref,
                            [['path' => 'bid_id', 'value' => $next_value]]
                        );
                    }

                    $trx->delete($bid_ref);
                }

                foreach ($notification_ids as $notification_id) {
                    $ref = $db->collection('notifications')
                        ->document($notification_id);

                    $trx->delete($ref);
                }
            }
        );

        // Stripe customer account
        // Stripe connect account
        // User chats
        // User posts
        // User likes
        // User comments
        // User bids
        // User notifications
        // User reviews
        // User orders
        // User blocks
        // User reports
        // User payment methods

        $user->delete();

        return ['message' => 'User successfully deleted'];
    }

    public function get_user_preferences(User $user)
    {
        $preferences = $user->preferences;

        return [
            'id' => $preferences->id,
            'user_id' => $preferences->user_id,
            'general_notifications' => (bool) $preferences->general_notifications,
            'biding_notifications' => (bool) $preferences->biding_notifications,
            'transaction_notifications' => (bool) $preferences->transaction_notifications,
            'activity_notifications' => (bool) $preferences->activity_notifications,
            'messages_notifications' => (bool) $preferences->messages_notifications,
            'who_can_see_connections' => $preferences->who_can_see_connections,
            'who_can_send_messages' => $preferences->who_can_send_messages,
        ];
    }

    public function update_preferences(
        User $user,
        ?bool $general_notification,
        ?bool $biding_notification,
        ?bool $transaction_notification,
        ?bool $activity_notification,
        ?bool $receive_message_notification,
        ?string $who_can_see_connection,
        ?string $who_can_send_message,
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
            $preferences->who_can_send_messages = $who_can_send_message
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
        $user->preferences->who_can_send_messages = $who_can_send_message
        ?? $user->preferences->who_can_send_messages;

        $user->preferences->save();

        return $user->preferences;
    }

    public function get_profile(User $user)
    {
        $current_user = auth()->user();
        $reviews = Review::whereHas(
            'post',
            function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }
        )
            ->with('user:id,first_name,last_name,avatar')
            ->get();

        $average_rating = round($reviews->avg('rating'), 1);
        $recent_post = $user->posts()->latest()->first();

        if ($recent_post) {
            $current_user_like = PostLike::where('user_id', $current_user->id)
                ->where('post_id', $recent_post->id)->exists();
        }

        $connection_request = ConnectionRequest::where([
            ['sender_id', '=', $user->id],
            ['receiver_id', '=', $current_user->id],
        ])
            ->orWhere([
                ['sender_id', '=', $current_user->id],
                ['receiver_id', '=', $user->id],
            ])->first();

        $reviews_data = [];
        $connections_data = [];
        $connection_count = 0;
        $distance = null;
        $connection_request_status = 'not send';

        $connection_visibility = $user->preferences?->who_can_see_connections;
        $is_own_profile = $user->id === $current_user->id;

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

        if ($is_own_profile || $connection_visibility === 'public' ||
           ($connection_visibility === 'connections' && $this->is_connected($current_user, $user))
        ) {
            $connections = ConnectionRequest::where(function ($query) use ($user) {
                $query->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })->where('status', 'accepted')
                ->limit(3)
                ->get();

            foreach ($connections as $connection) {
                $connected_user_id = $connection->sender_id == $user->id
                    ? $connection->receiver_id : $connection->sender_id;

                $user_connection = User::find($connected_user_id);

                if ($user_connection) {
                    $connections_data[] = [
                        'id' => $user_connection->id,
                        'user_name' => $user_connection->first_name.' '.$user_connection->last_name,
                        'avatar' => $user_connection->avatar,
                        'chat_id' => $user_connection->chat_id,
                    ];
                }
            }
            $connection_count = $connections->count();
        }

        foreach ($reviews as $review) {
            $reviews_data[] = [
                'post_id' => $review->post_id,
                'data' => $review->data,
                'rating' => $review->rating,
                'created_at' => $review->created_at->format('d M'),
                'user_id' => $review->user->id,
                'user_name' => $review->user->first_name.' '.$review->user->last_name,
                'avatar' => $review->user->avatar,
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
            'who_can_see_connection' => $connection_visibility,
            'is_connection' => $this->is_connected($current_user, $user) ? true : false,
            'connection_request_status' => $connection_request_status,
            'connection_count' => $connection_count,
            'connections' => $connections_data,
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
            'reviews' => $reviews_data,
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

    public function accept_connection_request(
        User $user,
        int $user_id,
    ) {
        $connection_request = ConnectionRequest::where('receiver_id', $user->id)
            ->where('sender_id', $user_id)->first();

        if (! $connection_request) {
            throw new Exceptions\ConnectionRequestNotFound;
        }

        $connection_request->status = 'accepted';

        $receiver_user = User::find($user_id);
        $type = 'accept_connection_request';
        $user_name = $user->first_name.' '.$user->last_name;

        $request_notification = Notification::whereJsonContains(
            'additional_data->connection_request_id',
            $connection_request->id
        )->first();

        if ($request_notification) {
            $request_notification->body = 'You and '.$request_notification->title.' are now connected';
            $request_notification->save();
        }

        if ($connection_request->chat_id == null) {
            $chat = $this->create_chat($user, $receiver_user->uid);
            $connection_request->chat_id = $chat['chat_id'];
        } else {
            $factory = app(Factory::class);
            $firebase = $factory->withServiceAccount(
                base_path()
                .DIRECTORY_SEPARATOR
                .config('firebase.projects.app.credentials')
            );
            $db = $firebase->createFirestore()->database();

            $ref = $db->collection('chats')->document(
                $connection_request->chat_id
            );

            $ref->update([['path' => 'connection_removed', 'value' => false]]);
        }
        $connection_request->save();

        $this->notification_service->push_notification(
            $receiver_user,
            NotificationType::ACTIVITY,
            $user_name,
            ' has accept your connection request',
            $user->avatar ?? '',
            [
                'user_name' => $user->first_name.' '.$user->last_name,
                'user_avatar' => $user->avatar,
                'description' => $user->about,
                'sender_id' => $user->id,
                'connection_request_id' => $connection_request->id,
            ]
        );

        return ['message' => 'Connections successfully created'];
    }

    public function decline_connection_request(User $current_user, int $user_id)
    {
        $connection_request = ConnectionRequest::where('receiver_id', $current_user->id)
            ->where('sender_id', $user_id)->first();

        if (! $connection_request) {
            throw new Exceptions\ConnectionRequestNotFound;
        }

        $connection_request->status = 'rejected';
        $connection_request->save();

        $request_notification = Notification::whereJsonContains(
            'additional_data->connection_request_id',
            $connection_request->id
        )->first();

        if ($request_notification) {
            $request_notification->body = 'Connection request has been canceled';
            $request_notification->save();
        }

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
            throw new Exceptions\BaseException(
                'The specified user is not in your connections.', 400
            );
        }

        $connection->delete();

        $this->remove_chat($connection->chat_id);

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

            $sender_user['chat_id'] = $con->chat_id;

            $connection_list[] = [
                'id' => $con->id,
                'status' => $con->status,
                'created_at' => $con->created_at,
                'user' => $sender_user,
            ];
        }

        return $connection_list;
    }

    private function send_request(
        int $sender_id,
        int $receiver_id,
    ) {
        $existing_request = ConnectionRequest::withTrashed()
            ->where([
                ['sender_id', '=', $sender_id],
                ['receiver_id', '=', $receiver_id],
            ])
            ->orWhere([
                ['sender_id', '=', $receiver_id],
                ['receiver_id', '=', $sender_id],
            ])
            ->first();

        if ($existing_request) {

            if ($existing_request->status == 'pending') {
                throw new Exceptions\BaseException(
                    'Connection request already sent!', 400
                );
            }

            if ($existing_request->status == 'accepted') {

                if ($existing_request->deleted_at == null) {
                    throw new Exceptions\BaseException(
                        'You are already connected this connection', 400
                    );
                } else {
                    $existing_request->restore();
                    $existing_request->status = 'rejected';
                    // It will be handled by the next check condition
                }
            }

            if ($existing_request->status == 'rejected') {
                $existing_request->status = 'pending';
                $existing_request->save();

                $receiver_user = User::find($receiver_id);
                $user = User::find($sender_id);
                $type = 'send_connection_request';
                $user_name = $user->first_name.' '.$user->last_name;

                $request_notification = Notification::whereJsonContains(
                    'additional_data->connection_request_id',
                    $existing_request->id
                )->delete();

                $this->notification_service->push_notification(
                    $receiver_user,
                    NotificationType::ACTIVITY,
                    $user_name,
                    ' has sent you a connection request',
                    $user->avatar ?? '',
                    [
                        'user_name' => $user->first_name.' '.$user->last_name,
                        'user_avatar' => $user->avatar,
                        'description' => $user->about,
                        'sender_id' => $user->id,
                        'connection_request_id' => $existing_request->id,
                        'is_connection_request' => true,
                    ]
                );

                return [
                    'message' => 'Connection request successfully sent',
                ];
            }
        }

        $connection_request = new ConnectionRequest;
        $connection_request->sender_id = $sender_id;
        $connection_request->receiver_id = $receiver_id;
        $connection_request->save();

        $receiver_user = User::find($receiver_id);
        $user = User::find($sender_id);
        $type = 'send_connection_request';
        $user_name = $user->first_name.' '.$user->last_name;

        $this->notification_service->push_notification(
            $receiver_user,
            NotificationType::ACTIVITY,
            $user_name,
            ' has sent you a connection request',
            $user->avatar ?? '',
            [
                'user_name' => $user->first_name.' '.$user->last_name,
                'user_avatar' => $user->avatar,
                'description' => $user->about,
                'sender_id' => $user->id,
                'connection_request_id' => $connection_request->id,
                'is_connection_request' => true,
            ]
        );

        return ['message' => 'Connection request successfully sent'];
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

        $connection_request->forceDelete();
        
        $request_notification = Notification::whereJsonContains(
            'additional_data->connection_request_id',
            $connection_request->id
        )->delete();

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

    public function get_notifications(User $user)
    {
        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $notifications->getCollection()->transform(
            function ($notif) {

                $connection_request = ConnectionRequest::withTrashed()->find(
                    $notif->additional_data['connection_request_id'] ?? 0
                );

                $is_request_accepted = $connection_request?->status == 'accepted';
                $is_request_rejected = $connection_request?->status == 'rejected';
                $is_connection_request = (! ($is_request_accepted || $is_request_rejected)) && str_contains(
                    $notif?->body,
                    'has sent you a connection request'
                );

                return [
                    'title' => $notif->title,
                    'body' => $notif->body,
                    'image' => $notif->image,
                    'created_at' => $notif->created_at,
                    'is_connection_request' => $is_connection_request,
                    'is_connection_request_accepted' => $is_request_accepted,
                    'is_connection_request_rejected' => $is_request_rejected,
                    'sender_id' => $notif->additional_data['sender_id'] ?? null,
                ];
            }
        );

        return $notifications;
    }

    public function clear_user_notifications(User $user)
    {
        Notification::where('user_id', $user->id)->delete();

        return ['message' => 'User notifications has successfully deleted'];
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
            throw new Exceptions\BaseException(
                'The current password you entered is incorrect. Please try again.', 400
            );
        }

        if (Hash::check($new_password, $user->password)) {
            throw new Exceptions\BaseException(
                'New password cannot be the same as the old password.', 400
            );
        }

        $user->password = Hash::make($new_password);
        $user->save();

        return [
            'message' => 'Password Update Succesfully',
        ];
    }

    public function create_chat(User $user, string $other_party_uid)
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();
        $chat_id = str()->random(20);

        $unseen_count = [];
        $unseen_count[$user->uid] = 0;
        $unseen_count[$other_party_uid] = 0;

        $data = [
            'blocked_by' => null,
            'created_at' => FieldValue::serverTimestamp(),
            'last_msg' => '',
            'members' => [
                $user->uid,
                $other_party_uid,
            ],
            'unseen_counts' => $unseen_count,
            'connection_removed' => false,
        ];

        $db->collection('chats')->document($chat_id)->set($data);

        return ['chat_id' => $chat_id];
    }

    public function remove_chat(string $chat_id)
    {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $ref = $db->collection('chats')->document($chat_id);

        if ($ref->snapshot()->exists()) {
            // $ref->delete();
            $ref->update([['path' => 'connection_removed', 'value' => true]]);
        }

        return ['message' => 'Chat successfully deleted'];
    }

    public function send_message_notificatfion(
        User $user,
        string $chat_id,
        string $receiver_uid
    ) {
        $receiver_user = User::where('uid', $receiver_uid)->first();
        $user_name = $user->first_name.' '.$user->last_name;

        if (! $receiver_user) return;

        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $chat_snap = $db->collection('chats')->document($chat_id)->snapshot();

        if (! $chat_snap->exists()) return;

        $chat_data = $chat_snap->data();

        if (! in_array($user->uid, $chat_data['members'])) {
            throw new Exceptions\AccessForbidden;
        }

        $this->notification_service->push_notification(
            $receiver_user,
            NotificationType::MESSAGE,
            $user_name,
            ' has sent you a message',
            $user->avatar ?? '',
            [
                'description' => $user->about,
                'sender_id' => $user->id,
                'connection_request_id' => null,
            ]
        );

        return ['message' => 'Message notification successfully sent'];
    }

    public function block_user(
        User $user,
        string $chat_id,
        string $reason_type,
        ?string $other_reason
    ) {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $chat_snap = $db->collection('chats')->document($chat_id)->snapshot();

        if (! $chat_snap->exists()) {
            throw new Exceptions\BaseException('Invalid chat id', 400);
        }
        $chat = $chat_snap->data();

        $other_uid = array_values(array_diff(
            $chat['members'],
            [$user->uid]
        ))[0];
        $other_user = User::where('uid', $other_uid)->first();

        if (! $other_user) {
            throw new Exceptions\UserNotFound;
        }

        if ($user->is_blocked($other_user->id)) {
            return [
                'message' => 'User is already blocked',
            ];
        } else {
            $user->blocked_users()->attach($other_user->id, [
                'reason_type' => $reason_type,
                'other_reason' => $other_reason ?: null,
            ]);

            $chat_snap->reference()->update([[
                'path' => 'blocked_by',
                'value' => $user->uid
            ]]);

            return [
                'message' => 'User successfully blocked',
            ];
        }
    }

    public function unblock_user(
        User $user,
        string $chat_id
    ) {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $chat_snap = $db->collection('chats')->document($chat_id)->snapshot();

        if (! $chat_snap->exists()) {
            throw new Exceptions\BaseException('Invalid chat id', 400);
        }
        $chat = $chat_snap->data();

        $other_uid = array_values(array_diff(
            $chat['members'],
            [$user->uid]
        ))[0];
        $other_user = User::where('uid', $other_uid)->first();

        if (! $other_user) {
            throw new Exceptions\UserNotFound;
        }

        if ($user->is_blocked($other_user->id)) {
            $user->blocked_users()->detach($other_user->id);

            $chat_snap->reference()->update([[
                'path' => 'blocked_by',
                'value' => null
            ]]);

            return [
                'message' => 'User successfully unblocked',
            ];
        } else {
            return [
                'message' => 'User is not blocked',
            ];
        }
    }

    public function get_blocked_users(User $user)
    {
        return $user->blocked_users->map(function ($blocked_user) use ($user) 
        {
            $chat_id = ConnectionRequest::withTrashed()
            ->where([
                ['sender_id', '=', $user->id],
                ['receiver_id', '=', $blocked_user->id],
            ])
            ->orWhere([
                ['sender_id', '=', $blocked_user->id],
                ['receiver_id', '=', $user->id],
            ])
            ->value('chat_id');

            return [
                'user_id' => $blocked_user->id,
                'first_name' => $blocked_user->first_name,
                'last_name' => $blocked_user->last_name,
                'avatar' => $blocked_user->avatar,
                'uid' => $blocked_user->uid,
                'email' => $blocked_user->email,
                'phone_number' => $blocked_user->phone_number,
                'location' => $blocked_user->location,
                'blocked_at' => $blocked_user->pivot->created_at->format('Y-m-d H:i:s'),
                'chat_id' => $chat_id,
            ];
        });
    }

    public function report_user(
        User $user,
        string $chat_id,
        string $reason_type,
        ?string $other_reason,
    ) {
        $factory = app(Factory::class);
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $db = $firebase->createFirestore()->database();

        $chat_snap = $db->collection('chats')->document($chat_id)->snapshot();

        if (! $chat_snap->exists()) {
            throw new Exceptions\BaseException('Invalid chat id', 400);
        }
        $chat = $chat_snap->data();

        $other_uid = array_values(array_diff(
            $chat['members'],
            [$user->uid]
        ))[0];
        $other_user = User::where('uid', $other_uid)->first();

        $user->blocked_users()->attach($other_user->id, [
            'reason_type' => $reason_type,
            'other_reason' => $other_reason ?: null,
        ]);

        return [
            'message' => 'User successfully reported',
        ];
    }

    public function add_payment_card(
        User $user,
        string $payment_method_id,
        string $last_digits,
        string $expiry_month,
        string $expiry_year,
        string $brand_name
    ) {

        $stripe_customer_id = $this->stripe_service->get_customer_id($user);

        $card = new UserPaymentCard;
        $card->id = $payment_method_id;
        $card->last_digits = $last_digits;
        $card->expiry_month = $expiry_month;
        $card->expiry_year = $expiry_year;
        $card->brand_name = $brand_name;
        $card->save();

        $this->stripe_service->add_card(
            $payment_method_id,
            stripe_customer_id
        );

        return ['message' => 'User card has been successfully attached'];
    }

    public function update_payment_card(
        string $payment_method_id,
        ?string $last_digits,
        ?string $expiry_month,
        ?string $expiry_year,
        ?string $brand_name
    ) {
        $card = UserPaymentCard::find($payment_method_id);

        if ($card->user_id != $user->id) {
            throw new Exceptions\AccessForbidden;
        }

        $card->last_digits = $last_digits ?? $card->last_digits;
        $card->expiry_month = $expiry_month ?? $card->expiry_month;
        $card->expiry_year = $expiry_year ?? $card->expiry_year;
        $card->brand_name = $brand_name ?? $card->brand_name;
        $card->save();

        return ['message' => 'User card has been successfully updated'];
    }

    public function delete_payment_card(
        User $user,
        string $payment_method_id
    ) {
        $card = UserPaymentCard::find($payment_method_id);

        if ($card->user_id != $user->id) {
            throw new Exceptions\AccessForbidden;
        }

        $card->delete();
        $this->stripe_service->detach_card($payment_method_id);

        return ['message' => 'User card has been successfully detached'];
    }
}
