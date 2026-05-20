<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Archive and search results for properties.
 * Can be overridden in theme at homlity-real-estate/archive-property.php
 */

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\TemplateService;

get_header();

$get = static function (string $key): string {
    return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
};

$category = $get('property_category');
$type = $get('property_type');
$operation = $get('property_operation');
$city = $get('property_city');
$neighborhood = $get('property_neighborhood');
$nearby = $get('property_nearby');
$tag = $get('property_tag');
$priceMin = $get('price_min');
$priceMax = $get('price_max');
$areaMin = $get('area_min');
$areaMax = $get('area_max');
$dateFrom = $get('date_from');
$dateTo = $get('date_to');

$categories = get_terms([
    'taxonomy' => PropertyTaxonomies::TAXONOMY_CATEGORY,
    'hide_empty' => false,
]);

$types = get_terms([
    'taxonomy' => PropertyTaxonomies::TAXONOMY_TYPE,
    'hide_empty' => false,
]);

$operations = get_terms([
    'taxonomy' => PropertyTaxonomies::TAXONOMY_OPERATION,
    'hide_empty' => false,
]);

$cities = get_terms([
    'taxonomy' => PropertyTaxonomies::TAXONOMY_CITY,
    'hide_empty' => false,
]);

$neighborhoods = get_terms([
    'taxonomy' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
    'hide_empty' => false,
]);

$nearbyTerms = get_terms([
    'taxonomy' => PropertyTaxonomies::TAXONOMY_NEARBY,
    'hide_empty' => false,
]);

$tags = get_terms([
    'taxonomy' => PropertyTaxonomies::TAXONOMY_TAG,
    'hide_empty' => false,
]);
?>
<main class="property-archive">
    <header class="property-archive__header">
        <h1><?php
            $archiveTitle = post_type_archive_title('', false);
            if (!$archiveTitle) {
                $archivePageId = (int) get_option('homlity_plugin_archive_page_id', 0);
                $archiveTitle  = $archivePageId ? get_the_title($archivePageId) : __('Propiedades', 'homlity-real-estate');
            }
            echo esc_html($archiveTitle);
        ?></h1>
        <form method="get" class="property-archive__search">
            <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>"
                   placeholder="<?php esc_attr_e('Buscar inmuebles', 'homlity-real-estate'); ?>">
            <select name="property_category">
                <option value=""><?php esc_html_e('Todas las categorías', 'homlity-real-estate'); ?></option>
                <?php foreach ($categories as $term): ?>
                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($category, $term->slug); ?>>
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="property_type">
                <option value=""><?php esc_html_e('Todos los tipos', 'homlity-real-estate'); ?></option>
                <?php foreach ($types as $term): ?>
                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($type, $term->slug); ?>>
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="property_operation">
                <option value=""><?php esc_html_e('Todas las gestiones', 'homlity-real-estate'); ?></option>
                <?php foreach ($operations as $term): ?>
                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($operation, $term->slug); ?>>
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="property_city">
                <option value=""><?php esc_html_e('Todas las ciudades', 'homlity-real-estate'); ?></option>
                <?php foreach ($cities as $term): ?>
                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($city, $term->slug); ?>>
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="property_neighborhood">
                <option value=""><?php esc_html_e('Todos los barrios', 'homlity-real-estate'); ?></option>
                <?php foreach ($neighborhoods as $term): ?>
                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($neighborhood, $term->slug); ?>>
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="property_nearby">
                <option value=""><?php esc_html_e('Lugares cercanos', 'homlity-real-estate'); ?></option>
                <?php foreach ($nearbyTerms as $term): ?>
                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($nearby, $term->slug); ?>>
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="property_tag">
                <option value=""><?php esc_html_e('Etiquetas', 'homlity-real-estate'); ?></option>
                <?php foreach ($tags as $term): ?>
                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($tag, $term->slug); ?>>
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="price_min" value="<?php echo esc_attr($priceMin); ?>" placeholder="<?php esc_attr_e('Precio mínimo', 'homlity-real-estate'); ?>" step="0.01">
            <input type="number" name="price_max" value="<?php echo esc_attr($priceMax); ?>" placeholder="<?php esc_attr_e('Precio máximo', 'homlity-real-estate'); ?>" step="0.01">
            <input type="number" name="area_min" value="<?php echo esc_attr($areaMin); ?>" placeholder="<?php esc_attr_e('Área mínima (m²)', 'homlity-real-estate'); ?>" step="0.01">
            <input type="number" name="area_max" value="<?php echo esc_attr($areaMax); ?>" placeholder="<?php esc_attr_e('Área máxima (m²)', 'homlity-real-estate'); ?>" step="0.01">
            <input type="date" name="date_from" value="<?php echo esc_attr($dateFrom); ?>">
            <input type="date" name="date_to" value="<?php echo esc_attr($dateTo); ?>">
            <button type="submit"><?php esc_html_e('Buscar', 'homlity-real-estate'); ?></button>
        </form>
    </header>

    <?php if (have_posts()) : ?>
        <div class="property-archive__grid">
            <?php while (have_posts()) : the_post(); ?>
                <?php TemplateService::includeComponent('property-card.php', ['post_id' => get_the_ID()]); ?>
            <?php endwhile; ?>
        </div>
        <div class="property-archive__pagination">
            <?php the_posts_pagination(); ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e('No hay inmuebles que coincidan con tu búsqueda.', 'homlity-real-estate'); ?></p>
    <?php endif; ?>
</main>

<?php
get_footer();
