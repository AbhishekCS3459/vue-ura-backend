<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomTreatmentAssignment extends Model
{
    protected $fillable = [
        'treatment_id',
        'room_id',
        'assigned',
    ];

    protected $casts = [
        'assigned' => 'boolean',
    ];

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(BranchRoom::class, 'room_id');
    }
}
