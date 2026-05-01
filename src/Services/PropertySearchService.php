<?php
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
    public function register(): void {}

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

        if (!empty($params['search'])) {
            $args['s'] = sanitize_text_field((string) $params['search']);
        }

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

        // User filter terms
        foreach ($taxMap as $key => $taxonomy) {
            $termId = absint($params[$key] ?? 0);
            if ($termId) {
                $args['tax_query'][] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => [$termId],
                ];
            }
        }

        // Widget preset terms override user filters
        foreach ($taxMap as $key => $taxonomy) {
            $presetKey = 'preset_' . $key;
            $presetId = absint($params[$presetKey] ?? 0);
            if ($presetId) {
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
            $priceQuery = ['key' => '_property_price_sale', 'type' => 'NUMERIC'];
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
                $args['orderby']  = 'meta_value_num';
                $args['meta_key'] = '_property_price_sale';
                $args['order']    = 'ASC';
                break;
            case 'price_desc':
                $args['orderby']  = 'meta_value_num';
                $args['meta_key'] = '_property_price_sale';
                $args['order']    = 'DESC';
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

    public function currentQueryParams(): array
    {
        $params = [];

        if (is_search()) {
            $params['search'] = get_search_query();
        }

        if (!empty($_GET['s'])) {
            $params['search'] = sanitize_text_field(wp_unslash($_GET['s']));
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

        foreach ([
            'property_category' => 'category',
            'property_operation' => 'operation',
            'property_type' => 'type',
            'property_tag' => 'tag',
            'property_feature' => 'feature',
            'property_country' => 'country',
            'property_state' => 'state',
            'property_city' => 'city',
            'property_neighborhood' => 'neighborhood',
            'property_nearby' => 'nearby',
        ] as $requestKey => $paramKey) {
            $value = isset($_GET[$requestKey]) ? sanitize_text_field(wp_unslash($_GET[$requestKey])) : '';
            if ($value === '') {
                continue;
            }

            if (is_numeric($value)) {
                $params[$paramKey] = absint($value);
                continue;
            }

            $taxonomy = $this->taxonomyForParam($paramKey);
            $term = $taxonomy ? get_term_by('slug', $value, $taxonomy) : false;
            if ($term instanceof \WP_Term) {
                $params[$paramKey] = (int) $term->term_id;
            }
        }

        foreach (['price_min', 'price_max', 'bedrooms', 'bathrooms'] as $key) {
            if (isset($_GET[$key]) && $_GET[$key] !== '') {
                $params[$key] = absint($_GET[$key]);
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
