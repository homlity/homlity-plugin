<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Agent profile page, reachable at the advisor's user URL /author/{nicename}/.
 * Can be overridden in theme at homlity-real-estate/property-agent.php
 *
 * When a page is configured in Configuración → Plantillas ("Página de perfil
 * del asesor") and that page is built with Elementor, Divi or WPBakery, its
 * layout is what renders below — inlined here, because WordPress owns the
 * /author/ route and it cannot be rewritten to a page without taking over the
 * archive of every author on the site. Only when no builder page is configured
 * does the plugin's own markup render.
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

if ($builderPageId > 0) {
    // Elementor will not run through `the_content` for a page it is not
    // currently the queried object of, so it needs its own call.
    if (get_post_meta($builderPageId, '_elementor_edit_mode', true) === 'builder' && class_exists('\Elementor\Plugin')) {
        $content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($builderPageId, true);
        if (is_string($content) && trim($content) !== '') {
            echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $rendered = true;
        }
    }

    // Divi and WPBakery both compile their shortcodes on `the_content`, so
    // running the stored content through it is enough to reproduce the layout.
    if (!$rendered) {
        $builderPage = get_post($builderPageId);
        if ($builderPage instanceof \WP_Post && trim((string) $builderPage->post_content) !== '') {
            echo apply_filters('the_content', $builderPage->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $rendered = true;
        }
    }
}

if (!$rendered) {
    TemplateService::includeComponent('agent-profile-content.php');
}

get_footer();
