<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Related properties component.
 * Overridable at homlity-real-estate/parts/property-related.php
 *
 * Expected args: $post_id (int)
 */

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}
$postsPerPage  = isset($posts_per_page) ? min(10, max(1, (int) $posts_per_page)) : 10;
$columns       = isset($columns) ? max(1, (int) $columns) : 3;
$passCardOptions = isset($card_options) && is_array($card_options) ? $card_options : [];

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

if (!$taxQuery) : ?>
    <section class="property-related">
        <p class="property-related__empty"><?php esc_html_e('No hay inmuebles relacionados.', 'homlity-real-estate'); ?></p>
    </section>
    <?php return;
endif;

$related = new WP_Query([
    'post_type' => PropertyPostType::POST_TYPE,
    'posts_per_page' => $postsPerPage,
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

if (!$related->have_posts()) : ?>
    <section class="property-related">
        <p class="property-related__empty"><?php esc_html_e('No hay inmuebles relacionados.', 'homlity-real-estate'); ?></p>
    </section>
    <?php wp_reset_postdata(); return;
endif;
?>
<section class="property-related">
    <div class="property-related__grid">
        <?php while ($related->have_posts()) : $related->the_post(); ?>
            <?php TemplateService::includeComponent('property-card.php', ['post_id' => get_the_ID(), 'card_options' => $passCardOptions]); ?>
        <?php endwhile; ?>
    </div>
</section>
<?php wp_reset_postdata(); ?>
