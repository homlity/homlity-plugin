<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!function_exists('homlity_property_map_is_valid_coord')) {
    function homlity_property_map_is_valid_coord($lat, $lng): bool
    {
        return is_float($lat) && is_float($lng) && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }
}

if (!function_exists('homlity_property_map_icon_svg')) {
    function homlity_property_map_icon_svg(string $type): string
    {
        if ($type === 'street') {
            return '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13 3.25a5.75 5.75 0 1 1-2 0V2h2v1.25Zm-4 5.75a3 3 0 1 0 6 0 3 3 0 0 0-6 0Zm2.5 6h1a4.5 4.5 0 0 1 4.5 4.5v1.25h-2V19.5a2.5 2.5 0 0 0-5 0v1.25h-2V19.5a4.5 4.5 0 0 1 4.5-4.5Z" fill="currentColor"/></svg>';
        }

        return '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2.25c4 0 7.25 3.25 7.25 7.25 0 5.09-5.9 11.38-6.16 11.64a1.5 1.5 0 0 1-2.18 0C10.65 20.88 4.75 14.6 4.75 9.5c0-4 3.25-7.25 7.25-7.25Zm0 2a5.26 5.26 0 0 0-5.25 5.25c0 3.34 3.58 7.95 5.25 9.9 1.67-1.95 5.25-6.56 5.25-9.9A5.26 5.26 0 0 0 12 4.25Zm0 2.5a2.75 2.75 0 1 1 0 5.5 2.75 2.75 0 0 1 0-5.5Z" fill="currentColor"/></svg>';
    }
}

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$meta = (new PropertyPostType())->metaKeys();
$latRaw = get_post_meta($post_id, $meta['latitude'], true);
$lngRaw = get_post_meta($post_id, $meta['longitude'], true);
$lat = is_numeric($latRaw) ? (float) $latRaw : null;
$lng = is_numeric($lngRaw) ? (float) $lngRaw : null;

$settingsGlobal = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
$providerGlobal = isset($settingsGlobal['default_map_provider']) && in_array($settingsGlobal['default_map_provider'], ['leaflet_map', 'google_map'], true)
    ? $settingsGlobal['default_map_provider']
    : 'leaflet_map';

$widgetSettings = isset($map_settings) && is_array($map_settings) ? $map_settings : [];
$provider = isset($widgetSettings['map_provider']) && in_array($widgetSettings['map_provider'], ['global', 'leaflet_map', 'google_map', 'google_js'], true)
    ? $widgetSettings['map_provider']
    : 'global';
$mapProvider = $provider === 'global' ? $providerGlobal : $provider;
$mapProvider = $mapProvider === 'google_js' ? 'google_map' : $mapProvider;

$mapHeight = isset($widgetSettings['map_height']['size']) ? (int) $widgetSettings['map_height']['size'] : 420;
$mapHeight = max(220, min(1000, $mapHeight));
$zoom = isset($widgetSettings['map_zoom']) ? (int) $widgetSettings['map_zoom'] : 16;
$zoom = max(1, min(21, $zoom));

if (!is_float($lat) || !is_float($lng) || !homlity_property_map_is_valid_coord($lat, $lng)) {
    ?>
    <section class="property-map property-map--empty" data-property-map-empty>
        <p class="property-map__empty"><?php echo esc_html($widgetSettings['empty_coords_message'] ?? __('Ubicación no disponible para este inmueble.', 'homlity-real-estate')); ?></p>
    </section>
    <?php
    return;
}

$travelMode = isset($widgetSettings['google_travel_mode']) && in_array($widgetSettings['google_travel_mode'], ['driving', 'walking', 'bicycling', 'transit'], true)
    ? $widgetSettings['google_travel_mode']
    : 'driving';
$openNewTab = !isset($widgetSettings['open_links_new_tab']) || $widgetSettings['open_links_new_tab'] === 'yes';
$target = $openNewTab ? '_blank' : '_self';
$rel = $openNewTab ? 'noopener noreferrer' : 'noopener';

$latLng = $lat . ',' . $lng;
$mapUrl = 'https://maps.google.com/maps?q=' . rawurlencode($latLng) . '&z=' . $zoom . '&output=embed';
$googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($latLng);
$googleDirectionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($latLng) . '&travelmode=' . rawurlencode($travelMode);
$wazeUrl = 'https://waze.com/ul?ll=' . rawurlencode($latLng) . '&navigate=yes&zoom=17';
$appleMapsUrl = 'https://maps.apple.com/?daddr=' . rawurlencode($latLng);
$streetViewUrl = 'https://maps.google.com/?cbll=' . rawurlencode($latLng) . '&layer=c';
$streetViewEmbedUrl = 'https://www.google.com/maps/embed?pb='
    . rawurlencode('!4v' . time() . '!6m8!1m7!1tstreetview!2m2!1d' . $lat . '!2d' . $lng . '!3f90!4f0!5f0.7820865974627469');

