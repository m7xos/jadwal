<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class WaGatewaySetting extends Model
{
    protected $fillable = [
        'base_url',
        'token',
        'key',
        'secret_key',
        'provider',
        'finish_whitelist',
    ];

    public static function current(): self
    {
        if (! Schema::hasTable('wa_gateway_settings')) {
            return new self(static::defaults());
        }

        return static::firstOrCreate(['id' => 1], static::defaults());
    }

    public static function defaults(): array
    {
        return [
            'base_url' => config('wa_gateway.base_url'),
            'token' => config('wa_gateway.token'),
            'key' => config('wa_gateway.key'),
            'secret_key' => config('wa_gateway.secret_key'),
            'provider' => config('wa_gateway.provider', 'wa-gateway'),
            'finish_whitelist' => config('wa_gateway.finish_whitelist'),
        ];
    }

    public function groupMappings(): array
    {
        return [];
    }
}
