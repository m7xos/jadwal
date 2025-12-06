<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleTaxReminderLog extends Model
{
    protected $fillable = [
        'vehicle_tax_id',
        'type',
        'stage',
        'status',
        'sent_at',
        'error_message',
        'response',
    ];

    protected $casts = [
        'response' => 'array',
        'sent_at' => 'datetime',
    ];

    public function vehicleTax(): BelongsTo
    {
        return $this->belongsTo(VehicleTax::class);
    }
}
