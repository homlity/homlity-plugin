<?php
/**
 * Secondary features component.
 * Overridable at homlity-plugin/parts/property-features-secondary.php
 *
 * Expected args: $post_id (int), $item_icon_html (string, optional)
 */

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$meta       = (new PropertyPostType())->metaKeys();
$iconHtml   = $item_icon_html ?? '';
$lat        = get_post_meta($post_id, $meta['latitude'],  true);
$lng        = get_post_meta($post_id, $meta['longitude'], true);
$features   = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_FEATURE);
$nearby     = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_NEARBY);

$featureGroups = ['interior' => [], 'exterior' => [], 'other' => []];

if ($features && !is_wp_error($features)) {
    foreach ($features as $feature) {
        $parent = $feature->parent ? get_term($feature->parent, PropertyTaxonomies::TAXONOMY_FEATURE) : null;
        $bucket = 'other';
        if ($parent) {
            $slug = sanitize_title($parent->name);
            if ($slug === 'interior') {
                $bucket = 'interior';
            } elseif ($slug === 'exterior') {
                $bucket = 'exterior';
            }
        }
        $featureGroups[$bucket][] = $feature->name;
    }
}

$items = [];

if ($lat && $lng) {
    $items[] = ['label' => __('Coordenadas', 'homlity-plugin'), 'value' => $lat . ', ' . $lng];
}

$operationTerms = get_the_terms($post_id, PropertyTaxonomies::TAXONOMY_OPERATION);
if ($operationTerms && !is_wp_error($operationTerms)) {
    $items[] = ['label' => __('Gestión', 'homlity-plugin'), 'value' => implode(', ', wp_list_pluck($operationTerms, 'name'))];
}

if ($featureGroups['interior']) {
    $items[] = ['label' => __('Características interiores', 'homlity-plugin'), 'value' => implode(', ', $featureGroups['interior'])];
}
if ($featureGroups['exterior']) {
    $items[] = ['label' => __('Características exteriores', 'homlity-plugin'), 'value' => implode(', ', $featureGroups['exterior'])];
}
if ($featureGroups['other']) {
    $items[] = ['label' => __('Otras características', 'homlity-plugin'), 'value' => implode(', ', $featureGroups['other'])];
}
if ($nearby && !is_wp_error($nearby)) {
    $items[] = ['label' => __('Lugares cercanos', 'homlity-plugin'), 'value' => implode(', ', wp_list_pluck($nearby, 'name'))];
}

if (empty($items)) {
    return;
}
?>
<ul class="property-features property-features--secondary">
    <?php foreach ($items as $item): ?>
        <li class="property-features__item">
            <?php if ($iconHtml !== ''): ?>
                <span class="property-features__icon"><?php echo $iconHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <?php endif; ?>
            <span class="property-features__text">
                <strong class="property-features__label"><?php echo esc_html($item['label']); ?>:</strong>
                <?php echo esc_html($item['value']); ?>
            </span>
        </li>
    <?php endforeach; ?>
</ul>
