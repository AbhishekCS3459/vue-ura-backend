<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Staff extends Model
{
    protected $fillable = [
        'name',
        'gender',
        'role',
        'phone',
        'session_types', // Legacy JSON field (kept for backward compatibility)
        'availability',
        'branch_id',
    ];

    protected $casts = [
        'session_types' => 'array',
        'availability' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function therapySessions(): HasMany
    {
        return $this->hasMany(TherapySession::class);
    }

    /**
     * Treatments that this staff can perform (many-to-many)
     */
    public function treatments(): BelongsToMany
    {
        return $this->belongsToMany(Treatment::class, 'staff_treatment_assignments', 'staff_id', 'treatment_id');
    }
}
