<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /**
     * Treatments that can be performed in this room (many-to-many)
     */
    public function treatments(): BelongsToMany
    {
        return $this->belongsToMany(Treatment::class, 'room_treatment_assignments', 'room_id', 'treatment_id');
    }

    /**
     * Therapy sessions booked in this room
     */
    public function therapySessions(): HasMany
    {
        return $this->hasMany(TherapySession::class, 'room_id');
    }

    /**
     * Availability slots for this room
     */
    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(RoomAvailabilitySlot::class, 'room_id');
    }
}
