<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaMessageTemplate extends Model
{
    protected $fillable = [
        'key',
        'content',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public static function contentFor(string $key): ?string
    {
        $record = static::query()->where('key', $key)->first();

        $content = $record?->content;
        if (! is_string($content)) {
            return null;
        }

        $content = trim($content);

        return $content !== '' ? $content : null;
    }
}
