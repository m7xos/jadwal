<?php

namespace App\Filament\Pages;

use App\Support\RoleAccess;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class PanduanAplikasi extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Panduan Aplikasi';
    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';
    protected static ?string $slug = 'panduan-aplikasi';
    protected static ?int $navigationSort = 300;

    protected string $view = 'filament.pages.panduan-aplikasi';

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.pages.panduan-aplikasi');
    }
}
