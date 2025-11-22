<?php
/**
 * Single property template.
 * Can be overridden in theme at plugin-inmobiliario/single-property.php
 */

use Codwelt\PluginInmobiliario\Services\CurrencyService;
use Codwelt\PluginInmobiliario\Services\PropertyPostType;
use Codwelt\PluginInmobiliario\Services\TemplateService;

get_header();

$metaKeys = (new PropertyPostType())->metaKeys();
$currencyService = new CurrencyService();

$priceFields = [
    'price_sale' => __('Precio venta', 'inmopress-listings-inmobiliaria'),
    'price_rent' => __('Precio arriendo', 'inmopress-listings-inmobiliaria'),
    'price_admin' => __('Precio administración', 'inmopress-listings-inmobiliaria'),
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
                            <?php echo esc_html(apply_filters('inmopress_format_price', $value, $currency)); ?>
                        </p>
                    <?php endforeach; ?>
                </div>
                <div class="property-single__taxonomies">
                    <?php
                    $taxonomies = [
                        'category' => \Codwelt\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_CATEGORY,
                        'type' => \Codwelt\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_TYPE,
                        'location' => \Codwelt\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_LOCATION,
                        'operation' => \Codwelt\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_OPERATION,
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
get_footer();
