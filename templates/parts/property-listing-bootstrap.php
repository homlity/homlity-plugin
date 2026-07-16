<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Property listing – Bootstrap 5 template.
 * Replicates the structure of templates/bootstrap/inmuebles/search.php but driven
 * by WordPress data (WP_Query + homlity taxonomy) and editable through Elementor.
 *
 * Overridable at homlity-real-estate/parts/property-listing-bootstrap.php in theme or child theme.
 *
 * Expected args:
 *   $config  (ListingConfig)
 *   $query   (WP_Query)
 *   $search  (PropertySearchService)
 *   $params  (array)
 *
 * Requires Bootstrap 5 CSS + JS (loaded by the active theme or another plugin).
 * JS hooks (.property-listing__*, data-view, data-page …) are preserved so
 * property-listing.js works regardless of template variant.
 */

use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

/** @var ListingConfig $config */
$uid        = 'hpl-' . substr(md5(uniqid('', true)), 0, 8);
$listTabId   = $uid . '-listtab';
$mapTabId    = $uid . '-maptab';

$mapData     = $search->getMapData($query);
$cardOptions = $config->cardOptions();
$sortOptions = [
    'date'       => __('Más recientes',         'homlity-real-estate'),
    'price_asc'  => __('Precio: menor a mayor', 'homlity-real-estate'),
    'price_desc' => __('Precio: mayor a menor', 'homlity-real-estate'),
    'title'      => __('Nombre A–Z',            'homlity-real-estate'),
];

