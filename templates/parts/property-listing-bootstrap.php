<?php
/**
 * Property listing – Bootstrap 5 template.
 * Replicates the structure of templates/bootstrap/inmuebles/search.php but driven
 * by WordPress data (WP_Query + homlity taxonomy) and editable through Elementor.
 *
 * Overridable at homlity-plugin/parts/property-listing-bootstrap.php in theme or child theme.
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
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

/** @var ListingConfig $config */
$uid        = 'hpl-' . substr(md5(uniqid('', true)), 0, 8);
$offcanvasId = $uid . '-offcanvas';
$listTabId   = $uid . '-listtab';
$mapTabId    = $uid . '-maptab';

// Which filters to show
$showFilterOp   = $config->showFilters() && $config->showFilterOperation() && !$config->presetOperation();
$showFilterType = $config->showFilters() && $config->showFilterType()      && !$config->presetType();
$showFilterCity = $config->showFilters() && $config->showFilterCity();
$showFilterPx   = $config->showFilters() && $config->showFilterPrice();
$showFilterBed  = $config->showFilters() && $config->showFilterBedrooms();
$hasAnyFilter   = $showFilterOp || $showFilterType || $showFilterCity || $showFilterPx || $showFilterBed;

// Taxonomy terms
$operationTerms = $showFilterOp   ? get_terms(['taxonomy' => PropertyTaxonomies::TAXONOMY_OPERATION,    'hide_empty' => true]) : [];
$typeTerms      = $showFilterType ? get_terms(['taxonomy' => PropertyTaxonomies::TAXONOMY_TYPE,         'hide_empty' => true]) : [];
$cityTerms      = $showFilterCity ? get_terms(['taxonomy' => PropertyTaxonomies::TAXONOMY_CITY,         'hide_empty' => true]) : [];
$neighborTerms  = $showFilterCity ? get_terms(['taxonomy' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, 'hide_empty' => true]) : [];

foreach (['operationTerms', 'typeTerms', 'cityTerms', 'neighborTerms'] as $var) {
    $$var = is_wp_error($$var) ? [] : $$var;
}

$mapData     = $search->getMapData($query);
$sortOptions = [
    'date'       => __('Más recientes',         'homlity-plugin'),
    'price_asc'  => __('Precio: menor a mayor', 'homlity-plugin'),
    'price_desc' => __('Precio: mayor a menor', 'homlity-plugin'),
    'title'      => __('Nombre A–Z',            'homlity-plugin'),
];

