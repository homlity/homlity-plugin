<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Breadcrumb for single property with filter links by operation and location.
 */

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}
$post_id = (int) $post_id;
if ($post_id <= 0) {
    return;
}

$archivePageId = (int) get_option('homlity_plugin_archive_page_id', 0);
$archiveUrl = $archivePageId > 0 ? get_permalink($archivePageId) : home_url('/inmuebles/');
$showHome = isset($show_home) ? (bool) $show_home : true;
$showTitle = isset($show_property_title) ? (bool) $show_property_title : true;

$operationTerms = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_OPERATION);
$cityTerms = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_CITY);
$neighborhoodTerms = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD);

$operation = (!is_wp_error($operationTerms) && !empty($operationTerms)) ? $operationTerms[0] : null;
$city = (!is_wp_error($cityTerms) && !empty($cityTerms)) ? $cityTerms[0] : null;
$neighborhood = (!is_wp_error($neighborhoodTerms) && !empty($neighborhoodTerms)) ? $neighborhoodTerms[0] : null;

$baseFilters = [];
if ($operation instanceof \WP_Term) {
    $baseFilters['property_operation'] = $operation->slug;
}

$crumbs = [];
if ($showHome) {
    $crumbs[] = ['label' => __('Inicio', 'homlity-real-estate'), 'url' => home_url('/')];
}
$crumbs[] = ['label' => __('Inmuebles', 'homlity-real-estate'), 'url' => $archiveUrl];

if ($operation instanceof \WP_Term) {
    $crumbs[] = [
        'label' => $operation->name,
        'url' => TemplateService::buildSeoArchiveUrl([
            'property_operation' => $operation->slug,
        ]),
    ];
}
if ($city instanceof \WP_Term) {
    $crumbs[] = [
        'label' => $city->name,
        'url' => TemplateService::buildSeoArchiveUrl(array_merge(
            $baseFilters,
            ['property_city' => $city->slug]
        )),
    ];
}
if ($neighborhood instanceof \WP_Term) {
    $crumbs[] = [
        'label' => $neighborhood->name,
        'url' => TemplateService::buildSeoArchiveUrl(array_merge($baseFilters, [
            'property_city' => $city instanceof \WP_Term ? $city->slug : '',
            'property_neighborhood' => $neighborhood->slug,
        ])),
    ];
}
if ($showTitle) {
    $crumbs[] = ['label' => get_the_title($post_id), 'url' => ''];
}
?>
<nav class="property-breadcrumb-widget" aria-label="<?php esc_attr_e('Breadcrumb', 'homlity-real-estate'); ?>">
    <?php foreach ($crumbs as $index => $crumb) : ?>
        <?php if ($index > 0) : ?>
            <span class="property-breadcrumb-widget__sep" aria-hidden="true">/</span>
        <?php endif; ?>
        <?php if (!empty($crumb['url'])) : ?>
            <a href="<?php echo esc_url((string) $crumb['url']); ?>"><?php echo esc_html((string) $crumb['label']); ?></a>
        <?php else : ?>
            <span aria-current="page"><?php echo esc_html((string) $crumb['label']); ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
