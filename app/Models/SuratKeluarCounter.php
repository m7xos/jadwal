<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratKeluarCounter extends Model
{
    protected $fillable = [
        'kode_surat_id',
        'tahun',
        'last_number',
    ];

    public function kodeSurat()
    {
        return $this->belongsTo(KodeSurat::class);
    }
}
