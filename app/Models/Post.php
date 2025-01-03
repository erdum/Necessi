<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    protected function orderStatus(): Attribute
    {
        return Attribute::make(function () {
            $status = null;
            $bid = $this->bids->first();

            if ($bid?->status != 'accepted' || !$bid?->order) return $status;

            if ($this->type == 'item') {
                $status = $this->start_date->isPast()
                    && $bid->order?->received_by_borrower
                        ? 'underway' : 'upcoming';

                $status = $this->end_date->isPast()
                    && $bid->order?->received_by_lender == null
                        ? 'past due' : $status;

                $status = $bid->order?->received_by_borrower
                    && $bid->order?->received_by_lender
                        ? 'completed' : $status;
            } else {
                $status = $bid->order?->received_by_borrower != null
                    ? 'completed' : 'upcoming';
            }

            return $status;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(PostImage::class);
    }

    public function likes()
    {
        return $this->hasMany(PostLike::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function bids()
    {
        return $this->hasMany(PostBid::class);
    }
}
