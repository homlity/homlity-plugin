<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Secondary features component.
 * Overridable at homlity-real-estate/parts/property-features-secondary.php
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

$operationTerms = get_the_terms($post_id, PropertyTaxonomies::TAXONOMY_OPERATION);
if ($operationTerms && !is_wp_error($operationTerms)) {
    $items[] = ['label' => __('Gestión', 'homlity-real-estate'), 'value' => implode(', ', wp_list_pluck($operationTerms, 'name'))];
}

if ($featureGroups['interior']) {
    foreach ($featureGroups['interior'] as $featureName) {
        $items[] = ['label' => __('Características interiores', 'homlity-real-estate'), 'value' => $featureName];
    }
}
if ($featureGroups['exterior']) {
    foreach ($featureGroups['exterior'] as $featureName) {
        $items[] = ['label' => __('Características exteriores', 'homlity-real-estate'), 'value' => $featureName];
    }
}
if ($featureGroups['other']) {
    foreach ($featureGroups['other'] as $featureName) {
        $items[] = ['label' => '', 'value' => $featureName];
    }
}
if ($nearby && !is_wp_error($nearby)) {
    $items[] = ['label' => __('Lugares cercanos', 'homlity-real-estate'), 'value' => implode(', ', wp_list_pluck($nearby, 'name'))];
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
                <?php if ($item['label'] !== ''): ?>
                    <strong class="property-features__label"><?php echo esc_html($item['label']); ?>:</strong>
                <?php endif; ?>
                <?php echo esc_html($item['value']); ?>
            </span>
        </li>
    <?php endforeach; ?>
</ul>
