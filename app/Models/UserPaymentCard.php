<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPaymentCard extends Model
{
    use HasFactory, HasUuids;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
