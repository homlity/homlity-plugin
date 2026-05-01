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
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

/** @var ListingConfig $config */
$uniqueId = 'hpl-' . substr(md5(uniqid('', true)), 0, 8);
$params = isset($params) && is_array($params) ? $params : [];

$showFilterOp   = $config->showFilters() && $config->showFilterOperation() && !$config->presetOperation();
$showFilterType = $config->showFilters() && $config->showFilterType()      && !$config->presetType();
$showFilterCity = $config->showFilters() && $config->showFilterCity();
$showFilterPx   = $config->showFilters() && $config->showFilterPrice();
$showFilterBed  = $config->showFilters() && $config->showFilterBedrooms();
$hasAnyFilter   = $showFilterOp || $showFilterType || $showFilterCity || $showFilterPx || $showFilterBed;

$operationTerms = $showFilterOp   ? get_terms(['taxonomy' => PropertyTaxonomies::TAXONOMY_OPERATION, 'hide_empty' => true]) : [];
$typeTerms      = $showFilterType ? get_terms(['taxonomy' => PropertyTaxonomies::TAXONOMY_TYPE,      'hide_empty' => true]) : [];
$cityTerms      = $showFilterCity ? get_terms(['taxonomy' => PropertyTaxonomies::TAXONOMY_CITY,      'hide_empty' => true]) : [];

$operationTerms = is_wp_error($operationTerms) ? [] : $operationTerms;
$typeTerms      = is_wp_error($typeTerms)      ? [] : $typeTerms;
$cityTerms      = is_wp_error($cityTerms)      ? [] : $cityTerms;

$mapData     = $search->getMapData($query);
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
     data-nonce="<?php echo esc_attr(wp_create_nonce('homlity_listing_nonce')); ?>"
     data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
     data-map-data="<?php echo esc_attr(wp_json_encode($mapData)); ?>">

    <?php if ($config->showFilters() && $hasAnyFilter) : ?>
    <form class="property-listing__filters" novalidate>
        <div class="property-listing__filters-row">

            <?php if ($showFilterOp && $operationTerms) : ?>
            <div class="property-listing__filter-group">
                <label class="property-listing__filter-label"><?php esc_html_e('Gestión', 'homlity-plugin'); ?></label>
                <select name="operation" class="property-listing__filter-select">
                    <option value=""><?php esc_html_e('Todas', 'homlity-plugin'); ?></option>
                    <?php foreach ($operationTerms as $term) : ?>
                        <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($showFilterType && $typeTerms) : ?>
            <div class="property-listing__filter-group">
                <label class="property-listing__filter-label"><?php esc_html_e('Tipo', 'homlity-plugin'); ?></label>
                <select name="type" class="property-listing__filter-select">
                    <option value=""><?php esc_html_e('Todos', 'homlity-plugin'); ?></option>
                    <?php foreach ($typeTerms as $term) : ?>
                        <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($showFilterCity && $cityTerms) : ?>
            <div class="property-listing__filter-group">
                <label class="property-listing__filter-label"><?php esc_html_e('Ciudad', 'homlity-plugin'); ?></label>
                <select name="city" class="property-listing__filter-select">
                    <option value=""><?php esc_html_e('Todas', 'homlity-plugin'); ?></option>
                    <?php foreach ($cityTerms as $term) : ?>
                        <option value="<?php echo esc_attr($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($showFilterPx) : ?>
            <div class="property-listing__filter-group property-listing__filter-group--price">
                <label class="property-listing__filter-label"><?php esc_html_e('Precio', 'homlity-plugin'); ?></label>
                <div class="property-listing__price-range">
                    <input type="number" name="price_min" class="property-listing__filter-input"
                           placeholder="<?php esc_attr_e('Mín.', 'homlity-plugin'); ?>" min="0" step="1000">
                    <span class="property-listing__price-sep">–</span>
                    <input type="number" name="price_max" class="property-listing__filter-input"
                           placeholder="<?php esc_attr_e('Máx.', 'homlity-plugin'); ?>" min="0" step="1000">
                </div>
            </div>
            <?php endif; ?>

            <?php if ($showFilterBed) : ?>
            <div class="property-listing__filter-group">
                <label class="property-listing__filter-label"><?php esc_html_e('Habitaciones', 'homlity-plugin'); ?></label>
                <select name="bedrooms" class="property-listing__filter-select">
                    <option value=""><?php esc_html_e('Cualquiera', 'homlity-plugin'); ?></option>
                    <?php foreach ([1, 2, 3, 4, 5] as $n) : ?>
                        <option value="<?php echo esc_attr($n); ?>"><?php echo esc_html($n); ?>+</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="property-listing__filter-actions">
                <button type="submit" class="property-listing__btn property-listing__btn--primary">
                    <?php esc_html_e('Buscar', 'homlity-plugin'); ?>
                </button>
                <button type="button" class="property-listing__btn property-listing__btn--ghost property-listing__filter-reset">
                    <?php esc_html_e('Limpiar', 'homlity-plugin'); ?>
                </button>
            </div>

        </div>
    </form>
    <?php endif; ?>

    <div class="property-listing__toolbar">
        <p class="property-listing__count">
            <strong class="property-listing__count-number"><?php echo esc_html($query->found_posts); ?></strong>
            <?php esc_html_e('inmuebles encontrados', 'homlity-plugin'); ?>
        </p>
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
                        ['post_id' => get_the_ID()]
                    );
                }
                wp_reset_postdata();
            } else {
                echo '<p class="property-listing__empty">' . esc_html__('No se encontraron inmuebles.', 'homlity-plugin') . '</p>';
            }
            ?>
        </div>

        <div class="property-listing__map-container" <?php echo $config->defaultView() === 'grid' ? 'hidden' : ''; ?>>
            <div id="<?php echo esc_attr($uniqueId); ?>-map"
                 class="property-listing__map"
                 style="height:<?php echo esc_attr($config->mapHeight()); ?>px;"></div>
        </div>
    </div>

    <?php if ($query->max_num_pages > 1) : ?>
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
