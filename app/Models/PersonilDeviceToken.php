<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonilDeviceToken extends Model
{
    protected $fillable = [
        'personil_id',
        'token',
        'platform',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function personil(): BelongsTo
    {
        return $this->belongsTo(Personil::class);
    }
}
