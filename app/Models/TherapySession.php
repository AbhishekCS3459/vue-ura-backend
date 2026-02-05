<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapySession extends Model
{
    protected $table = 'therapy_sessions';

    protected $fillable = [
        'patient_name', // Legacy field
        'emr_patient_id', // EMR patient ID (legacy string field, renamed from patient_id)
        'phone',
        'therapy_type', // Legacy field
        'staff_id',
        'branch_id',
        'room_id',
        'treatment_id',
        'patient_id', // Foreign key to patients table (integer)
        'date',
        'start_time',
        'end_time',
        'status',
        'whatsapp_status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        // start_time and end_time are stored as TIME (HH:MM:SS) in database
        // Laravel doesn't have a built-in 'time' cast, so we'll handle them as strings
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(BranchRoom::class, 'room_id');
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
