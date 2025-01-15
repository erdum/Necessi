<?php

namespace App\Services;

use App\Exceptions;
use App\Models\Notification as NotificationModel;
use App\Models\User;
use App\Models\Post;
use App\Models\ConnectionRequest;
use App\Models\UserNotificationDevice;
use App\Jobs\SendNotification;

enum NotificationType: string
{
    case GENERAL = 'general';
    case BID = 'bid';
    case TRANSACTION = 'transaction';
    case ACTIVITY = 'activity';
    case MESSAGE = 'message';
}

enum NotificationData
{
    case PICKUP_DATE_REMINDER;
    case RETURN_DATE_REMINDER;
    case ORDER_STATUS_CHANGED;
    case ORDER_PAYMENT_SUCCESSFULL;
    case ORDER_MOVED_TO_UPCOMING;
    case BID_RECEIVED;
    case BID_ACCEPTED;
    case BID_REJECTED;
    case POST_LIKED;
    case POST_COMMENT;
    case ACCEPTED_BID_CANCELED;
    case CONNECTION_REQUEST_SENT;
    case CONNECTION_REQUEST_ACCEPTED;
    case NEW_MESSAGE;

    public function get(
        User $receiver_user,
        User $sender_user,
        Post|ConnectionRequest|null $post
    ): array
    {
        return match ($this) {
            self::PICKUP_DATE_REMINDER => [
                'type' => NotificationType::TRANSACTION,
                'receiver_user' => $receiver_user,
                'title' => $receiver_user->full_name,
                'body' => "Your order pickup date as arrived, please mark it if you have received the item",
                'image' => $sender_user->avatar ?? '',
                'additional_data' => [
                    'description' => $sender_user->about,
                    'sender_id' => $sender_user->id,
                    'post_id' => $post?->id,
                    'notification_type' => 'bid_canceled',
                ]
            ],

            self::RETURN_DATE_REMINDER => [
                'type' => NotificationType::TRANSACTION,
                'receiver_user' => $receiver_user,
                'title' => $receiver_user->full_name,
                'body' => "Your order return date as arrived, please mark it if you have received the item",
                'image' => $sender_user->avatar ?? '',
                'additional_data' => [
                    'description' => $sender_user->about,
                    'sender_id' => $sender_user->id,
                    'post_id' => $post?->id,
                    'notification_type' => 'bid_canceled',
                ]
            ],

            self::ORDER_STATUS_CHANGED => [
                'type' => NotificationType::BID,
                'receiver_user' => $receiver_user,
                'title' => $receiver_user->full_name,
                'body' => "Your order status changed to {$post?->order_status}",
                'image' => $sender_user->avatar ?? '',
                'additional_data' => [
                    'description' => $sender_user->about,
                    'sender_id' => $sender_user->id,
                    'post_id' => $post?->id,
                    'notification_type' => 'post_details',
                ]
            ],

            self::ORDER_PAYMENT_SUCCESSFULL => [
                'type' => NotificationType::TRANSACTION,
                'receiver_user' => $receiver_user,
                'title' => $receiver_user->full_name,
                'body' => " payment for your accepted bid on {$post?->title} has been successfully completed",
                'image' => $sender_user->avatar ?? '',
                'additional_data' => [
                    'description' => $sender_user->about,
                    'sender_id' => $sender_user->id,
                    'post_id' => $post?->id,
                    'bid_id' => $post->bids[0]->id,
                    'notification_type' => 'bid_payment_complete',
                ]
            ],

            self::BID_RECEIVED => [
                'type' => NotificationType::BID,
                'receiver_user' => $receiver_user,
                'title' => $receiver_user->full_name,
                'body' => " you have received a new bid on {$post?->title}. Accept or decline now!",
                'image' => $sender_user->avatar ?? '',
                'additional_data' => [
                    'description' => $sender_user->about,
                    'sender_id' => $sender_user->id,
                    'post_id' => $post?->id,
                    'notification_type' => 'bid_received_accept_decline',
                ],
            ],

            self::BID_ACCEPTED => [
                'type' => NotificationType::BID,
                'receiver_user' => $receiver_user,
                'title' => $receiver_user->full_name,
                'body' => $receiver_user->id == $post?->user_id ? "You accepted a bid for {$post?->title}. Payment must be made within 24 hours to confirm the bid." : "Your bid has been accepted! View details and next steps.",
                'image' => $sender_user->avatar ?? '',
                'additional_data' => [
                    'description' => $sender_user->about,
                    'sender_id' => $sender_user->id,
                    'post_id' => $post?->id,
                    'notification_type' => $receiver_user->id == $post?->user_id
                        ? 'you_accepted_bid'
                        : 'bid_accepted',
                    'bid_id' => $post->bids[0]->id,
                    'bid_chip' => 0,
                ]
            ],

            self::BID_REJECTED => [
                'type' => NotificationType::BID,
                'receiver_user' => $receiver_user,
                'title' => $receiver_user->full_name,
                'body' => " Unfortunately, your bid was rejected. View details to try again",
                'image' => $sender_user->avatar ?? '',
                'additional_data' => [
                    'description' => $sender_user->about,
                    'sender_id' => $sender_user->id,
                    'post_id' => $post?->id,
                    'notification_type' => 'bid_rejected',
                    'bid_chip' => 1,
                ]
            ],

            self::POST_LIKED => [
                'type' => NotificationType::ACTIVITY,
                'receiver_user' => $receiver_user,
                'title' => $sender_user->full_name,
                'body' => " has liked your post",
                'image' => $sender_user->avatar ?? '',
                'additional_data' => [
                    'description' => $sender_user->about,
                    'sender_id' => $sender_user->id,
                    'post_id' => $post?->id,
                    'notification_type' => 'post_details',
                ]
            ],

            self::POST_COMMENT => [
                'type' => NotificationType::ACTIVITY,
                'receiver_user' => $receiver_user,
                'title' => $sender_user->full_name,
                'body' => " has commented on your post",
                'image' => $sender_user->avatar ?? '',
                'additional_data' => [
                    'description' => $sender_user->about,
                    'sender_id' => $sender_user->id,
                    'post_id' => $post?->id,
                    'notification_type' => 'post_details',
                ]
            ],

            self::ACCEPTED_BID_CANCELED => [
                'type' => NotificationType::BID,
                'receiver_user' => $receiver_user,
                'title' => $receiver_user->full_name,
                'body' => " your accepted bid has been canceled",
                'image' => $sender_user->avatar ?? '',
                'additional_data' => [
                    'description' => $sender_user->about,
                    'sender_id' => $sender_user->id,
                    'post_id' => $post?->id,
                    'notification_type' => 'bid_canceled',
                ]
            ],

            self::CONNECTION_REQUEST_SENT => [
                'type' => NotificationType::ACTIVITY,
                'receiver_user' => $receiver_user,
                'title' => $sender_user->full_name,
                'body' => " has sent you a connection request",
                'image' => $sender_user->avatar ?? '',
                'additional_data' => [
                    'user_name' => $sender_user->full_name,
                    'user_avatar' => $sender_user->avatar,
                    'description' => $sender_user->about,
                    'sender_id' => $sender_user->id,
                    'connection_request_id' => $post?->id,
                    'is_connection_request' => true,
                    'notification_type' => 'connection',
                ]
            ],

            self::CONNECTION_REQUEST_ACCEPTED => [
                'type' => NotificationType::ACTIVITY,
                'receiver_user' => $receiver_user,
                'title' => $sender_user->full_name,
                'body' => " has accept your connection request",
                'image' => $sender_user->avatar ?? '',
                'additional_data' => [
                    'user_name' => $sender_user->full_name,
                    'user_avatar' => $sender_user->avatar,
                    'description' => $sender_user->about,
                    'sender_id' => $sender_user->id,
                    'connection_request_id' => $post?->id,
                    'notification_type' => 'connection',
                ]
            ],

            self::NEW_MESSAGE => [
                'type' => NotificationType::MESSAGE,
                'receiver_user' => $receiver_user,
                'title' => $sender_user->full_name,
                'body' => " has sent you a message",
                'image' => $sender_user->avatar ?? '',
                'additional_data' => [
                    'description' => $sender_user->about,
                    'sender_id' => $sender_user->id,
                    'connection_request_id' => $post?->id,
                ]
            ],
        };
    }
}

