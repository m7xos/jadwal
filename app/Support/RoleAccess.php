<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\ModuleSetting;
use App\Models\RoleAccessSetting;
use App\Models\User;
use App\Models\Personil;
use BackedEnum;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use UnitEnum;

class RoleAccess
{
    /**
     * Daftar halaman Filament yang bisa dipilih di pengaturan.
     *
     * Key = prefix / nama route dasar. Middleware akan mengizinkan seluruh turunan (index/create/edit, dll).
     */
    public static function pageOptions(bool $applyModuleFilter = true): array
    {
        $options = [
            '*' => 'Semua halaman (akses penuh)',
            'filament.admin.pages.dashboard' => 'Dashboard',
            'filament.admin.pages.profile' => 'Profil Akun',
            'filament.admin.resources.kegiatans' => 'Agenda Surat Masuk',
            'filament.admin.resources.kegiatans-pkk' => 'Agenda PKK Kecamatan',
            'filament.admin.resources.follow-up-reminders' => 'Pengingat Kegiatan Lain',
            'filament.admin.resources.pajak-kendaraan' => 'Pajak Kendaraan Dinas',
            'filament.admin.resources.groups' => 'Group Whatsapp',
            'filament.admin.resources.personils' => 'Personil',
            'filament.admin.resources.vehicle-assets' => 'Data Kendaraan',
            'filament.admin.resources.data-kantor' => 'Daftar Dokumen Umum',
            'filament.admin.pages.pengurus-barang' => 'Pengurus Barang',
            'filament.admin.pages.wa-gateway' => 'Pengaturan WA Gateway',
            'filament.admin.pages.wa-message-templates' => 'Template Pesan WA',
            'filament.admin.pages.seksi-ekbang' => 'Dashboard Seksi Ekbang',
            'filament.admin.pages.verifikasi-banprov' => 'Verifikasi Banprov',
            'filament.admin.resources.wa-inbox-messages' => 'Chat Masuk WA',
            'filament.admin.resources.surat-keluar' => 'Surat Keluar',
            'filament.admin.resources.surat-keputusan' => 'Surat Keputusan',
            'filament.admin.resources.kode-surat' => 'Klasifikasi Surat',
            'filament.admin.resources.layanan-publik' => 'Layanan Publik',
            'filament.admin.resources.layanan-publik-register' => 'Register Layanan Publik',
            'filament.admin.pages.surat-keluar-status' => 'Status Nomor Surat Keluar',
            'filament.admin.pages.role-access-settings' => 'Pengaturan Hak Akses',
            'filament.admin.pages.module-settings' => 'Pengaturan Modul',
            'filament.admin.pages.panduan-aplikasi' => 'Panduan Aplikasi',
            'filament.admin.resources.tindak-lanjut-reminder-logs' => 'Log Pengingat TL',
            'filament.admin.resources.vehicle-tax-reminder-logs' => 'Log Pengingat Pajak',
            'filament.admin.resources.follow-up-reminder-logs' => 'Log Pengingat Lain',
            'filament.admin.pages.laporan-surat-masuk-bulanan' => 'Rekap Kegiatan',
            'filament.admin.pages.laporan-surat-keluar-bulanan' => 'Rekap Surat Keluar',
            'filament.admin.pages.laporan-pembayaran-pajak' => 'Laporan Pembayaran Pajak',
            'filament.admin.resources.personil-categories' => 'Kategori Personil',
        ];

        if (! $applyModuleFilter) {
            return $options;
        }

        $enabled = ModuleSetting::enabledPages();
        if (empty($enabled)) {
            return $options;
        }

        $filtered = ['*' => $options['*']];

        foreach ($options as $key => $label) {
            if ($key === '*') {
                continue;
            }

            if ($key === 'filament.admin.pages.module-settings' || in_array($key, $enabled, true)) {
                $filtered[$key] = $label;
            }
        }

        return $filtered;
    }

    /**
     * @param  array<int, string>  $exclude
     * @return array<string, array{label: string, options: array<string, string>}>
     */
    public static function pageOptionGroups(bool $applyModuleFilter = true, array $exclude = []): array
    {
        $options = static::pageOptions($applyModuleFilter);

        foreach ($exclude as $key) {
            unset($options[$key]);
        }

        $groupMap = static::navigationGroupMap();
        $groups = [];

        foreach ($options as $identifier => $label) {
            $groupLabel = static::resolveGroupLabel($identifier, $groupMap);
            $groupKey = Str::slug($groupLabel, '_');

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'label' => $groupLabel,
                    'options' => [],
                ];
            }

            $groups[$groupKey]['options'][$identifier] = $label;
        }

        return $groups;
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

        if (! static::isModuleEnabledForRoute($routeName)) {
            return false;
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

        if (! static::isModuleEnabled($identifier)) {
            return false;
        }

        return static::routeMatchesAllowed($identifier, static::allowedPagesFor($user->role));
    }

    /**
     * @return array<string, string>
     */
    protected static function navigationGroupMap(): array
    {
        $map = [];

        foreach (Filament::getPages() as $page) {
            if (! class_exists($page)) {
                continue;
            }

            $route = $page::getRouteName();
            $group = static::normalizeGroupLabel($page::getNavigationGroup());

            if ($route !== '') {
                $map[$route] = $group;
            }
        }

        foreach (Filament::getResources() as $resource) {
            if (! class_exists($resource)) {
                continue;
            }

            $route = $resource::getRouteBaseName();
            $group = static::normalizeGroupLabel($resource::getNavigationGroup());

            if ($route !== '') {
                $map[$route] = $group;
            }
        }

        return $map;
    }

    protected static function resolveGroupLabel(string $identifier, array $groupMap): string
    {
        $label = $groupMap[$identifier] ?? null;

        if ($label !== null && $label !== '') {
            return $label;
        }

        return 'Umum';
    }

    protected static function normalizeGroupLabel(string|UnitEnum|null $group): string
    {
        if ($group instanceof BackedEnum) {
            $group = (string) $group->value;
        } elseif ($group instanceof UnitEnum) {
            $group = $group->name;
        }

        $group = trim((string) $group);

        return $group === '' ? 'Umum' : $group;
    }

    public static function isModuleEnabled(string $identifier): bool
    {
        if ($identifier === 'filament.admin.pages.module-settings') {
            return true;
        }

        $enabled = ModuleSetting::enabledPages();

        if (empty($enabled)) {
            return true;
        }

        return in_array($identifier, $enabled, true);
    }

    public static function isModuleEnabledForRoute(string $routeName): bool
    {
        $enabled = ModuleSetting::enabledPages();

        if (empty($enabled)) {
            return true;
        }

        if (static::matches('filament.admin.pages.module-settings', $routeName)) {
            return true;
        }

        foreach ($enabled as $identifier) {
            if (static::matches($identifier, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
