<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Property filter component.
 * Overridable at homlity-real-estate/parts/property-filter.php
 *
 * Expected args: $settings (array)
 */

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\LocalityPostType;
use Homlity\PluginInmobiliario\Services\SearchLocationDefaults;

if (!defined('ABSPATH')) {
    exit;
}

$settings = isset($settings) && is_array($settings) ? $settings : [];
wp_enqueue_style('homlity-real-estate-front-components', HOMLITY_PLUGIN_URL . 'assets/css/front-components.css', [], HOMLITY_PLUGIN_VERSION);
wp_enqueue_style('homlity-real-estate-listing', HOMLITY_PLUGIN_URL . 'assets/css/property-listing.css', ['homlity-real-estate-front-components'], HOMLITY_PLUGIN_VERSION);
wp_enqueue_script('homlity-real-estate-filter', HOMLITY_PLUGIN_URL . 'assets/js/property-filter.js', [], HOMLITY_PLUGIN_VERSION, true);

$targetPageId = absint($settings['target_page_id'] ?? 0) ?: (int) get_option('homlity_plugin_archive_page_id', 0);
$action = $targetPageId ? get_permalink($targetPageId) : home_url('/inmuebles/');
$submitLabel = $settings['submit_label'] ?? __('Buscar', 'homlity-real-estate');
$resetLabel = $settings['reset_label'] ?? __('Limpiar', 'homlity-real-estate');
$keywordPlaceholder = isset($settings['keyword_placeholder']) && is_scalar($settings['keyword_placeholder'])
    ? (string) $settings['keyword_placeholder']
    : __('Buscar', 'homlity-real-estate');
$mobileSidebarEnabled = !empty($settings['mobile_sidebar_enabled']);
$mobileButtonLabel = $settings['mobile_filter_button_label'] ?? __('Filtrar inmuebles', 'homlity-real-estate');
$fieldLabelMode = 'placeholder';
$usePlaceholders = true;
$selectFirstOptionMode = (string) ($settings['select_first_option_mode'] ?? 'auto');
$selectUsesLabelOption = $selectFirstOptionMode === 'label'
    || ($selectFirstOptionMode === 'auto' && $usePlaceholders);
$instanceId = wp_unique_id('homlity-filter-');

$current = static function ($keys) {
    $keys = is_array($keys) ? $keys : [$keys];

    $normalize = static function ($raw) {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $items = explode(',', (string) $raw);
        }

        $clean = [];
        foreach ($items as $item) {
            $value = sanitize_text_field((string) $item);
            if ($value === '') {
                continue;
            }
            $clean[] = $value;
        }

        return array_values(array_unique($clean));
    };

    foreach ($keys as $key) {
        if (isset($_GET[$key])) {
            $values = $normalize(wp_unslash($_GET[$key]));
            if (count($values) > 1) {
                return $values;
            }
            return $values[0] ?? '';
        }

        $queryValue = get_query_var($key, null);
        if ($queryValue !== null && $queryValue !== '') {
            $values = $normalize($queryValue);
            if (count($values) > 1) {
                return $values;
            }
            return $values[0] ?? '';
        }
    }

    return '';
};

/**
 * Whether the visitor's request carries this field at all.
 *
 * Submitting the form sends every enabled field, empty ones included, so a
 * present-but-empty parameter means the visitor deliberately cleared it. Only
 * a request that never mentions the field can take the configured default.
 */
$searchSubmitted = isset($_GET['homlity_search']);

$requestHas = static function ($keys) use ($searchSubmitted): bool {
    // A submitted form speaks for every field at once. An unselected multiple
    // select sends nothing at all, so without this marker clearing the city
    // would look identical to arriving with a clean URL, and the configured
    // default would keep coming back.
    if ($searchSubmitted) {
        return true;
    }

    foreach ((array) $keys as $key) {
        if (isset($_GET[$key])) {
            return true;
        }

        $queryValue = get_query_var($key, null);
        if ($queryValue !== null && $queryValue !== '') {
            return true;
        }
    }

    return false;
};

// The agency's base location, pre-selected only when the administrator asked
// for it and the widget has not opted out.
$locationDefaults = empty($settings['ignore_default_location'])
    ? SearchLocationDefaults::slugs()
    : [];

$currentLocation = static function ($keys, string $level) use ($current, $requestHas, $locationDefaults) {
    return SearchLocationDefaults::pick($locationDefaults, $level, $requestHas($keys), $current($keys));
};

