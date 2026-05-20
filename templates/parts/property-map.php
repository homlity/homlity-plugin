<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Map and Street View component.
 * Overridable at homlity-real-estate/parts/property-map.php
 *
 * Expected args: $post_id (int)
 */

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

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
$streetViewEmbedUrl = 'https://www.google.com/maps?layer=c&cbll=' . rawurlencode($lat . ',' . $lng) . '&cbp=12,0,0,0,0&output=svembed';

$fallbackIconUrl = HOMLITY_PLUGIN_URL . 'assets/img/FAVICON.ico';
$markerIconUrl   = get_site_icon_url(32) ?: $fallbackIconUrl;
$tabMapIcon = isset($map_tab_icon_map) && is_array($map_tab_icon_map) ? $map_tab_icon_map : [];
$tabStreetIcon = isset($map_tab_icon_street) && is_array($map_tab_icon_street) ? $map_tab_icon_street : [];

$relatedMarkers = [];
$taxQuery = [];

$typeTerms = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_TYPE, ['fields' => 'ids']);
if (!is_wp_error($typeTerms) && $typeTerms) {
    $taxQuery[] = [
        'taxonomy' => PropertyTaxonomies::TAXONOMY_TYPE,
        'field' => 'term_id',
        'terms' => $typeTerms,
    ];
}

$operationTerms = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_OPERATION, ['fields' => 'ids']);
if (!is_wp_error($operationTerms) && $operationTerms) {
    $taxQuery[] = [
        'taxonomy' => PropertyTaxonomies::TAXONOMY_OPERATION,
        'field' => 'term_id',
        'terms' => $operationTerms,
    ];
}

$categoryTerms = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_CATEGORY, ['fields' => 'ids']);
if (!is_wp_error($categoryTerms) && $categoryTerms) {
    $taxQuery[] = [
        'taxonomy' => PropertyTaxonomies::TAXONOMY_CATEGORY,
        'field' => 'term_id',
        'terms' => $categoryTerms,
    ];
}

$tagTerms = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_TAG, ['fields' => 'ids']);
if (!is_wp_error($tagTerms) && $tagTerms) {
    $taxQuery[] = [
        'taxonomy' => PropertyTaxonomies::TAXONOMY_TAG,
        'field' => 'term_id',
        'terms' => $tagTerms,
    ];
}

if ($taxQuery) {
    $relatedQuery = new WP_Query([
        'post_type'      => PropertyPostType::POST_TYPE,
        'posts_per_page' => 12,
        'post__not_in'   => [$post_id],
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'tax_query'      => $taxQuery,
        'meta_query'     => [
            [
                'key'     => $meta['latitude'],
                'compare' => 'EXISTS',
            ],
            [
                'key'     => $meta['longitude'],
                'compare' => 'EXISTS',
            ],
        ],
    ]);

    if ($relatedQuery->have_posts()) {
        foreach ($relatedQuery->posts as $relatedId) {
            $relatedLat = (float) get_post_meta((int) $relatedId, $meta['latitude'], true);
            $relatedLng = (float) get_post_meta((int) $relatedId, $meta['longitude'], true);
            if (!$relatedLat || !$relatedLng) {
                continue;
            }
            $relatedMarkers[] = [
                'lat'   => $relatedLat,
                'lng'   => $relatedLng,
                'title' => get_the_title((int) $relatedId),
                'url'   => get_permalink((int) $relatedId),
            ];
        }
    }
    wp_reset_postdata();
}
?>
<section class="property-map">
    <div class="property-map__tabs" role="tablist" aria-label="<?php esc_attr_e('Mapa del inmueble', 'homlity-real-estate'); ?>">
        <button type="button" class="property-map__tab is-active" data-map-tab="map" role="tab" aria-selected="true">
            <?php if (class_exists('\Elementor\Icons_Manager') && !empty($tabMapIcon['value'])) : ?>
                <span class="property-map__tab-icon" aria-hidden="true"><?php \Elementor\Icons_Manager::render_icon($tabMapIcon, ['aria-hidden' => 'true']); ?></span>
            <?php endif; ?>
            <?php esc_html_e('Mapa', 'homlity-real-estate'); ?>
        </button>
        <button type="button" class="property-map__tab" data-map-tab="street" role="tab" aria-selected="false">
            <?php if (class_exists('\Elementor\Icons_Manager') && !empty($tabStreetIcon['value'])) : ?>
                <span class="property-map__tab-icon" aria-hidden="true"><?php \Elementor\Icons_Manager::render_icon($tabStreetIcon, ['aria-hidden' => 'true']); ?></span>
            <?php endif; ?>
            <?php esc_html_e('Street View', 'homlity-real-estate'); ?>
        </button>
    </div>
    <div class="property-map__frame" data-map-tabs>
        <div class="property-map__panel is-active" data-map-panel="map" role="tabpanel">
            <?php if ($mapProvider === 'google_map') : ?>
                <iframe src="<?php echo esc_url($mapUrl); ?>" width="100%" height="420" style="border:0;" loading="lazy"
                        allowfullscreen title="<?php esc_attr_e('Mapa de la propiedad', 'homlity-real-estate'); ?>"></iframe>
            <?php else : ?>
                <div
                    class="property-map__leaflet homlity-front-leaflet-map"
                    data-lat="<?php echo esc_attr($lat); ?>"
                    data-lng="<?php echo esc_attr($lng); ?>"
                    data-zoom="16"
                    data-title="<?php echo esc_attr(get_the_title($post_id)); ?>"
                    data-marker-icon="<?php echo esc_attr($markerIconUrl); ?>"
                    data-marker-icon-fallback="<?php echo esc_attr($fallbackIconUrl); ?>"
                    data-related-markers="<?php echo esc_attr(wp_json_encode($relatedMarkers)); ?>"
                ></div>
            <?php endif; ?>

        </div>
        <div
            class="property-map__panel"
            data-map-panel="street"
            data-map-fallback-url="<?php echo esc_url($streetViewUrl); ?>"
            role="tabpanel"
            hidden
        >
            <iframe
                src="about:blank"
                data-map-src="<?php echo esc_url($streetViewEmbedUrl); ?>"
                width="100%"
                height="420"
                style="border:0;"
                loading="eager"
                allowfullscreen
                title="<?php esc_attr_e('Street View de la propiedad', 'homlity-real-estate'); ?>"
            ></iframe>
            <p class="property-map__street-fallback" data-map-street-fallback hidden>
                <a href="<?php echo esc_url($streetViewUrl); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('No se pudo cargar Street View aquí. Abrir en Google Maps', 'homlity-real-estate'); ?>
                </a>
            </p>
        </div>
    </div>
    <p class="property-map__actions">
        <a href="<?php echo esc_url($googleMapsUrl); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('Abrir en Google Maps', 'homlity-real-estate'); ?>
        </a>
        <a href="<?php echo esc_url($streetViewUrl); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('Ver Street View', 'homlity-real-estate'); ?>
        </a>
    </p>
</section>
