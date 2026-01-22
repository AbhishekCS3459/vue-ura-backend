<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Treatment extends Model
{
    protected $fillable = [
        'name',
        'gender',
    ];

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(RoomTreatmentAssignment::class);
    }
}
