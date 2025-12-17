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
        'wa_gateway_group_id',
        'is_default',
        'keterangan',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Group $group): void {
            $group->is_default = (bool) $group->is_default;

            if (! $group->is_default) {
                return;
            }

            $query = static::query()->where('is_default', true);

            if ($group->exists) {
                $query->where('id', '!=', $group->id);
            }

            $query->update(['is_default' => false]);
        });
    }

    public function personils(): BelongsToMany
    {
        return $this->belongsToMany(Personil::class, 'group_personil')->withTimestamps();
    }

    public function kegiatans(): BelongsToMany
    {
        return $this->belongsToMany(Kegiatan::class, 'group_kegiatan')->withTimestamps();
    }
}
