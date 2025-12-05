<?php

namespace App\Models;

use App\Models\TindakLanjutReminderLog;
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
        'tampilkan_di_public',
        'batas_tindak_lanjut',
        'tl_reminder_sent_at',
    ];

    protected $casts = [
        'batas_tindak_lanjut' => 'datetime',
        'tl_reminder_sent_at' => 'datetime',
        'tanggal' => 'date',
        'sudah_disposisi' => 'boolean',   // <--- baru
        'tampilkan_di_public' => 'boolean',
        'tindak_lanjut_deadline' => 'datetime',
        'tindak_lanjut_reminder_sent_at' => 'datetime',
    ];

    public function personils()
    {
        return $this->belongsToMany(Personil::class, 'kegiatan_personil')
            ->withTimestamps();
    }

    public function tindakLanjutReminderLogs()
    {
        return $this->hasMany(TindakLanjutReminderLog::class);
    }

    protected static function booted(): void
    {
        static::saved(function (Kegiatan $kegiatan) {
            if ($kegiatan->jenis_surat !== 'tindak_lanjut') {
                return;
            }

            if ($kegiatan->tl_reminder_sent_at) {
                return;
            }

            if (! $kegiatan->batas_tindak_lanjut) {
                return;
            }

            $shouldEnsureLog = $kegiatan->wasRecentlyCreated
                || $kegiatan->wasChanged('jenis_surat')
                || $kegiatan->wasChanged('batas_tindak_lanjut');

            if (! $shouldEnsureLog) {
                return;
            }

            TindakLanjutReminderLog::firstOrCreate(
                ['kegiatan_id' => $kegiatan->id],
                ['status' => 'pending'],
            );
        });
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

    public function getTindakLanjutDeadlineLabelAttribute(): ?string
    {
        if (! $this->tindak_lanjut_deadline) {
            return null;
        }

        return $this->tindak_lanjut_deadline
            ->locale('id')
            ->isoFormat('dddd, D MMMM Y HH.mm');
    }
}
