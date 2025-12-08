<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'wablas_group_id',
        'keterangan',
    ];

    public function personils(): BelongsToMany
    {
        return $this->belongsToMany(Personil::class, 'group_personil')->withTimestamps();
    }

    public function kegiatans(): BelongsToMany
    {
        return $this->belongsToMany(Kegiatan::class, 'group_kegiatan')->withTimestamps();
    }
}
