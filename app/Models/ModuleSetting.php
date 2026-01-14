<?php

namespace App\Models;

use App\Support\RoleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ModuleSetting extends Model
{
    protected $fillable = [
        'enabled_pages',
    ];

    protected $casts = [
        'enabled_pages' => 'array',
    ];

    public static function current(): self
    {
        if (! Schema::hasTable('module_settings')) {
            return new self(['enabled_pages' => []]);
        }

        return static::firstOrCreate(['id' => 1], [
            'enabled_pages' => static::defaultEnabledPages(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function enabledPages(): array
    {
        $setting = static::current();
        $pages = $setting->enabled_pages;

        if (! is_array($pages)) {
            return [];
        }

        $pages = array_values(array_filter($pages, fn ($item) => is_string($item) && $item !== ''));

        return array_values(array_unique($pages));
    }

    /**
     * @return array<int, string>
     */
    public static function defaultEnabledPages(): array
    {
        $options = RoleAccess::pageOptions(false);

        return array_values(array_filter(array_keys($options), fn ($key) => $key !== '*'));
    }
}
