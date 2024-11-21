<?php

namespace App\Models;

use App\Observers\UserNotificationDeviceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([UserNotificationDeviceObserver::class])]
class UserNotificationDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fcm_token',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
