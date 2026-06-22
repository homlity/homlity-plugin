<?php
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in,WordPress.DB.SlowDBQuery.slow_db_query_tax_query
/**
 * Builds progressive WP_Query args to find published properties similar to
 * a retired one.
 *
 * Unlike RelatedPropertiesQueryBuilder (taxonomy-only, designed for active
 * properties in the listing widget), this builder adds meta-based price and
 * area range filtering and degrades across five levels of decreasing
 * specificity until enough results are found.
 *
 * Levels
 * ──────
 * 1 — Strong:   operation + type + city + neighborhood + price ±tol% + area ±tol%
 * 2 — Medium:   operation + type + city + price ±(tol×1.5)%
 * 3 — Local:    operation + type + city (no price/area filter)
 * 4 — Fallback: operation + city (any type, no price/area filter)
 * 5 — None:     no viable args
 *
 * All levels automatically exclude retired/unavailable properties and
 * the source property itself.
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

class SimilarPropertiesQueryBuilder
{
    public const LEVEL_STRONG   = 1;
    public const LEVEL_MEDIUM   = 2;
    public const LEVEL_LOCAL    = 3;
    public const LEVEL_FALLBACK = 4;
    public const LEVEL_NONE     = 5;

    private const DEFAULT_MIN_RESULTS = 3;
    private const DEFAULT_MAX_RESULTS = 12;
    private const DEFAULT_PRICE_TOL   = 0.20;
    private const DEFAULT_AREA_TOL    = 0.20;

    /**
     * Find WP_Query args for properties similar to the given retired property.
     *
     * @param array<string,mixed> $propertyData  Structured data extracted by
     *                                           RetiredPropertyRecoveryService::extractPropertyData().
     * @param array{
     *     min_results?: int,
     *     max_results?: int,
     *     price_tolerance?: float,
     *     area_tolerance?: float,
     *     exclude_id?: int,
     * } $options
     *
     * @return array{
     *     args: array|null,
     *     count: int,
     *     fallback_level: int,
     *     applied_filters: string[],
     * }
     */
    public function findSimilar(array $propertyData, array $options = []): array
    {
        $minResults = max(1, (int) ($options['min_results'] ?? self::DEFAULT_MIN_RESULTS));
        $maxResults = max(1, min(48, (int) ($options['max_results'] ?? self::DEFAULT_MAX_RESULTS)));
        $priceTol   = min(0.9, max(0.05, (float) ($options['price_tolerance'] ?? self::DEFAULT_PRICE_TOL)));
        $areaTol    = min(0.9, max(0.05, (float) ($options['area_tolerance'] ?? self::DEFAULT_AREA_TOL)));
        $excludeId  = (int) ($options['exclude_id'] ?? 0);

        // ── Level 1: Strong ───────────────────────────────────────────────────
        $args1 = $this->buildLevel($propertyData, $maxResults, $excludeId, true, $priceTol, $areaTol);
        if ($args1 !== null) {
            $count1 = $this->probeCount($args1);
            if ($count1 >= $minResults) {
                return $this->wrap($args1, $count1, self::LEVEL_STRONG, $this->describe($propertyData, self::LEVEL_STRONG));
            }
        }

        // ── Level 2: Medium ───────────────────────────────────────────────────
        $args2 = $this->buildLevel($propertyData, $maxResults, $excludeId, false, min(0.9, $priceTol * 1.5), null);
        if ($args2 !== null) {
            $count2 = $this->probeCount($args2);
            if ($count2 >= $minResults) {
                return $this->wrap($args2, $count2, self::LEVEL_MEDIUM, $this->describe($propertyData, self::LEVEL_MEDIUM));
            }
        }

        // ── Level 3: Local ────────────────────────────────────────────────────
        $args3 = $this->buildLevel($propertyData, $maxResults, $excludeId, false, null, null);
        if ($args3 !== null) {
            $count3 = $this->probeCount($args3);
            if ($count3 >= $minResults) {
                return $this->wrap($args3, $count3, self::LEVEL_LOCAL, $this->describe($propertyData, self::LEVEL_LOCAL));
            }
        }

        // ── Level 4: Commercial fallback ──────────────────────────────────────
        $args4 = $this->buildFallback($propertyData, $maxResults, $excludeId);
        if ($args4 !== null) {
            $count4 = $this->probeCount($args4);
            if ($count4 > 0) {
                return $this->wrap($args4, $count4, self::LEVEL_FALLBACK, $this->describe($propertyData, self::LEVEL_FALLBACK));
            }
        }

        // ── Level 5: No results ───────────────────────────────────────────────
        return $this->wrap(null, 0, self::LEVEL_NONE, []);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Build query args for levels 1–3.
     *
     * @param float|null $priceTolerance  Null = skip price filter.
     * @param float|null $areaTolerance   Null = skip area filter.
     */
    private function buildLevel(
        array $propertyData,
        int $maxResults,
        int $excludeId,
        bool $requireNeighborhood,
        ?float $priceTolerance,
        ?float $areaTolerance
    ): ?array {
        $operationIds    = $this->ints($propertyData['operation_term_ids'] ?? []);
        $typeIds         = $this->ints($propertyData['type_term_ids'] ?? []);
        $cityIds         = $this->ints($propertyData['city_term_ids'] ?? []);
        $neighborhoodIds = $this->ints($propertyData['neighborhood_term_ids'] ?? []);

        // City is the minimum required location anchor.
        if (empty($cityIds)) {
            return null;
        }

        $args       = $this->baseArgs($maxResults, $excludeId);
        $taxClauses = [];

        if (!empty($operationIds)) {
            $taxClauses[] = [
                'taxonomy' => PropertyTaxonomies::TAXONOMY_OPERATION,
                'field'    => 'term_id',
                'terms'    => $operationIds,
                'operator' => 'IN',
            ];
        }
        if (!empty($typeIds)) {
            $taxClauses[] = [
                'taxonomy' => PropertyTaxonomies::TAXONOMY_TYPE,
                'field'    => 'term_id',
                'terms'    => $typeIds,
                'operator' => 'IN',
            ];
        }
        $taxClauses[] = [
            'taxonomy' => PropertyTaxonomies::TAXONOMY_CITY,
            'field'    => 'term_id',
            'terms'    => $cityIds,
            'operator' => 'IN',
        ];
        if ($requireNeighborhood && !empty($neighborhoodIds)) {
            $taxClauses[] = [
                'taxonomy' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
                'field'    => 'term_id',
                'terms'    => $neighborhoodIds,
                'operator' => 'IN',
            ];
        }

        $args['tax_query'] = array_merge(['relation' => 'AND'], $taxClauses);

        if ($priceTolerance !== null) {
            $clause = $this->priceClause($propertyData, $priceTolerance);
            if ($clause !== null) {
                $args['meta_query'][] = $clause;
            }
        }
        if ($areaTolerance !== null) {
            $clause = $this->areaClause($propertyData, $areaTolerance);
            if ($clause !== null) {
                $args['meta_query'][] = $clause;
            }
        }

        return $args;
    }

    /** Level 4: operation + city, no type, no price/area filters. */
    private function buildFallback(array $propertyData, int $maxResults, int $excludeId): ?array
    {
        $cityIds      = $this->ints($propertyData['city_term_ids'] ?? []);
        $operationIds = $this->ints($propertyData['operation_term_ids'] ?? []);

        if (empty($cityIds) && empty($operationIds)) {
            return null;
        }

        $args       = $this->baseArgs($maxResults, $excludeId);
        $taxClauses = [];

        if (!empty($cityIds)) {
            $taxClauses[] = [
                'taxonomy' => PropertyTaxonomies::TAXONOMY_CITY,
                'field'    => 'term_id',
                'terms'    => $cityIds,
                'operator' => 'IN',
            ];
        }
        if (!empty($operationIds)) {
            $taxClauses[] = [
                'taxonomy' => PropertyTaxonomies::TAXONOMY_OPERATION,
                'field'    => 'term_id',
                'terms'    => $operationIds,
                'operator' => 'IN',
            ];
        }

        if (!empty($taxClauses)) {
            $args['tax_query'] = array_merge(['relation' => 'AND'], $taxClauses);
        }

        return $args;
    }

    /**
     * Build a meta_query clause for price range matching.
     * Automatically selects sale or rent key based on operation slugs.
     */
    private function priceClause(array $propertyData, float $tol): ?array
    {
        $slugs  = (array) ($propertyData['operation_slugs'] ?? []);
        $isRent = false;
        foreach ($slugs as $slug) {
            $s = strtolower((string) $slug);
            if (
                strpos($s, 'arriendo') !== false || strpos($s, 'alquil') !== false
                || strpos($s, 'renta') !== false || strpos($s, 'rent') !== false
            ) {
                $isRent = true;
                break;
            }
        }

        $price   = $isRent ? (float) ($propertyData['price_rent'] ?? 0) : (float) ($propertyData['price_sale'] ?? 0);
        $metaKey = $isRent ? '_property_price_rent' : '_property_price_sale';

        // Fallback to the alternate price key
        if ($price <= 0) {
            $price   = $isRent ? (float) ($propertyData['price_sale'] ?? 0) : (float) ($propertyData['price_rent'] ?? 0);
            $metaKey = $isRent ? '_property_price_sale' : '_property_price_rent';
        }
        if ($price <= 0) {
            return null;
        }

        return [
            'key'     => $metaKey,
            'value'   => [(int) round($price * (1 - $tol)), (int) round($price * (1 + $tol))],
            'type'    => 'NUMERIC',
            'compare' => 'BETWEEN',
        ];
    }

    /** Build a meta_query clause for area range matching. */
    private function areaClause(array $propertyData, float $tol): ?array
    {
        $area = (float) ($propertyData['area'] ?? 0);
        if ($area <= 0) {
            $area = (float) ($propertyData['area_built'] ?? 0);
        }
        if ($area <= 0) {
            $area = (float) ($propertyData['area_private'] ?? 0);
        }
        if ($area <= 0) {
            return null;
        }

        return [
            'key'     => '_property_area',
            'value'   => [max(1, (int) round($area * (1 - $tol))), (int) round($area * (1 + $tol))],
            'type'    => 'NUMERIC',
            'compare' => 'BETWEEN',
        ];
    }

    /**
     * Shared base args: only published, active/available properties.
     * Availability mirrors the logic in PropertySearchService::buildQueryArgs().
     */
    private function baseArgs(int $maxResults, int $excludeId): array
    {
        $args = [
            'post_type'      => PropertyPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $maxResults,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    ['key' => '_property_status', 'compare' => 'NOT EXISTS'],
                    ['key' => '_property_status', 'value' => 'active', 'compare' => '='],
                ],
                [
                    'relation' => 'OR',
                    ['key' => '_property_available', 'compare' => 'NOT EXISTS'],
                    ['key' => '_property_available', 'value' => ['1', 'true', 'yes', 'active'], 'compare' => 'IN'],
                ],
            ],
        ];

        if ($excludeId > 0) {
            $args['post__not_in'] = [$excludeId];
        }

        return $args;
    }

    /**
     * Quick count probe (up to 48) without fetching post objects.
     * Returns the actual count (may be less than 48).
     */
    private function probeCount(array $args): int
    {
        $probe = array_merge($args, [
            'posts_per_page'         => 48,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        $q = new \WP_Query($probe);
        return (int) $q->post_count;
    }

    /** Sanitize an array to a list of positive integers. */
    private function ints(array $raw): array
    {
        return array_values(array_filter(array_map('absint', $raw)));
    }

    /**
     * Wrap the result into the canonical return shape.
     *
     * @param string[] $filters
     * @return array{args: array|null, count: int, fallback_level: int, applied_filters: string[]}
     */
    private function wrap(?array $args, int $count, int $level, array $filters): array
    {
        return [
            'args'            => $args,
            'count'           => $count,
            'fallback_level'  => $level,
            'applied_filters' => $filters,
        ];
    }

    /**
     * Human-readable labels for the filters applied at a given level.
     * Used in the template to show "Resultados basados en: …".
     *
     * @return string[]
     */
    private function describe(array $propertyData, int $level): array
    {
        $parts = [];
        if ($propertyData['type_name'] ?? '') {
            $parts[] = (string) $propertyData['type_name'];
        }
        if ($propertyData['operation_name'] ?? '') {
            $parts[] = strtolower((string) $propertyData['operation_name']);
        }
        if ($propertyData['city_name'] ?? '') {
            $parts[] = (string) $propertyData['city_name'];
        }
        if ($level === self::LEVEL_STRONG && ($propertyData['neighborhood_name'] ?? '')) {
            $parts[] = (string) $propertyData['neighborhood_name'];
        }
        if ($level <= self::LEVEL_MEDIUM) {
            $parts[] = __('precio aproximado', 'homlity-real-estate');
        }
        if ($level === self::LEVEL_STRONG) {
            $parts[] = __('área similar', 'homlity-real-estate');
        }

        return $parts;
    }
}