// Default view: map tab starts active if configured
$listActive = $config->defaultView() !== 'map';
$mapActive  = !$listActive;
$params = isset($params) && is_array($params) ? $params : [];
$currentPage = max(1, (int) ($query->get('paged') ?: ($params['page'] ?? 1)));
$currentOrder = sanitize_key((string) ($params['orderby'] ?? $config->orderby()));
$paramToAttr = static function ($value): string {
    if (is_array($value)) {
        return implode(',', array_map('strval', $value));
    }
    return (string) $value;
};
?>
<div id="<?php echo esc_attr($uid); ?>"
     class="homlity-real-estate-search property-listing property-listing--bootstrap"
     data-default-order="<?php echo esc_attr($config->orderby()); ?>"
     data-view="<?php echo esc_attr($config->defaultView()); ?>"
     data-per-page="<?php echo esc_attr($config->postsPerPage()); ?>"
     data-columns="<?php echo esc_attr($config->columns()); ?>"
     data-map-zoom="<?php echo esc_attr($config->mapZoom()); ?>"
     data-template="bootstrap"
     data-query-mode="<?php echo esc_attr($params['query_mode'] ?? $config->queryMode()); ?>"
     data-search="<?php echo esc_attr($params['search'] ?? $config->searchKeyword()); ?>"
     data-featured="<?php echo esc_attr(!empty($params['featured']) || $config->featuredOnly() ? '1' : '0'); ?>"
     data-preset-category="<?php echo esc_attr($params['preset_category'] ?? $config->presetCategory()); ?>"
     data-preset-operation="<?php echo esc_attr($params['preset_operation'] ?? $config->presetOperation()); ?>"
     data-preset-type="<?php echo esc_attr($params['preset_type'] ?? $config->presetType()); ?>"
     data-preset-tag="<?php echo esc_attr($params['preset_tag'] ?? $config->presetTag()); ?>"
     data-preset-tag-ids="<?php echo esc_attr($paramToAttr($params['preset_tag_ids'] ?? $config->presetTagIds())); ?>"
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
     data-card-hover-effect="<?php echo esc_attr($cardOptions['hover_effect'] ?? 'lift'); ?>"
     data-card-show-title="<?php echo esc_attr(!empty($cardOptions['show_title']) ? '1' : '0'); ?>"
     data-card-show-excerpt="<?php echo esc_attr(!empty($cardOptions['show_excerpt']) ? '1' : '0'); ?>"
     data-card-show-operation="<?php echo esc_attr(!empty($cardOptions['show_operation']) ? '1' : '0'); ?>"
     data-card-show-price="<?php echo esc_attr(!empty($cardOptions['show_price']) ? '1' : '0'); ?>"
     data-card-show-features="<?php echo esc_attr(!empty($cardOptions['show_features']) ? '1' : '0'); ?>"
     data-card-show-whatsapp="<?php echo esc_attr(!empty($cardOptions['show_whatsapp']) ? '1' : '0'); ?>"
     data-card-whatsapp-label="<?php echo esc_attr($cardOptions['whatsapp_label'] ?? ''); ?>"
     data-card-whatsapp-show-icon="<?php echo esc_attr(!empty($cardOptions['whatsapp_show_icon']) ? '1' : '0'); ?>"
     data-card-whatsapp-icon-position="<?php echo esc_attr($cardOptions['whatsapp_icon_position'] ?? 'left'); ?>"
     data-card-whatsapp-icon="<?php echo esc_attr(wp_json_encode($cardOptions['whatsapp_icon'] ?? [])); ?>"
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
     data-card-feature-icon-area="<?php echo esc_attr(wp_json_encode($cardOptions['feature_icon_area'] ?? [])); ?>"
     data-card-feature-icon-bedrooms="<?php echo esc_attr(wp_json_encode($cardOptions['feature_icon_bedrooms'] ?? [])); ?>"
     data-card-feature-icon-bathrooms="<?php echo esc_attr(wp_json_encode($cardOptions['feature_icon_bathrooms'] ?? [])); ?>"
     data-card-feature-icon-parking="<?php echo esc_attr(wp_json_encode($cardOptions['feature_icon_parking'] ?? [])); ?>"
     data-card-feature-icon-area-lot="<?php echo esc_attr(wp_json_encode($cardOptions['feature_icon_area_lot'] ?? [])); ?>"
     data-card-feature-icon-area-private="<?php echo esc_attr(wp_json_encode($cardOptions['feature_icon_area_private'] ?? [])); ?>"
     data-card-feature-icon-area-built="<?php echo esc_attr(wp_json_encode($cardOptions['feature_icon_area_built'] ?? [])); ?>"
     data-card-feature-icon-age="<?php echo esc_attr(wp_json_encode($cardOptions['feature_icon_age'] ?? [])); ?>"
     data-card-feature-icon-condition="<?php echo esc_attr(wp_json_encode($cardOptions['feature_icon_condition'] ?? [])); ?>"
     data-card-feature-icon-code="<?php echo esc_attr(wp_json_encode($cardOptions['feature_icon_code'] ?? [])); ?>"
     data-current-page="<?php echo esc_attr($currentPage); ?>"
     data-nonce="<?php echo esc_attr(wp_create_nonce('homlity_listing_nonce')); ?>"
     data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
     data-map-data="<?php echo esc_attr(wp_json_encode($mapData)); ?>"
     data-list-tab-id="<?php echo esc_attr($listTabId); ?>"
     data-map-tab-id="<?php echo esc_attr($mapTabId); ?>">

    <!-- ── Main container ────────────────────────────────────────────────── -->
    <div class="container-fluid contenedor-homlity-inmueble px-0">

        <!-- ── Search header (filter btn + tabs + count + sort) ─────────── -->
        <div class="clearfix mb-3 homlity-search-header">

            <div class="float-start d-flex align-items-center flex-wrap gap-2">

                <?php if ($config->showViewToggle()) : ?>
                <ul class="nav nav-pills border rounded property-listing__view-toggle"
                    id="<?php echo esc_attr($uid); ?>-tabs"
                    role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link<?php echo $listActive ? ' active' : ''; ?> property-listing__view-btn property-listing__view-btn--grid"
                                id="<?php echo esc_attr($uid); ?>-list-tab"
                                data-view="grid"
                                data-bs-toggle="pill"
                                data-bs-target="#<?php echo esc_attr($listTabId); ?>"
                                type="button" role="tab"
                                aria-controls="<?php echo esc_attr($listTabId); ?>"
                                aria-selected="<?php echo $listActive ? 'true' : 'false'; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" class="me-1 property-listing__view-icon">
                                <rect x="0" y="0" width="5" height="5" rx="1"/><rect x="7" y="0" width="5" height="5" rx="1"/><rect x="0" y="7" width="5" height="5" rx="1"/>
                                <rect x="7" y="7" width="5" height="5" rx="1"/><rect x="0" y="0" width="5" height="5" rx="1" transform="translate(0,0)"/>
                            </svg>
                            <?php esc_html_e('Listado', 'homlity-real-estate'); ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link<?php echo $mapActive ? ' active' : ''; ?> property-listing__view-btn property-listing__view-btn--map"
                                id="<?php echo esc_attr($uid); ?>-map-tab"
                                data-view="map"
                                data-bs-toggle="pill"
                                data-bs-target="#<?php echo esc_attr($mapTabId); ?>"
                                type="button" role="tab"
                                aria-controls="<?php echo esc_attr($mapTabId); ?>"
                                aria-selected="<?php echo $mapActive ? 'true' : 'false'; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" class="me-1 property-listing__view-icon">
                                <path fill="currentColor" d="M8 1C5.24 1 3 3.24 3 6c0 4 5 9 5 9s5-5 5-9c0-2.76-2.24-5-5-5zm0 6.5A1.5 1.5 0 1 1 8 4a1.5 1.5 0 0 1 0 3.5z"/>
                            </svg>
                            <?php esc_html_e('Mapa', 'homlity-real-estate'); ?>
                        </button>
                    </li>
                </ul>
                <?php endif; ?>
            </div>

            <div class="float-end d-flex align-items-center gap-2">
                <?php if ($config->showResultsCount()) : ?>
                <div class="fst-italic text-muted">
                    <small class="property-listing__count">
                        <strong class="property-listing__count-number"><?php echo esc_html($query->found_posts); ?></strong>
                        <?php esc_html_e('inmuebles encontrados', 'homlity-real-estate'); ?>
                    </small>
                </div>
                <?php endif; ?>

                <?php if ($config->showSort()) : ?>
                <div class="homlity-btns-order">
                    <select class="form-select form-select-sm property-listing__sort"
                            aria-label="<?php esc_attr_e('Ordenar por', 'homlity-real-estate'); ?>">
                        <?php foreach ($sortOptions as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>"
                                <?php selected($currentOrder, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /search-header -->

        <!-- ── Tab content ──────────────────────────────────────────────── -->
        <div class="tab-content" id="<?php echo esc_attr($uid); ?>-tab-content">

            <!-- Listado tab -->
            <div class="tab-pane<?php echo $listActive ? ' show active' : ''; ?>"
                 id="<?php echo esc_attr($listTabId); ?>"
                 role="tabpanel"
                 aria-labelledby="<?php echo esc_attr($uid); ?>-list-tab">
                <div class="cuerpo-grilla-inmuebles">
                    <div class="property-listing__grid row d-flex align-items-stretch flex-wrap g-3"
                         itemscope itemtype="https://schema.org/ItemList">
                        <meta itemprop="numberOfItems" content="<?php echo esc_attr($query->found_posts); ?>"/>
                        <meta itemprop="itemListOrder" content="https://schema.org/ItemListOrderDescending"/>
                        <?php
                        if ($query->have_posts()) {
                            $cont = 1;
                            while ($query->have_posts()) {
                                $query->the_post();
                                TemplateService::includeComponent(
                                    'property-card-bootstrap.php',
                                    [
                                        'post_id' => get_the_ID(),
                                        'cont' => $cont++,
                                        'card_options' => $config->cardOptions(),
                                    ]
                                );
                            }
                            wp_reset_postdata();
                        } else {
                            echo '<div class="col-12 text-center py-5">'
                                . '<p class="text-muted property-listing__empty">'
                                . esc_html__('No se han encontrado inmuebles para esta consulta.', 'homlity-real-estate')
                                . '</p></div>';
                        }
                        ?>
                    </div>
                </div>
            </div><!-- /listtab -->

            <!-- Mapa tab -->
            <div class="tab-pane<?php echo $mapActive ? ' show active' : ''; ?>"
                 id="<?php echo esc_attr($mapTabId); ?>"
                 role="tabpanel"
                 aria-labelledby="<?php echo esc_attr($uid); ?>-map-tab">
                <div class="mapa-inmueble property-listing__map-container">
                    <div id="<?php echo esc_attr($uid); ?>-map"
                         class="property-listing__map w-100"
                         style="height:<?php echo esc_attr($config->mapHeight()); ?>px;"></div>
                </div>
            </div><!-- /maptab -->

        </div><!-- /tab-content -->

        <!-- ── Paginación ────────────────────────────────────────────────── -->
        <?php if ($config->showPagination() && $query->max_num_pages > 1) : ?>
        <div class="property-listing__pagination mt-4"
             data-current="<?php echo esc_attr($currentPage); ?>"
             data-pages="<?php echo esc_attr($query->max_num_pages); ?>">
            <nav aria-label="<?php esc_attr_e('Paginación de inmuebles', 'homlity-real-estate'); ?>">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $query->max_num_pages; $i++) : ?>
                    <li class="page-item<?php echo $i === $currentPage ? ' active' : ''; ?>">
                        <button type="button"
                                class="page-link property-listing__page-btn"
                                data-page="<?php echo esc_attr($i); ?>">
                            <?php echo esc_html($i); ?>
                        </button>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

    </div><!-- /container-fluid -->

    <!-- ── Loading overlay ──────────────────────────────────────────────── -->
    <div class="property-listing__overlay" aria-hidden="true">
        <div class="spinner-border text-primary" role="status" style="width:2.5rem;height:2.5rem;">
            <span class="visually-hidden"><?php esc_html_e('Cargando…', 'homlity-real-estate'); ?></span>
        </div>
    </div>

</div><!-- /homlity-real-estate-search -->
