<?php

namespace Homlity\PluginInmobiliario\Integrations\Shared;

final class FilterShadow
{
    public static function css(mixed $value, string $position = ''): string
    {
        if (!is_array($value)) {
            return is_scalar($value) ? trim((string) $value) : '';
        }
        if (!isset($value['horizontal'], $value['vertical'], $value['blur'])) {
            return '';
        }
        $parts = [];
        if ($position === 'inset') {
            $parts[] = 'inset';
        }
        foreach (['horizontal', 'vertical', 'blur', 'spread'] as $key) {
            $number = $value[$key] ?? 0;
            if (!is_numeric($number)) {
                return '';
            }
            $parts[] = (float) $number . 'px';
        }
        $parts[] = (string) ($value['color'] ?? 'rgba(0,0,0,.25)');
        return implode(' ', $parts);
    }
}
