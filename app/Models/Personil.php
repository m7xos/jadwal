<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class Personil extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory;
    use Notifiable;

    protected $table = 'personils';

    protected $fillable = [
        'nama',
		'nip', 
        'jabatan',
        'no_wa',
        'keterangan',
        'password',
        'role',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'role' => UserRole::class,
        'password' => 'hashed',
    ];

    public function kegiatans()
    {
        return $this->belongsToMany(Kegiatan::class, 'kegiatan_personil')
            ->withTimestamps();
    }

    public function getLabelAttribute(): string
    {
        $parts = [$this->nama];

        if ($this->jabatan) {
            $parts[] = '(' . $this->jabatan . ')';
        }

        return implode(' ', $parts);
    }

    protected static function booted(): void
    {
        static::saving(function (Personil $personil): void {
            if ($personil->isDirty('no_wa')) {
                $normalized = static::normalizePhone($personil->no_wa);
                $personil->no_wa = $normalized;

                if ($normalized) {
                    $personil->password = Hash::make($normalized);
                }
            }

            if (! $personil->role) {
                $personil->role = UserRole::Pengguna;
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array(
            $this->role?->value,
            [
                UserRole::Admin->value,
                UserRole::Arsiparis->value,
                UserRole::Pengguna->value,
            ],
            true
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isArsiparis(): bool
    {
        return $this->role === UserRole::Arsiparis;
    }

    public function isPengguna(): bool
    {
        return $this->role === UserRole::Pengguna;
    }

    public function getFilamentName(): string
    {
        return $this->nama ?? $this->attributes['nama'] ?? 'Pengguna';
    }

    protected static function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }

        return $digits;
    }
}
