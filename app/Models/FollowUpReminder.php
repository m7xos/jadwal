<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'personil_id',
        'nama_kegiatan',
        'tanggal',
        'jam',
        'tempat',
        'keterangan',
        'no_wa',
        'normalized_no_wa',
        'send_via',
        'group_id',
        'status',
        'sent_count',
        'last_sent_at',
        'next_send_at',
        'acknowledged_at',
        'last_response',
        'last_error',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'last_sent_at' => 'datetime',
        'next_send_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'last_response' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (FollowUpReminder $reminder) {
            $reminder->status = $reminder->status ?? 'pending';
            $reminder->next_send_at = $reminder->next_send_at ?? now();
            $reminder->send_via = $reminder->send_via ?? 'personal';
            if ($reminder->user_id === null) {
                $currentUser = auth()->user();
                $reminder->user_id = $currentUser instanceof User ? $currentUser->id : null;
            }
        });

        static::saving(function (FollowUpReminder $reminder) {
            $normalized = PhoneNumber::normalize($reminder->no_wa);
            $reminder->normalized_no_wa = $normalized ?? $reminder->normalized_no_wa;
            $reminder->no_wa = $normalized ?? $reminder->no_wa;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function personil(): BelongsTo
    {
        return $this->belongsTo(Personil::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function getReminderCodeAttribute(): string
    {
        return 'PR-' . $this->id;
    }

    public function scopeAwaitingAck($query)
    {
        return $query->whereNull('acknowledged_at');
    }
}
