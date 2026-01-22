<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    protected $fillable = [
        'name',
        'gender',
        'role',
        'phone',
        'session_types',
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
}
