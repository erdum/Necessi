<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    $casts = [
        'additional_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
