<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LayananPublikRequest extends Model
{
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_READY = 'ready';
    public const STATUS_PICKED_BY_VILLAGE = 'picked_by_village';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'layanan_publik_id',
        'kode_register',
        'nama_pemohon',
        'no_wa_pemohon',
        'status',
        'tanggal_masuk',
        'tanggal_selesai',
        'perangkat_desa_nama',
        'perangkat_desa_wa',
        'catatan',
        'source',
    ];

    protected $casts = [
        'tanggal_masuk' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function layanan()
    {
        return $this->belongsTo(LayananPublik::class, 'layanan_publik_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(LayananPublikStatusLog::class, 'layanan_publik_request_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_REGISTERED => 'Terdaftar',
            self::STATUS_IN_PROGRESS => 'Diproses',
            self::STATUS_READY => 'Siap Diambil',
            self::STATUS_PICKED_BY_VILLAGE => 'Diambil Perangkat Desa',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_CANCELLED => 'Dibatalkan',
        ];
    }
}
