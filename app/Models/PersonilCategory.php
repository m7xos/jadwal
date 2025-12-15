<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PersonilCategory extends Model
{
    use HasFactory;

    protected $table = 'personil_categories';

    protected $fillable = [
        'slug',
        'nama',
        'keterangan',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get active kategori options keyed by slug.
     */
    public static function options(): array
    {
        if (! Schema::hasTable('personil_categories')) {
            return static::fallbackOptions();
        }

        $options = static::query()
            ->where('is_active', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->pluck('nama', 'slug')
            ->all();

        if (! empty($options)) {
            return $options;
        }

        return static::fallbackOptions();
    }

    /**
     * Get label for a slug with graceful fallback.
     */
    public static function labelFor(?string $slug): string
    {
        if ($slug === null || $slug === '') {
            return 'Lainnya';
        }

        $options = static::options();

        if (isset($options[$slug])) {
            return $options[$slug];
        }

        return ucfirst(str_replace(['_', '-'], ' ', $slug));
    }

    /**
     * Resolve slug from free-form input (slug or label).
     */
    public static function slugFromInput(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $normalized = static::normalizeKey($value);

        $options = static::options();

        foreach ($options as $slug => $label) {
            if (
                $normalized === static::normalizeKey($slug)
                || $normalized === static::normalizeKey($label)
            ) {
                return $slug;
            }
        }

        $normalized = static::stripPersonilPrefix($normalized);

        $aliases = [
            'kel' => 'kelurahan',
            'kades' => 'kades_lurah',
            'lurah' => 'kades_lurah',
            'kades lurah' => 'kades_lurah',
            'sekdes' => 'sekdes_admin',
            'selur' => 'sekdes_admin',
            'admin' => 'sekdes_admin',
            'sekdes selur admin' => 'sekdes_admin',
        ];

        if (isset($aliases[$normalized]) && isset($options[$aliases[$normalized]])) {
            return $aliases[$normalized];
        }

        return null;
    }

    /**
     * Default seeds used when table still kosong.
     *
     * @return array<int, array{slug: string, nama: string, urutan: int}>
     */
    public static function defaultSeeds(): array
    {
        return [
            ['slug' => 'kecamatan', 'nama' => 'Personil Kecamatan', 'urutan' => 1],
            ['slug' => 'kelurahan', 'nama' => 'Personil Kelurahan', 'urutan' => 2],
            ['slug' => 'kades_lurah', 'nama' => 'Personil Kades/Lurah', 'urutan' => 3],
            ['slug' => 'sekdes_admin', 'nama' => 'Personil Sekdes/Selur/Admin', 'urutan' => 4],
        ];
    }

    protected static function normalizeKey(string $value): string
    {
        $value = Str::of($value)
            ->lower()
            ->replace(['-', '/', '\\'], ' ')
            ->squish()
            ->toString();

        return $value;
    }

    protected static function fallbackOptions(): array
    {
        return collect(static::defaultSeeds())
            ->pluck('nama', 'slug')
            ->all();
    }

    protected static function stripPersonilPrefix(string $value): string
    {
        $prefix = 'personil ';

        if (str_starts_with($value, $prefix)) {
            return substr($value, strlen($prefix));
        }

        return $value;
    }
}
