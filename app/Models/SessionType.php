<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionType extends Model
{
    protected $fillable = [
        'name',
        'duration_minutes',
    ];
}
