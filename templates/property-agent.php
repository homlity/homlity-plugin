<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Agent profile page, reachable via /property-agent/{user_nicename}.
 * Can be overridden in theme at homlity-real-estate/property-agent.php
 */

use Homlity\PluginInmobiliario\Services\TemplateService;

get_header();

$agentSlug = get_query_var('property_agent');
$agent = $agentSlug ? get_user_by('slug', $agentSlug) : null;

if (!$agent) {
    echo '<main class="property-agent"><p>' . esc_html__('Asesor no encontrado.', 'homlity-real-estate') . '</p></main>';
    get_footer();
    return;
}

$elementorPageId = (int) get_option('homlity_plugin_agent_profile_page_id', 0);
$rendered = false;

if (
    $elementorPageId > 0
    && get_post_status($elementorPageId)
    && class_exists('\Elementor\Plugin')
) {
    $content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($elementorPageId, true);
    if (is_string($content) && trim($content) !== '') {
        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $rendered = true;
    }
}

if (!$rendered) {
    TemplateService::includeComponent('agent-profile-content.php');
}

get_footer();

