<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

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

    public function likes()
    {
        return $this->hasMany(PostLike::class);
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function connection_request()
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
        return $this->belongsToMany(
            User::class,
            'user_connections',
            'user_id',
            'connection_id'
        )->withTimestamps();
    }

    public function connected_by()
    {
        return $this->belongsToMany(
            User::class,
            'user_connections',
            'connection_id',
            'user_id'
        )->withTimestamps();
    }
}
