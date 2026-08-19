<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_tax_query
/**
 * Builds WP_Query args from filter params and extracts map data from query results.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PropertySearchService implements ServiceInterface
{
    /** Named meta clause used to order by price. */
    private const ORDER_PRICE_CLAUSE = 'homlity_price_order';

    public function register(): void
    {
        add_filter('posts_clauses', [$this, 'applyPriorityKeywordSearch'], 20, 2);
        add_action('template_redirect', [$this, 'maybeRedirectExactCodeSearch'], 1);
    }

    public function maybeRedirectExactCodeSearch(): void
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (is_singular(PropertyPostType::POST_TYPE)) {
            return;
        }

        $code = $this->extractRequestedPropertyCode();
        if ($code === '') {
            return;
        }

        $postId = $this->findPropertyByExactCode($code);
        $syncResult = null;

        if ($postId <= 0 && $this->looksLikePropertyCode($code)) {
            $syncResult = SyncRegistry::syncByCodeDetailed($code);
            $postId = (int) ($syncResult['post_id'] ?? 0);
        }

        if ($postId <= 0) {
            return;
        }

        $permalink = get_permalink($postId);
        if (!$permalink) {
            return;
        }

        wp_safe_redirect($permalink, 302);
        exit;
    }

    public function buildQueryArgs(array $params): array
    {
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 12)));
        $page    = max(1, (int) ($params['page'] ?? 1));
        $orderby = sanitize_key($params['orderby'] ?? 'date');
        $order   = strtoupper($params['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $args = [
            'post_type'      => PropertyPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'tax_query'      => ['relation' => 'AND'],
            'meta_query'     => ['relation' => 'AND'],
        ];

        // Mostrar únicamente inmuebles activos/disponibles.
        // Si el meta no existe (carga manual o integraciones antiguas), se permite.
        $args['meta_query'][] = [
            'relation' => 'OR',
            [
                'key' => '_property_status',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_property_status',
                'value' => 'active',
                'compare' => '=',
            ],
        ];
        $args['meta_query'][] = [
            'relation' => 'OR',
            [
                'key' => '_property_available',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_property_available',
                'value' => ['1', 'true', 'yes', 'active'],
                'compare' => 'IN',
            ],
        ];

        $taxMap = [
            'category'     => PropertyTaxonomies::TAXONOMY_CATEGORY,
            'operation'    => PropertyTaxonomies::TAXONOMY_OPERATION,
            'type'         => PropertyTaxonomies::TAXONOMY_TYPE,
            'tag'          => PropertyTaxonomies::TAXONOMY_TAG,
            'feature'      => PropertyTaxonomies::TAXONOMY_FEATURE,
            'country'      => PropertyTaxonomies::TAXONOMY_COUNTRY,
            'city'         => PropertyTaxonomies::TAXONOMY_CITY,
            'state'        => PropertyTaxonomies::TAXONOMY_STATE,
            'neighborhood' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            'nearby'       => PropertyTaxonomies::TAXONOMY_NEARBY,
        ];

        if (($params['query_mode'] ?? '') === 'current') {
            $params = array_merge($params, $this->currentQueryParams());
        }

        // Read after the `current` merge: otherwise a keyword coming from the
        // URL would be consumed before it was collected, and every other filter
        // of that same request would apply except the free-text one.
        if (!empty($params['search'])) {
            $args['homlity_keyword_search'] = sanitize_text_field((string) $params['search']);
        }

        // User filter terms
        foreach ($taxMap as $key => $taxonomy) {
            $termIds = $this->filterExistingTermIds(
                $this->extractTermIds($params[$key] ?? null),
                $taxonomy
            );
            if ($termIds) {
                $args['tax_query'][] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $termIds,
                ];
            }
        }

        // Widget preset terms override user filters
        foreach ($taxMap as $key => $taxonomy) {
            $presetKey = 'preset_' . $key;
            $presetId = absint($params[$presetKey] ?? 0);
            if ($presetId && term_exists($presetId, $taxonomy)) {
                // Remove any user-supplied filter for the same taxonomy
                foreach ($args['tax_query'] as $i => $clause) {
                    if (is_array($clause) && ($clause['taxonomy'] ?? '') === $taxonomy) {
                        unset($args['tax_query'][$i]);
                    }
                }
                $args['tax_query'][] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => [$presetId],
                ];
            }
        }

        // Locality is an optional bridge (city -> locality -> neighborhoods),
        // not a taxonomy assigned directly to properties. Translate it to the
        // neighborhood terms that are actually attached to each property.
        $localityRaw = !empty($params['preset_locality'])
            ? absint($params['preset_locality'])
            : ($params['locality'] ?? null);
        if ($localityRaw !== null && $localityRaw !== '' && $localityRaw !== []) {
            $localityIds = LocalityPostType::resolvePublishedIds($localityRaw);
            $neighborhoodIds = [];
            foreach ($localityIds as $localityId) {
                $neighborhoodIds = array_merge($neighborhoodIds, LocalityPostType::neighborhoodIds($localityId));
            }
            $neighborhoodIds = array_values(array_unique(array_filter(array_map('absint', $neighborhoodIds))));

            if ($localityIds === [] || $neighborhoodIds === []) {
                $args['post__in'] = [0];
            } else {
                $args['tax_query'][] = [
                    'taxonomy' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
                    'field' => 'term_id',
                    'terms' => $neighborhoodIds,
                    'operator' => 'IN',
                ];
            }
        }

        // Multi-tag preset from widgets/builders.
        $presetTagIds = array_values(array_filter(array_map('absint', (array) ($params['preset_tag_ids'] ?? []))));
        if (!empty($presetTagIds)) {
            $presetTagIds = $this->filterExistingTermIds($presetTagIds, PropertyTaxonomies::TAXONOMY_TAG);
            if (!empty($presetTagIds)) {
                foreach ($args['tax_query'] as $i => $clause) {
                    if (is_array($clause) && ($clause['taxonomy'] ?? '') === PropertyTaxonomies::TAXONOMY_TAG) {
                        unset($args['tax_query'][$i]);
                    }
                }
                $args['tax_query'][] = [
                    'taxonomy' => PropertyTaxonomies::TAXONOMY_TAG,
                    'field'    => 'term_id',
                    'terms'    => $presetTagIds,
                    'operator' => 'IN',
                ];
            }
        }

        // Advisor (asesor) filter
        $agentId = absint($params['preset_agent'] ?? 0);
        if ($agentId > 0) {
            $args['meta_query'][] = [
                'key'     => '_property_agent_id',
                'value'   => $agentId,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        // Featured only
        if (!empty($params['featured'])) {
            $args['meta_query'][] = [
                'key'     => '_property_featured',
                'value'   => '1',
                'compare' => '=',
            ];
        }

        // Price range
        $priceMin = absint($params['price_min'] ?? 0);
        $priceMax = absint($params['price_max'] ?? 0);
        if ($priceMin || $priceMax) {
            $priceMeta = $this->resolvePriceMetaKey($params);
            $priceQuery = ['key' => $priceMeta, 'type' => 'NUMERIC'];
            if ($priceMin && $priceMax) {
                $priceQuery['compare'] = 'BETWEEN';
                $priceQuery['value']   = [$priceMin, $priceMax];
            } elseif ($priceMin) {
                $priceQuery['compare'] = '>=';
                $priceQuery['value']   = $priceMin;
            } else {
                $priceQuery['compare'] = '<=';
                $priceQuery['value']   = $priceMax;
            }
            $args['meta_query'][] = $priceQuery;
        }

        // Bedrooms / bathrooms (minimum)
        $bedrooms = absint($params['bedrooms'] ?? 0);
        if ($bedrooms) {
            $args['meta_query'][] = [
                'key'     => '_property_bedrooms',
                'value'   => $bedrooms,
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ];
        }

        $bathrooms = absint($params['bathrooms'] ?? 0);
        if ($bathrooms) {
            $args['meta_query'][] = [
                'key'     => '_property_bathrooms',
                'value'   => $bathrooms,
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ];
        }

        $parking = absint($params['parking'] ?? 0);
        if ($parking) {
            $args['meta_query'][] = [
                'key'     => '_property_parking',
                'value'   => $parking,
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ];
        }

        $areaMin = absint($params['area_min'] ?? 0);
        $areaMax = absint($params['area_max'] ?? 0);
        if ($areaMin || $areaMax) {
            $areaQuery = ['key' => '_property_area', 'type' => 'NUMERIC'];
            if ($areaMin && $areaMax) {
                $areaQuery['compare'] = 'BETWEEN';
                $areaQuery['value'] = [$areaMin, $areaMax];
            } elseif ($areaMin) {
                $areaQuery['compare'] = '>=';
                $areaQuery['value'] = $areaMin;
            } else {
                $areaQuery['compare'] = '<=';
                $areaQuery['value'] = $areaMax;
            }
            $args['meta_query'][] = $areaQuery;
        }

        $lat = is_numeric($params['geo_latitude'] ?? null) ? (float) $params['geo_latitude'] : null;
        $lng = is_numeric($params['geo_longitude'] ?? null) ? (float) $params['geo_longitude'] : null;
        $radiusKm = max(0, (float) ($params['geo_radius_km'] ?? 0));
        if ($lat !== null && $lng !== null && $radiusKm > 0) {
            $latDelta = $radiusKm / 111.045;
            $lngDelta = $radiusKm / max(1, 111.045 * cos(deg2rad($lat)));
            $args['meta_query'][] = [
                'key' => '_property_latitude',
                'value' => [$lat - $latDelta, $lat + $latDelta],
                'type' => 'DECIMAL(10,6)',
                'compare' => 'BETWEEN',
            ];
            $args['meta_query'][] = [
                'key' => '_property_longitude',
                'value' => [$lng - $lngDelta, $lng + $lngDelta],
                'type' => 'DECIMAL(10,6)',
                'compare' => 'BETWEEN',
            ];
        }

        // Orderby
        switch ($orderby) {
            case 'price_asc':
            case 'price_desc':
                // Ordering by a named meta clause instead of `meta_key` +
                // `meta_value_num`: WP_Query resolves the latter against
                // `reset($meta_clauses)` (wp-includes/class-wp-query.php),
                // which here is the availability guard added above, not the
                // price. That made every row tie on 0 and the sort do nothing.
                $args['meta_query'][self::ORDER_PRICE_CLAUSE] = [
                    'key'     => $this->resolvePriceMetaKey($params),
                    'type'    => 'NUMERIC',
                    'compare' => 'EXISTS',
                ];
                $args['orderby'] = [self::ORDER_PRICE_CLAUSE => $orderby === 'price_asc' ? 'ASC' : 'DESC'];
                break;
            case 'title':
                $args['orderby'] = 'title';
                $args['order']   = $order;
                break;
            default:
                $args['orderby'] = 'date';
                $args['order']   = 'DESC';
        }

        return $args;
    }

    public function applyPriorityKeywordSearch(array $clauses, \WP_Query $query): array
    {
        $rawTerm = $query->get('homlity_keyword_search');
        if (!is_string($rawTerm) || $rawTerm === '') {
            return $clauses;
        }

        if ($query->get('post_type') !== PropertyPostType::POST_TYPE) {
            return $clauses;
        }

        global $wpdb;

        $term = sanitize_text_field($rawTerm);
        if ($term === '') {
            return $clauses;
        }

        $likeAny = '%' . $wpdb->esc_like($term) . '%';
        $likePrefix = $wpdb->esc_like($term) . '%';

        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS homlity_pm_code
            ON {$wpdb->posts}.ID = homlity_pm_code.post_id
            AND homlity_pm_code.meta_key = '_property_code'";

        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS homlity_pm_sync
            ON {$wpdb->posts}.ID = homlity_pm_sync.post_id
            AND homlity_pm_sync.meta_key = '_homlity_sync_id'";

        $clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS homlity_tr
            ON {$wpdb->posts}.ID = homlity_tr.object_id
            LEFT JOIN {$wpdb->term_taxonomy} AS homlity_tt
            ON homlity_tr.term_taxonomy_id = homlity_tt.term_taxonomy_id
            AND homlity_tt.taxonomy = '" . esc_sql(PropertyTaxonomies::TAXONOMY_FEATURE) . "'
            LEFT JOIN {$wpdb->terms} AS homlity_terms
            ON homlity_tt.term_id = homlity_terms.term_id";

        $whereSearch = $wpdb->prepare(
            "(
                homlity_pm_code.meta_value LIKE %s
                OR homlity_pm_sync.meta_value LIKE %s
                OR {$wpdb->posts}.post_title LIKE %s
                OR {$wpdb->posts}.post_excerpt LIKE %s
                OR {$wpdb->posts}.post_content LIKE %s
                OR homlity_terms.name LIKE %s
            )",
            $likeAny,
            $likeAny,
            $likeAny,
            $likeAny,
            $likeAny,
            $likeAny
        );
        $clauses['where'] .= " AND {$whereSearch}";

        $rankSql = $wpdb->prepare(
            "CASE
                WHEN homlity_pm_code.meta_value = %s THEN 0
                WHEN homlity_pm_sync.meta_value = %s THEN 0
                WHEN homlity_pm_code.meta_value LIKE %s THEN 1
                WHEN homlity_pm_sync.meta_value LIKE %s THEN 1
                WHEN {$wpdb->posts}.post_title LIKE %s THEN 2
                WHEN {$wpdb->posts}.post_title LIKE %s THEN 3
                WHEN homlity_terms.name LIKE %s THEN 4
                WHEN {$wpdb->posts}.post_excerpt LIKE %s THEN 5
                WHEN {$wpdb->posts}.post_content LIKE %s THEN 6
                ELSE 9
            END",
            $term,
            $term,
            $likePrefix,
            $likePrefix,
            $likePrefix,
            $likeAny,
            $likeAny,
            $likeAny,
            $likeAny
        );

        // The rank reads columns from the joined tables while the query groups
        // by post ID. Aggregating it keeps the best match per property and,
        // more importantly, makes the statement valid under MySQL's
        // ONLY_FULL_GROUP_BY, which is on by default since 5.7 and otherwise
        // fails the whole query — leaving the search with zero results.
        $rankSql = 'MIN(' . $rankSql . ')';

        $existingOrderby = trim((string) ($clauses['orderby'] ?? ''));
        $clauses['orderby'] = $rankSql . ($existingOrderby !== '' ? ', ' . $existingOrderby : '');
        $clauses['distinct'] = 'DISTINCT';

        $groupBy = trim((string) ($clauses['groupby'] ?? ''));
        $postIdField = "{$wpdb->posts}.ID";
        if ($groupBy === '') {
            $clauses['groupby'] = $postIdField;
        } elseif (stripos($groupBy, $postIdField) === false) {
            $clauses['groupby'] .= ', ' . $postIdField;
        }

        return $clauses;
    }

    public function currentQueryParams(): array
    {
        $params = [];

        $requestedPage = max(
            1,
            (int) (
                get_query_var('paged')
                ?: get_query_var('page')
                ?: ($_GET['paged'] ?? 0)
                ?: ($_GET['page'] ?? 0)
                ?: 1
            )
        );
        $params['page'] = $requestedPage;

        if (is_search()) {
            $params['search'] = get_search_query();
        }

        if (!empty($_GET['q'])) {
            $params['search'] = sanitize_text_field(wp_unslash($_GET['q']));
        } elseif (!empty($_GET['s'])) {
            $params['search'] = sanitize_text_field(wp_unslash($_GET['s']));
        }

        foreach ([
            'gestion' => 'operation',
            'tipo' => 'type',
            'ciudad' => 'city',
            'barrios' => 'neighborhood',
        ] as $qv => $paramKey) {
            $qvValue = get_query_var($qv, '');
            if (!$qvValue) {
                continue;
            }
            if (!isset($collected[$paramKey])) {
                $collected[$paramKey] = [];
            }
            $queryVarValues = is_array($qvValue) ? $qvValue : explode(',', (string) $qvValue);
            foreach ($queryVarValues as $queryVarValue) {
                $queryVarValue = sanitize_text_field(trim((string) $queryVarValue));
                if ($queryVarValue !== '') {
                    $collected[$paramKey][] = $queryVarValue;
                }
            }
        }

        $queried = get_queried_object();
        if ($queried instanceof \WP_Term) {
            $map = [
                PropertyTaxonomies::TAXONOMY_CATEGORY => 'category',
                PropertyTaxonomies::TAXONOMY_OPERATION => 'operation',
                PropertyTaxonomies::TAXONOMY_TYPE => 'type',
                PropertyTaxonomies::TAXONOMY_TAG => 'tag',
                PropertyTaxonomies::TAXONOMY_FEATURE => 'feature',
                PropertyTaxonomies::TAXONOMY_COUNTRY => 'country',
                PropertyTaxonomies::TAXONOMY_STATE => 'state',
                PropertyTaxonomies::TAXONOMY_CITY => 'city',
                PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => 'neighborhood',
                PropertyTaxonomies::TAXONOMY_NEARBY => 'nearby',
            ];

            if (isset($map[$queried->taxonomy])) {
                $params[$map[$queried->taxonomy]] = (int) $queried->term_id;
            }
        }

        $requestToParam = [
            'categoria' => 'category',
            'property_category' => 'category',
            'gestion' => 'operation',
            'property_operation' => 'operation',
            'tipo' => 'type',
            'property_type' => 'type',
            'etiquetas' => 'tag',
            'property_tag' => 'tag',
            'caracteristica' => 'feature',
            'property_feature' => 'feature',
            'pais' => 'country',
            'property_country' => 'country',
            'departamento' => 'state',
            'property_state' => 'state',
            'ciudad' => 'city',
            'property_city' => 'city',
            'localidades' => 'locality',
            'property_locality' => 'locality',
            'barrios' => 'neighborhood',
            'property_neighborhood' => 'neighborhood',
            'cercanias' => 'nearby',
            'property_nearby' => 'nearby',
        ];

        $collected = isset($collected) && is_array($collected) ? $collected : [];
        foreach ($requestToParam as $requestKey => $paramKey) {
            $raw = $_GET[$requestKey] ?? null;
            if ($raw === null) {
                continue;
            }
            $values = is_array($raw)
                ? array_values(array_filter(array_map('sanitize_text_field', wp_unslash($raw)), static fn ($item) => $item !== ''))
                : [sanitize_text_field(wp_unslash($raw))];
            $expandedValues = [];
            foreach ($values as $valueItem) {
                $parts = array_map('trim', explode(',', (string) $valueItem));
                foreach ($parts as $part) {
                    if ($part !== '') {
                        $expandedValues[] = $part;
                    }
                }
            }
            $values = $expandedValues;
            $values = array_values(array_filter($values, static fn ($item) => $item !== ''));
            if (!$values) {
                continue;
            }

            if (!isset($collected[$paramKey])) {
                $collected[$paramKey] = [];
            }
            $collected[$paramKey] = array_merge($collected[$paramKey], $values);
        }

        foreach ($collected as $paramKey => $values) {
            $values = array_values(array_unique(array_filter($values, static fn ($item) => $item !== '')));
            if (!$values) {
                continue;
            }

            if ($paramKey === 'locality') {
                $localityIds = LocalityPostType::resolvePublishedIds($values);
                if ($localityIds) {
                    $params[$paramKey] = count($localityIds) === 1 ? $localityIds[0] : $localityIds;
                }
                continue;
            }

            $taxonomy = $this->taxonomyForParam($paramKey);
            $termIds = [];
            foreach ($values as $value) {
                if (is_numeric($value)) {
                    $termIds[] = absint($value);
                    continue;
                }
                $term = $taxonomy ? get_term_by('slug', $value, $taxonomy) : false;
                if ($term instanceof \WP_Term) {
                    $termIds[] = (int) $term->term_id;
                }
            }
            $termIds = array_values(array_unique(array_filter($termIds)));
            if ($termIds) {
                $params[$paramKey] = count($termIds) === 1 ? $termIds[0] : $termIds;
            }
        }

        $numericMap = [
            'precio_min' => 'price_min',
            'price_min' => 'price_min',
            'precio_max' => 'price_max',
            'price_max' => 'price_max',
            'alcobas' => 'bedrooms',
            'bedrooms' => 'bedrooms',
            'banos' => 'bathrooms',
            'bathrooms' => 'bathrooms',
            'garajes' => 'parking',
            'parking' => 'parking',
            'area_min' => 'area_min',
            'area_max' => 'area_max',
        ];
        foreach ($numericMap as $requestKey => $paramKey) {
            if (isset($_GET[$requestKey]) && $_GET[$requestKey] !== '') {
                $params[$paramKey] = absint($_GET[$requestKey]);
            }
        }

        return $params;
    }

    private function taxonomyForParam(string $param): ?string
    {
        return match ($param) {
            'category' => PropertyTaxonomies::TAXONOMY_CATEGORY,
            'operation' => PropertyTaxonomies::TAXONOMY_OPERATION,
            'type' => PropertyTaxonomies::TAXONOMY_TYPE,
            'tag' => PropertyTaxonomies::TAXONOMY_TAG,
            'feature' => PropertyTaxonomies::TAXONOMY_FEATURE,
            'country' => PropertyTaxonomies::TAXONOMY_COUNTRY,
            'state' => PropertyTaxonomies::TAXONOMY_STATE,
            'city' => PropertyTaxonomies::TAXONOMY_CITY,
            'neighborhood' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            'nearby' => PropertyTaxonomies::TAXONOMY_NEARBY,
            default => null,
        };
    }

    private function extractRequestedPropertyCode(): string
    {
        foreach (['codigo', 'property_code', 'code', 'q', 's'] as $param) {
            if (!isset($_GET[$param])) {
                continue;
            }

            $value = sanitize_text_field(wp_unslash((string) $_GET[$param]));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function looksLikePropertyCode(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || strpos($value, ' ') !== false) {
            return false;
        }

        return (bool) preg_match('/^(?:\d{3,}|(?=.*\d)[A-Za-z0-9-]{4,})$/', $value);
    }

    private function findPropertyByExactCode(string $code): int
    {
        $code = sanitize_text_field($code);
        if ($code === '') {
            return 0;
        }

        $posts = get_posts([
            'post_type'      => PropertyPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_property_code',
                    'value'   => $code,
                    'compare' => '=',
                ],
                [
                    'key'     => '_homlity_sync_id',
                    'value'   => $code,
                    'compare' => '=',
                ],
            ],
        ]);

        return !empty($posts) ? (int) $posts[0] : 0;
    }

    private function extractTermIds($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $values = is_array($raw) ? $raw : explode(',', (string) $raw);
        $ids = [];
        foreach ($values as $value) {
            $id = absint($value);
            if ($id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param int[] $termIds
     * @return int[]
     */
    private function filterExistingTermIds(array $termIds, string $taxonomy): array
    {
        $valid = [];
        foreach ($termIds as $termId) {
            if ($termId > 0 && term_exists($termId, $taxonomy)) {
                $valid[] = $termId;
            }
        }

        return array_values(array_unique($valid));
    }

    /**
     * Returns the price meta key to use for ordering/filtering based on the
     * operation filter. If the active operation is rent-type, returns
     * `_property_price_rent`; otherwise falls back to `_property_price_sale`.
     */
    private function resolvePriceMetaKey(array $params): string
    {
        $operationIds = $this->extractTermIds($params['operation'] ?? null);
        foreach ($operationIds as $termId) {
            $term = get_term($termId, PropertyTaxonomies::TAXONOMY_OPERATION);
            if (!($term instanceof \WP_Term)) {
                continue;
            }
            $text = strtolower(remove_accents($term->slug . ' ' . $term->name));
            if (
                strpos($text, 'arriendo') !== false || strpos($text, 'alquil') !== false
                || strpos($text, 'renta') !== false  || strpos($text, 'rent') !== false
            ) {
                return '_property_price_rent';
            }
        }
        return '_property_price_sale';
    }

    public function getMapData(\WP_Query $query): array
    {
        if (empty($query->posts)) {
            return [];
        }

        $data            = [];
        $currencyService = new CurrencyService();

        foreach ($query->posts as $post) {
            $lat = get_post_meta($post->ID, '_property_latitude', true);
            $lng = get_post_meta($post->ID, '_property_longitude', true);

            if (!$lat || !$lng) {
                continue;
            }

            $price    = get_post_meta($post->ID, '_property_price_sale', true);
            $currency = get_post_meta($post->ID, '_property_currency_sale', true) ?: $currencyService->baseCurrency();

            $data[] = [
                'id'        => $post->ID,
                'title'     => get_the_title($post->ID),
                'permalink' => get_permalink($post->ID),
                'thumbnail' => get_the_post_thumbnail_url($post->ID, 'thumbnail') ?: '',
                'lat'       => (float) $lat,
                'lng'       => (float) $lng,
                'price'     => $price ? homlity_plugin_apply_filters('homlity_plugin_format_price', null, $price, $currency) : '',
                'bedrooms'  => get_post_meta($post->ID, '_property_bedrooms', true),
                'bathrooms' => get_post_meta($post->ID, '_property_bathrooms', true),
                'area'      => get_post_meta($post->ID, '_property_area', true),
            ];
        }

        return $data;
    }
}
