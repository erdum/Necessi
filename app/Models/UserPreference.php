<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Observers\UserPreferenceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([UserPreferenceObserver::class])]
class UserPreference extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fire_updated_observer()
    {
        return $this->fireModelEvent('updated', false);
    }

    public function fire_created_observer()
    {
        return $this->fireModelEvent('created', false);
    }
}
