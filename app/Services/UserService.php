<?php

namespace App\Services;

use App\Exceptions;
use App\Jobs\StoreImages;
use App\Models\ConnectionRequest;
use App\Models\Notification;
use App\Models\Otp;
use App\Models\PostBid;
use App\Models\PostLike;
use App\Models\Review;
use App\Models\User;
use App\Models\UserBank;
use App\Models\UserCard;
use App\Models\UserNotificationDevice;
use App\Models\UserPreference;
use App\Models\Withdraw;
use Carbon\Carbon;
use Google\Cloud\Firestore\FieldValue;
use Google\Cloud\Firestore\Filter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserService
{
    protected $post_service;

    protected $notification_service;

    protected $stripe_service;

    protected $firestore;

    public function __construct(
        PostService $post_service,
        FirebaseNotificationService $notification_service,
        StripeService $stripe_service
    ) {
        $this->post_service = $post_service;
        $this->notification_service = $notification_service;
        $this->stripe_service = $stripe_service;
        $this->firestore = app('firebase')->createFirestore()->database();
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

        if ($is_connection && $is_connection->status == 'accepted') {
            return $is_connection;
        }

        return false;
    }

    private function haversineDistance(
        float $lat1,
        float $long1,
        float $lat2,
        float $long2
    ) {
        // Earth's radius in miles
        $earth_radius = 3958.8;
        // Earth's radius in kilometers
        // $earth_radius = 6371;

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

    private function send_request(
        int $sender_id,
        int $receiver_id,
    ) {
        $existing_request = ConnectionRequest::where([
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
                    'Connection request already sent', 400
                );
            }

            if ($existing_request->status == 'accepted') {
                throw new Exceptions\ConnectionExists;
            }

            if ($existing_request->status == 'rejected') {
                $existing_request->sender_id = $sender_id;
                $existing_request->receiver_id = $receiver_id;
                $existing_request->status = 'pending';
                $existing_request->save();

                $receiver_user = User::find($receiver_id);
                $user = User::find($sender_id);

                $request_notification = Notification::whereJsonContains(
                    'additional_data->connection_request_id',
                    $existing_request->id
                )->delete();

                $this->notification_service->push_notification(
                    ...NotificationData::CONNECTION_REQUEST_SENT->get(
                        $existing_request->receiver,
                        $existing_request->sender,
                        $existing_request
                    )
                );

                return [
                    'message' => 'Connection request successfully sent',
                ];
            }
        }

        $connection_request = new ConnectionRequest;
        $connection_request->sender_id = $sender_id;
        $connection_request->receiver_id = $receiver_id;
        $connection_request->status = 'pending';
        $connection_request->save();

        $receiver_user = User::find($receiver_id);
        $user = User::find($sender_id);

        $this->notification_service->push_notification(
            ...NotificationData::CONNECTION_REQUEST_SENT->get(
                $receiver_user,
                $user,
                $connection_request
            )
        );

        return ['message' => 'Connection request successfully sent'];
    }

    private function chat_exists(User $user, string $other_party_uid)
    {
        $ref = $this->firestore->collection('chats');

        $data = $ref->where(Filter::or([
            Filter::and([
                Filter::field('first_party', '=', $user->uid),
                Filter::field('second_party', '=', $other_party_uid),
            ]),
            Filter::and([
                Filter::field('first_party', '=', $other_party_uid),
                Filter::field('second_party', '=', $user->uid),
            ]),
        ]))->documents()->rows();

        if (count($data) > 0) {
            return $data[0];
        }

        return null;
    }

    public function update_profile(
        User $user,
        ?string $first_name,
        ?string $last_name,
        ?string $about,
        ?string $age,
        UploadedFile|string|null $avatar,
        ?string $phone_number,
        ?float $lat,
        ?float $long,
        ?string $location,
    ) {
        $user->first_name = $first_name ?? $user->first_name ?? null;
        $user->last_name = $last_name ?? $user->last_name ?? null;
        $user->about = $about ?? $user->about ?? null;
        $user->age = $age ?? $user->age ?? null;
        $user->phone_number = $phone_number ? preg_replace('/^\+1/', '', $phone_number) : ($user->phone_number ?? null);
        $user->lat = $lat ?? $user->lat ?? null;
        $user->long = $long ?? $user->long ?? null;
        $user->location = $location ?? $user->location ?? null;

        if ($avatar == 'none') {
            $user->avatar = null;
        } elseif ($avatar) {
            $avatar_name = str()->random(15);
            $user->avatar = urlencode("avatars/$avatar_name.webp");

            StoreImages::dispatchAfterResponse(
                $avatar->path(),
                'avatars',
                $avatar_name,
                'firestorage'
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
        $user_post_ids = $user->posts()->pluck('id');
        $user_post_bids = PostBid::whereIn('post_id', $user_post_ids)
            ->where('status', 'pending')->get();

        foreach ($user_post_bids as $user_post_bid) {
            if (! $user_post_bid->order) {
                throw new Exceptions\BaseException(
                    'Account could not be deleted due to pending orders.', 400
                );
            }
        }

        if ($user->avatar ?? false) {
            Storage::delete($user->avatar);
        }

        Otp::where('identifier', $user->email)->first()?->delete();

        $firestore_copy = $this->firestore;
        $this->firestore->runTransaction(
            function ($trx) use ($firestore_copy, $user) {
                $chat_ids = $user->connections->pluck('chat_id')->toArray();
                $post_ids = $user->bids()->with('post')->get()->pluck('post.id')
                    ->toArray();

                foreach ($chat_ids as $chat_id) {
                    if(empty($chat_id)){
                        continue;
                    }
                    $ref = $firestore_copy->collection('chats')
                        ->document($chat_id);
                    $messages = $ref->collection('messages')->listDocuments();

                    foreach ($messages as $msg) {
                        $trx->delete($msg);
                    }

                    $trx->delete($ref);
                }

                foreach ($post_ids as $post_id) {
                    $ref = $firestore_copy->collection('posts')
                        ->document($post_id)
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

                            if ($id != $user->uid) {
                                $next_value = $id;
                            }
                        }
                        $trx->update(
                            $lowest_bid_ref,
                            [['path' => 'bid_id', 'value' => $next_value]]
                        );
                    }

                    $trx->delete($bid_ref);
                }
            }
        );

        $firebase_auth = app('firebase')->createAuth();
        $firebase_user = $firebase_auth->getUserByEmail($user->email);

        if ($firebase_user) {
            $firebase_auth->deleteUser($user->uid);
        }

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
        $user_is_blocked = $user->is_blocked($current_user->id)
            || $user->is_blocker($current_user->id);

        $reviews = Review::whereHas(
            'post',
            function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }
        )
            ->with('user:id,first_name,last_name,avatar')
            ->get();

        $average_rating = number_format($reviews->avg('rating'), 1);
        $recent_post = $user->posts()
            ->with(['bids', 'likes', 'images'])
            ->latest()
            ->first();

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

        if ($connection_request) {
            $connection_request_status = $connection_request->status;
        } else {
            $connection_request_status = 'not send';
        }

        if ($user?->lat && $user?->long && $recent_post) {
            $calculated_distance = $this->post_service->calculateDistance(
                $user->lat,
                $user->long,
                $recent_post->lat,
                $recent_post->long,
            );

            $distance = round($calculated_distance, 2).' miles away';
        }

        $connection_visibility = $user->preferences?->who_can_see_connections;
        $is_own_profile = $user->id === $current_user->id;

        if (
            $is_own_profile || $connection_visibility === 'public'
            || (
                $connection_visibility === 'connections'
                && $this->is_connected($current_user, $user)
            )
        ) {
            $connections = ConnectionRequest::where(
                function ($query) use ($user) {
                    $query->where('sender_id', $user->id)
                        ->orWhere('receiver_id', $user->id);
                }
            )
                ->where('status', 'accepted')
                ->with(['sender', 'receiver'])
                ->limit(3)
                ->get();

            $connections->transform(function ($con) use ($user) {
                $other_user = $con->sender_id == $user->id
                    ? $con->receiver : $con->sender;

                return [
                    'user_id' => $other_user->id,
                    'user_uid' => $other_user->uid,
                    'user_name' => $other_user->full_name,
                    'avatar' => $other_user->avatar,
                    'chat_id' => $con?->chat_id,
                ];
            });
        }

        $reviews->transform(function ($review) {
            return [
                'post_id' => $review->post_id,
                'data' => $review->data,
                'rating' => $review->rating,
                'created_at' => $review->created_at->format('d M'),
                'user_id' => $review->user->id,
                'user_name' => $review->user->full_name,
                'avatar' => $review->user->avatar,
            ];
        });

        $is_account_active = $this->stripe_service->is_account_active($user);

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
            'location' => $user->location,
            'lat' => $user->lat,
            'long' => $user->long,
            'has_active_stripe_connect' => $is_account_active,
            'has_active_bank' => $user->banks->count() > 0,
            'has_active_card' => $user->cards->count() > 0,
            'who_can_see_connection' => $connection_visibility,
            'is_connection' => $this->is_connected($current_user, $user) ? true : false,
            'connection_request_status' => $connection_request_status,
            'connection_count' => $connections?->count() ?? 0,
            'connections' => $connections ?? [],
            'recent_post' => $recent_post ? [[
                'post_id' => $recent_post->id,
                'user_id' => $recent_post->user_id,
                'type' => $recent_post->type,
                'title' => $recent_post->title,
                'description' => $recent_post->description,
                'location' => $recent_post->location,
                'location_details' => $recent_post->city.', '.$recent_post->state,
                'lat' => $recent_post->lat,
                'long' => $recent_post->long,
                'distance' => $distance ?? null,
                'budget' => $recent_post->budget,
                'duration' => ($recent_post->start_time && $recent_post->end_time)
                    ? Carbon::parse($recent_post->start_time)->format('h:i A').' - '.Carbon::parse($recent_post->end_time)->format('h:i A')
                    : null,
                'date' => Carbon::parse($recent_post->start_date)->format('d M').' - '.
                          Carbon::parse($recent_post->end_date)->format('d M y'),
                'start_date' => $recent_post->start_date->format('d M Y'),
                'end_date' => $recent_post->end_date->format('d M Y'),
                'start_time' => $recent_post->start_time?->format('h:i A'),
                'end_time' => $recent_post->end_time?->format('h:i A'),
                'delivery_requested' => (bool) $recent_post->delivery_requested,
                'created_at' => $recent_post->created_at->diffForHumans(),
                'bids' => $recent_post->bids->count(),
                'current_user_like' => $current_user_like,
                'likes' => $recent_post->likes->count(),
                'images' => $recent_post->images,
            ]] : [],
            'reviews' => $reviews,
            'is_social' => $user->password == null,
            'user_is_blocked' => $user_is_blocked,
        ];
    }

    public function set_location(
        User $user,
        float $lat,
        float $long,
        string $location,
        ?string $city,
        ?string $state,
    ) {
        $user->lat = $lat;
        $user->long = $long;
        $user->location = $location;
        $user->city = $city ?? $user->city ?? null;
        $user->state = $state ?? $user->state ?? null;

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

    public function get_nearby_users(User $current_user)
    {
        $nearby_users = [];
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
        )
            // ->orWhere('state', $current_user->state)
            // ->orWhere('city', $current_user->city)
            ->whereNot('id', $current_user->id)
            ->whereNotNull('lat')
            ->whereNotNull('long')
            ->chunk(10, function ($users) use (&$nearby_users, $current_user) {
                foreach ($users as $user) {
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

                if (count($nearby_users) >= 9) {
                    return false;
                }
            });

        return $nearby_users;
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
            'message' => 'Connection request sent successfully',
        ];
    }

    public function cancel_connection_request(User $user, User $other_user)
    {
        $connection_request = ConnectionRequest::where([
            ['sender_id', '=', $user->id],
            ['receiver_id', '=', $other_user->id],
        ])
            ->orWhere([
                ['sender_id', '=', $other_user->id],
                ['receiver_id', '=', $user->id],
            ])
            ->firstOrFail();

        if ($connection_request->status == 'pending') {

            if ($connection_request->chat_id) {
                $this->remove_chat($connection_request->chat_id);
            }

            $notification = Notification::whereJsonContains(
                'additional_data->connection_request_id',
                $connection_request->id
            )->first();

            $notification?->delete();
            $connection_request->delete();

            return [
                'mesage' => 'Connection request canceled',
            ];
        }
    }

    public function accept_connection_request(
        User $user,
        User $other_user,
    ) {
        $connection_request = ConnectionRequest::whereNot('status', 'rejected')
            ->where([
                ['sender_id', '=', $other_user->id],
                ['receiver_id', '=', $user->id],
            ])->firstOrFail();

        if ($connection_request->status == 'accepted') {
            throw new Exceptions\ConnectionExists;
        }

        $connection_request->status = 'accepted';

        $request_notification = Notification::whereJsonContains(
            'additional_data->connection_request_id',
            $connection_request->id
        )->first();

        if ($request_notification) {
            $request_notification->body = 'You and '.$request_notification->title.' are now connected';
            $request_notification->save();
        }

        $firestore_chat_exists = $this->chat_exists($user, $other_user->uid);

        if ($firestore_chat_exists) {
            $connection_request->chat_id = $firestore_chat_exists?->id();
        }

        if ($connection_request->chat_id == null) {
            // $chat = $this->create_chat($user, $other_user);
            // $connection_request->chat_id = $chat['chat_id'];
        } else {
            $ref = $this->firestore->collection('chats')->document(
                $connection_request->chat_id
            );

            $ref->update([['path' => 'connection_removed', 'value' => false]]);
        }
        $connection_request->save();

        $this->notification_service->push_notification(
            ...NotificationData::CONNECTION_REQUEST_ACCEPTED->get(
                $connection_request->sender,
                $connection_request->receiver,
                $connection_request
            )
        );

        return ['message' => 'Connections successfully created'];
    }

    public function decline_connection_request(
        User $user,
        User $other_user
    ) {
        $connection = ConnectionRequest::where([
            ['sender_id', '=', $user->id],
            ['receiver_id', '=', $other_user->id],
        ])
            ->orWhere([
                ['sender_id', '=', $other_user->id],
                ['receiver_id', '=', $user->id],
            ])->firstOrFail();

        if ($connection->status == 'accepted') {
            throw new Exceptions\ConnectionExists;
        }

        if ($connection->status == 'rejected') {
            throw new Exceptions\BaseException(
                'This connection request has already been rejected.', 400
            );
        }

        $connection->status = 'rejected';
        $connection->save();

        $request_notification = Notification::whereJsonContains(
            'additional_data->connection_request_id',
            $connection->id
        )->first();

        if ($request_notification) {
            $request_notification->title = null;
            $request_notification->body = 'Connection request has been declined';
            $request_notification->save();
        }

        return [
            'message' => 'Connection Decline successfully',
        ];
    }

    public function remove_connection(User $user, User $other_user)
    {
        $connection = ConnectionRequest::where([
            ['sender_id', '=', $user->id],
            ['receiver_id', '=', $other_user->id],
        ])
            ->orWhere([
                ['sender_id', '=', $other_user->id],
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

        if ($connection->chat_id) {
            $this->remove_chat($connection->chat_id);
        }

        return [
            'message' => 'user Disconnected Successfully',
        ];
    }

    public function get_connections(User $user)
    {
        $connections = ConnectionRequest::where(function ($query) use ($user) {
            $query->where('sender_id', $user->id)
                ->orWhere('receiver_id', $user->id);
        })
            ->whereDoesntHave(
                'sender.blocked_users',
                function ($query) use ($user) {
                    $query->where('blocked_id', $user->id);
                }
            )
            ->whereDoesntHave(
                'sender.blocker_users',
                function ($query) use ($user) {
                    $query->where('blocker_id', $user->id);
                }
            )
            ->whereDoesntHave(
                'receiver.blocked_users',
                function ($query) use ($user) {
                    $query->where('blocked_id', $user->id);
                }
            )
            ->whereDoesntHave(
                'receiver.blocker_users',
                function ($query) use ($user) {
                    $query->where('blocker_id', $user->id);
                }
            )
            ->where('status', 'accepted')
            ->with([
                'sender',
                'receiver',
            ])
            ->paginate(2);

        $connections->getCollection()->transform(function ($con) use ($user) {
            $connected_user = $con->sender['id'] == $user->id
                ? $con->receiver
                : $con->sender;

            $connected_user['chat_id'] = $con->chat_id;

            return [
                'id' => $con->id,
                'status' => $con->status,
                'created_at' => $con->created_at,
                'user' => $connected_user,
            ];
        });

        return $connections;
    }

    public function get_chat_users(User $user)
    {
        $users = ConnectionRequest::whereNotNull('chat_id')
            ->whereNull('deleted_at')
            ->where('status', 'accepted')
            ->where('sender_id', $user->id)
            ->orWhere('receiver_id', $user->id)
            ->get();

        return $users;
    }

    public function store_fcm(string $fcm_token, User $user)
    {
        $user->notification_device?->delete();

        UserNotificationDevice::updateOrCreate(
            ['fcm_token' => $fcm_token],
            ['user_id' => $user->id]
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

                $connection_request = ConnectionRequest::find(
                    $notif->additional_data['connection_request_id'] ?? 0
                );

                $is_request_accepted =
                    $connection_request?->status == 'accepted';

                $is_request_rejected =
                    $connection_request?->status == 'rejected';

                $is_connection_request = (
                    ! ($is_request_accepted || $is_request_rejected)
                ) && str_contains(
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
            ->whereNotIn('status', ['rejected', 'accepted'])
            ->with('sender:id,first_name,last_name,avatar')
            ->paginate(2);

        $connection_requests->getCollection()->transform(
            function ($con) {
                return [
                    'id' => $con->id,
                    'sender_id' => $con->sender_id,
                    'receiver_id' => $con->receiver_id,
                    'status' => $con->status,
                    'chat_id' => $con->chat_id,
                    'created_at' => $con->created_at,
                    'updated_at' => $con->updated_at,
                    'sender' => $con->sender,
                ];
            }
        );

        return $connection_requests;
    }

    public function update_password(
        User $user,
        string $old_password,
        string $new_password
    ) {
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

    public function initiate_chat(User $user, User $other_user)
    {

        if ($user->is_blocked($other_user->id)) {
            throw new Exceptions\BaseException(
                "You're blocked by the other user",
                400
            );
        }

        if ($user->is_blocker($other_user->id)) {
            throw new Exceptions\BaseException(
                "You've blocked the other user",
                400
            );
        }

        $connection_request = ConnectionRequest::where([
            ['sender_id', '=', $other_user->id],
            ['receiver_id', '=', $user->id],
        ])
            ->orWhere([
                ['sender_id', '=', $user->id],
                ['receiver_id', '=', $other_user->id],
            ])->first();

        $chat_id = $this->create_chat($user, $other_user)['chat_id'];

        if ($connection_request && $connection_request?->chat_id == null) {
            $connection_request->chat_id = $chat_id;
            $connection_request->save();
        }

        return [
            'user_id' => $other_user->id,
            'user_uid' => $other_user->uid,
            'chat_id' => $chat_id,
        ];
    }

    public function initiate_chats(User $user, array $other_party_uids)
    {
        $other_users = User::whereIn('uid', $other_party_uids)
            ->whereDoesntHave(
                'blocked_users',
                function ($query) use ($user) {
                    $query->where('blocked_id', $user->id);
                }
            )
            ->whereDoesntHave(
                'blocker_users',
                function ($query) use ($user) {
                    $query->where('blocker_id', $user->id);
                }
            )
            ->get();

        $other_users->transform(function ($other_party) use ($user) {
            $chat_response = $this->create_chat($user, $other_party);

            return [
                'user_id' => $other_party->id,
                'user_uid' => $other_party->uid,
                'chat_id' => $chat_response['chat_id'],
            ];
        });

        return $other_users;
    }

    public function create_chat(User $user, User $other_party)
    {
        $existing_chat_id = $this->chat_exists($user, $other_party->uid)?->id();

        if ($existing_chat_id) {
            return ['chat_id' => $existing_chat_id];
        }

        $chat_id = str()->random(20);

        $unseen_count = [];
        $unseen_count[$user->uid] = 0;
        $unseen_count[$other_party->uid] = 0;

        $is_deleted = [];
        $is_deleted[$user->uid] = false;
        $is_deleted[$other_party->uid] = false;

        $data = [
            'id' => $chat_id,
            'blocked_by' => null,
            'created_at' => FieldValue::serverTimestamp(),
            'last_msg' => '',
            'members' => [
                $user->uid,
                $other_party->uid,
            ],
            'unseen_counts' => $unseen_count,
            'is_deleted' => $is_deleted,
            'connection_removed' => false,
            'first_party' => $user->uid,
            'second_party' => $other_party->uid,
            'is_order_running' => $this->is_order_running($user, $other_party),
        ];

        $this->firestore->collection('chats')->document($chat_id)->set($data);

        return ['chat_id' => $chat_id];
    }

    public function is_order_running(User $user1, User $user2)
    {
        $user1_posts = $user1->posts()->pluck('id');
        $user2_posts = $user2->posts()->pluck('id');

        $user1_bid_on_user2_post = PostBid::whereIn('post_id', $user2_posts)
            ->where('user_id', $user1->id)
            ->where('status', 'accepted')
            ->doesntHave('order')
            ->exists();

        $user2_bid_on_user1_post = PostBid::whereIn('post_id', $user1_posts)
            ->where('user_id', $user2->id)
            ->where('status', 'accepted')
            ->doesntHave('order')
            ->exists();

        if ($user1_bid_on_user2_post || $user2_bid_on_user1_post) {
            return true;
        }

        return false;
    }

    public function remove_chat(string $chat_id)
    {
        $ref = $this->firestore->collection('chats')->document($chat_id);

        if ($ref->snapshot()->exists()) {
            // $ref->delete();
            $ref->update([['path' => 'connection_removed', 'value' => true]]);
        }

        return ['message' => 'Chat successfully deleted'];
    }

    public function send_message_notificatfion(
        User $user,
        User $receiver_user,
        string $chat_id,
        string $type
    ) {
        $chat_snap = $this->firestore->collection('chats')->document($chat_id)
            ->snapshot();

        if (! $chat_snap->exists()) {
            return;
        }

        $chat_data = $chat_snap->data();

        if (! in_array($user->uid, $chat_data['members'])) {
            throw new Exceptions\AccessForbidden;
        }

        $connection = $this->is_connected($user, $receiver_user);

        switch ($type) {
            case 'text':
                $this->notification_service->push_notification(
                    ...NotificationData::NEW_MESSAGE->get(
                        $receiver_user,
                        $user,
                        $connection
                    )
                );
                break;

            case 'image':
                $this->notification_service->push_notification(
                    ...NotificationData::NEW_MESSAGE->get(
                        $receiver_user,
                        $user,
                        $connection
                    )
                );
                break;

            case 'sharepost':
                $this->notification_service->push_notification(
                    ...NotificationData::NEW_SHARE_POST_MESSAGE->get(
                        $receiver_user,
                        $user,
                        $connection
                    )
                );
                break;
        }

        return ['message' => 'Message notification successfully sent'];
    }

    public function block_user(
        User $user,
        User $other_user,
        string $reason_type,
        ?string $other_reason
    ) {
        if ($user->is_blocked($other_user->id)) {

            return [
                'message' => 'User is already blocked',
            ];
        } else {
            $user->blocked_users()->attach($other_user->id, [
                'reason_type' => $reason_type,
                'other_reason' => $other_reason,
            ]);

            $chat_ref = $this->chat_exists(
                $user,
                $other_user->uid
            )?->reference();

            if ($chat_ref) {
                $chat_ref->update([[
                    'path' => 'blocked_by',
                    'value' => $user->uid,
                ]]);
            }

            return [
                'message' => 'User successfully blocked',
            ];
        }
    }

    public function unblock_user(
        User $user,
        User $other_user
    ) {
        if (! $user->is_blocked($other_user->id)) {

            return [
                'message' => 'User is not blocked',
            ];
        } else {
            $user->blocked_users()->detach($other_user->id);

            $chat_ref = $this->chat_exists(
                $user,
                $other_user->uid
            )?->reference();

            if ($chat_ref) {
                $chat_ref->update([[
                    'path' => 'blocked_by',
                    'value' => null,
                ]]);
            }

            return [
                'message' => 'User successfully unblocked',
            ];
        }
    }

    public function get_blocked_users(User $user)
    {
        return $user->blocked_users->map(function ($blocked_user) use ($user) {
            $chat_id = ConnectionRequest::where([
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

    public function report_chat(
        User $user,
        string $chat_id,
        string $reason_type,
        ?string $other_reason,
    ) {
        $chat_snap = $this->firestore->collection('chats')->document($chat_id)
            ->snapshot();

        if (! $chat_snap->exists()) {
            throw new Exceptions\BaseException('Invalid chat id', 400);
        }
        $chat = $chat_snap->data();

        $other_uid = array_values(array_diff(
            $chat['members'],
            [$user->uid]
        ))[0];
        $other_user = User::where('uid', $other_uid)->first();

        return $this->report_user(
            $user,
            $other_user,
            $reason_type,
            $other_reason
        );
    }

    public function report_user(
        User $user,
        User $reported_user,
        string $reason_type,
        ?string $other_reason
    ) {
        $already_reported = $reported_user->reports()->where([
            ['reporter_id', $user->id],
        ])->exists();

        if ($already_reported) {
            throw new Exceptions\BaseException(
                'You have already reported this user.',
                422
            );
        }

        $reported_user->reports()->create([
            'reporter_id' => $user->id,
            'reason_type' => $reason_type,
            'other_reason' => $other_reason ?: null,
        ]);

        return [
            'message' => 'User Successfully reported',
        ];
    }

    public function get_payment_details(User $user)
    {
        return [
            'cards' => $user->cards,
            'bank_details' => $user->banks,
        ];
    }

    public function add_payment_card(
        User $user,
        string $card_token,
        string $last_digits,
        string $expiry_month,
        string $expiry_year,
        string $brand_name
    ) {
        $card_id = $this->stripe_service->add_card($user, $card_token);

        UserCard::updateOrCreate(
            [
                'id' => $card_id,
                'user_id' => $user->id,
            ],
            [
                'last_digits' => $last_digits,
                'expiry_month' => $expiry_month,
                'expiry_year' => $expiry_year,
                'brand' => $brand_name,
            ]
        );

        $user->fire_updated_observer();

        return ['message' => 'User card has been successfully attached'];
    }

    public function update_payment_card(
        User $user,
        string $card_id,
        ?string $expiry_month,
        ?string $expiry_year
    ) {
        $this->stripe_service->update_card(
            $user,
            $card_id,
            $expiry_month,
            $expiry_year
        );

        $card = UserCard::find($card_id);
        $card->expiry_month = $expiry_month ?? $card->expiry_month;
        $card->expiry_year = $expiry_year ?? $card->expiry_year;
        $card->save();

        return $card;
    }

    public function delete_payment_card(
        User $user,
        string $card_id
    ) {
        $card = UserCard::find($card_id);

        if (! $card) {
            throw new Exceptions\BaseException(
                'Card not found', 400
            );
        }

        if ($card->user_id != $user->id) {
            throw new Exceptions\AccessForbidden;
        }
        $card->delete();

        $user->fire_updated_observer();

        return [
            'message' => 'User card has been successfully detached',
        ];
    }

    public function add_bank(
        User $user,
        string $account_number,
        string $routing_number,
        string $bank_name,
        string $holder_name
    ) {
        $bank_id = $this->stripe_service->add_bank(
            $user,
            $account_number,
            $routing_number,
            $holder_name
        );

        UserBank::updateOrCreate(
            [
                'id' => $bank_id,
                'user_id' => $user->id,
            ],
            [
                'last_digits' => substr($account_number, -4),
                'routing_number' => $routing_number,
                'bank_name' => $bank_name,
                'holder_name' => $holder_name,
            ]
        );

        return [
            'message' => 'Bank account attached successfully',
        ];
    }

    public function delete_bank(User $user, string $bank_id)
    {
        $bank = UserBank::find($bank_id);

        if (! $bank) {
            throw new Exceptions\BaseException(
                'Bank not found', 400
            );
        }

        if ($bank->user_id != $user->id) {
            throw new Exceptions\AccessForbidden;
        }
        $bank->delete();

        return [
            'message' => 'User bank has been successfully detached',
        ];
    }

    public function get_payment_card(User $user)
    {
        return [
            'cards' => $user->cards,
            'bank_details' => $user->banks,
        ];
    }

    public function get_onboarding_link(User $user)
    {
        return $this->stripe_service->get_onboarding_link($user);
    }

    public function withdraw_funds(
        User $user,
        string $bank_id,
        float $amount
    ) {
        $balance = $this->stripe_service->get_account_balance(
            $user
        )['available'][0]['amount'];

        if ($balance < $amount) {
            throw new Exceptions\BaseException('Insufficient funds', 400);
        }

        $withdraw = new Withdraw;
        $withdraw->id = (string) str()->uuid();
        $withdraw->user_id = $user->id;
        $withdraw->amount = $amount;
        $withdraw->bank_id = $bank_id;
        $withdraw->save();

        return ['message' => 'Funds successfully transferred'];
    }

    public function get_user_funds(User $user, string $year, ?string $month)
    {
        $balance = $this->stripe_service->get_account_balance(
            $user
        )['available'][0]['amount'] / 100;

        $withdraws = Withdraw::where('user_id', $user->id)
            ->latest()
            ->whereYear('created_at', $year)
            ->when($month, function ($query) use ($month) {
                $query->whereMonth('created_at', $month);
            })
            ->paginate(4);
        $withdraws->getCollection()->transform(function ($record) {
            return [
                'id' => $record->id,
                'user_id' => $record->user_id,
                'amount' => $record->amount,
                'bank_id' => $record->bank_id,
                'created_at' => $record->created_at->format('d M Y'),
                'status' => 'successful',
            ];
        });

        if (! $month) {
            $points = Withdraw::where('user_id', $user->id)->selectRaw(
                'SUM(amount) as value,
                YEAR(created_at) as year,
                MONTH(created_at) as month,
                DAY(created_at) as day'
            )
                ->whereYear('created_at', $year)
                ->groupByRaw('MONTH(`created_at`)')
                ->orderByRaw('MONTH(`created_at`)')
                ->get();
        } else {
            $points = Withdraw::where('user_id', $user->id)->selectRaw(
                'SUM(amount) as value,
                YEAR(created_at) as year,
                MONTH(created_at) as month,
                DAY(created_at) as day'
            )
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->groupByRaw('DAY(`created_at`)')
                ->orderByRaw('DAY(`created_at`)')
                ->get();
        }

        $max_value = 0;

        foreach ($points as $point) {

            if ($point->value > $max_value) {
                $max_value = $point->value;
            }
        }

        return [
            'balance' => $balance,
            'withdraws' => $withdraws,
            'graph' => [
                'view' => $month ? 'monthly' : 'yearly',
                'points' => $points,
                'max_value' => $max_value,
            ],
            'banks' => $user->banks,
        ];
    }
}