class FirebaseNotificationService
{
    public function store_fcm_token(User $user, string $token)
    {
        try {
            $user->notification_device()->updateOrCreate(
                ['fcm_token' => $token],
                ['user_id' => $user->id]
            );
        } catch (\Exception $error) {
            throw new Exceptions\TokenAlreadyLinked;
        }

        return ['message' => 'FCM token successfully stored'];
    }

    public function push_notification(
        NotificationType $type,
        User $receiver_user,
        string $title,
        string $body,
        ?string $image = null,
        array $additional_data = []
    ) {

        if (
            $receiver_user->is_blocked(auth()->user()->id)
            || $receiver_user->is_blocker(auth()->user()->id)
        ) {
            return;
        }

        $notification_device = UserNotificationDevice::where(
            'user_id',
            $receiver_user->id
        )->first();

        if (! $notification_device) {
            return;
        }

        switch ($type) {
            case NotificationType::GENERAL:

                if (! $receiver_user?->preferences?->general_notifications) {
                    return;
                }
                break;

            case NotificationType::BID:

                if (! $receiver_user?->preferences?->biding_notifications) {
                    return;
                }
                break;

            case NotificationType::TRANSACTION:

                if (! $receiver_user?->preferences?->transaction_notifications) {
                    return;
                }
                break;

            case NotificationType::ACTIVITY:

                if (! $receiver_user?->preferences?->activity_notifications) {
                    return;
                }
                break;

            case NotificationType::MESSAGE:

                if (! $receiver_user?->preferences?->messages_notifications) {
                    return;
                }
                break;

            default:
                break;
        }

        $notification = new NotificationModel;
        $notification->type = $type->value;
        $notification->title = $title;
        $notification->body = $body;
        $notification->image = $image;
        $notification->additional_data = $additional_data;
        $receiver_user->notifications()->save($notification);

        SendNotification::dispatch(
            $notification_device->fcm_token,
            $title,
            $body,
            $image ?? null,
            $additional_data ?? []
        );

        return [
            'message' => 'Notifications successfully sent'
        ];
    }
}
