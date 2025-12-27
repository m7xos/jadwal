<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LayananPublik extends Model
{
    protected $fillable = [
        'nama',
        'kategori',
        'deskripsi',
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'bool',
    ];
}
