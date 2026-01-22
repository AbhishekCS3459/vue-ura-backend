<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InactivePatient extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'last_session_date',
        'days_since_last_session',
        'last_therapist',
        'last_therapist_id',
        'status',
        'last_action',
        'last_status_update',
        'next_follow_up_date',
    ];

    protected $casts = [
        'last_session_date' => 'date',
        'last_status_update' => 'datetime',
        'next_follow_up_date' => 'date',
    ];

    public function lastTherapist(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'last_therapist_id');
    }
}
