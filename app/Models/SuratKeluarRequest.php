<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SuratKeluarRequest extends Model
{
    public const STATUS_WAITING_KLASIFIKASI = 'waiting_klasifikasi';
    public const STATUS_WAITING_HAL = 'waiting_hal';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'requester_number',
        'requester_personil_id',
        'group_id',
        'status',
        'kode_surat_id',
        'perihal',
        'source',
    ];

    public function requester()
    {
        return $this->belongsTo(Personil::class, 'requester_personil_id');
    }

    public function kodeSurat()
    {
        return $this->belongsTo(KodeSurat::class);
    }

    /**
     * @return Builder
     */
    public function scopeActiveFor(Builder $query, string $number): Builder
    {
        return $query
            ->where('requester_number', $number)
            ->whereIn('status', [
                static::STATUS_WAITING_KLASIFIKASI,
                static::STATUS_WAITING_HAL,
            ])
            ->orderByDesc('updated_at');
    }
}
