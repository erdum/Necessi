<?php

namespace App\Models;

use App\Observers\UserBankObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([UserBankObserver::class])]
class UserBank extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'holder_name',
        'last_digits',
        'bank_name',
        'routing_number',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
