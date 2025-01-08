<?php

namespace App\Models;

use App\Observers\PostBidObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

#[ObservedBy([PostBidObserver::class])]
class PostBid extends Model
{
    use HasFactory;

    protected function getStatus(): Attribute
    {
        return Attribute::make(function () {

            if ($this->status == 'pending') {
                return 'pending';
            }

            if ($this->status == 'rejected') {
                return 'rejected';
            }

            if ($this->status == 'accepted') {

                if ($this?->order) {

                    if ($this->order?->transaction_id == null) {
                        $check_time = Carbon::parse($this->order?->created_at)
                            ->addDay();

                        if ($check_time->isPast()) {
                            return 'canceled';
                        } else {
                            return 'payment pending';
                        }
                    }

                    return 'paid';
                }

                return 'payment pending';
            }
        });
    }

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

    public function reviews()
    {
        return $this->belongsTo(Review::class);
    }
}
