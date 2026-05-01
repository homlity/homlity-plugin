<?php
/**
 * Property summary component.
 * Overridable at homlity-plugin/parts/property-summary.php
 *
 * Expected args: $post_id (int)
 */

use Homlity\PluginInmobiliario\Services\CurrencyService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$meta = (new PropertyPostType())->metaKeys();
$currencyService = new CurrencyService();
$priceFields = [
    'price_sale' => __('Precio venta', 'homlity-plugin'),
    'price_rent' => __('Precio arriendo', 'homlity-plugin'),
    'price_admin' => __('Precio administración', 'homlity-plugin'),
];
$taxonomies = [
    PropertyTaxonomies::TAXONOMY_CATEGORY,
    PropertyTaxonomies::TAXONOMY_TYPE,
    PropertyTaxonomies::TAXONOMY_OPERATION,
    PropertyTaxonomies::TAXONOMY_CITY,
    PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
];
?>
<section class="property-summary">
    <header class="property-summary__header">
        <h1><?php echo esc_html(get_the_title($post_id)); ?></h1>
        <div class="property-summary__prices">
            <?php foreach ($priceFields as $key => $label): ?>
                <?php
                $value = get_post_meta($post_id, $meta[$key], true);
                $currency = get_post_meta($post_id, $meta[str_replace('price', 'currency', $key)], true) ?: $currencyService->baseCurrency();
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
    </header>

    <div class="property-summary__terms">
        <?php foreach ($taxonomies as $taxonomy): ?>
            <?php the_terms($post_id, $taxonomy, '<span class="property-summary__term">', ', ', '</span>'); ?>
        <?php endforeach; ?>
    </div>

    <div class="property-summary__content">
        <?php echo apply_filters('the_content', get_post_field('post_content', $post_id)); ?>
    </div>
</section>
