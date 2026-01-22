<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BranchRoom extends Model
{
    protected $fillable = [
        'name',
        'branch_id',
        'gender',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(RoomTreatmentAssignment::class, 'room_id');
    }
}
