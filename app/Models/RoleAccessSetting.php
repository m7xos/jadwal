<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;

class RoleAccessSetting extends Model
{
    protected $fillable = [
        'role',
        'allowed_pages',
    ];

    protected $casts = [
        'role' => UserRole::class,
        'allowed_pages' => 'array',
    ];

    public static function getForRole(UserRole|string|null $role): self
    {
        $roleValue = $role instanceof UserRole ? $role->value : ($role ?? UserRole::Pengguna->value);

        return static::firstOrCreate(
            ['role' => $roleValue],
            ['allowed_pages' => static::defaultPagesFor($roleValue)],
        );
    }

    public static function allowedPagesFor(UserRole|string|null $role): array
    {
        return static::getForRole($role)->allowed_pages ?? [];
    }

    public static function defaultPagesFor(UserRole|string $role): array
    {
        $roleValue = $role instanceof UserRole ? $role->value : $role;

        return match ($roleValue) {
            UserRole::Admin->value => ['*'],
            UserRole::Arsiparis->value => [
                'filament.admin.pages.dashboard',
                'filament.admin.pages.profile',
                'filament.admin.resources.kegiatans',
                'filament.admin.resources.personils',
            ],
            default => [
                'filament.admin.pages.dashboard',
                'filament.admin.pages.profile',
                'filament.admin.resources.kegiatans',
            ],
        };
    }
}
