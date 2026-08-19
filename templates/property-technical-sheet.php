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

$sheetPropertyId = TechnicalSheetService::currentPropertyId();

if ($sheetPropertyId <= 0) {
    echo '<main class="homlity-tech-sheet"><p>' . esc_html__('Inmueble no encontrado.', 'homlity-real-estate') . '</p></main>';
    get_footer();
    return;
}

$sheetBuilderPageId = TechnicalSheetService::pageId();
$sheetRendered = false;

// Builder page configured but the request did not resolve to it (stale rewrite
// rules, or a theme template override). Render its content inline so the
// configured layout is still honoured.
if ($sheetBuilderPageId > 0 && class_exists('\Elementor\Plugin')) {
    $sheetContent = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($sheetBuilderPageId, true);
    if (is_string($sheetContent) && trim($sheetContent) !== '') {
        echo $sheetContent; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $sheetRendered = true;
    }
}

if (!$sheetRendered && $sheetBuilderPageId > 0 && get_post_meta($sheetBuilderPageId, '_homlity_seeded_builder', true) !== '') {
    $sheetBuilderPage = get_post($sheetBuilderPageId);
    if ($sheetBuilderPage instanceof \WP_Post) {
        echo apply_filters('the_content', $sheetBuilderPage->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $sheetRendered = true;
    }
}

if (!$sheetRendered) {
    TemplateService::includeComponent('property-technical-sheet.php', [
        'post_id' => $sheetPropertyId,
    ]);
}

get_footer();
