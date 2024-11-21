<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderHistory extends Model
{
    use HasFactory;

    protected $casts = [
        'received_by_borrower' => 'datetime',
        'received_by_lender' => 'datetime',
    ];

    public function bid()
    {
        return $this->belongsTo(PostBid::class);
    }
}
