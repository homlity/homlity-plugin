<?php
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
/**
 * Builds WP_Query args for "related properties" based on a source property's taxonomy terms.
 *
 * Three strategies
 * ─────────────────
 * any        – OR relation: results share at least one term in any of the selected taxonomies.
 * all        – AND relation: results share terms in ALL selected taxonomies.
 * tags_first – tries a tag-only OR query first; if empty, degrades to 'any'.
 *
 * Four fallbacks (used when the primary query yields 0 results)
 * ─────────────────────────────────────────────────────────────
 * recent    – return the most-recent published properties (no taxonomy filter).
 * same_city – return properties in the same city; degrades to 'recent' if empty.
 * hide      – caller hides the section entirely (no output).
 * empty     – caller shows the configured empty message.
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

class RelatedPropertiesQueryBuilder
{
    public const MAX_RESULTS = 10;

    // ── Strategy constants ────────────────────────────────────────────────────
    public const STRATEGY_ANY        = 'any';
    public const STRATEGY_ALL        = 'all';
    public const STRATEGY_TAGS_FIRST = 'tags_first';

    // ── Fallback constants ────────────────────────────────────────────────────
    public const FALLBACK_RECENT    = 'recent';
    public const FALLBACK_SAME_CITY = 'same_city';
    public const FALLBACK_HIDE      = 'hide';
    public const FALLBACK_EMPTY     = 'empty';

    /**
     * Taxonomies eligible for the related query (ordered by relevance priority).
     * Overridden via the $taxonomies parameter; empty list = all of these.
     */
    private static function allowedTaxonomies(): array
    {
        return [
            PropertyTaxonomies::TAXONOMY_TAG,
            PropertyTaxonomies::TAXONOMY_TYPE,
            PropertyTaxonomies::TAXONOMY_OPERATION,
            PropertyTaxonomies::TAXONOMY_CATEGORY,
            PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_STATE,
            PropertyTaxonomies::TAXONOMY_COUNTRY,
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
        ];
    }

    /**
     * Build WP_Query args for related properties.
     *
     * @param int    $propertyId    Source property post ID.
     * @param int    $postsPerPage  Maximum number of results.
     * @param array  $taxonomies    Taxonomy slugs to match (empty = all allowed).
     * @param string $strategy      'any' | 'all' | 'tags_first'.
     * @param string $fallback      'recent' | 'same_city' | 'hide' | 'empty'.
     * @param bool   $excludeCurrent Whether to exclude $propertyId from results.
     *
     * @return array{args: array, fallback_type: string}
     *   - args:          WP_Query args ready to pass to new \WP_Query().
     *   - fallback_type: 'none' | 'recent' | 'same_city' | 'hide' | 'empty'.
     *                    'none' = primary query succeeded.
     */
    public function build(
        int    $propertyId,
        int    $postsPerPage,
        array  $taxonomies     = [],
        string $strategy       = self::STRATEGY_ANY,
        string $fallback       = self::FALLBACK_RECENT,
        bool   $excludeCurrent = true
    ): array {
        $taxonomies = $this->sanitizeTaxonomies($taxonomies);
        $base       = $this->baseArgs($propertyId, $postsPerPage, $excludeCurrent);

        // ── tags_first: attempt a tag-only OR query before the full strategy ──
        if ($strategy === self::STRATEGY_TAGS_FIRST) {
            if (in_array(PropertyTaxonomies::TAXONOMY_TAG, $taxonomies, true)) {
                $tagArgs = $this->buildTaxQuery($base, [PropertyTaxonomies::TAXONOMY_TAG], $propertyId, 'OR');
                if ($this->probeHasResults($tagArgs)) {
                    return ['args' => $tagArgs, 'fallback_type' => 'none'];
                }
            }
            $strategy = self::STRATEGY_ANY; // degrade
        }

        // ── Primary query ─────────────────────────────────────────────────────
        $relation    = ($strategy === self::STRATEGY_ALL) ? 'AND' : 'OR';
        $primaryArgs = $this->buildTaxQuery($base, $taxonomies, $propertyId, $relation);

        if ($this->probeHasResults($primaryArgs)) {
            return ['args' => $primaryArgs, 'fallback_type' => 'none'];
        }

        // ── Fallback ──────────────────────────────────────────────────────────
        return $this->runFallback($base, $propertyId, $fallback);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Filter to allowed slugs; empty input returns the full allowed list.
     */
    private function sanitizeTaxonomies(array $taxonomies): array
    {
        $allowed = self::allowedTaxonomies();
        if (empty($taxonomies)) {
            return $allowed;
        }
        return array_values(array_intersect(
            array_map('sanitize_key', $taxonomies),
            $allowed
        ));
    }

    /**
     * Shared base args (post type, status, per_page, exclusion).
     */
    private function baseArgs(int $propertyId, int $postsPerPage, bool $excludeCurrent): array
    {
        $postsPerPage = min(self::MAX_RESULTS, max(1, $postsPerPage));

        $args = [
            'post_type'      => PropertyPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $postsPerPage,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        if ($excludeCurrent && $propertyId > 0) {
            $args['post__not_in'] = [$propertyId];
        }
        return $args;
    }

    /**
     * Clone $base and attach a tax_query built from the source property's terms.
     * If the property has no terms in the selected taxonomies, forces 0 results
     * by setting post__in => [0].
     */
    private function buildTaxQuery(array $base, array $taxonomies, int $propertyId, string $relation): array
    {
        $clauses = [];
        foreach ($taxonomies as $taxonomy) {
            $termIds = wp_get_post_terms($propertyId, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($termIds) || empty($termIds)) {
                continue;
            }
            $termIds = array_values(array_filter(array_map('absint', $termIds)));
            if (!$termIds) {
                continue;
            }
            $clauses[] = [
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $termIds,
                'operator' => 'IN',
            ];
        }

        if (empty($clauses)) {
            // No terms found on source property for the given taxonomies.
            // post__in with a non-existent ID guarantees 0 results without SQL errors.
            $base['post__in'] = [0];
            return $base;
        }

        $base['tax_query'] = count($clauses) === 1
            ? $clauses
            : array_merge(['relation' => $relation], $clauses);

        return $base;
    }

    /**
     * Run a cheap 1-post probe to decide whether args will produce results.
     */
    private function probeHasResults(array $args): bool
    {
        $probe = array_merge($args, [
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        $q = new \WP_Query($probe);
        return $q->post_count > 0;
    }

    /**
     * @return array{args: array, fallback_type: string}
     */
    private function runFallback(array $base, int $propertyId, string $fallback): array
    {
        // hide / empty: force 0 results and signal the caller
        if ($fallback === self::FALLBACK_HIDE || $fallback === self::FALLBACK_EMPTY) {
            $base['post__in'] = [0];
            return ['args' => $base, 'fallback_type' => $fallback];
        }

        // same_city: try a city-term query, degrade to recent if empty
        if ($fallback === self::FALLBACK_SAME_CITY) {
            $cityIds = wp_get_post_terms($propertyId, PropertyTaxonomies::TAXONOMY_CITY, ['fields' => 'ids']);
            if (!is_wp_error($cityIds) && !empty($cityIds)) {
                $cityIds = array_values(array_filter(array_map('absint', $cityIds)));
                if ($cityIds) {
                    $cityArgs              = $base;
                    $cityArgs['tax_query'] = [[
                        'taxonomy' => PropertyTaxonomies::TAXONOMY_CITY,
                        'field'    => 'term_id',
                        'terms'    => $cityIds,
                        'operator' => 'IN',
                    ]];
                    if ($this->probeHasResults($cityArgs)) {
                        return ['args' => $cityArgs, 'fallback_type' => self::FALLBACK_SAME_CITY];
                    }
                }
            }
            // no city results → fall through to recent
        }

        // recent: remove all taxonomy / post__in constraints
        unset($base['tax_query'], $base['post__in']);
        return ['args' => $base, 'fallback_type' => self::FALLBACK_RECENT];
    }
}
