<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\RoleAccessSetting;
use App\Models\User;
use App\Models\Personil;

class RoleAccess
{
    /**
     * Daftar halaman Filament yang bisa dipilih di pengaturan.
     *
     * Key = prefix / nama route dasar. Middleware akan mengizinkan seluruh turunan (index/create/edit, dll).
     */
    public static function pageOptions(): array
    {
        return [
            '*' => 'Semua halaman (akses penuh)',
            'filament.admin.pages.dashboard' => 'Dashboard',
            'filament.admin.pages.profile' => 'Profil Akun',
            'filament.admin.resources.kegiatans' => 'Agenda Kegiatan Kantor',
            'filament.admin.resources.follow-up-reminders' => 'Pengingat Kegiatan Lainnya',
            'filament.admin.resources.pajak-kendaraan' => 'Pajak Kendaraan Dinas',
            'filament.admin.resources.groups' => 'Group Whatsapp',
            'filament.admin.resources.personils' => 'Personil',
            'filament.admin.resources.vehicle-assets' => 'Data Kendaraan',
            'filament.admin.pages.pengurus-barang' => 'Pengurus Barang',
            'filament.admin.pages.wa-gateway' => 'Pengaturan WA Gateway',
            'filament.admin.pages.wa-message-templates' => 'Template Pesan WA',
            'filament.admin.resources.surat-keluar' => 'Surat Keluar',
            'filament.admin.resources.surat-keputusan' => 'Surat Keputusan',
            'filament.admin.pages.surat-keluar-status' => 'Status Nomor Surat Keluar',
            'filament.admin.pages.role-access-settings' => 'Pengaturan Hak Akses',
            'filament.admin.pages.panduan-aplikasi' => 'Panduan Aplikasi',
            'filament.admin.resources.tindak-lanjut-reminder-logs' => 'Log Pengingat TL',
            'filament.admin.resources.vehicle-tax-reminder-logs' => 'Log Pengiriman Pengingat Pajak',
            'filament.admin.resources.follow-up-reminder-logs' => 'Log Pengingat Kegiatan Lainnya',
            'filament.admin.pages.laporan-surat-masuk-bulanan' => 'Rekap Kegiatan',
            'filament.admin.pages.laporan-pembayaran-pajak' => 'Laporan Pembayaran Pajak',
            'filament.admin.resources.personil-categories' => 'Kategori Personil',
        ];
    }

    public static function allowedPagesFor(UserRole|string|null $role): array
    {
        return RoleAccessSetting::allowedPagesFor($role);
    }

    public static function canAccessRoute(User|Personil|null $user, ?string $routeName): bool
    {
        if (! $user) {
            return false;
        }

        if (! $routeName || ! str_starts_with($routeName, 'filament.')) {
            return true;
        }

        // Izinkan rute autentikasi atau reset password tanpa pengecekan peran tambahan.
        if (str_contains($routeName, '.auth.') || str_contains($routeName, '.password-reset.')) {
            return true;
        }

        $allowedPages = static::allowedPagesFor($user->role);

        return static::routeMatchesAllowed($routeName, $allowedPages);
    }

    public static function routeMatchesAllowed(string $routeName, array $allowedPages): bool
    {
        foreach ($allowedPages as $allowed) {
            if (static::matches($allowed, $routeName)) {
                return true;
            }
        }

        return false;
    }

    public static function matches(string $allowed, string $routeName): bool
    {
        return $allowed === '*'
            || $routeName === $allowed
            || str_starts_with($routeName, "{$allowed}.");
    }

    public static function canSeeNav(User|Personil|null $user, string $identifier): bool
    {
        if (! $user) {
            return false;
        }

        return static::routeMatchesAllowed($identifier, static::allowedPagesFor($user->role));
    }
}
