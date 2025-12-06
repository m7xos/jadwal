<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleTaxSetting extends Model
{
    protected $fillable = [
        'personil_id',
        'pengurus_barang_nama',
        'pengurus_barang_no_wa',
        'pengurus_barang_nip',
    ];

    protected $casts = [
        'personil_id' => 'integer',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'personil_id' => null,
                'pengurus_barang_nama' => null,
                'pengurus_barang_no_wa' => null,
                'pengurus_barang_nip' => null,
            ],
        );
    }

    public function pengurusBarangPersonil(): BelongsTo
    {
        return $this->belongsTo(Personil::class, 'personil_id');
    }

    public function setPengurusBarangNoWaAttribute(?string $value): void
    {
        $this->attributes['pengurus_barang_no_wa'] = PhoneNumber::normalize($value);
    }

    public function setPengurusBarangNamaAttribute(?string $value): void
    {
        $this->attributes['pengurus_barang_nama'] = $value ? trim($value) : null;
    }

    public function setPengurusBarangNipAttribute(?string $value): void
    {
        $this->attributes['pengurus_barang_nip'] = $value ? trim($value) : null;
    }

    public function getResolvedPengurusBarangNamaAttribute(): ?string
    {
        return $this->pengurusBarangPersonil?->nama
            ?? $this->pengurus_barang_nama;
    }

    public function getPengurusBarangNoWaNormalizedAttribute(): ?string
    {
        return PhoneNumber::normalize($this->pengurus_barang_no_wa);
    }

    public function getResolvedPengurusBarangNoWaAttribute(): ?string
    {
        $fromPersonil = $this->pengurusBarangPersonil?->no_wa;

        if ($fromPersonil) {
            return PhoneNumber::normalize($fromPersonil);
        }

        return $this->pengurus_barang_no_wa_normalized;
    }

    public function getResolvedPengurusBarangNipAttribute(): ?string
    {
        return $this->pengurusBarangPersonil?->nip
            ?? $this->pengurus_barang_nip;
    }
}
