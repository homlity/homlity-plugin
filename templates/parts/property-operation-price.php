<?php
/**
 * Operation & price component.
 * Overridable at homlity-plugin/parts/property-operation-price.php
 *
 * Expected args: $post_id (int), $settings (array, optional — Elementor widget settings)
 */

use Homlity\PluginInmobiliario\Services\CurrencyService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$meta            = (new PropertyPostType())->metaKeys();
$currencyService = new CurrencyService();
$s               = $settings ?? [];

$operations    = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_OPERATION);
$operationName = (!is_wp_error($operations) && !empty($operations)) ? $operations[0]->name : '';

$priceSale    = get_post_meta($post_id, $meta['price_sale'],    true);
$currencySale = get_post_meta($post_id, $meta['currency_sale'], true) ?: $currencyService->baseCurrency();
$priceRent    = get_post_meta($post_id, $meta['price_rent'],    true);
$currencyRent = get_post_meta($post_id, $meta['currency_rent'], true) ?: $currencyService->baseCurrency();
$priceAdmin   = get_post_meta($post_id, $meta['price_admin'],   true);
$currencyAdmin = get_post_meta($post_id, $meta['currency_admin'], true) ?: $currencyService->baseCurrency();

$items = [
    'operation'   => [
        'label' => __('Gestión',        'homlity-plugin'),
        'value' => $operationName,
    ],
    'price_sale'  => [
        'label' => __('Venta',          'homlity-plugin'),
        'value' => ($priceSale !== '' && $priceSale !== null)
            ? homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceSale, $currencySale)
            : '',
    ],
    'price_rent'  => [
        'label' => __('Arriendo',       'homlity-plugin'),
        'value' => ($priceRent !== '' && $priceRent !== null)
            ? homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceRent, $currencyRent)
            : '',
    ],
    'price_admin' => [
        'label' => __('Administración', 'homlity-plugin'),
        'value' => ($priceAdmin !== '' && $priceAdmin !== null)
            ? homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceAdmin, $currencyAdmin)
            : '',
    ],
];
?>
<ul class="property-operation-price-widget">
    <?php foreach ($items as $key => $item): ?>
        <?php
        if (($s['show_' . $key] ?? 'yes') !== 'yes') {
            continue;
        }
        if ($item['value'] === '' || $item['value'] === null) {
            continue;
        }
        $icon = $s['icon_' . $key] ?? [];
        ?>
        <li class="property-operation-price__item">
            <?php if (!empty($icon['value']) && class_exists('\Elementor\Icons_Manager')): ?>
                <span class="property-operation-price__icon">
                    <?php \Elementor\Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']); ?>
                </span>
            <?php endif; ?>
            <span class="property-operation-price__label"><?php echo esc_html($item['label']); ?></span>
            <span class="property-operation-price__value"><?php echo esc_html($item['value']); ?></span>
        </li>
    <?php endforeach; ?>
</ul>
