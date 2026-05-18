<?php
/**
 * Property filter component.
 * Overridable at homlity-plugin/parts/property-filter.php
 *
 * Expected args: $settings (array)
 */

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

$settings = isset($settings) && is_array($settings) ? $settings : [];
wp_enqueue_style('homlity-plugin-front-components', HOMLITY_PLUGIN_URL . 'assets/css/front-components.css', [], HOMLITY_PLUGIN_VERSION);
wp_enqueue_style('homlity-plugin-listing', HOMLITY_PLUGIN_URL . 'assets/css/property-listing.css', ['homlity-plugin-front-components'], HOMLITY_PLUGIN_VERSION);
wp_enqueue_script('homlity-plugin-filter', HOMLITY_PLUGIN_URL . 'assets/js/property-filter.js', [], HOMLITY_PLUGIN_VERSION, true);

$targetPageId = absint($settings['target_page_id'] ?? 0) ?: (int) get_option('homlity_plugin_archive_page_id', 0);
$action = $targetPageId ? get_permalink($targetPageId) : home_url('/inmuebles/');
$submitLabel = $settings['submit_label'] ?? __('Buscar', 'homlity-plugin');
$resetLabel = $settings['reset_label'] ?? __('Limpiar', 'homlity-plugin');
$mobileSidebarEnabled = !empty($settings['mobile_sidebar_enabled']);
$mobileButtonLabel = $settings['mobile_filter_button_label'] ?? __('Filtrar inmuebles', 'homlity-plugin');
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
                $multiPlaceholder = ($usePlaceholders || $selectUsesLabelOption) ? $label : __('Selecciona opciones', 'homlity-plugin');
                echo 'multiple data-placeholder="' . esc_attr($multiPlaceholder) . '"';
            }
            ?>>
            <option value=""><?php echo esc_html($selectUsesLabelOption ? $label : __('Todos', 'homlity-plugin')); ?></option>
            <?php foreach ($terms as $term): ?>
                <option value="<?php echo esc_attr($term->slug); ?>" <?php selected(in_array($term->slug, $currentValues, true)); ?>>
                    <?php echo esc_html($term->name); ?>
                </option>
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
            title="<?php esc_attr_e('Abrir filtros de inmuebles', 'homlity-plugin'); ?>">
            <span class="property-listing__mobile-toggle-title"><?php esc_html_e('Filtro', 'homlity-plugin'); ?></span>
            <span class="property-listing__mobile-toggle-text"><?php echo esc_html($mobileButtonLabel); ?></span>
        </button>
    <?php endif; ?>

    <div class="property-listing__mobile-sidebar-overlay" data-mobile-filter-overlay hidden></div>
    <form class="property-listing__filters<?php echo $mobileSidebarEnabled ? ' property-listing__filters--mobile-sidebar' : ''; ?>" id="<?php echo esc_attr($instanceId); ?>-sidebar" action="<?php echo esc_url($action); ?>" method="get" data-mobile-filter-sidebar>
        <?php if ($mobileSidebarEnabled): ?>
            <div class="property-listing__mobile-sidebar-header">
                <strong><?php esc_html_e('Filtros', 'homlity-plugin'); ?></strong>
                <button type="button" class="property-listing__mobile-close" data-mobile-filter-close aria-label="<?php esc_attr_e('Cerrar filtros', 'homlity-plugin'); ?>">&times;</button>
            </div>
        <?php endif; ?>
        <div class="property-listing__filters-row">
            <?php if (!empty($settings['show_keyword'])): ?>
                <div class="property-listing__filter-group">
                    <?php if (!$usePlaceholders): ?>
                        <label class="property-listing__filter-label" for="homlity-filter-s"><?php esc_html_e('Buscar', 'homlity-plugin'); ?></label>
                    <?php endif; ?>
                    <input id="homlity-filter-s" class="property-listing__filter-input" type="search" name="q" value="<?php echo esc_attr($current(['q', 's'])); ?>" placeholder="<?php echo esc_attr($usePlaceholders ? __('Buscar', 'homlity-plugin') : ''); ?>">
                </div>
            <?php endif; ?>

            <?php
            if (!empty($settings['show_category'])) {
                $termSelect('categoria', PropertyTaxonomies::TAXONOMY_CATEGORY, __('Categoría', 'homlity-plugin'), $current(['categoria', 'property_category']), false, $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_operation'])) {
                $termSelect('gestion', PropertyTaxonomies::TAXONOMY_OPERATION, __('Gestión', 'homlity-plugin'), $current(['gestion', 'property_operation']), !empty($settings['multiple_operation']), $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_type'])) {
                $termSelect('tipo', PropertyTaxonomies::TAXONOMY_TYPE, __('Tipo', 'homlity-plugin'), $current(['tipo', 'property_type']), !empty($settings['multiple_type']), $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_tag'])) {
                $termSelect('etiquetas', PropertyTaxonomies::TAXONOMY_TAG, __('Etiqueta', 'homlity-plugin'), $current(['etiquetas', 'property_tag']), !empty($settings['multiple_tag']), $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_country'])) {
                $termSelect('pais', PropertyTaxonomies::TAXONOMY_COUNTRY, __('País', 'homlity-plugin'), $current(['pais', 'property_country']), false, $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_state'])) {
                $termSelect('departamento', PropertyTaxonomies::TAXONOMY_STATE, __('Departamento', 'homlity-plugin'), $current(['departamento', 'property_state']), false, $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_city'])) {
                $termSelect('ciudad', PropertyTaxonomies::TAXONOMY_CITY, __('Ciudad', 'homlity-plugin'), $current(['ciudad', 'property_city']), true, $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_neighborhood'])) {
                $termSelect('barrios', PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, __('Barrio', 'homlity-plugin'), $current(['barrios', 'property_neighborhood']), true, $usePlaceholders, $selectUsesLabelOption);
            }
            if (!empty($settings['show_nearby'])) {
                $termSelect('cercanias', PropertyTaxonomies::TAXONOMY_NEARBY, __('Lugar cercano', 'homlity-plugin'), $current(['cercanias', 'property_nearby']), false, $usePlaceholders, $selectUsesLabelOption);
            }
            ?>

            <?php if (!empty($settings['show_price'])): ?>
                <div class="property-listing__filter-group property-listing__filter-group--price">
                    <?php if (!$usePlaceholders): ?>
                        <label class="property-listing__filter-label"><?php esc_html_e('Precio', 'homlity-plugin'); ?></label>
                    <?php endif; ?>
                    <div class="property-listing__price-range">
                        <input class="property-listing__filter-input" type="number" name="precio_min" value="<?php echo esc_attr($current(['precio_min', 'price_min'])); ?>" placeholder="<?php echo esc_attr($usePlaceholders ? __('Precio mín.', 'homlity-plugin') : __('Mín.', 'homlity-plugin')); ?>" min="0">
                        <span class="property-listing__price-sep">-</span>
                        <input class="property-listing__filter-input" type="number" name="precio_max" value="<?php echo esc_attr($current(['precio_max', 'price_max'])); ?>" placeholder="<?php echo esc_attr($usePlaceholders ? __('Precio máx.', 'homlity-plugin') : __('Máx.', 'homlity-plugin')); ?>" min="0">
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($settings['show_bedrooms'])): ?>
                <div class="property-listing__filter-group">
                    <?php if (!$usePlaceholders): ?>
                        <label class="property-listing__filter-label" for="homlity-filter-bedrooms"><?php esc_html_e('Habitaciones', 'homlity-plugin'); ?></label>
                    <?php endif; ?>
                    <select name="alcobas" id="homlity-filter-bedrooms" class="property-listing__filter-select">
                        <option value=""><?php echo esc_html($selectUsesLabelOption ? __('Habitaciones', 'homlity-plugin') : __('Cualquiera', 'homlity-plugin')); ?></option>
                        <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
                            <option value="<?php echo esc_attr($n); ?>" <?php selected((string) $current(['alcobas', 'bedrooms']), (string) $n); ?>><?php echo esc_html($n); ?>+</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($settings['show_bathrooms'])): ?>
                <div class="property-listing__filter-group">
                    <?php if (!$usePlaceholders): ?>
                        <label class="property-listing__filter-label" for="homlity-filter-bathrooms"><?php esc_html_e('Baños', 'homlity-plugin'); ?></label>
                    <?php endif; ?>
                    <select name="banos" id="homlity-filter-bathrooms" class="property-listing__filter-select">
                        <option value=""><?php echo esc_html($selectUsesLabelOption ? __('Baños', 'homlity-plugin') : __('Cualquiera', 'homlity-plugin')); ?></option>
                        <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
                            <option value="<?php echo esc_attr($n); ?>" <?php selected((string) $current(['banos', 'bathrooms']), (string) $n); ?>><?php echo esc_html($n); ?>+</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($settings['show_parking'])): ?>
                <div class="property-listing__filter-group">
                    <?php if (!$usePlaceholders): ?>
                        <label class="property-listing__filter-label" for="homlity-filter-parking"><?php esc_html_e('Garajes', 'homlity-plugin'); ?></label>
                    <?php endif; ?>
                    <select name="garajes" id="homlity-filter-parking" class="property-listing__filter-select">
                        <option value=""><?php echo esc_html($selectUsesLabelOption ? __('Garajes', 'homlity-plugin') : __('Cualquiera', 'homlity-plugin')); ?></option>
                        <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
                            <option value="<?php echo esc_attr($n); ?>" <?php selected((string) $current(['garajes', 'parking']), (string) $n); ?>><?php echo esc_html($n); ?>+</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($settings['show_area'])): ?>
                <div class="property-listing__filter-group property-listing__filter-group--price">
                    <?php if (!$usePlaceholders): ?>
                        <label class="property-listing__filter-label"><?php esc_html_e('Área (m²)', 'homlity-plugin'); ?></label>
                    <?php endif; ?>
                    <div class="property-listing__price-range">
                        <input class="property-listing__filter-input" type="number" name="area_min" value="<?php echo esc_attr($current('area_min')); ?>" placeholder="<?php echo esc_attr($usePlaceholders ? __('Área mín.', 'homlity-plugin') : __('Mín.', 'homlity-plugin')); ?>" min="0">
                        <span class="property-listing__price-sep">-</span>
                        <input class="property-listing__filter-input" type="number" name="area_max" value="<?php echo esc_attr($current('area_max')); ?>" placeholder="<?php echo esc_attr($usePlaceholders ? __('Área máx.', 'homlity-plugin') : __('Máx.', 'homlity-plugin')); ?>" min="0">
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
