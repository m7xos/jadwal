<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_pemda',
        'kode_upb',
        'nama_upb',
        'kode_aset',
        'nama_aset',
        'reg',
        'merk_type',
        'ukuran_cc',
        'bahan',
        'tahun',
        'nomor_pabrik',
        'nomor_rangka',
        'nomor_mesin',
        'nomor_polisi',
        'nomor_bpkb',
        'harga',
        'keterangan',
    ];

    protected $casts = [
        'tahun' => 'date',
        'harga' => 'decimal:2',
    ];
}
