<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Operation & price component.
 * Overridable at homlity-real-estate/parts/property-operation-price.php
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

$opSlug     = mb_strtolower($operationName);
$isVenta    = str_contains($opSlug, 'venta');
$isArriendo = str_contains($opSlug, 'arriendo');
$hideZero   = ($s['hide_zero_values'] ?? 'no') === 'yes';

// Price keys visible for this operation type; if unknown, show all
if (!$isVenta && !$isArriendo) {
    $shownPrices = ['price_sale', 'price_rent', 'price_admin'];
} else {
    $shownPrices = ['price_admin'];
    if ($isVenta)    $shownPrices[] = 'price_sale';
    if ($isArriendo) $shownPrices[] = 'price_rent';
}

$priceSale    = get_post_meta($post_id, $meta['price_sale'],    true);
$currencySale = get_post_meta($post_id, $meta['currency_sale'], true) ?: $currencyService->baseCurrency();
$priceRent    = get_post_meta($post_id, $meta['price_rent'],    true);
$currencyRent = get_post_meta($post_id, $meta['currency_rent'], true) ?: $currencyService->baseCurrency();
$priceAdmin   = get_post_meta($post_id, $meta['price_admin'],   true);
$currencyAdmin = get_post_meta($post_id, $meta['currency_admin'], true) ?: $currencyService->baseCurrency();

$rawPrices = [
    'price_sale'  => $priceSale,
    'price_rent'  => $priceRent,
    'price_admin' => $priceAdmin,
];

$items = [
    'operation'   => [
        'label' => __('Gestión',        'homlity-real-estate'),
        'value' => $operationName,
    ],
    'price_sale'  => [
        'label' => __('Venta',          'homlity-real-estate'),
        'value' => ($priceSale !== '' && $priceSale !== null)
            ? homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceSale, $currencySale)
            : '',
    ],
    'price_rent'  => [
        'label' => __('Arriendo',       'homlity-real-estate'),
        'value' => ($priceRent !== '' && $priceRent !== null)
            ? homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceRent, $currencyRent)
            : '',
    ],
    'price_admin' => [
        'label' => __('Administración', 'homlity-real-estate'),
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
        if ($key !== 'operation' && !in_array($key, $shownPrices, true)) {
            continue;
        }
        if ($hideZero && isset($rawPrices[$key]) && (float) ($rawPrices[$key] ?? 0) === 0.0) {
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
