<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaInboxMessage extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_REPLIED = 'replied';

    protected $fillable = [
        'sender_number',
        'sender_name',
        'message',
        'received_at',
        'status',
        'assigned_to',
        'assigned_at',
        'replied_by',
        'replied_at',
        'reply_message',
        'meta',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'assigned_at' => 'datetime',
        'replied_at' => 'datetime',
        'meta' => 'array',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Personil::class, 'assigned_to');
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(Personil::class, 'replied_by');
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'Baru',
            self::STATUS_ASSIGNED => 'Diambil',
            self::STATUS_REPLIED => 'Dibalas',
        ];
    }
}