$fallbackIconUrl = HOMLITY_PLUGIN_URL . 'assets/img/FAVICON.ico';
$markerIconUrl = get_site_icon_url(32) ?: $fallbackIconUrl;
$tabMapIcon = isset($map_tab_icon_map) && is_array($map_tab_icon_map) ? $map_tab_icon_map : [];
$tabStreetIcon = isset($map_tab_icon_street) && is_array($map_tab_icon_street) ? $map_tab_icon_street : [];

$relatedEnabled = !isset($widgetSettings['show_related_markers']) || $widgetSettings['show_related_markers'] === 'yes';
$relatedLimit = isset($widgetSettings['related_markers_limit']) ? max(0, min(10, (int) $widgetSettings['related_markers_limit'])) : 10;
$relatedMarkers = [];

if ($relatedEnabled && $relatedLimit > 0) {
    $taxQuery = [];
    foreach ([PropertyTaxonomies::TAXONOMY_TYPE, PropertyTaxonomies::TAXONOMY_OPERATION, PropertyTaxonomies::TAXONOMY_CATEGORY, PropertyTaxonomies::TAXONOMY_TAG] as $taxonomy) {
        $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);
        if (!is_wp_error($terms) && !empty($terms)) {
            $taxQuery[] = [
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => $terms,
            ];
        }
    }

    if (!empty($taxQuery)) {
        $relatedQuery = new WP_Query([
            'post_type' => PropertyPostType::POST_TYPE,
            'posts_per_page' => $relatedLimit,
            'post__not_in' => [(int) $post_id], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
            'fields' => 'ids',
            'no_found_rows' => true,
            'tax_query' => $taxQuery, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                ['key' => $meta['latitude'], 'compare' => 'EXISTS'],
                ['key' => $meta['longitude'], 'compare' => 'EXISTS'],
            ],
        ]);

        if ($relatedQuery->have_posts()) {
            foreach ($relatedQuery->posts as $relatedId) {
                $rLatRaw = get_post_meta((int) $relatedId, $meta['latitude'], true);
                $rLngRaw = get_post_meta((int) $relatedId, $meta['longitude'], true);
                $rLat = is_numeric($rLatRaw) ? (float) $rLatRaw : null;
                $rLng = is_numeric($rLngRaw) ? (float) $rLngRaw : null;
                if (!is_float($rLat) || !is_float($rLng) || !homlity_property_map_is_valid_coord($rLat, $rLng)) {
                    continue;
                }
                $relatedMarkers[] = [
                    'lat' => $rLat,
                    'lng' => $rLng,
                    'title' => get_the_title((int) $relatedId),
                    'url' => get_permalink((int) $relatedId),
                ];
            }
        }
        wp_reset_postdata();
    }
}

$showStreetView = !isset($widgetSettings['enable_street_view']) || $widgetSettings['enable_street_view'] === 'yes';
$showTabs = !isset($widgetSettings['show_tabs']) || $widgetSettings['show_tabs'] === 'yes';
$showActions = !isset($widgetSettings['show_route_actions']) || $widgetSettings['show_route_actions'] === 'yes';
$streetMode = isset($widgetSettings['street_mode']) && in_array($widgetSettings['street_mode'], ['google_js', 'iframe'], true)
    ? $widgetSettings['street_mode']
    : 'google_js';

$mapId = wp_unique_id('hml-map-');
$settingsPayload = [
    'initial_tab' => isset($widgetSettings['initial_tab']) ? sanitize_key((string) $widgetSettings['initial_tab']) : 'map',
    'street_mode' => $streetMode,
    'street_radius' => isset($widgetSettings['street_radius']) ? (int) $widgetSettings['street_radius'] : 100,
    'street_unavailable_behavior' => isset($widgetSettings['street_unavailable_behavior']) ? sanitize_key((string) $widgetSettings['street_unavailable_behavior']) : 'disabled',
    'street_heading' => isset($widgetSettings['street_heading']) ? (float) $widgetSettings['street_heading'] : 0,
    'street_pitch' => isset($widgetSettings['street_pitch']) ? (float) $widgetSettings['street_pitch'] : 0,
    'street_zoom' => isset($widgetSettings['street_zoom']) ? (int) $widgetSettings['street_zoom'] : 1,
    'street_controls' => !isset($widgetSettings['street_controls']) || $widgetSettings['street_controls'] === 'yes',
    'google_maps_api_key' => isset($widgetSettings['google_maps_api_key']) ? sanitize_text_field((string) $widgetSettings['google_maps_api_key']) : '',
    'open_new_tab' => $openNewTab,
];

