<?php
/**
 * Central renderer for property listings.
 *
 * All page-builder integrations (Elementor, WPBakery, Divi, shortcode) converge
 * here. They each produce a ListingConfig and call ListingRenderer::render().
 * The renderer is completely unaware of which builder triggered it.
 */

namespace Homlity\PluginInmobiliario\Listing;

use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

class ListingRenderer
{
    private PropertySearchService $search;

    public function __construct()
    {
        $this->search = new PropertySearchService();
    }

    /**
     * Enqueue assets, build the query and include the appropriate template.
     */
    public function render(ListingConfig $config): void
    {
        self::enqueueAssets();

        $params = $config->toQueryParams();
        $requestFilterKeys = [
            'q',
            's',
            'categoria',
            'property_category',
            'gestion',
            'property_operation',
            'tipo',
            'property_type',
            'etiquetas',
            'property_tag',
            'caracteristica',
            'property_feature',
            'pais',
            'property_country',
            'departamento',
            'property_state',
            'ciudad',
            'property_city',
            'barrios',
            'property_neighborhood',
            'cercanias',
            'property_nearby',
            'precio_min',
            'price_min',
            'precio_max',
            'price_max',
            'alcobas',
            'bedrooms',
            'banos',
            'bathrooms',
            'garajes',
            'parking',
            'area_min',
            'area_max',
        ];
        $hasRequestFilters = false;
        foreach ($requestFilterKeys as $requestKey) {
            if (isset($_GET[$requestKey]) && wp_unslash($_GET[$requestKey]) !== '') {
                $hasRequestFilters = true;
                break;
            }
        }

        if (!$hasRequestFilters) {
            foreach (['gestion', 'tipo', 'ciudad', 'barrios'] as $seoVar) {
                if ((string) get_query_var($seoVar, '') !== '') {
                    $hasRequestFilters = true;
                    break;
                }
            }
        }

        if (($params['query_mode'] ?? '') === 'current' || $hasRequestFilters) {
            $currentParams = $this->search->currentQueryParams();
            foreach ($currentParams as $key => $value) {
                if ($key === 'search') {
                    $params['search'] = $value;
                    continue;
                }

                if (in_array($key, ['price_min', 'price_max', 'bedrooms', 'bathrooms', 'parking', 'area_min', 'area_max'], true)) {
                    $params[$key] = $value;
                    continue;
                }

                if (in_array($key, ['category', 'operation', 'type', 'tag', 'feature', 'country', 'state', 'city', 'neighborhood', 'nearby'], true)) {
                    $params[$key] = $value;
                    continue;
                }

                $presetKey = 'preset_' . $key;
                if (array_key_exists($presetKey, $params) && empty($params[$presetKey])) {
                    $params[$presetKey] = $value;
                }
            }
            $params['query_mode'] = 'custom';
        }

        $args   = $this->search->buildQueryArgs($params);
        $query  = new \WP_Query($args);

        TemplateService::includeComponent($config->listingTemplate(), [
            'config' => $config,
            'query'  => $query,
            'search' => $this->search,
            'params' => $params,
        ]);

        wp_reset_postdata();
    }

    /**
     * Render a single page of cards as an HTML string (used by the AJAX handler).
     * The card template is chosen from $config->cardTemplate().
     */
    public function renderCards(\WP_Query $query, ListingConfig $config): string
    {
        ob_start();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                TemplateService::includeComponent($config->cardTemplate(), [
                    'post_id' => get_the_ID(),
                    'card_options' => $config->cardOptions(),
                ]);
            }
            wp_reset_postdata();
        }

        return ob_get_clean() ?: '';
    }

    /**
     * Register and enqueue all scripts/styles required by the listing.
     * Safe to call multiple times – WordPress deduplicates by handle.
     */
    public static function enqueueAssets(): void
    {
        wp_enqueue_style(
            'homlity-plugin-front-components',
            HOMLITY_PLUGIN_URL . 'assets/css/front-components.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );

        wp_enqueue_style(
            'homlity-plugin-listing',
            HOMLITY_PLUGIN_URL . 'assets/css/property-listing.css',
            ['homlity-plugin-front-components'],
            HOMLITY_PLUGIN_VERSION
        );

        wp_enqueue_style(
            'homlity-plugin-swiper',
            'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
            [],
            '11.1.4'
        );

        wp_enqueue_style(
            'homlity-plugin-leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            [],
            '1.9.4'
        );

        wp_enqueue_script(
            'homlity-plugin-leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            [],
            '1.9.4',
            true
        );

        wp_enqueue_script(
            'homlity-plugin-swiper',
            'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
            [],
            '11.1.4',
            true
        );

        wp_enqueue_script(
            'homlity-plugin-listing',
            HOMLITY_PLUGIN_URL . 'assets/js/property-listing.js',
            ['homlity-plugin-leaflet', 'homlity-plugin-swiper'],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        wp_localize_script('homlity-plugin-listing', 'homlityListingI18n', [
            'noResults' => __('No se han encontrado inmuebles para esta consulta.', 'homlity-plugin'),
            'loading'   => __('Cargando...', 'homlity-plugin'),
        ]);
    }
}
