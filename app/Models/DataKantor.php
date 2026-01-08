<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class DataKantor extends Model
{
    protected $fillable = [
        'jenis_dokumen',
        'nama_dokumen',
        'tahun',
        'keterangan',
        'berkas',
    ];

    protected $casts = [
        'tahun' => 'int',
    ];

    public function getBerkasUrlAttribute(): ?string
    {
        if (! $this->berkas) {
            return null;
        }

        $relativeUrl = Storage::disk('public')->url($this->encodePathForUrl($this->berkas));

        return URL::to($relativeUrl);
    }

    protected function encodePathForUrl(string $path): string
    {
        $segments = array_map('rawurlencode', explode('/', $path));

        return implode('/', $segments);
    }
}
