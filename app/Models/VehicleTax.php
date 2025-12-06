<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class VehicleTax extends Model
{
    use HasFactory;

    protected $fillable = [
        'jenis_kendaraan',
        'plat_nomor',
        'personil_id',
        'tgl_pajak_tahunan',
        'tgl_pajak_lima_tahunan',
        'last_tahunan_reminder_sent_at',
        'last_lima_tahunan_reminder_sent_at',
        'last_tahunan_reminder_stage',
        'last_tahunan_reminder_for_date',
        'last_lima_tahunan_reminder_stage',
        'last_lima_tahunan_reminder_for_date',
        'status_pajak',
        'pajak_lunas_at',
    ];

    protected $casts = [
        'tgl_pajak_tahunan' => 'date',
        'tgl_pajak_lima_tahunan' => 'date',
        'last_tahunan_reminder_sent_at' => 'datetime',
        'last_lima_tahunan_reminder_sent_at' => 'datetime',
        'last_tahunan_reminder_for_date' => 'date',
        'last_lima_tahunan_reminder_for_date' => 'date',
        'pajak_lunas_at' => 'datetime',
    ];

    public function isPaid(): bool
    {
        return $this->status_pajak === 'lunas';
    }

    public function personil(): BelongsTo
    {
        return $this->belongsTo(Personil::class);
    }

    public function setPlatNomorAttribute(?string $value): void
    {
        $this->attributes['plat_nomor'] = strtoupper(trim((string) $value));
    }

    public function getJenisLabelAttribute(): string
    {
        return ucfirst((string) ($this->jenis_kendaraan ?? ''));
    }

    public function getPlatNomorLabelAttribute(): string
    {
        return strtoupper((string) ($this->plat_nomor ?? ''));
    }

    public function dueDateFor(string $type): ?Carbon
    {
        return match ($type) {
            'tahunan' => $this->tgl_pajak_tahunan,
            'lima_tahunan' => $this->tgl_pajak_lima_tahunan,
            default => null,
        };
    }

    public function lastReminderFieldFor(string $type): ?string
    {
        return match ($type) {
            'tahunan' => 'last_tahunan_reminder_sent_at',
            'lima_tahunan' => 'last_lima_tahunan_reminder_sent_at',
            default => null,
        };
    }
}
