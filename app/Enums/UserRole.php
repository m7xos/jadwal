<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Arsiparis = 'arsiparis';
    case Pengguna = 'pengguna';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Arsiparis => 'Arsiparis',
            self::Pengguna => 'Pengguna',
        };
    }
}
