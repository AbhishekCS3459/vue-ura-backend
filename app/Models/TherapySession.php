<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapySession extends Model
{
    protected $table = 'therapy_sessions';

    protected $fillable = [
        'patient_name',
        'patient_id',
        'phone',
        'therapy_type',
        'staff_id',
        'branch_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'whatsapp_status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
