<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratKeluar extends Model
{
    public const STATUS_ISSUED = 'issued';
    public const STATUS_BOOKED = 'booked';
    public const BOOKED_PLACEHOLDER = 'Nomor sudah dibooking';

    protected $fillable = [
        'kode_surat_id',
        'tahun',
        'nomor_urut',
        'nomor_sisipan',
        'tanggal_surat',
        'master_id',
        'perihal',
        'status',
        'booked_at',
        'requested_by_number',
        'requested_by_personil_id',
        'request_id',
        'source',
    ];

    protected $casts = [
        'nomor_urut' => 'int',
        'nomor_sisipan' => 'int',
        'tahun' => 'int',
        'tanggal_surat' => 'date',
        'booked_at' => 'date',
    ];

    public function kodeSurat()
    {
        return $this->belongsTo(KodeSurat::class);
    }

    public function master()
    {
        return $this->belongsTo(self::class, 'master_id');
    }

    public function requester()
    {
        return $this->belongsTo(Personil::class, 'requested_by_personil_id');
    }

    public function getNomorFormatAttribute(): string
    {
        if ($this->nomor_sisipan > 0) {
            return $this->nomor_urut . '.' . $this->nomor_sisipan;
        }

        return (string) $this->nomor_urut;
    }

    public function getNomorLabelAttribute(): string
    {
        $kode = $this->kodeSurat?->kode ?? '-';

        return $kode . '/' . $this->nomor_format;
    }
}
