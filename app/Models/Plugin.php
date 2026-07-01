<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    protected $fillable = [
        'slug',
        'is_enabled',
        'installed_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'installed_at' => 'datetime',
    ];
}