$termSelect = static function (string $name, string $taxonomy, string $label, $currentValue, bool $multiple = false, bool $usePlaceholders = false, bool $selectUsesLabelOption = false): void {
    $onlyPublishedOptions = [
        PropertyTaxonomies::TAXONOMY_OPERATION,
        PropertyTaxonomies::TAXONOMY_TYPE,
        PropertyTaxonomies::TAXONOMY_CITY,
        PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
    ];

    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => in_array($taxonomy, $onlyPublishedOptions, true),
    ]);
    if (is_wp_error($terms) || !$terms) {
        return;
    }
    $currentValues = is_array($currentValue) ? $currentValue : [$currentValue];
    $inputName = $multiple ? $name . '[]' : $name;
    ?>
    <div class="property-listing__filter-group">
        <?php if (!$usePlaceholders): ?>
            <label class="property-listing__filter-label" for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label>
        <?php endif; ?>
        <select
            name="<?php echo esc_attr($inputName); ?>"
            id="<?php echo esc_attr($name); ?>"
            class="property-listing__filter-select<?php echo $multiple ? ' property-filter-multiselect' : ''; ?>"
            <?php
            if ($multiple) {
                $multiPlaceholder = ($usePlaceholders || $selectUsesLabelOption) ? $label : __('Selecciona opciones', 'homlity-real-estate');
                echo 'multiple data-placeholder="' . esc_attr($multiPlaceholder) . '"';
            }
            ?>>
            <option value=""><?php echo esc_html($selectUsesLabelOption ? $label : __('Todos', 'homlity-real-estate')); ?></option>
            <?php foreach ($terms as $term):
                $optionData = '';
                if ($taxonomy === PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD) {
                    $cityId = (int) get_term_meta((int) $term->term_id, '_parent_city', true);
                    $city = $cityId > 0 ? get_term($cityId, PropertyTaxonomies::TAXONOMY_CITY) : null;
                    $localityId = (int) get_term_meta((int) $term->term_id, LocalityPostType::TERM_META_LOCALITY_ID, true);
                    $optionData = ' data-city-slug="' . esc_attr($city instanceof \WP_Term ? $city->slug : '') . '"'
                        . ' data-locality-id="' . esc_attr((string) $localityId) . '"';
                }
                ?>
                <option value="<?php echo esc_attr($term->slug); ?>"<?php echo $optionData; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php selected(in_array($term->slug, $currentValues, true)); ?>>
                    <?php echo esc_html($term->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
};

