<?php
/**
 * Secondary features component.
 * Overridable at plugin-inmobiliario/parts/property-features-secondary.php
 *
 * Expected args: $post_id (int)
 */

use Codwelt\PluginInmobiliario\Services\PropertyPostType;
use Codwelt\PluginInmobiliario\Services\PropertyTaxonomies;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$meta = (new PropertyPostType())->metaKeys();
$lat = get_post_meta($post_id, $meta['latitude'], true);
$lng = get_post_meta($post_id, $meta['longitude'], true);
$features = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_FEATURE);
$nearby = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_NEARBY);

$featureGroups = [
    'interior' => [],
    'exterior' => [],
    'other' => [],
];

if ($features && !is_wp_error($features)) {
    foreach ($features as $feature) {
        $parent = $feature->parent ? get_term($feature->parent, PropertyTaxonomies::TAXONOMY_FEATURE) : null;
        $bucket = 'other';
        if ($parent) {
            $slug = sanitize_title($parent->name);
            if (in_array($slug, ['interior'], true)) {
                $bucket = 'interior';
            } elseif (in_array($slug, ['exterior'], true)) {
                $bucket = 'exterior';
            }
        }
        $featureGroups[$bucket][] = $feature->name;
    }
}
?>
<section class="property-features property-features--secondary">
    <h3><?php esc_html_e('Características secundarias', 'inmopress-listings-inmobiliaria'); ?></h3>
    <ul>
        <?php if ($lat && $lng): ?>
            <li><strong><?php esc_html_e('Coordenadas', 'inmopress-listings-inmobiliaria'); ?>:</strong> <?php echo esc_html($lat . ', ' . $lng); ?></li>
        <?php endif; ?>
        <?php
$terms = get_the_terms($post_id, \Codwelt\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_OPERATION);
if ($terms && !is_wp_error($terms)): ?>
            <li><strong><?php esc_html_e('Gestión', 'inmopress-listings-inmobiliaria'); ?>:</strong> <?php echo esc_html(join(', ', wp_list_pluck($terms, 'name'))); ?></li>
<?php endif; ?>
        <?php if ($featureGroups['interior']): ?>
            <li><strong><?php esc_html_e('Características interiores', 'inmopress-listings-inmobiliaria'); ?>:</strong> <?php echo esc_html(join(', ', $featureGroups['interior'])); ?></li>
        <?php endif; ?>
        <?php if ($featureGroups['exterior']): ?>
            <li><strong><?php esc_html_e('Características exteriores', 'inmopress-listings-inmobiliaria'); ?>:</strong> <?php echo esc_html(join(', ', $featureGroups['exterior'])); ?></li>
        <?php endif; ?>
        <?php if ($featureGroups['other']): ?>
            <li><strong><?php esc_html_e('Otras características', 'inmopress-listings-inmobiliaria'); ?>:</strong> <?php echo esc_html(join(', ', $featureGroups['other'])); ?></li>
        <?php endif; ?>
        <?php if ($nearby && !is_wp_error($nearby)): ?>
            <li><strong><?php esc_html_e('Lugares cercanos', 'inmopress-listings-inmobiliaria'); ?>:</strong> <?php echo esc_html(join(', ', wp_list_pluck($nearby, 'name'))); ?></li>
        <?php endif; ?>
    </ul>
</section>
