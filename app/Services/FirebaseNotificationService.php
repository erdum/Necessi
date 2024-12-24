<?php

namespace App\Services;

use App\Exceptions;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotificationDevice;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

enum NotificationType: string
{
    case GENERAL = 'general';
    case BID = 'bid';
    case TRANSACTION = 'transaction';
    case ACTIVITY = 'activity';
    case MESSAGE = 'message';
}

class FirebaseNotificationService
{
    protected $messaging;

    public function __construct(Factory $factory)
    {
        $firebase = $factory->withServiceAccount(
            base_path()
            .DIRECTORY_SEPARATOR
            .config('firebase.projects.app.credentials')
        );
        $this->messaging = $firebase->createMessaging();
    }

    protected function make_status(PostBid $bid) {}

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
        User $user,
        NotificationType $type,
        string $title,
        string $body,
        ?string $image = null,
        array $additional_data = []
    ) {
        $notification_device = UserNotificationDevice::where(
            'user_id',
            $user->id
        )->first();

        if (! $notification_device) {
            return;
        }

        switch ($type) {
            case NotificationType::GENERAL:

                if (! $user?->preferences?->general_notifications) {
                    return;
                }
                break;

            case NotificationType::BID:

                if (! $user?->preferences?->biding_notifications) {
                    return;
                }
                break;

            case NotificationType::TRANSACTION:

                if (! $user?->preferences?->transaction_notifications) {
                    return;
                }
                break;

            case NotificationType::ACTIVITY:

                if (! $user?->preferences?->activity_notifications) {
                    return;
                }
                break;

            case NotificationType::MESSAGE:

                if (! $user?->preferences?->messages_notifications) {
                    return;
                }
                break;

            default:
                break;
        }

        $notification = new Notification;
        $notification->type = $type->value;
        $notification->title = $title;
        $notification->body = $body;
        $notification->image = $image;
        $notification->additional_data = $additional_data;
        $user->notifications()->save($notification);

        $firebaseNotification = FirebaseNotification::create(
            $title,
            $body,
            $image
        );

        $message = CloudMessage::new()
            ->withNotification($firebaseNotification)
            ->withData($additional_data)
            ->withDefaultSounds();

        $send_report = $this->messaging->sendMulticast(
            $message,
            [$notification_device->fcm_token]
        );

        if ($send_report->hasFailures()) {
            $messages = [];
            foreach ($send_report->failures()->getItems() as $failure) {
                $messages[] = $failure->error()->getMessage();
            }
            Log::warning('Failed to send notifications: ', $messages);
        }

        return [
            'message' => 'Notifications successfully sent',
            'send_report' => $send_report ?? null,
        ];
    }
}
