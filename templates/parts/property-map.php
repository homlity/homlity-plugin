<?php
/**
 * Map and Street View component.
 * Overridable at homlity-plugin/parts/property-map.php
 *
 * Expected args: $post_id (int)
 */

use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$meta = (new PropertyPostType())->metaKeys();
$lat = get_post_meta($post_id, $meta['latitude'], true);
$lng = get_post_meta($post_id, $meta['longitude'], true);

if (!$lat || !$lng) {
    return;
}

$settings = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
$mapProvider = isset($settings['default_map_provider']) && in_array($settings['default_map_provider'], ['leaflet_map', 'google_map'], true)
    ? $settings['default_map_provider']
    : 'leaflet_map';

$mapUrl = 'https://maps.google.com/maps?q=' . rawurlencode($lat . ',' . $lng) . '&z=16&output=embed';
$googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lng);
$streetViewUrl = 'https://maps.google.com/?cbll=' . rawurlencode($lat . ',' . $lng) . '&layer=c';
?>
<section class="property-map">
    <h2><?php esc_html_e('Ubicación', 'homlity-plugin'); ?></h2>
    <div class="property-map__frame">
        <?php if ($mapProvider === 'google_map') : ?>
            <iframe src="<?php echo esc_url($mapUrl); ?>" width="100%" height="360" style="border:0;" loading="lazy"
                    allowfullscreen title="<?php esc_attr_e('Mapa de la propiedad', 'homlity-plugin'); ?>"></iframe>
        <?php else : ?>
            <div
                class="property-map__leaflet homlity-front-leaflet-map"
                data-lat="<?php echo esc_attr($lat); ?>"
                data-lng="<?php echo esc_attr($lng); ?>"
                data-zoom="16"
                data-title="<?php echo esc_attr(get_the_title($post_id)); ?>"
            ></div>
        <?php endif; ?>
    </div>
    <p class="property-map__actions">
        <a href="<?php echo esc_url($googleMapsUrl); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('Abrir en Google Maps', 'homlity-plugin'); ?>
        </a>
        <a href="<?php echo esc_url($streetViewUrl); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('Ver Street View', 'homlity-plugin'); ?>
        </a>
    </p>
</section>
