<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'phone_number',
        'phone_number_verified_at',
        'uid',
        'avatar',
        'password',
        'gender',
        'age',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_number_verified_at' => 'datetime',
    ];

    protected function fullName(): Attribute
    {
        return Attribute::make(function () {
            return $this->first_name.' '.$this->last_name;
        });
    }

    public function fire_updated_observer()
    {
        return $this->fireModelEvent('updated', false);
    }

    public function notification_device()
    {
        return $this->hasOne(UserNotificationDevice::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function user_otp()
    {
        return $this->hasOne(Otp::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function likes()
    {
        return $this->hasMany(PostLike::class);
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function connection_requests()
    {
        return $this->hasMany(ConnectionRequest::class);
    }

    public function bids()
    {
        return $this->hasMany(PostBid::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function connections()
    {
        return $this->hasMany(ConnectionRequest::class, 'sender_id')
            ->orWhere('receiver_id', $this->id)->withTrashed();
    }

    public function blocked_users()
    {
        return $this->belongsToMany(
            User::class,
            'blocked_users',
            'blocker_id',
            'blocked_id'
        )->withTimestamps();
    }

    public function blocker_users()
    {
        return $this->belongsToMany(
            User::class,
            'blocked_users',
            'blocked_id',
            'blocker_id'
        )->withTimestamps();
    }

    public function is_blocked($user_id)
    {
        return $this->blocked_users()->where('blocked_id', $user_id)->exists();
    }

    public function is_blocker($user_id)
    {
        return $this->blocker_users()->where('blocker_id', $user_id)->exists();
    }

    public function reported_users()
    {
        return $this->belongsToMany(
            User::class,
            'reported_users',
            'reporter_id',
            'reported_id'
        )->withTimestamps();
    }

    public function reporter_users()
    {
        return $this->belongsToMany(
            User::class,
            'reported_users',
            'reported_id',
            'reporter_id'
        )->withTimestamps();
    }

    public function cards()
    {
        return $this->hasMany(UserCard::class);
    }

    public function banks()
    {
        return $this->hasMany(UserBank::class);
    }

    public function withdraws()
    {
        return $this->hasMany(Withdraw::class);
    }
}
