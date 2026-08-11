<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Single property template.
 * Can be overridden in theme at homlity-real-estate/single-property.php
 */

use Homlity\PluginInmobiliario\Services\CurrencyService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\TemplateService;

$singleLayout = (string) get_option('homlity_plugin_single_page_layout', 'default');
$isCanvasLayout = ($singleLayout === 'elementor_canvas');
$homlityBuilderTemplateId = (int) get_option('homlity_plugin_single_template_id', 0);
$homlityBuilderTemplate = $homlityBuilderTemplateId > 0 ? get_post($homlityBuilderTemplateId) : null;
$homlityTemplateBuilder = '';

if ($homlityBuilderTemplate instanceof \WP_Post) {
    // Detect the renderer from the template itself. Elementor can be active on
    // a Divi site, so class_exists('Elementor\\Plugin') alone is not enough and
    // previously caused valid Divi layouts to be rendered as empty Elementor
    // documents on property detail pages.
    if (
        get_post_meta($homlityBuilderTemplateId, '_elementor_edit_mode', true) === 'builder'
        || get_post_meta($homlityBuilderTemplateId, '_homlity_seeded_builder', true) === 'elementor'
    ) {
        $homlityTemplateBuilder = 'elementor';
    } elseif (
        get_post_meta($homlityBuilderTemplateId, '_et_pb_use_builder', true) === 'on'
        || get_post_meta($homlityBuilderTemplateId, '_homlity_seeded_builder', true) === 'divi'
    ) {
        $homlityTemplateBuilder = 'divi';
    } elseif (
        get_post_meta($homlityBuilderTemplateId, '_wpb_vc_js_status', true) === 'true'
        || get_post_meta($homlityBuilderTemplateId, '_homlity_seeded_builder', true) === 'wpbakery'
    ) {
        $homlityTemplateBuilder = 'wpbakery';
    } else {
        $homlityTemplateBuilder = 'native';
    }
}

if (!$isCanvasLayout) {
    get_header();
}

if (
    $homlityTemplateBuilder === 'elementor' &&
    class_exists('\Elementor\Plugin')
) {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor sanitises its own widget output internally
    echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($homlityBuilderTemplateId, true);
    if (!$isCanvasLayout) {
        get_footer();
    }
    return;
}

if (
    $homlityBuilderTemplate instanceof \WP_Post
    && $homlityTemplateBuilder !== 'elementor'
) {
    // Keep the queried property as the global post so dynamic modules and
    // shortcodes resolve its data, while rendering the template page content.
    echo apply_filters('the_content', $homlityBuilderTemplate->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    if (!$isCanvasLayout) {
        get_footer();
    }
    return;
}

// Elementor Theme Builder remains available as a fallback when no explicit
// Homlity detail template can be rendered.
if (function_exists('elementor_theme_do_location') && elementor_theme_do_location('single')) {
    if (!$isCanvasLayout) {
        get_footer();
    }
    return;
}

$metaKeys = (new PropertyPostType())->metaKeys();
$currencyService = new CurrencyService();

$priceFields = [
    'price_sale' => __('Precio venta', 'homlity-real-estate'),
    'price_rent' => __('Precio arriendo', 'homlity-real-estate'),
    'price_admin' => __('Precio administración', 'homlity-real-estate'),
];

$agentId = (int) get_post_meta(get_the_ID(), $metaKeys['agent_id'], true);
$agentPhone = get_post_meta(get_the_ID(), $metaKeys['agent_phone'], true);
$agentEmail = get_post_meta(get_the_ID(), $metaKeys['agent_email'], true);
$agentUser = $agentId ? get_user_by('id', $agentId) : null;
?>
<main <?php post_class('property-single'); ?>>
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <header class="property-single__header">
            <h1><?php the_title(); ?></h1>
            <div class="property-single__meta">
                <div class="property-single__prices">
                    <?php foreach ($priceFields as $key => $label) :
                        $value = get_post_meta(get_the_ID(), $metaKeys[$key], true);
                        $currency = get_post_meta(get_the_ID(), $metaKeys[str_replace('price', 'currency', $key)], true) ?: $currencyService->baseCurrency();
                        if ($value === '' || $value === null) {
                            continue;
                        }
                        ?>
                        <p>
                            <strong><?php echo esc_html($label); ?>:</strong>
                            <?php echo esc_html(\homlity_plugin_apply_filters('homlity_plugin_format_price', null, $value, $currency)); ?>
                        </p>
                    <?php endforeach; ?>
                </div>
                <div class="property-single__taxonomies">
                    <?php
                    $taxonomies = [
                        'category' => \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_CATEGORY,
                        'type' => \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_TYPE,
                        'location' => \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_LOCATION,
                        'operation' => \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_OPERATION,
                    ];
                    foreach ($taxonomies as $tax) {
                        the_terms(get_the_ID(), $tax, '<span class="property-single__terms">', ', ', '</span>');
                    }
                    ?>
                </div>
            </div>
        </header>

        <section class="property-single__gallery">
            <?php TemplateService::includeComponent('property-gallery.php', ['post_id' => get_the_ID()]); ?>
        </section>

        <section class="property-single__content">
            <?php the_content(); ?>
            <?php TemplateService::includeComponent('property-features-primary.php', ['post_id' => get_the_ID()]); ?>
            <?php TemplateService::includeComponent('property-features-secondary.php', ['post_id' => get_the_ID()]); ?>
        </section>

        <?php TemplateService::includeComponent('property-map.php', ['post_id' => get_the_ID()]); ?>
        <?php TemplateService::includeComponent('property-agent-info.php', ['post_id' => get_the_ID()]); ?>
        <?php TemplateService::includeComponent('property-related.php', ['post_id' => get_the_ID()]); ?>
    <?php endwhile; endif; ?>
</main>

<?php
if (!$isCanvasLayout) {
    get_footer();
}
