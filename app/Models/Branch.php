<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'city',
        'opening_hours',
    ];

    protected $casts = [
        'opening_hours' => 'array',
    ];

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function therapySessions(): HasMany
    {
        return $this->hasMany(TherapySession::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(BranchRoom::class);
    }
}
