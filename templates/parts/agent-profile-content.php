<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Agent profile content component.
 * Overridable at homlity-real-estate/parts/agent-profile-content.php
 */

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\TemplateService;

$agentSlug = get_query_var('property_agent');
$agent = $agentSlug ? get_user_by('slug', $agentSlug) : null;

if (!$agent) {
    echo '<main class="property-agent"><p>' . esc_html__('Asesor no encontrado.', 'homlity-real-estate') . '</p></main>';
    return;
}

$agentQuery = new WP_Query([
    'post_type' => PropertyPostType::POST_TYPE,
    'posts_per_page' => 12,
    'meta_query' => [
        [
            'key' => '_property_agent_id',
            'value' => $agent->ID,
        ],
    ],
    'paged' => get_query_var('paged') ?: 1,
]);
?>
<main class="property-agent">
    <header class="property-agent__header">
        <div class="property-agent__avatar">
            <?php echo get_avatar($agent->ID, 128); ?>
        </div>
        <div class="property-agent__info">
            <h1><?php echo esc_html($agent->display_name); ?></h1>
            <p><?php echo esc_html($agent->user_email); ?></p>
        </div>
    </header>

    <?php if ($agentQuery->have_posts()) : ?>
        <h2><?php esc_html_e('Inmuebles del asesor', 'homlity-real-estate'); ?></h2>
        <div class="property-agent__grid">
            <?php while ($agentQuery->have_posts()) : $agentQuery->the_post(); ?>
                <?php TemplateService::includeComponent('property-card.php', ['post_id' => get_the_ID()]); ?>
            <?php endwhile; ?>
        </div>
        <div class="property-agent__pagination">
            <?php
            echo wp_kses_post( paginate_links([
                'total' => $agentQuery->max_num_pages,
            ]) );
            ?>
        </div>
        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <p><?php esc_html_e('No hay inmuebles publicados por este asesor.', 'homlity-real-estate'); ?></p>
    <?php endif; ?>
</main>
