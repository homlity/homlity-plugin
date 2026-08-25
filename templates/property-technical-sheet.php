<?php
/**
 * Technical sheet full page template.
 *
 * This file is the *fallback* renderer. When a page is configured in
 * Configuración → Plantillas ("Página de ficha técnica") and that page is built
 * with Elementor, Divi or WPBakery, the rewrite resolves /ficha-tecnica/{slug}/
 * to that page and the builder renders it directly — this template is not used.
 */

use Homlity\PluginInmobiliario\Services\TechnicalSheetService;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$homlitySheetPropertyId = TechnicalSheetService::currentPropertyId();

if ($homlitySheetPropertyId <= 0) {
    echo '<main class="homlity-tech-sheet"><p>' . esc_html__('Inmueble no encontrado.', 'homlity-real-estate') . '</p></main>';
    get_footer();
    return;
}

$homlitySheetBuilderPageId = TechnicalSheetService::pageId();
$homlitySheetRendered = false;

// Builder page configured but the request did not resolve to it (stale rewrite
// rules, or a theme template override). Render its content inline so the
// configured layout is still honoured.
if ($homlitySheetBuilderPageId > 0 && class_exists('\Elementor\Plugin')) {
    $homlitySheetContent = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($homlitySheetBuilderPageId, true);
    if (is_string($homlitySheetContent) && trim($homlitySheetContent) !== '') {
        echo $homlitySheetContent; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $homlitySheetRendered = true;
    }
}

if (!$homlitySheetRendered && $homlitySheetBuilderPageId > 0 && get_post_meta($homlitySheetBuilderPageId, '_homlity_seeded_builder', true) !== '') {
    $homlitySheetBuilderPage = get_post($homlitySheetBuilderPageId);
    if ($homlitySheetBuilderPage instanceof \WP_Post) {
        echo apply_filters('the_content', $homlitySheetBuilderPage->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- 'the_content' es un filtro del núcleo; aplicarlo es justo lo que se quiere.
        $homlitySheetRendered = true;
    }
}

if (!$homlitySheetRendered) {
    TemplateService::includeComponent('property-technical-sheet.php', [
        'post_id' => $homlitySheetPropertyId,
    ]);
}

get_footer();