// Default view: map tab starts active if configured
$listActive = $config->defaultView() !== 'map';
$mapActive  = !$listActive;
$params = isset($params) && is_array($params) ? $params : [];
?>
<div id="<?php echo esc_attr($uid); ?>"
     class="visualinmueble-search property-listing property-listing--bootstrap"
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
     data-nonce="<?php echo esc_attr(wp_create_nonce('homlity_listing_nonce')); ?>"
     data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
     data-map-data="<?php echo esc_attr(wp_json_encode($mapData)); ?>"
     data-list-tab-id="<?php echo esc_attr($listTabId); ?>"
     data-map-tab-id="<?php echo esc_attr($mapTabId); ?>">

    <?php if ($hasAnyFilter) : ?>
    <!-- ── Offcanvas: filtros ─────────────────────────────────────────────── -->
    <div class="offcanvas offcanvas-start"
         data-bs-scroll="true"
         tabindex="-1"
         id="<?php echo esc_attr($offcanvasId); ?>"
         aria-labelledby="<?php echo esc_attr($offcanvasId); ?>-label">
        <div class="offcanvas-header border-bottom">
            <span class="offcanvas-title h5" id="<?php echo esc_attr($offcanvasId); ?>-label">
                <?php esc_html_e('Filtros', 'homlity-plugin'); ?>
            </span>
            <button type="button" class="btn-close"
                    data-bs-dismiss="offcanvas"
                    aria-label="<?php esc_attr_e('Cerrar', 'homlity-plugin'); ?>"></button>
        </div>
        <div class="offcanvas-body">
            <form class="property-listing__filters" id="<?php echo esc_attr($uid); ?>-filters" novalidate>

                <?php if ($showFilterOp && $operationTerms) : ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small"><?php esc_html_e('Gestión', 'homlity-plugin'); ?></label>
                    <select name="operation" class="form-select form-select-sm">
                        <option value=""><?php esc_html_e('Todas', 'homlity-plugin'); ?></option>
                        <?php foreach ($operationTerms as $term) : ?>
                            <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($showFilterType && $typeTerms) : ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small"><?php esc_html_e('Tipo de inmueble', 'homlity-plugin'); ?></label>
                    <select name="type" class="form-select form-select-sm">
                        <option value=""><?php esc_html_e('Todos', 'homlity-plugin'); ?></option>
                        <?php foreach ($typeTerms as $term) : ?>
                            <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($showFilterCity && $cityTerms) : ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small"><?php esc_html_e('Ciudad', 'homlity-plugin'); ?></label>
                    <select name="city" class="form-select form-select-sm">
                        <option value=""><?php esc_html_e('Todas', 'homlity-plugin'); ?></option>
                        <?php foreach ($cityTerms as $term) : ?>
                            <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($showFilterCity && $neighborTerms) : ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small"><?php esc_html_e('Barrio', 'homlity-plugin'); ?></label>
                    <select name="neighborhood" class="form-select form-select-sm">
                        <option value=""><?php esc_html_e('Todos', 'homlity-plugin'); ?></option>
                        <?php foreach ($neighborTerms as $term) : ?>
                            <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($showFilterPx) : ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small"><?php esc_html_e('Precio', 'homlity-plugin'); ?></label>
                    <div class="input-group input-group-sm">
                        <input type="number" name="price_min" class="form-control"
                               placeholder="<?php esc_attr_e('Mín.', 'homlity-plugin'); ?>" min="0" step="1000000">
                        <span class="input-group-text">–</span>
                        <input type="number" name="price_max" class="form-control"
                               placeholder="<?php esc_attr_e('Máx.', 'homlity-plugin'); ?>" min="0" step="1000000">
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($showFilterBed) : ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small"><?php esc_html_e('Habitaciones', 'homlity-plugin'); ?></label>
                    <select name="bedrooms" class="form-select form-select-sm">
                        <option value=""><?php esc_html_e('Cualquiera', 'homlity-plugin'); ?></option>
                        <?php foreach ([1, 2, 3, 4, 5] as $n) : ?>
                            <option value="<?php echo esc_attr($n); ?>"><?php echo esc_html($n); ?>+</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="d-grid gap-2 mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <?php esc_html_e('Buscar', 'homlity-plugin'); ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm property-listing__filter-reset">
                        <?php esc_html_e('Limpiar', 'homlity-plugin'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Main container ────────────────────────────────────────────────── -->
    <div class="container-fluid contenedor-visual-inmueble px-0">

        <!-- ── Search header (filter btn + tabs + count + sort) ─────────── -->
        <div class="clearfix mb-3 visualinmu-search-header">

            <div class="float-start d-flex align-items-center flex-wrap gap-2">

                <?php if ($hasAnyFilter) : ?>
                <a class="btn btn-primary btn-sm"
                   id="<?php echo esc_attr($uid); ?>-filter-btn"
                   data-bs-toggle="offcanvas"
                   href="#<?php echo esc_attr($offcanvasId); ?>"
                   role="button"
                   aria-controls="<?php echo esc_attr($offcanvasId); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" class="me-1">
                        <path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5z"/>
                    </svg>
                    <?php esc_html_e('Filtros', 'homlity-plugin'); ?>
                </a>
                <?php endif; ?>

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
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" class="me-1">
                                <rect x="0" y="0" width="5" height="5" rx="1"/><rect x="7" y="0" width="5" height="5" rx="1"/><rect x="0" y="7" width="5" height="5" rx="1"/>
                                <rect x="7" y="7" width="5" height="5" rx="1"/><rect x="0" y="0" width="5" height="5" rx="1" transform="translate(0,0)"/>
                            </svg>
                            <?php esc_html_e('Listado', 'homlity-plugin'); ?>
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
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" class="me-1">
                                <path d="M8 1C5.24 1 3 3.24 3 6c0 4 5 9 5 9s5-5 5-9c0-2.76-2.24-5-5-5zm0 6.5A1.5 1.5 0 1 1 8 4a1.5 1.5 0 0 1 0 3.5z"/>
                            </svg>
                            <?php esc_html_e('Mapa', 'homlity-plugin'); ?>
                        </button>
                    </li>
                </ul>
                <?php endif; ?>
            </div>

            <div class="float-end d-flex align-items-center gap-2">
                <div class="fst-italic text-muted">
                    <small class="property-listing__count">
                        <strong class="property-listing__count-number"><?php echo esc_html($query->found_posts); ?></strong>
                        <?php esc_html_e('inmuebles encontrados', 'homlity-plugin'); ?>
                    </small>
                </div>

                <?php if ($config->showSort()) : ?>
                <div class="visualinmu-btns-order">
                    <select class="form-select form-select-sm property-listing__sort"
                            aria-label="<?php esc_attr_e('Ordenar por', 'homlity-plugin'); ?>">
                        <?php foreach ($sortOptions as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>"
                                <?php selected($config->orderby(), $value); ?>>
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
                                    ['post_id' => get_the_ID(), 'cont' => $cont++]
                                );
                            }
                            wp_reset_postdata();
                        } else {
                            echo '<div class="col-12 text-center py-5">'
                                . '<h3 class="text-muted property-listing__empty">'
                                . esc_html__('No existen resultados para esta búsqueda.', 'homlity-plugin')
                                . '</h3></div>';
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
        <?php if ($query->max_num_pages > 1) : ?>
        <div class="property-listing__pagination mt-4"
             data-current="1"
             data-pages="<?php echo esc_attr($query->max_num_pages); ?>">
            <nav aria-label="<?php esc_attr_e('Paginación de inmuebles', 'homlity-plugin'); ?>">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $query->max_num_pages; $i++) : ?>
                    <li class="page-item<?php echo $i === 1 ? ' active' : ''; ?>">
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
            <span class="visually-hidden"><?php esc_html_e('Cargando…', 'homlity-plugin'); ?></span>
        </div>
    </div>

</div><!-- /visualinmueble-search -->
