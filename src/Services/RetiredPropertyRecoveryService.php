<?php
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_tax_query
/**
 * Builds the full recovery context for a retired property URL.
 *
 * Called by PropertyUnavailableService when a visitor lands on the permalink
 * of an unavailable (retired, sold, rented, draft, private) property.
 *
 * The context array drives:
 * – HTTP status (200, 301, 404, 410)
 * – robots meta (index,follow / noindex,follow)
 * – canonical link
 * – meta title / meta description
 * – WP_Query args for similar/available properties
 * – internal links for SEO authority distribution
 * – human-readable "applied filters" description
 *
 * Decision logic
 * ──────────────
 * CASE A (has data + enough similar results) → 200 index,follow  + recovery landing
 * CASE B (has data but few/no similar results) → 200 noindex,follow  or 404/410 (per option)
 * CASE C (post doesn't exist at all) → 404
 * CASE D (explicit replacement property set) → 301 to new property
 *
 * Caching
 * ───────
 * Results are cached in a WordPress transient (`homlity_rcv_{propertyId}`) for
 * CACHE_TTL seconds. The cache is deleted automatically whenever any property
 * post is saved, ensuring fresh results are served after an inventory update.
 *
 * Admin options consumed
 * ──────────────────────
 * homlity_recovery_enabled          — '1' (default) | '0'  activate recovery
 * homlity_recovery_min_results      — int (default 3)
 * homlity_recovery_max_results      — int (default 12)
 * homlity_recovery_price_tolerance  — float 0–1 (default 0.20)
 * homlity_recovery_area_tolerance   — float 0–1 (default 0.20)
 * homlity_recovery_no_results_action — 'noindex' (default) | '404' | '410'
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

class RetiredPropertyRecoveryService
{
    private const CACHE_PREFIX  = 'homlity_rcv_';
    private const CACHE_TTL     = 6 * HOUR_IN_SECONDS;
    private const MIN_RESULTS   = 3;

    private SimilarPropertiesQueryBuilder $queryBuilder;

    public function __construct()
    {
        $this->queryBuilder = new SimilarPropertiesQueryBuilder();
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Build (or retrieve from cache) the full recovery context for $propertyId.
     *
     * @return array<string,mixed>
     */
    public function buildRecoveryContext(int $propertyId): array
    {
        if ($propertyId <= 0) {
            return $this->emptyContext(404);
        }

        $cacheKey = self::CACHE_PREFIX . $propertyId;
        $cached   = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $context = $this->buildFresh($propertyId);
        set_transient($cacheKey, $context, self::CACHE_TTL);

        return $context;
    }

    /**
     * Extract all relevant meta and taxonomy data from a property post.
     * Works for any post status (publish, draft, private, pending, future).
     *
     * @return array<string,mixed>
     */
    public function extractPropertyData(int $propertyId): array
    {
        if ($propertyId <= 0) {
            return [];
        }

        $metaKeys = [
            'price_sale'    => '_property_price_sale',
            'price_rent'    => '_property_price_rent',
            'price_admin'   => '_property_price_admin',
            'currency_sale' => '_property_currency_sale',
            'currency_rent' => '_property_currency_rent',
            'area'          => '_property_area',
            'area_built'    => '_property_area_built',
            'area_private'  => '_property_area_private',
            'area_lot'      => '_property_area_lot',
            'bedrooms'      => '_property_bedrooms',
            'bathrooms'     => '_property_bathrooms',
            'parking'       => '_property_parking',
            'condition'     => '_property_condition',
            'age'           => '_property_age',
            'code'          => '_property_code',
            'address'       => '_property_address',
            'featured'      => '_property_featured',
            'status'        => '_property_status',
            'available'     => '_property_available',
        ];

        $data = [];
        foreach ($metaKeys as $field => $metaKey) {
            $data[$field] = get_post_meta($propertyId, $metaKey, true);
        }

        // Optional manual replacement
        $data['replacement_id'] = (int) get_post_meta($propertyId, '_homlity_replacement_property_id', true);

        // Taxonomies: store both IDs and first-term display name
        $termMap = [
            'operation'    => PropertyTaxonomies::TAXONOMY_OPERATION,
            'type'         => PropertyTaxonomies::TAXONOMY_TYPE,
            'city'         => PropertyTaxonomies::TAXONOMY_CITY,
            'neighborhood' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            'country'      => PropertyTaxonomies::TAXONOMY_COUNTRY,
            'state'        => PropertyTaxonomies::TAXONOMY_STATE,
            'category'     => PropertyTaxonomies::TAXONOMY_CATEGORY,
            'tag'          => PropertyTaxonomies::TAXONOMY_TAG,
        ];

        foreach ($termMap as $key => $taxonomy) {
            $ids = wp_get_post_terms($propertyId, $taxonomy, ['fields' => 'ids']);
            $data[$key . '_term_ids'] = !is_wp_error($ids)
                ? array_values(array_filter(array_map('absint', $ids)))
                : [];

            $names = wp_get_post_terms($propertyId, $taxonomy, ['fields' => 'names']);
            $data[$key . '_name'] = (!is_wp_error($names) && !empty($names))
                ? (string) $names[0]
                : '';
        }

        // Operation slugs used by SimilarPropertiesQueryBuilder to pick the price key
        $opSlugs = wp_get_post_terms($propertyId, PropertyTaxonomies::TAXONOMY_OPERATION, ['fields' => 'slugs']);
        $data['operation_slugs'] = !is_wp_error($opSlugs) ? array_values($opSlugs) : [];

        return $data;
    }

    /**
     * Purge the transient cache for a specific property.
     * Hooked to `save_post_{property}` by PropertyUnavailableService.
     */
    public static function invalidateCache(int $postId): void
    {
        if ($postId > 0) {
            delete_transient(self::CACHE_PREFIX . $postId);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function buildFresh(int $propertyId): array
    {
        $post = get_post($propertyId);

        // CASE C: not in the database
        if (!$post instanceof \WP_Post) {
            return $this->emptyContext(404);
        }

        $propertyData = $this->extractPropertyData($propertyId);

        // CASE D: explicit replacement property
        $replacementId = (int) ($propertyData['replacement_id'] ?? 0);
        if ($replacementId > 0 && get_post_status($replacementId) === 'publish') {
            return [
                'is_retired'         => true,
                'property_id'        => $propertyId,
                'status_code'        => 301,
                'robots'             => 'noindex,follow',
                'canonical'          => '',
                'redirect_url'       => (string) get_permalink($replacementId),
                'meta_title'         => '',
                'meta_description'   => '',
                'property_summary'   => null,
                'similar_args'       => null,
                'similar_count'      => 0,
                'applied_filters'    => [],
                'fallback_level'     => SimilarPropertiesQueryBuilder::LEVEL_NONE,
                'has_enough_results' => false,
                'internal_links'     => [],
            ];
        }

        // CASES A & B: recover with similar properties
        $minResults = max(1, (int) get_option('homlity_recovery_min_results', self::MIN_RESULTS));
        $maxResults = max(3, min(48, (int) get_option('homlity_recovery_max_results', 12)));
        $priceTol   = min(0.9, max(0.05, (float) get_option('homlity_recovery_price_tolerance', 0.20)));
        $areaTol    = min(0.9, max(0.05, (float) get_option('homlity_recovery_area_tolerance', 0.20)));

        $result = $this->queryBuilder->findSimilar($propertyData, [
            'min_results'     => $minResults,
            'max_results'     => $maxResults,
            'price_tolerance' => $priceTol,
            'area_tolerance'  => $areaTol,
            'exclude_id'      => $propertyId,
        ]);

        $similarCount  = (int) $result['count'];
        $hasEnough     = $similarCount >= $minResults;
        $fallbackLevel = (int) $result['fallback_level'];

        $noResultsAction  = (string) get_option('homlity_recovery_no_results_action', 'noindex');
        [$statusCode, $robots, $canonical] = $this->resolveStatus($hasEnough, $noResultsAction);

        return [
            'is_retired'         => true,
            'property_id'        => $propertyId,
            'status_code'        => $statusCode,
            'robots'             => $robots,
            'canonical'          => $canonical,
            'redirect_url'       => '',
            'meta_title'         => $this->buildMetaTitle($propertyData),
            'meta_description'   => $this->buildMetaDescription($propertyData),
            'property_summary'   => $this->buildPropertySummary($propertyId, $propertyData),
            'similar_args'       => $result['args'],
            'similar_count'      => $similarCount,
            'applied_filters'    => $result['applied_filters'],
            'fallback_level'     => $fallbackLevel,
            'has_enough_results' => $hasEnough,
            'internal_links'     => $this->buildInternalLinks($propertyData),
        ];
    }

    /**
     * Determine HTTP status + robots + canonical flag based on content quality.
     *
     * @return array{int, string, string}   [statusCode, robots, canonical]
     */
    private function resolveStatus(bool $hasEnough, string $noResultsAction): array
    {
        if ($hasEnough) {
            return [200, 'index,follow', 'self'];
        }

        return match ($noResultsAction) {
            '404'   => [404, 'noindex,follow', ''],
            '410'   => [410, 'noindex,follow', ''],
            default => [200, 'noindex,follow', ''], // 'noindex' — show page but don't index
        };
    }

    /** @return array<string,string>|null */
    private function buildPropertySummary(int $propertyId, array $propertyData): ?array
    {
        if (!get_post($propertyId) instanceof \WP_Post) {
            return null;
        }

        $price = '';
        if ((float) ($propertyData['price_sale'] ?? 0) > 0) {
            $currency = $propertyData['currency_sale'] ?: (new CurrencyService())->baseCurrency();
            $price    = (string) homlity_plugin_apply_filters('homlity_plugin_format_price', null, $propertyData['price_sale'], $currency);
        } elseif ((float) ($propertyData['price_rent'] ?? 0) > 0) {
            $currency = $propertyData['currency_rent'] ?: (new CurrencyService())->baseCurrency();
            $price    = (string) homlity_plugin_apply_filters('homlity_plugin_format_price', null, $propertyData['price_rent'], $currency);
        }

        return [
            'title'             => (string) get_the_title($propertyId),
            'type_name'         => (string) ($propertyData['type_name'] ?? ''),
            'operation_name'    => (string) ($propertyData['operation_name'] ?? ''),
            'city_name'         => (string) ($propertyData['city_name'] ?? ''),
            'neighborhood_name' => (string) ($propertyData['neighborhood_name'] ?? ''),
            'state_name'        => (string) ($propertyData['state_name'] ?? ''),
            'country_name'      => (string) ($propertyData['country_name'] ?? ''),
            'price_formatted'   => $price,
            'bedrooms'          => (string) ($propertyData['bedrooms'] ?? ''),
            'bathrooms'         => (string) ($propertyData['bathrooms'] ?? ''),
            'parking'           => (string) ($propertyData['parking'] ?? ''),
            'area'              => (string) ($propertyData['area'] ?? ''),
            'area_built'        => (string) ($propertyData['area_built'] ?? ''),
            'code'              => (string) ($propertyData['code'] ?? ''),
        ];
    }

    private function buildMetaTitle(array $propertyData): string
    {
        $typeParts = array_filter([
            (string) ($propertyData['type_name'] ?? ''),
            strtolower((string) ($propertyData['operation_name'] ?? '')),
        ]);
        $typeOp = implode(' en ', $typeParts);

        $locationParts = array_filter([
            (string) ($propertyData['neighborhood_name'] ?? ''),
            (string) ($propertyData['city_name'] ?? ''),
        ]);
        $location = implode(', ', $locationParts);

        if ($typeOp !== '' && $location !== '') {
            /* translators: 1: property type + operation (e.g. "apartamento en venta"), 2: location */
            return sprintf(__('Propiedades similares en %2$s | %1$s disponibles', 'homlity-real-estate'), $typeOp, $location);
        }
        if ($location !== '') {
            /* translators: %s: location string (city, neighborhood or both) */
            return sprintf(__('Propiedades similares en %s', 'homlity-real-estate'), $location);
        }

        return __('Propiedades similares disponibles', 'homlity-real-estate');
    }

    private function buildMetaDescription(array $propertyData): string
    {
        $parts    = array_filter([
            (string) ($propertyData['neighborhood_name'] ?? ''),
            (string) ($propertyData['city_name'] ?? ''),
        ]);
        $location = implode(', ', $parts);

        if ($location !== '') {
            return sprintf(
                /* translators: %s: location string (neighborhood + city) */
                __('Este inmueble ya no está disponible, pero encontramos opciones similares en %s. Explora propiedades con características parecidas y agenda tu asesoría.', 'homlity-real-estate'),
                $location
            );
        }

        return __('Este inmueble ya no está disponible, pero encontramos opciones similares que podrían interesarte. Explora nuestro catálogo de propiedades disponibles.', 'homlity-real-estate');
    }

    /**
     * Build internal links for SEO equity distribution and UX navigation.
     *
     * @return array<int,array{label:string,url:string}>
     */
    private function buildInternalLinks(array $propertyData): array
    {
        $links   = [];
        $baseUrl = home_url('/inmuebles/');

        // City
        $cityName    = (string) ($propertyData['city_name'] ?? '');
        $cityTermIds = (array) ($propertyData['city_term_ids'] ?? []);
        if ($cityName !== '' && !empty($cityTermIds)) {
            $term = get_term((int) $cityTermIds[0], PropertyTaxonomies::TAXONOMY_CITY);
            if ($term instanceof \WP_Term) {
                $links[] = [
                    /* translators: %s: city name */
                    'label' => sprintf(__('Inmuebles en %s', 'homlity-real-estate'), $cityName),
                    'url'   => $baseUrl . 'ciudad/' . rawurlencode($term->slug) . '/',
                ];
            }
        }

        // Neighborhood
        $neighborhoodName    = (string) ($propertyData['neighborhood_name'] ?? '');
        $neighborhoodTermIds = (array) ($propertyData['neighborhood_term_ids'] ?? []);
        if ($neighborhoodName !== '' && !empty($neighborhoodTermIds)) {
            $term = get_term((int) $neighborhoodTermIds[0], PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD);
            if ($term instanceof \WP_Term) {
                $links[] = [
                    /* translators: %s: neighborhood name */
                    'label' => sprintf(__('Inmuebles en %s', 'homlity-real-estate'), $neighborhoodName),
                    'url'   => $baseUrl . 'barrios/' . rawurlencode($term->slug) . '/',
                ];
            }
        }

        // Operation
        $operationName    = (string) ($propertyData['operation_name'] ?? '');
        $operationTermIds = (array) ($propertyData['operation_term_ids'] ?? []);
        if ($operationName !== '' && !empty($operationTermIds)) {
            $term = get_term((int) $operationTermIds[0], PropertyTaxonomies::TAXONOMY_OPERATION);
            if ($term instanceof \WP_Term) {
                $links[] = [
                    /* translators: %s: operation name (e.g. "venta", "arriendo") */
                    'label' => sprintf(__('Inmuebles en %s', 'homlity-real-estate'), strtolower($operationName)),
                    'url'   => $baseUrl . 'gestion/' . rawurlencode($term->slug) . '/',
                ];
            }
        }

        // Type
        $typeName    = (string) ($propertyData['type_name'] ?? '');
        $typeTermIds = (array) ($propertyData['type_term_ids'] ?? []);
        if ($typeName !== '' && !empty($typeTermIds)) {
            $term = get_term((int) $typeTermIds[0], PropertyTaxonomies::TAXONOMY_TYPE);
            if ($term instanceof \WP_Term) {
                $links[] = [
                    /* translators: %s: property type name (e.g. "Apartamentos") */
                    'label' => sprintf(__('%s disponibles', 'homlity-real-estate'), $typeName),
                    'url'   => $baseUrl . 'tipo/' . rawurlencode($term->slug) . '/',
                ];
            }
        }

        // General catalog – always included as final option
        $links[] = [
            'label' => __('Ver todos los inmuebles', 'homlity-real-estate'),
            'url'   => $baseUrl,
        ];

        return $links;
    }

    /** @return array<string,mixed> */
    private function emptyContext(int $statusCode): array
    {
        return [
            'is_retired'         => false,
            'property_id'        => 0,
            'status_code'        => $statusCode,
            'robots'             => 'noindex,follow',
            'canonical'          => '',
            'redirect_url'       => '',
            'meta_title'         => '',
            'meta_description'   => '',
            'property_summary'   => null,
            'similar_args'       => null,
            'similar_count'      => 0,
            'applied_filters'    => [],
            'fallback_level'     => SimilarPropertiesQueryBuilder::LEVEL_NONE,
            'has_enough_results' => false,
            'internal_links'     => [],
        ];
    }
}
