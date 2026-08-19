<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Agent profile page, reachable via /property-agent/{user_nicename}/.
 * Can be overridden in theme at homlity-real-estate/property-agent.php
 *
 * This file is the *fallback* renderer. When a page is configured in
 * Configuración → Plantillas ("Página de perfil del asesor") and that page is
 * built with Elementor, Divi or WPBakery, the rewrite resolves the request to
 * that page and the builder renders it directly — this template is not used.
 */

use Homlity\PluginInmobiliario\Services\AgentProfileService;
use Homlity\PluginInmobiliario\Services\TemplateService;

get_header();

$agent = AgentProfileService::currentAgent();

if (!$agent) {
    echo '<main class="property-agent"><p>' . esc_html__('Asesor no encontrado.', 'homlity-real-estate') . '</p></main>';
    get_footer();
    return;
}

$builderPageId = AgentProfileService::pageId();
$rendered = false;

// Builder page configured but the request did not resolve to it (stale rewrite
// rules, or a theme template override). Render its content inline so the
// configured layout is still honoured.
if ($builderPageId > 0 && class_exists('\Elementor\Plugin')) {
    $content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($builderPageId, true);
    if (is_string($content) && trim($content) !== '') {
        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $rendered = true;
    }
}

if (!$rendered && $builderPageId > 0 && get_post_meta($builderPageId, '_homlity_seeded_builder', true) !== '') {
    $builderPage = get_post($builderPageId);
    if ($builderPage instanceof \WP_Post) {
        echo apply_filters('the_content', $builderPage->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $rendered = true;
    }
}

if (!$rendered) {
    TemplateService::includeComponent('agent-profile-content.php');
}

get_footer();
