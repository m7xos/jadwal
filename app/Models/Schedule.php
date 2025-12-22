<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_group',
        'title',
        'starts_at',
        'location',
        'notes',
        'is_disposed',
    ];

    protected $casts = [
        'starts_at'   => 'datetime',
        'is_disposed' => 'boolean',
    ];
}
