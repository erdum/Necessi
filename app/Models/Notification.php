<?php

namespace App\Models;

use App\Observers\NotificationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([NotificationObserver::class])]
class Notification extends Model
{
    use HasFactory;

    protected $casts = [
        'additional_data' => 'array',
    ];

    protected function status(): Attribute
    {
        return Attribute::make(function () {

            if (str_contains($this->body, 'connection')) {
                return 'someone has sent you a connetion request';
            }

            if (str_contains($this->body, 'connected')) {
                return 'you and someone are now connected';
            }

            if (str_contains($this->body, 'canceled')) {
                return 'conection request has been cancelled';
            }

            if (str_contains($this->body, 'placed bid')) {
                return 'someone bid on your post';
            }

            if (str_contains($this->body, 'accept')) {
                return 'someone has accept your bid request';
            }

            if (str_contains($this->body, 'rejected')) {
                return 'someone has rejected your bid request';
            }

            if (str_contains($this->body, 'liked')) {
                return 'somone like on you post';
            }

            if (str_contains($this->body, 'commented')) {
                return 'someone has comment on your post';
            }

            if (str_contains($this->body, 'message')) {
                return 'someone msg you';
            }

            if (str_contains($this->body, 'shared')) {
                return 'someone has shared your post';
            }

            return 'unknown';
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
