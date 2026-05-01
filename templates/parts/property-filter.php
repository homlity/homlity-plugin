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

$targetPageId = absint($settings['target_page_id'] ?? 0) ?: (int) get_option('homlity_plugin_archive_page_id', 0);
$action = $targetPageId ? get_permalink($targetPageId) : home_url('/inmuebles/');
$submitLabel = $settings['submit_label'] ?? __('Buscar', 'homlity-plugin');
$resetLabel = $settings['reset_label'] ?? __('Limpiar', 'homlity-plugin');

$current = static function (string $key): string {
    return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
};

$termSelect = static function (string $name, string $taxonomy, string $label, string $currentValue): void {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
    if (is_wp_error($terms) || !$terms) {
        return;
    }
    ?>
    <div class="property-listing__filter-group">
        <label class="property-listing__filter-label" for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label>
        <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" class="property-listing__filter-select">
            <option value=""><?php esc_html_e('Todos', 'homlity-plugin'); ?></option>
            <?php foreach ($terms as $term): ?>
                <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($currentValue, $term->slug); ?>>
                    <?php echo esc_html($term->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
};
?>
<div class="property-listing property-filter-widget">
    <form class="property-listing__filters" action="<?php echo esc_url($action); ?>" method="get">
        <div class="property-listing__filters-row">
            <?php if (!empty($settings['show_keyword'])): ?>
                <div class="property-listing__filter-group">
                    <label class="property-listing__filter-label" for="homlity-filter-s"><?php esc_html_e('Buscar', 'homlity-plugin'); ?></label>
                    <input id="homlity-filter-s" class="property-listing__filter-input" type="search" name="s" value="<?php echo esc_attr($current('s')); ?>">
                </div>
            <?php endif; ?>

            <?php
            if (!empty($settings['show_category'])) {
                $termSelect('property_category', PropertyTaxonomies::TAXONOMY_CATEGORY, __('Categoría', 'homlity-plugin'), $current('property_category'));
            }
            if (!empty($settings['show_operation'])) {
                $termSelect('property_operation', PropertyTaxonomies::TAXONOMY_OPERATION, __('Gestión', 'homlity-plugin'), $current('property_operation'));
            }
            if (!empty($settings['show_type'])) {
                $termSelect('property_type', PropertyTaxonomies::TAXONOMY_TYPE, __('Tipo', 'homlity-plugin'), $current('property_type'));
            }
            if (!empty($settings['show_tag'])) {
                $termSelect('property_tag', PropertyTaxonomies::TAXONOMY_TAG, __('Etiqueta', 'homlity-plugin'), $current('property_tag'));
            }
            if (!empty($settings['show_country'])) {
                $termSelect('property_country', PropertyTaxonomies::TAXONOMY_COUNTRY, __('País', 'homlity-plugin'), $current('property_country'));
            }
            if (!empty($settings['show_state'])) {
                $termSelect('property_state', PropertyTaxonomies::TAXONOMY_STATE, __('Departamento', 'homlity-plugin'), $current('property_state'));
            }
            if (!empty($settings['show_city'])) {
                $termSelect('property_city', PropertyTaxonomies::TAXONOMY_CITY, __('Ciudad', 'homlity-plugin'), $current('property_city'));
            }
            if (!empty($settings['show_neighborhood'])) {
                $termSelect('property_neighborhood', PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, __('Barrio', 'homlity-plugin'), $current('property_neighborhood'));
            }
            if (!empty($settings['show_nearby'])) {
                $termSelect('property_nearby', PropertyTaxonomies::TAXONOMY_NEARBY, __('Lugar cercano', 'homlity-plugin'), $current('property_nearby'));
            }
            ?>

            <?php if (!empty($settings['show_price'])): ?>
                <div class="property-listing__filter-group property-listing__filter-group--price">
                    <label class="property-listing__filter-label"><?php esc_html_e('Precio', 'homlity-plugin'); ?></label>
                    <div class="property-listing__price-range">
                        <input class="property-listing__filter-input" type="number" name="price_min" value="<?php echo esc_attr($current('price_min')); ?>" placeholder="<?php esc_attr_e('Mín.', 'homlity-plugin'); ?>" min="0">
                        <span class="property-listing__price-sep">-</span>
                        <input class="property-listing__filter-input" type="number" name="price_max" value="<?php echo esc_attr($current('price_max')); ?>" placeholder="<?php esc_attr_e('Máx.', 'homlity-plugin'); ?>" min="0">
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($settings['show_bedrooms'])): ?>
                <div class="property-listing__filter-group">
                    <label class="property-listing__filter-label" for="homlity-filter-bedrooms"><?php esc_html_e('Habitaciones', 'homlity-plugin'); ?></label>
                    <select name="bedrooms" id="homlity-filter-bedrooms" class="property-listing__filter-select">
                        <option value=""><?php esc_html_e('Cualquiera', 'homlity-plugin'); ?></option>
                        <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
                            <option value="<?php echo esc_attr($n); ?>" <?php selected($current('bedrooms'), (string) $n); ?>><?php echo esc_html($n); ?>+</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($settings['show_bathrooms'])): ?>
                <div class="property-listing__filter-group">
                    <label class="property-listing__filter-label" for="homlity-filter-bathrooms"><?php esc_html_e('Baños', 'homlity-plugin'); ?></label>
                    <select name="bathrooms" id="homlity-filter-bathrooms" class="property-listing__filter-select">
                        <option value=""><?php esc_html_e('Cualquiera', 'homlity-plugin'); ?></option>
                        <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
                            <option value="<?php echo esc_attr($n); ?>" <?php selected($current('bathrooms'), (string) $n); ?>><?php echo esc_html($n); ?>+</option>
                        <?php endforeach; ?>
                    </select>
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
