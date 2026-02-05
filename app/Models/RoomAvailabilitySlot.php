<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAvailabilitySlot extends Model
{
    protected $table = 'room_availability_slots';

    protected $fillable = [
        'branch_id',
        'room_id',
        'date',
        'time_slot',
        'status',
        'booking_id',
    ];

    protected $casts = [
        'date' => 'date',
        'time_slot' => 'string', // Stored as TIME in DB, but we'll work with it as string
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(BranchRoom::class, 'room_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TherapySession::class, 'booking_id');
    }
}
