<?php
/**
 * Related properties component.
 * Overridable at homlity-plugin/parts/property-related.php
 *
 * Expected args: $post_id (int)
 */

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$taxQuery = [];

$typeTerms = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_TYPE, ['fields' => 'ids']);
if (!is_wp_error($typeTerms) && $typeTerms) {
    $taxQuery[] = [
        'taxonomy' => PropertyTaxonomies::TAXONOMY_TYPE,
        'field' => 'term_id',
        'terms' => $typeTerms,
    ];
}

$operationTerms = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_OPERATION, ['fields' => 'ids']);
if (!is_wp_error($operationTerms) && $operationTerms) {
    $taxQuery[] = [
        'taxonomy' => PropertyTaxonomies::TAXONOMY_OPERATION,
        'field' => 'term_id',
        'terms' => $operationTerms,
    ];
}

$categoryTerms = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_CATEGORY, ['fields' => 'ids']);
if (!is_wp_error($categoryTerms) && $categoryTerms) {
    $taxQuery[] = [
        'taxonomy' => PropertyTaxonomies::TAXONOMY_CATEGORY,
        'field' => 'term_id',
        'terms' => $categoryTerms,
    ];
}

$tagTerms = wp_get_post_terms($post_id, PropertyTaxonomies::TAXONOMY_TAG, ['fields' => 'ids']);
if (!is_wp_error($tagTerms) && $tagTerms) {
    $taxQuery[] = [
        'taxonomy' => PropertyTaxonomies::TAXONOMY_TAG,
        'field' => 'term_id',
        'terms' => $tagTerms,
    ];
}

if (!$taxQuery) {
    return;
}

$related = new WP_Query([
    'post_type' => PropertyPostType::POST_TYPE,
    'posts_per_page' => 3,
    'post__not_in' => [$post_id],
    'tax_query' => $taxQuery,
    'meta_query' => [
        'relation' => 'OR',
        [
            'key' => '_property_price_sale',
            'compare' => 'EXISTS',
        ],
        [
            'key' => '_property_price_rent',
            'compare' => 'EXISTS',
        ],
    ],
]);

if (!$related->have_posts()) {
    return;
}
?>
<section class="property-related">
    <h2><?php esc_html_e('Propiedades relacionadas', 'homlity-plugin'); ?></h2>
    <div class="property-related__grid">
        <?php while ($related->have_posts()) : $related->the_post(); ?>
            <?php TemplateService::includeComponent('property-card.php', ['post_id' => get_the_ID()]); ?>
        <?php endwhile; ?>
    </div>
</section>
<?php wp_reset_postdata(); ?>
