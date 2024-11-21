<?php

namespace App\Models;

use App\Observers\PostBidObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([PostBidObserver::class])]
class PostBid extends Model
{
    use HasFactory;

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
