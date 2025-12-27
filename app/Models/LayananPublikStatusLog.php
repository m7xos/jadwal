<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LayananPublikStatusLog extends Model
{
    protected $fillable = [
        'layanan_publik_request_id',
        'status',
        'catatan',
        'created_by_personil_id',
    ];

    public function layananRequest()
    {
        return $this->belongsTo(LayananPublikRequest::class, 'layanan_publik_request_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Personil::class, 'created_by_personil_id');
    }
}
