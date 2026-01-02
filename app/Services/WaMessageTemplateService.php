<?php

namespace App\Services;

use App\Models\WaMessageTemplate;

class WaMessageTemplateService
{
    /**
     * Render template by key. If template is missing, returns fallback.
     *
     * @param array<string, string> $data
     */
    public function render(string $key, array $data, string $fallback): string
    {
        $template = WaMessageTemplate::contentFor($key);

        if (! $template) {
            return $fallback;
        }

        return $this->renderString($template, $data);
    }

    /**
     * Render template string by replacing {placeholders}.
     *
     * @param array<string, string> $data
     */
    public function renderString(string $template, array $data): string
    {
        foreach ($this->commonPlaceholders() as $placeholder) {
            if (! array_key_exists($placeholder, $data)) {
                $data[$placeholder] = '';
            }
        }

        $replace = [];

        foreach ($data as $key => $value) {
            $replace['{' . $key . '}'] = $value;
        }

        $result = strtr($template, $replace);
        $result = $this->convertMarkdownToWhatsApp($result);

        // Trim extra blank lines but preserve intentional spacing.
        $result = preg_replace("/\\n{3,}/", "\n\n", $result) ?? $result;

        return trim($result);
    }

    /**
     * @return array<int, string>
     */
    protected function commonPlaceholders(): array
    {
        return [
            'personil_block',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function metaFor(string $key): array
    {
        $record = WaMessageTemplate::query()->where('key', $key)->first();
        $meta = $record?->meta;

        return is_array($meta) ? $meta : [];
    }

    public function includePersonilTag(string $key, bool $default = true): bool
    {
        $meta = $this->metaFor($key);

        if (array_key_exists('include_personil_tag', $meta)) {
            return (bool) $meta['include_personil_tag'];
        }

        return $default;
    }

    protected function convertMarkdownToWhatsApp(string $text): string
    {
        $text = preg_replace('/\\*\\*(.+?)\\*\\*/s', '*$1*', $text) ?? $text;
        $text = preg_replace('/__(.+?)__/s', '_$1_', $text) ?? $text;
        $text = preg_replace('/~~(.+?)~~/s', '~$1~', $text) ?? $text;
        $text = preg_replace('/\\[(.+?)\\]\\((https?:\\/\\/[^)]+)\\)/', '$1 ($2)', $text) ?? $text;

        return $text;
    }
}
