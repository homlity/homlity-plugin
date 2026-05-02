<?php
/**
 * Property listing – default (custom CSS) template.
 * Overridable at homlity-plugin/parts/property-listing.php inside theme or child theme.
 *
 * Expected args:
 *   $config  (ListingConfig)      – all display/query settings
 *   $query   (WP_Query)           – pre-built query
 *   $search  (PropertySearchService)
 *   $params  (array)              – base query params
 */

use Homlity\PluginInmobiliario\Listing\ListingConfig;

if (!defined('ABSPATH')) {
    exit;
}

/** @var ListingConfig $config */
$uniqueId = 'hpl-' . substr(md5(uniqid('', true)), 0, 8);
$params = isset($params) && is_array($params) ? $params : [];
$paramToAttr = static function ($value): string {
    if (is_array($value)) {
        return implode(',', array_map('strval', $value));
    }
    return (string) $value;
};

$mapData     = $search->getMapData($query);
$cardOptions = $config->cardOptions();
$sortOptions = [
    'date'       => __('Más recientes',        'homlity-plugin'),
    'price_asc'  => __('Precio: menor a mayor','homlity-plugin'),
    'price_desc' => __('Precio: mayor a menor','homlity-plugin'),
    'title'      => __('Nombre A–Z',           'homlity-plugin'),
];
?>
<div id="<?php echo esc_attr($uniqueId); ?>"
     class="property-listing"
     data-view="<?php echo esc_attr($config->defaultView()); ?>"
     data-per-page="<?php echo esc_attr($config->postsPerPage()); ?>"
     data-columns="<?php echo esc_attr($config->columns()); ?>"
     data-map-zoom="<?php echo esc_attr($config->mapZoom()); ?>"
     data-template="<?php echo esc_attr($config->template()); ?>"
     data-query-mode="<?php echo esc_attr($params['query_mode'] ?? $config->queryMode()); ?>"
     data-search="<?php echo esc_attr($params['search'] ?? $config->searchKeyword()); ?>"
     data-featured="<?php echo esc_attr(!empty($params['featured']) || $config->featuredOnly() ? '1' : '0'); ?>"
     data-preset-category="<?php echo esc_attr($params['preset_category'] ?? $config->presetCategory()); ?>"
     data-preset-operation="<?php echo esc_attr($params['preset_operation'] ?? $config->presetOperation()); ?>"
     data-preset-type="<?php echo esc_attr($params['preset_type'] ?? $config->presetType()); ?>"
     data-preset-tag="<?php echo esc_attr($params['preset_tag'] ?? $config->presetTag()); ?>"
     data-preset-feature="<?php echo esc_attr($params['preset_feature'] ?? $config->presetFeature()); ?>"
     data-preset-country="<?php echo esc_attr($params['preset_country'] ?? $config->presetCountry()); ?>"
     data-preset-state="<?php echo esc_attr($params['preset_state'] ?? $config->presetState()); ?>"
     data-preset-city="<?php echo esc_attr($params['preset_city'] ?? $config->presetCity()); ?>"
     data-preset-neighborhood="<?php echo esc_attr($params['preset_neighborhood'] ?? $config->presetNeighborhood()); ?>"
     data-preset-nearby="<?php echo esc_attr($params['preset_nearby'] ?? $config->presetNearby()); ?>"
     data-geo-latitude="<?php echo esc_attr($params['geo_latitude'] ?? $config->geoLatitude()); ?>"
     data-geo-longitude="<?php echo esc_attr($params['geo_longitude'] ?? $config->geoLongitude()); ?>"
     data-geo-radius-km="<?php echo esc_attr($params['geo_radius_km'] ?? $config->geoRadiusKm()); ?>"
     data-price-min="<?php echo esc_attr($params['price_min'] ?? ''); ?>"
     data-price-max="<?php echo esc_attr($params['price_max'] ?? ''); ?>"
     data-bedrooms="<?php echo esc_attr($params['bedrooms'] ?? ''); ?>"
     data-bathrooms="<?php echo esc_attr($params['bathrooms'] ?? ''); ?>"
     data-parking="<?php echo esc_attr($params['parking'] ?? ''); ?>"
     data-area-min="<?php echo esc_attr($params['area_min'] ?? ''); ?>"
     data-area-max="<?php echo esc_attr($params['area_max'] ?? ''); ?>"
     data-category="<?php echo esc_attr($paramToAttr($params['category'] ?? '')); ?>"
     data-operation="<?php echo esc_attr($paramToAttr($params['operation'] ?? '')); ?>"
     data-type="<?php echo esc_attr($paramToAttr($params['type'] ?? '')); ?>"
     data-tag="<?php echo esc_attr($paramToAttr($params['tag'] ?? '')); ?>"
     data-feature="<?php echo esc_attr($paramToAttr($params['feature'] ?? '')); ?>"
     data-country="<?php echo esc_attr($paramToAttr($params['country'] ?? '')); ?>"
     data-state="<?php echo esc_attr($paramToAttr($params['state'] ?? '')); ?>"
     data-city="<?php echo esc_attr($paramToAttr($params['city'] ?? '')); ?>"
     data-neighborhood="<?php echo esc_attr($paramToAttr($params['neighborhood'] ?? '')); ?>"
     data-nearby="<?php echo esc_attr($paramToAttr($params['nearby'] ?? '')); ?>"
     data-card-media-mode="<?php echo esc_attr($cardOptions['media_mode'] ?? 'single'); ?>"
     data-card-visual-preset="<?php echo esc_attr($cardOptions['visual_preset'] ?? 'default'); ?>"
     data-card-show-title="<?php echo esc_attr(!empty($cardOptions['show_title']) ? '1' : '0'); ?>"
     data-card-show-excerpt="<?php echo esc_attr(!empty($cardOptions['show_excerpt']) ? '1' : '0'); ?>"
     data-card-show-operation="<?php echo esc_attr(!empty($cardOptions['show_operation']) ? '1' : '0'); ?>"
     data-card-show-price="<?php echo esc_attr(!empty($cardOptions['show_price']) ? '1' : '0'); ?>"
     data-card-show-features="<?php echo esc_attr(!empty($cardOptions['show_features']) ? '1' : '0'); ?>"
     data-card-show-whatsapp="<?php echo esc_attr(!empty($cardOptions['show_whatsapp']) ? '1' : '0'); ?>"
     data-card-whatsapp-label="<?php echo esc_attr($cardOptions['whatsapp_label'] ?? ''); ?>"
     data-card-feature-area="<?php echo esc_attr(!empty($cardOptions['feature_area']) ? '1' : '0'); ?>"
     data-card-feature-bedrooms="<?php echo esc_attr(!empty($cardOptions['feature_bedrooms']) ? '1' : '0'); ?>"
     data-card-feature-bathrooms="<?php echo esc_attr(!empty($cardOptions['feature_bathrooms']) ? '1' : '0'); ?>"
     data-card-feature-parking="<?php echo esc_attr(!empty($cardOptions['feature_parking']) ? '1' : '0'); ?>"
     data-card-feature-area-lot="<?php echo esc_attr(!empty($cardOptions['feature_area_lot']) ? '1' : '0'); ?>"
     data-card-feature-area-private="<?php echo esc_attr(!empty($cardOptions['feature_area_private']) ? '1' : '0'); ?>"
     data-card-feature-area-built="<?php echo esc_attr(!empty($cardOptions['feature_area_built']) ? '1' : '0'); ?>"
     data-card-feature-age="<?php echo esc_attr(!empty($cardOptions['feature_age']) ? '1' : '0'); ?>"
     data-card-feature-condition="<?php echo esc_attr(!empty($cardOptions['feature_condition']) ? '1' : '0'); ?>"
     data-card-feature-code="<?php echo esc_attr(!empty($cardOptions['feature_code']) ? '1' : '0'); ?>"
     data-nonce="<?php echo esc_attr(wp_create_nonce('homlity_listing_nonce')); ?>"
     data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
     data-map-data="<?php echo esc_attr(wp_json_encode($mapData)); ?>">

    <div class="property-listing__toolbar">
        <?php if ($config->showResultsCount()) : ?>
        <p class="property-listing__count">
            <strong class="property-listing__count-number"><?php echo esc_html($query->found_posts); ?></strong>
            <?php esc_html_e('inmuebles encontrados', 'homlity-plugin'); ?>
        </p>
        <?php endif; ?>
        <div class="property-listing__toolbar-right">
            <?php if ($config->showSort()) : ?>
            <select class="property-listing__sort" aria-label="<?php esc_attr_e('Ordenar por', 'homlity-plugin'); ?>">
                <?php foreach ($sortOptions as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($config->orderby(), $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <?php if ($config->showViewToggle()) : ?>
            <div class="property-listing__view-toggle" role="group" aria-label="<?php esc_attr_e('Cambiar vista', 'homlity-plugin'); ?>">
                <button type="button"
                        class="property-listing__view-btn property-listing__view-btn--grid<?php echo $config->defaultView() === 'grid' ? ' is-active' : ''; ?>"
                        data-view="grid" title="<?php esc_attr_e('Vista grilla', 'homlity-plugin'); ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <rect x="0" y="0" width="7" height="7" rx="1"/>
                        <rect x="9" y="0" width="7" height="7" rx="1"/>
                        <rect x="0" y="9" width="7" height="7" rx="1"/>
                        <rect x="9" y="9" width="7" height="7" rx="1"/>
                    </svg>
                </button>
                <button type="button"
                        class="property-listing__view-btn property-listing__view-btn--map<?php echo $config->defaultView() === 'map' ? ' is-active' : ''; ?>"
                        data-view="map" title="<?php esc_attr_e('Vista mapa', 'homlity-plugin'); ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path d="M8 1C5.24 1 3 3.24 3 6c0 4 5 9 5 9s5-5 5-9c0-2.76-2.24-5-5-5zm0 6.5A1.5 1.5 0 1 1 8 4a1.5 1.5 0 0 1 0 3.5z"/>
                    </svg>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="property-listing__content">
        <div class="property-listing__grid"
             style="--columns:<?php echo esc_attr($config->columns()); ?>;"
             <?php echo $config->defaultView() === 'map' ? 'hidden' : ''; ?>>
            <?php
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    \Homlity\PluginInmobiliario\Services\TemplateService::includeComponent(
                        'property-card.php',
                        [
                            'post_id' => get_the_ID(),
                            'card_options' => $config->cardOptions(),
                        ]
                    );
                }
                wp_reset_postdata();
            } else {
                echo '<p class="property-listing__empty">' . esc_html__('No se han encontrado inmuebles para esta consulta.', 'homlity-plugin') . '</p>';
            }
            ?>
        </div>

        <div class="property-listing__map-container" <?php echo $config->defaultView() === 'grid' ? 'hidden' : ''; ?>>
            <div id="<?php echo esc_attr($uniqueId); ?>-map"
                 class="property-listing__map"
                 style="height:<?php echo esc_attr($config->mapHeight()); ?>px;"></div>
        </div>
    </div>

    <?php if ($config->showPagination() && $query->max_num_pages > 1) : ?>
    <div class="property-listing__pagination" data-current="1" data-pages="<?php echo esc_attr($query->max_num_pages); ?>">
        <?php for ($i = 1; $i <= $query->max_num_pages; $i++) : ?>
        <button type="button"
                class="property-listing__page-btn<?php echo $i === 1 ? ' is-active' : ''; ?>"
                data-page="<?php echo esc_attr($i); ?>">
            <?php echo esc_html($i); ?>
        </button>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <div class="property-listing__overlay" aria-hidden="true">
        <span class="property-listing__spinner"></span>
    </div>

</div>
