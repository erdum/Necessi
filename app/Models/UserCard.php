<?php

namespace App\Models;

use App\Observers\UserCardObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([UserCardObserver::class])]
class UserCard extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'last_digits',
        'expiry_month',
        'expiry_year',
        'brand',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
