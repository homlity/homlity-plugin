<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builder-neutral icon renderer used by shared property templates.
 */
final class IconRenderer
{
    public static function render(array $icon, array $attributes = []): void
    {
        if (class_exists('\\Elementor\\Icons_Manager')) {
            \Elementor\Icons_Manager::render_icon($icon, $attributes);
            return;
        }

        $value = trim((string) ($icon['value'] ?? ''));
        if ($value === '') {
            return;
        }

        $classes = array_values(array_filter(array_map(
            'sanitize_html_class',
            preg_split('/\s+/', $value) ?: []
        )));
        if ($classes === []) {
            return;
        }

        $htmlAttributes = '';
        foreach ($attributes as $name => $attributeValue) {
            $attributeName = sanitize_key((string) $name);
            if ($attributeName === '') {
                continue;
            }
            $htmlAttributes .= sprintf(
                ' %s="%s"',
                esc_attr($attributeName),
                esc_attr((string) $attributeValue)
            );
        }

        echo '<i class="' . esc_attr(implode(' ', $classes)) . '"' . $htmlAttributes . '></i>';
    }
}
