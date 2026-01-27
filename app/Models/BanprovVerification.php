<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BanprovVerification extends Model
{
    protected $fillable = [
        'tahap',
        'kecamatan',
        'desa',
        'no_dpa',
        'jenis_kegiatan',
        'jumlah',
        'status_lpj',
        'status_monev',
        'sumber_file',
    ];

    protected $casts = [
        'jumlah' => 'int',
        'status_lpj' => 'bool',
        'status_monev' => 'bool',
    ];
}
