<?php
/**
 * AJAX handler for the property listing widget (filter, sort, paginate).
 * Works with any page-builder adapter – the template type is passed in the request.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyAjaxService implements ServiceInterface
{
    private PropertySearchService $search;

    public function __construct()
    {
        $this->search = new PropertySearchService();
    }

    public function register(): void
    {
        add_action('wp_ajax_homlity_listing',        [$this, 'handle']);
        add_action('wp_ajax_nopriv_homlity_listing', [$this, 'handle']);
    }

    public function handle(): void
    {
        check_ajax_referer('homlity_listing_nonce', 'nonce');

        // Build a ListingConfig from POST params so the correct card template is used.
        $config = ListingConfig::fromArray([
            'posts_per_page'   => absint($_POST['per_page']  ?? 12),
            'orderby'          => sanitize_key($_POST['orderby'] ?? 'date'),
            'query_mode'       => sanitize_key($_POST['query_mode'] ?? 'custom'),
            'featured_only'    => !empty($_POST['featured']),
            'search_keyword'   => sanitize_text_field(wp_unslash($_POST['search'] ?? '')),
            'preset_category'  => absint($_POST['preset_category'] ?? 0),
            'preset_operation' => absint($_POST['preset_operation'] ?? 0),
            'preset_type'      => absint($_POST['preset_type']      ?? 0),
            'preset_tag'       => absint($_POST['preset_tag']       ?? 0),
            'preset_feature'   => absint($_POST['preset_feature']   ?? 0),
            'preset_country'   => absint($_POST['preset_country']   ?? 0),
            'preset_state'     => absint($_POST['preset_state']     ?? 0),
            'preset_city'      => absint($_POST['preset_city']      ?? 0),
            'preset_neighborhood' => absint($_POST['preset_neighborhood'] ?? 0),
            'preset_nearby'    => absint($_POST['preset_nearby']    ?? 0),
            'geo_latitude'     => sanitize_text_field(wp_unslash($_POST['geo_latitude'] ?? '')),
            'geo_longitude'    => sanitize_text_field(wp_unslash($_POST['geo_longitude'] ?? '')),
            'geo_radius_km'    => (float) ($_POST['geo_radius_km'] ?? 0),
            'template'         => sanitize_key($_POST['template']   ?? 'default'),
            'card_media_mode'  => sanitize_key($_POST['card_media_mode'] ?? 'single'),
            'card_show_title'  => !empty($_POST['card_show_title']),
            'card_show_excerpt' => !empty($_POST['card_show_excerpt']),
            'card_show_operation' => !empty($_POST['card_show_operation']),
            'card_show_price'  => !empty($_POST['card_show_price']),
            'card_show_features' => !empty($_POST['card_show_features']),
            'card_show_whatsapp' => !empty($_POST['card_show_whatsapp']),
            'card_whatsapp_label' => sanitize_text_field(wp_unslash($_POST['card_whatsapp_label'] ?? '')),
            'card_feature_area' => !empty($_POST['card_feature_area']),
            'card_feature_bedrooms' => !empty($_POST['card_feature_bedrooms']),
            'card_feature_bathrooms' => !empty($_POST['card_feature_bathrooms']),
            'card_feature_parking' => !empty($_POST['card_feature_parking']),
            'card_feature_area_lot' => !empty($_POST['card_feature_area_lot']),
            'card_feature_area_private' => !empty($_POST['card_feature_area_private']),
            'card_feature_area_built' => !empty($_POST['card_feature_area_built']),
            'card_feature_age' => !empty($_POST['card_feature_age']),
            'card_feature_condition' => !empty($_POST['card_feature_condition']),
            'card_feature_code' => !empty($_POST['card_feature_code']),
        ]);

        $filterParams = [
            'per_page'         => $config->postsPerPage(),
            'page'             => absint($_POST['page'] ?? 1),
            'orderby'          => $config->orderby(),
            'query_mode'       => $config->queryMode(),
            'featured'         => $config->featuredOnly(),
            'search'           => $config->searchKeyword(),
            'preset_category'  => $config->presetCategory(),
            'preset_operation' => $config->presetOperation(),
            'preset_type'      => $config->presetType(),
            'preset_tag'       => $config->presetTag(),
            'preset_feature'   => $config->presetFeature(),
            'preset_country'   => $config->presetCountry(),
            'preset_state'     => $config->presetState(),
            'preset_city'      => $config->presetCity(),
            'preset_neighborhood' => $config->presetNeighborhood(),
            'preset_nearby'    => $config->presetNearby(),
            'operation'        => sanitize_text_field(wp_unslash($_POST['operation']    ?? '')),
            'category'         => sanitize_text_field(wp_unslash($_POST['category']     ?? '')),
            'type'             => sanitize_text_field(wp_unslash($_POST['type']         ?? '')),
            'tag'              => sanitize_text_field(wp_unslash($_POST['tag']          ?? '')),
            'feature'          => sanitize_text_field(wp_unslash($_POST['feature']      ?? '')),
            'country'          => sanitize_text_field(wp_unslash($_POST['country']      ?? '')),
            'city'             => sanitize_text_field(wp_unslash($_POST['city']         ?? '')),
            'state'            => sanitize_text_field(wp_unslash($_POST['state']        ?? '')),
            'neighborhood'     => sanitize_text_field(wp_unslash($_POST['neighborhood'] ?? '')),
            'nearby'           => sanitize_text_field(wp_unslash($_POST['nearby']       ?? '')),
            'price_min'        => absint($_POST['price_min']    ?? 0),
            'price_max'        => absint($_POST['price_max']    ?? 0),
            'bedrooms'         => absint($_POST['bedrooms']     ?? 0),
            'bathrooms'        => absint($_POST['bathrooms']    ?? 0),
            'parking'          => absint($_POST['parking']      ?? 0),
            'area_min'         => absint($_POST['area_min']     ?? 0),
            'area_max'         => absint($_POST['area_max']     ?? 0),
            'geo_latitude'     => $config->geoLatitude(),
            'geo_longitude'    => $config->geoLongitude(),
            'geo_radius_km'    => $config->geoRadiusKm(),
        ];

        $args    = $this->search->buildQueryArgs($filterParams);
        $query   = new \WP_Query($args);
        $renderer = new ListingRenderer();

        wp_send_json_success([
            'html'     => $renderer->renderCards($query, $config),
            'total'    => (int) $query->found_posts,
            'pages'    => (int) $query->max_num_pages,
            'map_data' => $this->search->getMapData($query),
        ]);
    }
}
