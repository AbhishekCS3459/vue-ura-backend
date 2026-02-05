<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = [
        'patient_id',
        'name',
        'gender',
        'phone',
        'email',
        'date_of_birth',
        'address',
        'emr_system_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    /**
     * Get all therapy sessions for this patient
     */
    public function therapySessions(): HasMany
    {
        return $this->hasMany(TherapySession::class);
    }
}
