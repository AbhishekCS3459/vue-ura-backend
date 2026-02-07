<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Treatment extends Model
{
    protected $fillable = [
        'external_id',
        'name',
        'noof_sessions',
    ];

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(RoomTreatmentAssignment::class);
    }

    /**
     * Rooms that can perform this treatment (many-to-many)
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(BranchRoom::class, 'room_treatment_assignments', 'treatment_id', 'room_id');
    }

    /**
     * Staff that can perform this treatment (many-to-many)
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'staff_treatment_assignments', 'treatment_id', 'staff_id');
    }
}
