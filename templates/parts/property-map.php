<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
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
$streetViewEmbedUrl = 'https://maps.google.com/maps?layer=c&cbll=' . rawurlencode($lat . ',' . $lng) . '&cbp=12,0,0,0,0&output=svembed';

$fallbackIconUrl = HOMLITY_PLUGIN_URL . 'assets/img/FAVICON.ico';
$markerIconUrl   = get_site_icon_url(32) ?: $fallbackIconUrl;
$tabMapIcon = isset($map_tab_icon_map) && is_array($map_tab_icon_map) ? $map_tab_icon_map : [];
$tabStreetIcon = isset($map_tab_icon_street) && is_array($map_tab_icon_street) ? $map_tab_icon_street : [];
?>
<section class="property-map">
    <div class="property-map__tabs" role="tablist" aria-label="<?php esc_attr_e('Mapa del inmueble', 'homlity-plugin'); ?>">
        <button type="button" class="property-map__tab is-active" data-map-tab="map" role="tab" aria-selected="true">
            <?php if (class_exists('\Elementor\Icons_Manager') && !empty($tabMapIcon['value'])) : ?>
                <span class="property-map__tab-icon" aria-hidden="true"><?php \Elementor\Icons_Manager::render_icon($tabMapIcon, ['aria-hidden' => 'true']); ?></span>
            <?php endif; ?>
            <?php esc_html_e('Mapa', 'homlity-plugin'); ?>
        </button>
        <button type="button" class="property-map__tab" data-map-tab="street" role="tab" aria-selected="false">
            <?php if (class_exists('\Elementor\Icons_Manager') && !empty($tabStreetIcon['value'])) : ?>
                <span class="property-map__tab-icon" aria-hidden="true"><?php \Elementor\Icons_Manager::render_icon($tabStreetIcon, ['aria-hidden' => 'true']); ?></span>
            <?php endif; ?>
            <?php esc_html_e('Street View', 'homlity-plugin'); ?>
        </button>
    </div>
    <div class="property-map__frame" data-map-tabs>
        <div class="property-map__panel is-active" data-map-panel="map" role="tabpanel">
            <?php if ($mapProvider === 'google_map') : ?>
                <iframe src="<?php echo esc_url($mapUrl); ?>" width="100%" height="420" style="border:0;" loading="lazy"
                        allowfullscreen title="<?php esc_attr_e('Mapa de la propiedad', 'homlity-plugin'); ?>"></iframe>
            <?php else : ?>
                <div
                    class="property-map__leaflet homlity-front-leaflet-map"
                    data-lat="<?php echo esc_attr($lat); ?>"
                    data-lng="<?php echo esc_attr($lng); ?>"
                    data-zoom="16"
                    data-title="<?php echo esc_attr(get_the_title($post_id)); ?>"
                    data-marker-icon="<?php echo esc_attr($markerIconUrl); ?>"
                    data-marker-icon-fallback="<?php echo esc_attr($fallbackIconUrl); ?>"
                ></div>
            <?php endif; ?>
            <?php if (!empty($settings['show_powered_by'])) : ?>
            <p class="property-map__powered-by">
                <?php esc_html_e('Construido con ecosistema inmobiliario', 'homlity-plugin'); ?>
                <a href="https://homlity.com/" target="_blank" rel="noopener noreferrer">homlity.com</a>
            </p>
            <?php endif; ?>
        </div>
        <div class="property-map__panel" data-map-panel="street" role="tabpanel" hidden>
            <iframe
                src="<?php echo esc_url($streetViewEmbedUrl); ?>"
                width="100%"
                height="420"
                style="border:0;"
                loading="lazy"
                allowfullscreen
                title="<?php esc_attr_e('Street View de la propiedad', 'homlity-plugin'); ?>"
            ></iframe>
        </div>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.property-map [data-map-tabs]').forEach(function (tabsWrap) {
        var root = tabsWrap.closest('.property-map');
        if (!root) {
            return;
        }
        var tabs = root.querySelectorAll('.property-map__tab[data-map-tab]');
        var panels = root.querySelectorAll('.property-map__panel[data-map-panel]');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-map-tab') || '';
                tabs.forEach(function (btn) {
                    var active = btn === tab;
                    btn.classList.toggle('is-active', active);
                    btn.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                panels.forEach(function (panel) {
                    var active = panel.getAttribute('data-map-panel') === target;
                    panel.classList.toggle('is-active', active);
                    panel.hidden = !active;
                });
                if (target === 'map') {
                    root.querySelectorAll('.homlity-front-leaflet-map').forEach(function (node) {
                        node.dispatchEvent(new CustomEvent('homlity:map-resize'));
                    });
                }
            });
        });
    });
});
</script>
