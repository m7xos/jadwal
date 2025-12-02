<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Kegiatan extends Model
{
    use HasFactory;

    protected $table = 'kegiatans';

    protected $fillable = [
        'jenis_surat',
        'nomor',
        'nama_kegiatan',
        'tanggal',
        'waktu',
        'tempat',
        'keterangan',
                'surat_undangan',   // <--- TAMBAHKAN
                'sudah_disposisi',   // <--- baru
        'batas_tindak_lanjut',
        'tl_reminder_sent_at',
    ];

    protected $casts = [
        'batas_tindak_lanjut' => 'datetime',
        'tl_reminder_sent_at' => 'datetime',
        'tanggal' => 'date',
        'sudah_disposisi' => 'boolean',   // <--- baru
    ];

    public function personils()
    {
        return $this->belongsToMany(Personil::class, 'kegiatan_personil')
            ->withTimestamps();
    }

    public function getTanggalLabelAttribute(): string
    {
        if (! $this->tanggal) {
            return '-';
        }

        // Pastikan locale Carbon dan app di-set ke 'id' jika mau Indonesia
        //return $this->tanggal->translatedFormat('l, d-m-Y');
		// Hasil contoh: "Senin, 24 November 2025"
        return $this->tanggal
            ->locale('id')
            ->isoFormat('dddd, D MMMM Y');
    }

    public function getJudulSingkatAttribute(): string
    {
        return $this->nama_kegiatan . ' (' . $this->tanggal_label . ')';
    }
}
