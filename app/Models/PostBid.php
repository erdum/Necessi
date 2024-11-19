<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\NotificationType;
use App\Observers\PostBidObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([PostBidObserver::class])]
class PostBid extends Model
{
    use HasFactory;

    protected $casts = [
        'type' => NotificationType::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function order()
    {
        return $this->hasOne(OrderHistory::class, 'bid_id');
    }
}