$localitySelect = static function ($currentValue, bool $usePlaceholders, bool $selectUsesLabelOption): void {
    $localities = get_posts([
        'post_type' => LocalityPostType::POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
    if (!$localities) {
        return;
    }
    $currentValues = is_array($currentValue) ? $currentValue : [$currentValue];
    ?>
    <div class="property-listing__filter-group">
        <?php if (!$usePlaceholders): ?>
            <label class="property-listing__filter-label" for="localidades"><?php esc_html_e('Localidad', 'homlity-real-estate'); ?></label>
        <?php endif; ?>
        <select name="localidades[]" id="localidades" class="property-listing__filter-select property-filter-multiselect" multiple data-placeholder="<?php esc_attr_e('Localidad', 'homlity-real-estate'); ?>">
            <option value=""><?php echo esc_html($selectUsesLabelOption ? __('Localidad', 'homlity-real-estate') : __('Todas', 'homlity-real-estate')); ?></option>
            <?php foreach ($localities as $locality):
                $city = get_term(LocalityPostType::cityId((int) $locality->ID), PropertyTaxonomies::TAXONOMY_CITY);
                ?>
                <option
                    value="<?php echo esc_attr((string) $locality->post_name); ?>"
                    data-locality-id="<?php echo esc_attr((string) $locality->ID); ?>"
                    data-city-slug="<?php echo esc_attr($city instanceof \WP_Term ? $city->slug : ''); ?>"
                    <?php selected(in_array((string) $locality->post_name, $currentValues, true) || in_array((string) $locality->ID, $currentValues, true)); ?>
                ><?php echo esc_html((string) $locality->post_title); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
};
?>
<div class="property-listing property-filter-widget<?php echo $mobileSidebarEnabled ? ' property-filter-widget--mobile-sidebar' : ''; ?>" data-filter-instance="<?php echo esc_attr($instanceId); ?>">
    <?php if ($mobileSidebarEnabled): ?>
        <button
            type="button"
            class="property-listing__mobile-toggle"
            data-mobile-filter-open
            data-target="<?php echo esc_attr($instanceId); ?>"
            aria-controls="<?php echo esc_attr($instanceId); ?>-sidebar"
            aria-expanded="false"
            title="<?php esc_attr_e('Abrir filtros de inmuebles', 'homlity-real-estate'); ?>">
            <span class="property-listing__mobile-toggle-title"><?php esc_html_e('Filtro', 'homlity-real-estate'); ?></span>
            <span class="property-listing__mobile-toggle-text"><?php echo esc_html($mobileButtonLabel); ?></span>
        </button>
    <?php endif; ?>

    <div class="property-listing__mobile-sidebar-overlay" data-mobile-filter-overlay hidden></div>
    <form class="property-listing__filters<?php echo $mobileSidebarEnabled ? ' property-listing__filters--mobile-sidebar' : ''; ?>" id="<?php echo esc_attr($instanceId); ?>-sidebar" action="<?php echo esc_url($action); ?>" method="get" data-mobile-filter-sidebar>
        <?php if ($locationDefaults): ?>
            <input type="hidden" name="homlity_search" value="1">
        <?php endif; ?>
        <?php if ($mobileSidebarEnabled): ?>
            <div class="property-listing__mobile-sidebar-header">
                <strong><?php esc_html_e('Filtros', 'homlity-real-estate'); ?></strong>
                <button type="button" class="property-listing__mobile-close" data-mobile-filter-close aria-label="<?php esc_attr_e('Cerrar filtros', 'homlity-real-estate'); ?>">&times;</button>
            </div>
        <?php endif; ?>
        <div class="property-listing__filters-row">
            <?php if (!empty($settings['show_keyword'])): ?>
                <div class="property-listing__filter-group">
                    <?php if (!$usePlaceholders): ?>
                        <label class="property-listing__filter-label" for="homlity-filter-s"><?php esc_html_e('Buscar', 'homlity-real-estate'); ?></label>
                    <?php endif; ?>
                    <input id="homlity-filter-s" class="property-listing__filter-input" type="search" name="q" value="<?php echo esc_attr($current(['q', 's'])); ?>" placeholder="<?php echo esc_attr($usePlaceholders ? $keywordPlaceholder : ''); ?>">
                </div>
            <?php endif; ?>

            <?php
            if (!empty($settings['show_category'])) {
                $termSelect('categoria', PropertyTaxonomies::TAXONOMY_CATEGORY, __('Categoría', 'homlity-real-estate'), $current(['categoria', 'property_category']), false, $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_operation'])) {
                $termSelect('gestion', PropertyTaxonomies::TAXONOMY_OPERATION, __('Gestión', 'homlity-real-estate'), $current(['gestion', 'property_operation']), !empty($settings['multiple_operation']), $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_type'])) {
                $termSelect('tipo', PropertyTaxonomies::TAXONOMY_TYPE, __('Tipo', 'homlity-real-estate'), $current(['tipo', 'property_type']), !empty($settings['multiple_type']), $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_tag'])) {
                $termSelect('etiquetas', PropertyTaxonomies::TAXONOMY_TAG, __('Etiqueta', 'homlity-real-estate'), $current(['etiquetas', 'property_tag']), !empty($settings['multiple_tag']), $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_country'])) {
                $termSelect('pais', PropertyTaxonomies::TAXONOMY_COUNTRY, __('País', 'homlity-real-estate'), $currentLocation(['pais', 'property_country'], 'country'), false, $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_state'])) {
                $termSelect('departamento', PropertyTaxonomies::TAXONOMY_STATE, __('Departamento', 'homlity-real-estate'), $currentLocation(['departamento', 'property_state'], 'state'), false, $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_city'])) {
                $termSelect('ciudad', PropertyTaxonomies::TAXONOMY_CITY, __('Ciudad', 'homlity-real-estate'), $currentLocation(['ciudad', 'property_city'], 'city'), true, $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_locality'])) {
                $localitySelect($current(['localidades', 'property_locality']), $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_neighborhood'])) {
                $termSelect('barrios', PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, __('Barrio', 'homlity-real-estate'), $currentLocation(['barrios', 'property_neighborhood'], 'neighborhood'), true, $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_nearby'])) {
                $termSelect('cercanias', PropertyTaxonomies::TAXONOMY_NEARBY, __('Lugar cercano', 'homlity-real-estate'), $current(['cercanias', 'property_nearby']), false, $usePlaceholders, $selectUsesLabelOption);
            }
            ?>

            <?php if (!empty($settings['show_price'])): ?>
                <div class="property-listing__filter-group property-listing__filter-group--price">
                    <?php if (!$usePlaceholders): ?>
                        <label class="property-listing__filter-label"><?php esc_html_e('Precio', 'homlity-real-estate'); ?></label>
                    <?php endif; ?>
                    <div class="property-listing__price-range">
                        <input class="property-listing__filter-input" type="number" name="precio_min" value="<?php echo esc_attr($current(['precio_min', 'price_min'])); ?>" placeholder="<?php echo esc_attr($usePlaceholders ? __('Precio mín.', 'homlity-real-estate') : __('Mín.', 'homlity-real-estate')); ?>" min="0">
                        <span class="property-listing__price-sep">-</span>
                        <input class="property-listing__filter-input" type="number" name="precio_max" value="<?php echo esc_attr($current(['precio_max', 'price_max'])); ?>" placeholder="<?php echo esc_attr($usePlaceholders ? __('Precio máx.', 'homlity-real-estate') : __('Máx.', 'homlity-real-estate')); ?>" min="0">
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($settings['show_bedrooms'])): ?>
                <div class="property-listing__filter-group">
                    <?php if (!$usePlaceholders): ?>
                        <label class="property-listing__filter-label" for="homlity-filter-bedrooms"><?php esc_html_e('Habitaciones', 'homlity-real-estate'); ?></label>
                    <?php endif; ?>
                    <select name="alcobas" id="homlity-filter-bedrooms" class="property-listing__filter-select">
                        <option value=""><?php echo esc_html($selectUsesLabelOption ? __('Habitaciones', 'homlity-real-estate') : __('Cualquiera', 'homlity-real-estate')); ?></option>
                        <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
                            <option value="<?php echo esc_attr($n); ?>" <?php selected((string) $current(['alcobas', 'bedrooms']), (string) $n); ?>><?php echo esc_html($n); ?>+</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($settings['show_bathrooms'])): ?>
                <div class="property-listing__filter-group">
                    <?php if (!$usePlaceholders): ?>
                        <label class="property-listing__filter-label" for="homlity-filter-bathrooms"><?php esc_html_e('Baños', 'homlity-real-estate'); ?></label>
                    <?php endif; ?>
                    <select name="banos" id="homlity-filter-bathrooms" class="property-listing__filter-select">
                        <option value=""><?php echo esc_html($selectUsesLabelOption ? __('Baños', 'homlity-real-estate') : __('Cualquiera', 'homlity-real-estate')); ?></option>
                        <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
                            <option value="<?php echo esc_attr($n); ?>" <?php selected((string) $current(['banos', 'bathrooms']), (string) $n); ?>><?php echo esc_html($n); ?>+</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($settings['show_parking'])): ?>
                <div class="property-listing__filter-group">
                    <?php if (!$usePlaceholders): ?>
                        <label class="property-listing__filter-label" for="homlity-filter-parking"><?php esc_html_e('Garajes', 'homlity-real-estate'); ?></label>
                    <?php endif; ?>
                    <select name="garajes" id="homlity-filter-parking" class="property-listing__filter-select">
                        <option value=""><?php echo esc_html($selectUsesLabelOption ? __('Garajes', 'homlity-real-estate') : __('Cualquiera', 'homlity-real-estate')); ?></option>
                        <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
                            <option value="<?php echo esc_attr($n); ?>" <?php selected((string) $current(['garajes', 'parking']), (string) $n); ?>><?php echo esc_html($n); ?>+</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($settings['show_area'])): ?>
                <div class="property-listing__filter-group property-listing__filter-group--price">
                    <?php if (!$usePlaceholders): ?>
                        <label class="property-listing__filter-label"><?php esc_html_e('Área (m²)', 'homlity-real-estate'); ?></label>
                    <?php endif; ?>
                    <div class="property-listing__price-range">
                        <input class="property-listing__filter-input" type="number" name="area_min" value="<?php echo esc_attr($current('area_min')); ?>" placeholder="<?php echo esc_attr($usePlaceholders ? __('Área mín.', 'homlity-real-estate') : __('Mín.', 'homlity-real-estate')); ?>" min="0">
                        <span class="property-listing__price-sep">-</span>
                        <input class="property-listing__filter-input" type="number" name="area_max" value="<?php echo esc_attr($current('area_max')); ?>" placeholder="<?php echo esc_attr($usePlaceholders ? __('Área máx.', 'homlity-real-estate') : __('Máx.', 'homlity-real-estate')); ?>" min="0">
                    </div>
                </div>
            <?php endif; ?>

            <div class="property-listing__filter-actions">
                <button type="submit" class="property-listing__btn property-listing__btn--primary"><?php echo esc_html($submitLabel); ?></button>
                <?php if (!empty($settings['show_reset'])): ?>
                    <a class="property-listing__btn property-listing__btn--ghost" href="<?php echo esc_url($action); ?>"><?php echo esc_html($resetLabel); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