wp_enqueue_script(
    'homlity-real-estate-map-tabs',
    HOMLITY_PLUGIN_URL . 'assets/js/property-map-tabs.js',
    [],
    HOMLITY_PLUGIN_VERSION,
    true
);
?>
<section
    class="property-map"
    data-property-map
    data-map-id="<?php echo esc_attr($mapId); ?>"
    data-lat="<?php echo esc_attr((string) $lat); ?>"
    data-lng="<?php echo esc_attr((string) $lng); ?>"
    data-street-external-url="<?php echo esc_url($streetViewUrl); ?>"
    data-settings="<?php echo esc_attr(wp_json_encode($settingsPayload)); ?>"
    style="--hml-property-map-height: <?php echo esc_attr((string) $mapHeight); ?>px;"
>
    <?php if ($showTabs) : ?>
    <div class="property-map__tabs" role="tablist" aria-label="<?php esc_attr_e('Mapa del inmueble', 'homlity-real-estate'); ?>">
        <button type="button" class="property-map__tab is-active" data-map-tab="map" id="<?php echo esc_attr($mapId . '-tab-map'); ?>" role="tab" aria-selected="true" aria-controls="<?php echo esc_attr($mapId . '-panel-map'); ?>">
            <span class="property-map__tab-icon" aria-hidden="true">
                <?php if (class_exists('\Homlity\PluginInmobiliario\Services\IconRenderer') && !empty($tabMapIcon['value'])) : ?>
                    <?php \Homlity\PluginInmobiliario\Services\IconRenderer::render($tabMapIcon, ['aria-hidden' => 'true']); ?>
                <?php else : ?>
                    <?php echo homlity_property_map_icon_svg('map'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
            </span>
            <span class="property-map__tab-label"><?php echo esc_html($widgetSettings['map_tab_text_map'] ?? __('Mapa', 'homlity-real-estate')); ?></span>
        </button>
        <?php if ($showStreetView) : ?>
        <button
            type="button"
            class="property-map__tab"
            data-map-tab="street"
            id="<?php echo esc_attr($mapId . '-tab-street'); ?>"
            role="tab"
            aria-selected="false"
            aria-controls="<?php echo esc_attr($mapId . '-panel-street'); ?>"
            aria-disabled="false"
        >
            <span class="property-map__tab-icon" aria-hidden="true">
                <?php if (class_exists('\Homlity\PluginInmobiliario\Services\IconRenderer') && !empty($tabStreetIcon['value'])) : ?>
                    <?php \Homlity\PluginInmobiliario\Services\IconRenderer::render($tabStreetIcon, ['aria-hidden' => 'true']); ?>
                <?php else : ?>
                    <?php echo homlity_property_map_icon_svg('street'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
            </span>
            <span class="property-map__tab-label"><?php echo esc_html($widgetSettings['map_tab_text_street'] ?? __('Street View', 'homlity-real-estate')); ?></span>
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="property-map__frame" data-map-tabs>
        <div class="property-map__panel is-active" data-map-panel="map" id="<?php echo esc_attr($mapId . '-panel-map'); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr($mapId . '-tab-map'); ?>">
            <?php if ($mapProvider === 'google_map') : ?>
                <iframe src="<?php echo esc_url($mapUrl); ?>" loading="lazy" allowfullscreen title="<?php esc_attr_e('Mapa de la propiedad', 'homlity-real-estate'); ?>"></iframe>
            <?php else : ?>
                <div
                    class="property-map__leaflet homlity-front-leaflet-map"
                    data-lat="<?php echo esc_attr((string) $lat); ?>"
                    data-lng="<?php echo esc_attr((string) $lng); ?>"
                    data-zoom="<?php echo esc_attr((string) $zoom); ?>"
                    data-title="<?php echo esc_attr(get_the_title($post_id)); ?>"
                    data-marker-icon="<?php echo esc_attr($markerIconUrl); ?>"
                    data-marker-icon-fallback="<?php echo esc_attr($fallbackIconUrl); ?>"
                    data-marker-bg-color="<?php echo esc_attr(sanitize_hex_color((string) ($widgetSettings['marker_bg_color'] ?? '')) ?: '#ffffff'); ?>"
                    data-related-markers="<?php echo esc_attr(wp_json_encode($relatedMarkers)); ?>"
                ></div>
            <?php endif; ?>
        </div>

        <?php if ($showStreetView) : ?>
        <div class="property-map__panel" data-map-panel="street" id="<?php echo esc_attr($mapId . '-panel-street'); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr($mapId . '-tab-street'); ?>" hidden>
            <!-- Canvas: only shown when Google Maps JS API renders a panorama into it -->
            <div class="property-map__street-canvas" aria-label="<?php esc_attr_e('Street View de la propiedad', 'homlity-real-estate'); ?>" hidden></div>
            <!-- Iframe: src is set by JS on demand to avoid loading while hidden -->
            <iframe
                data-map-src="<?php echo esc_url($streetViewEmbedUrl); ?>"
                style="border:0; width:100%;"
                allowfullscreen
                title="<?php esc_attr_e('Street View embebido', 'homlity-real-estate'); ?>"
                hidden
            ></iframe>
            <p class="property-map__street-fallback" data-map-street-fallback hidden>
                <?php echo esc_html($widgetSettings['street_unavailable_message'] ?? __('Street View no está disponible para esta ubicación. Puedes abrir la ubicación en Google Maps.', 'homlity-real-estate')); ?>
                <a href="<?php echo esc_url($streetViewUrl); ?>" target="<?php echo esc_attr($target); ?>" rel="<?php echo esc_attr($rel); ?>"><?php esc_html_e('Abrir Street View', 'homlity-real-estate'); ?></a>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($showActions) : ?>
    <div class="property-map__actions">
        <?php if (!isset($widgetSettings['show_btn_google_maps']) || $widgetSettings['show_btn_google_maps'] === 'yes') : ?>
            <a href="<?php echo esc_url($googleMapsUrl); ?>" target="<?php echo esc_attr($target); ?>" rel="<?php echo esc_attr($rel); ?>"><?php echo esc_html($widgetSettings['text_btn_google_maps'] ?? __('Abrir en Google Maps', 'homlity-real-estate')); ?></a>
        <?php endif; ?>
        <?php if (!isset($widgetSettings['show_btn_google_directions']) || $widgetSettings['show_btn_google_directions'] === 'yes') : ?>
            <a href="<?php echo esc_url($googleDirectionsUrl); ?>" target="<?php echo esc_attr($target); ?>" rel="<?php echo esc_attr($rel); ?>"><?php echo esc_html($widgetSettings['text_btn_google_directions'] ?? __('Cómo llegar', 'homlity-real-estate')); ?></a>
        <?php endif; ?>
        <?php if (!isset($widgetSettings['show_btn_waze']) || $widgetSettings['show_btn_waze'] === 'yes') : ?>
            <a href="<?php echo esc_url($wazeUrl); ?>" target="<?php echo esc_attr($target); ?>" rel="<?php echo esc_attr($rel); ?>"><?php echo esc_html($widgetSettings['text_btn_waze'] ?? __('Ir con Waze', 'homlity-real-estate')); ?></a>
        <?php endif; ?>
        <?php if (isset($widgetSettings['show_btn_apple_maps']) && $widgetSettings['show_btn_apple_maps'] === 'yes') : ?>
            <a href="<?php echo esc_url($appleMapsUrl); ?>" target="<?php echo esc_attr($target); ?>" rel="<?php echo esc_attr($rel); ?>"><?php echo esc_html($widgetSettings['text_btn_apple_maps'] ?? __('Apple Maps', 'homlity-real-estate')); ?></a>
        <?php endif; ?>
        <?php if (isset($widgetSettings['show_btn_copy_coords']) && $widgetSettings['show_btn_copy_coords'] === 'yes') : ?>
            <button type="button" class="property-map__action-button" data-map-action="copy"><?php echo esc_html($widgetSettings['text_btn_copy_coords'] ?? __('Copiar ubicación', 'homlity-real-estate')); ?></button>
        <?php endif; ?>
    </div>
    <p class="property-map__copy-feedback" data-map-copy-feedback hidden><?php esc_html_e('Ubicación copiada.', 'homlity-real-estate'); ?></p>
    <?php endif; ?>
</section>
