<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratKeluarGlobalCounter extends Model
{
    protected $fillable = [
        'tahun',
        'last_number',
    ];

    protected $casts = [
        'tahun' => 'int',
        'last_number' => 'int',
    ];
}
