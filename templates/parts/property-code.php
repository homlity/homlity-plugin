<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Property code component.
 * Overridable at homlity-real-estate/parts/property-code.php
 *
 * Expected args:
 *   $post_id (int)
 *   $code (string, optional: se resuelve solo si no llega)
 *   $before_text, $after_text, $fallback_text (string)
 *   $keep_spaces (bool)   — pinta los espacios del texto tal como se escribieron
 *   $html_tag (string)
 *   $link_url (string)    — vacío: sin enlace
 *   $link_scope (string)  — 'all' | 'code'
 *   $open_in_new_tab (bool)
 */

use Homlity\PluginInmobiliario\Services\PropertyCodeResolver;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$code = trim(isset($code) ? (string) $code : PropertyCodeResolver::forDisplay((int) $post_id));

$fallbackText = trim((string) ($fallback_text ?? ''));

if ($code === '' && $fallbackText === '') {
    return;
}

$keepSpaces = !isset($keep_spaces) || (bool) $keep_spaces;
$beforeText = (string) ($before_text ?? '');
$afterText  = (string) ($after_text ?? '');

if (!$keepSpaces) {
    $beforeText = trim($beforeText);
    $afterText  = trim($afterText);
}

$allowedTags = ['div', 'p', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
$tag         = strtolower((string) ($html_tag ?? 'div'));
if (!in_array($tag, $allowedTags, true)) {
    $tag = 'div';
}

$linkUrl   = esc_url_raw((string) ($link_url ?? ''));
$linkScope = ($link_scope ?? 'all') === 'code' ? 'code' : 'all';
$linkAttrs = '';
if ($linkUrl !== '') {
    $linkAttrs = ' href="' . esc_url($linkUrl) . '"';
    if (!isset($open_in_new_tab) || $open_in_new_tab) {
        $linkAttrs .= ' target="_blank" rel="noopener noreferrer"';
    }
}

/**
 * Los espacios que se escriben en «Código: » son parte del texto: el navegador
 * colapsa el que queda al final del <span> y las partes acabarían pegadas.
 * Con la opción desactivada se separan igual, pero con un espacio simple.
 */
$escape = static function (string $text) use ($keepSpaces): string {
    $escaped = esc_html($text);

    return $keepSpaces ? str_replace(' ', '&nbsp;', $escaped) : $escaped;
};

// Sin código manda el texto de respaldo, y los textos de alrededor se callan:
// «Código: consúltanos con un asesor» no dice lo que se quiso decir.
$hasCode = $code !== '';
$value   = $hasCode ? $code : $fallbackText;

$separator  = $keepSpaces ? '' : ' ';
$beforeHtml = $hasCode && $beforeText !== ''
    ? '<span class="homlity-property-code__before">' . $escape($beforeText) . '</span>' . $separator
    : '';
$valueHtml  = '<span class="homlity-property-code__value">' . esc_html($value) . '</span>';
$afterHtml  = $hasCode && $afterText !== ''
    ? $separator . '<span class="homlity-property-code__after">' . $escape($afterText) . '</span>'
    : '';

if ($linkUrl === '') {
    $inner = $beforeHtml . $valueHtml . $afterHtml;
} elseif ($linkScope === 'code') {
    $inner = $beforeHtml . '<a class="homlity-property-code__link"' . $linkAttrs . '>' . $valueHtml . '</a>' . $afterHtml;
} else {
    $inner = '<a class="homlity-property-code__link"' . $linkAttrs . '>' . $beforeHtml . $valueHtml . $afterHtml . '</a>';
}

$classes = 'homlity-property-code';
if ($linkUrl !== '') {
    $classes .= ' homlity-property-code--linked homlity-property-code--link-' . $linkScope;
}

printf(
    '<%1$s class="%2$s">%3$s</%1$s>',
    esc_attr($tag),
    esc_attr($classes),
    $inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partes ya escapadas arriba.
);
