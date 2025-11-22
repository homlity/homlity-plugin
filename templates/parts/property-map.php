<?php
/**
 * Map and Street View component.
 * Overridable at plugin-inmobiliario/parts/property-map.php
 *
 * Expected args: $post_id (int)
 */

use Codwelt\PluginInmobiliario\Services\PropertyPostType;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$meta = (new PropertyPostType())->metaKeys();
$lat = get_post_meta($post_id, $meta['latitude'], true);
$lng = get_post_meta($post_id, $meta['longitude'], true);

if (!$lat || !$lng) {
    return;
}

$mapUrl = 'https://maps.google.com/maps?q=' . rawurlencode($lat . ',' . $lng) . '&z=16&output=embed';
$streetViewUrl = 'https://maps.google.com/?cbll=' . rawurlencode($lat . ',' . $lng) . '&layer=c';
?>
<section class="property-map">
    <h2><?php esc_html_e('Ubicación', 'plugin-inmobiliario'); ?></h2>
    <div class="property-map__frame">
        <iframe src="<?php echo esc_url($mapUrl); ?>" width="100%" height="360" style="border:0;" loading="lazy"
                allowfullscreen title="<?php esc_attr_e('Mapa de la propiedad', 'plugin-inmobiliario'); ?>"></iframe>
    </div>
    <p class="property-map__actions">
        <a href="<?php echo esc_url($streetViewUrl); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('Ver Street View', 'plugin-inmobiliario'); ?>
        </a>
    </p>
</section>
