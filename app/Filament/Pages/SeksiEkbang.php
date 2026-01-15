<?php

namespace App\Filament\Pages;

use App\Support\RoleAccess;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class SeksiEkbang extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel = 'Dashboard Seksi Ekbang';
    protected static string|UnitEnum|null $navigationGroup = 'Seksi Ekbang';
    protected static ?string $slug = 'seksi-ekbang';
    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.seksi-ekbang';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (! $user || ! RoleAccess::canSeeNav($user, 'filament.admin.pages.seksi-ekbang')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $akronim = strtolower(trim((string) ($user->jabatan_akronim ?? '')));

        return $akronim === 'ekbang';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user || ! RoleAccess::canAccessRoute($user, 'filament.admin.pages.seksi-ekbang')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $akronim = strtolower(trim((string) ($user->jabatan_akronim ?? '')));

        return $akronim === 'ekbang';
    }
}
