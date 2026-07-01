<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Primary features component.
 * Overridable at homlity-real-estate/parts/property-features-primary.php
 *
 * Expected args: $post_id (int), $settings (array, optional — Elementor widget settings)
 */

use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$meta = (new PropertyPostType())->metaKeys();
$s    = $settings ?? [];

$features = [
    'area'         => ['label' => __('Área total',      'homlity-real-estate'), 'value' => get_post_meta($post_id, $meta['area'],         true), 'suffix' => ' m²'],
    'area_lot'     => ['label' => __('Área de lote',    'homlity-real-estate'), 'value' => get_post_meta($post_id, $meta['area_lot'],     true), 'suffix' => ' m²'],
    'area_private' => ['label' => __('Área privada',    'homlity-real-estate'), 'value' => get_post_meta($post_id, $meta['area_private'], true), 'suffix' => ' m²'],
    'area_built'   => ['label' => __('Área construida', 'homlity-real-estate'), 'value' => get_post_meta($post_id, $meta['area_built'],   true), 'suffix' => ' m²'],
    'bedrooms'     => ['label' => __('Habitaciones',    'homlity-real-estate'), 'value' => get_post_meta($post_id, $meta['bedrooms'],     true), 'suffix' => ''],
    'bathrooms'    => ['label' => __('Baños',           'homlity-real-estate'), 'value' => get_post_meta($post_id, $meta['bathrooms'],    true), 'suffix' => ''],
    'parking'      => ['label' => __('Parqueaderos',    'homlity-real-estate'), 'value' => get_post_meta($post_id, $meta['parking'],      true), 'suffix' => ''],
    'condition'    => ['label' => __('Estado',          'homlity-real-estate'), 'value' => get_post_meta($post_id, $meta['condition'],    true), 'suffix' => ''],
    'age'          => ['label' => __('Edad (años)',     'homlity-real-estate'), 'value' => get_post_meta($post_id, $meta['age'],          true), 'suffix' => ''],
    'code'         => ['label' => __('Código',          'homlity-real-estate'), 'value' => get_post_meta($post_id, $meta['code'],         true), 'suffix' => ''],
];
?>
<ul class="property-features property-features--primary">
    <?php foreach ($features as $key => $feature): ?>
        <?php
        if (($s['show_' . $key] ?? 'yes') !== 'yes') {
            continue;
        }
        if ($feature['value'] === '' || $feature['value'] === null || $feature['value'] === false) {
            continue;
        }
        $icon = $s['icon_' . $key] ?? [];
        ?>
        <li class="property-features__item">
            <?php if (!empty($icon['value']) && class_exists('\Elementor\Icons_Manager')): ?>
                <span class="property-features__icon">
                    <?php \Elementor\Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']); ?>
                </span>
            <?php endif; ?>
            <span class="property-features__name"><?php echo esc_html($feature['label']); ?></span>
            <span class="property-features__value"><?php echo esc_html($feature['value'] . $feature['suffix']); ?></span>
        </li>
    <?php endforeach; ?>
</ul>
