<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_id',
        'reason_type',
        'other_reason',
    ];

    public function reportable()
    {
        return $this->morphTo();
    }
}
