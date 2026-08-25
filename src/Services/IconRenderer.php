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
        if (self::isDiviIcon($icon)) {
            self::renderDiviIcon((string) ($icon['value'] ?? ''), $attributes);
            return;
        }

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

        $attributes['class'] = trim(implode(' ', $classes) . ' ' . (string) ($attributes['class'] ?? ''));
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- htmlAttributes() escapa nombre y valor con esc_attr().
        echo '<i' . self::htmlAttributes($attributes) . '></i>';
    }

    private static function isDiviIcon(array $icon): bool
    {
        $value = trim((string) ($icon['value'] ?? ''));

        return ($icon['library'] ?? '') === 'divi'
            || str_contains($value, '||')
            || preg_match('/^%%\d+%%$/', $value) === 1;
    }

    private static function renderDiviIcon(string $value, array $attributes): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        $glyph = function_exists('et_pb_extended_process_font_icon')
            ? (string) et_pb_extended_process_font_icon($value)
            : self::decodeDiviIconValue($value);
        if ($glyph === '') {
            return;
        }

        $reportedFontFamily = function_exists('et_pb_get_icon_font_family')
            ? (string) et_pb_get_icon_font_family($value)
            : (str_contains($value, '||fa||') ? 'FontAwesome' : 'ETmodules');
        $fontWeight = function_exists('et_pb_get_icon_font_weight')
            ? (int) et_pb_get_icon_font_weight($value)
            : (preg_match('/\|\|(400|900)$/', $value, $matches) === 1 ? (int) $matches[1] : 400);
        $fontFamily = self::fontFamily($reportedFontFamily, $fontWeight, $glyph);

        // Do not use Divi's generic `et-pb-icon` class here. Divi assigns a
        // 96px font size to that class for its own standalone icon module,
        // which makes inline icons inside cards, buttons and labels enormous.
        $attributes['class'] = trim('homlity-divi-icon ' . (string) ($attributes['class'] ?? ''));
        $iconStyle = sprintf(
            'font-family:%s!important;font-weight:%d!important;font-style:normal;font-variant:normal;line-height:1;text-transform:none;',
            $fontFamily,
            $fontWeight
        );
        $attributes['style'] = $iconStyle . (string) ($attributes['style'] ?? '');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- htmlAttributes() escapa nombre y valor con esc_attr().
        echo '<span' . self::htmlAttributes($attributes) . '>' . esc_html($glyph) . '</span>';
    }

    private static function fontFamily(string $reportedFontFamily, int $fontWeight, string $glyph): string
    {
        if ($reportedFontFamily !== 'FontAwesome') {
            return 'HomlityDiviIcons';
        }

        if ($fontWeight === 900) {
            return 'HomlityDiviFontAwesomeSolid';
        }

        return self::isRegularFontAwesomeGlyph($glyph)
            ? 'HomlityDiviFontAwesomeRegular'
            : 'HomlityDiviFontAwesomeBrands';
    }

    private static function isRegularFontAwesomeGlyph(string $glyph): bool
    {
        static $regularGlyphs = null;

        if ($regularGlyphs === null) {
            $regularGlyphs = [];
            if (function_exists('et_pb_get_extended_font_icon_symbols')) {
                foreach ((array) et_pb_get_extended_font_icon_symbols() as $icon) {
                    if (!is_array($icon)
                        || !empty($icon['is_divi_icon'])
                        || (int) ($icon['font_weight'] ?? 0) !== 400
                        || !in_array('line', (array) ($icon['styles'] ?? []), true)) {
                        continue;
                    }

                    $decoded = html_entity_decode(
                        str_replace('&amp;', '&', (string) ($icon['unicode'] ?? '')),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    if ($decoded !== '') {
                        $regularGlyphs[$decoded] = true;
                    }
                }
            }
        }

        return isset($regularGlyphs[$glyph]);
    }

    private static function decodeDiviIconValue(string $value): string
    {
        if (preg_match('/^%%\d+%%$/', $value) === 1) {
            return function_exists('et_pb_process_font_icon')
                ? (string) et_pb_process_font_icon($value)
                : '';
        }

        $encoded = explode('||', $value, 2)[0] ?? '';
        return html_entity_decode($encoded, ENT_QUOTES, 'UTF-8');
    }

    private static function htmlAttributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $attributeValue) {
            $attributeName = sanitize_key((string) $name);
            if ($attributeName === '' || $attributeValue === null || $attributeValue === false) {
                continue;
            }
            $html .= sprintf(
                ' %s="%s"',
                esc_attr($attributeName),
                esc_attr((string) $attributeValue)
            );
        }

        return $html;
    }
}
