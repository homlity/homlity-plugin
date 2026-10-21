<?php
/**
 * Integra el schema de inmuebles y el sitemap core; redirige los sitemaps heredados al índice activo.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class SeoIntegrationService implements ServiceInterface
{
    private const SEARCH_SITEMAP_SLUG   = 'homlity-search-sitemap.xml';
    private const PROPERTY_SITEMAP_SLUG = 'homlity-properties-sitemap';

    public function register(): void
    {
        add_filter('rank_math/json_ld', [$this, 'addRankMathPropertySchema'], 20, 2);

        // WordPress core sitemaps (WP 5.5+)
        add_filter('wp_sitemaps_post_types', [$this, 'addWpCoreSitemapPostType']);

        // Las rutas antiguas siguen resolviendo después de actualizar el plugin.
        add_filter('query_vars', [$this, 'registerSitemapQueryVars']);
        add_action('init', [$this, 'registerSearchSitemapRewrite']);
        add_action('init', [$this, 'registerPropertySitemapRewrite']);
        add_action('template_redirect', [$this, 'maybeRedirectLegacySitemap'], 0);
    }

    public function registerSitemapQueryVars(array $vars): array
    {
        foreach ([
            'homlity_properties_sitemap',
            'homlity_sitemap_paged',
            'homlity_search_sitemap',
        ] as $var) {
            if (!in_array($var, $vars, true)) {
                $vars[] = $var;
            }
        }

        return $vars;
    }

    /**
     * @param array<string,\WP_Post_Type> $postTypes
     * @return array<string,\WP_Post_Type>
     */
    public function addWpCoreSitemapPostType(array $postTypes): array
    {
        if (!isset($postTypes[PropertyPostType::POST_TYPE])) {
            $obj = get_post_type_object(PropertyPostType::POST_TYPE);
            if ($obj instanceof \WP_Post_Type) {
                $postTypes[PropertyPostType::POST_TYPE] = $obj;
            }
        }
        return $postTypes;
    }

    public function registerPropertySitemapRewrite(): void
    {
        $slug = preg_quote(self::PROPERTY_SITEMAP_SLUG, '/');

        add_rewrite_rule(
            '^' . $slug . '-([0-9]+)\.xml$',
            'index.php?homlity_properties_sitemap=1&homlity_sitemap_paged=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^' . $slug . '\.xml$',
            'index.php?homlity_properties_sitemap=1',
            'top'
        );
    }

    public function registerSearchSitemapRewrite(): void
    {
        add_rewrite_rule('^' . preg_quote(self::SEARCH_SITEMAP_SLUG, '/') . '$', 'index.php?homlity_search_sitemap=1', 'top');
    }

    /** Redirige también con rewrites antiguas para evitar un 404 del tema. @since 2.9.15 */
    public function maybeRedirectLegacySitemap(): void
    {
        if (
            $this->requestedPropertySitemapPage() === null
            && (string) get_query_var('homlity_search_sitemap', '') !== '1'
            && $this->requestedRelativePath() !== self::SEARCH_SITEMAP_SLUG
        ) {
            return;
        }

        wp_safe_redirect($this->sitemapIndexUrl(), 301);
        exit;
    }

    private function sitemapIndexUrl(): string
    {
        $rankMathSitemap = class_exists('\\RankMath\\Sitemap\\Sitemap')
            || (class_exists('\\RankMath\\Helper') && \RankMath\Helper::is_module_active('sitemap'));

        return home_url($rankMathSitemap || defined('WPSEO_VERSION') ? '/sitemap_index.xml' : '/wp-sitemap.xml');
    }

    /**
     * Resolves the requested property sitemap even when WordPress still has
     * stale rewrite rules cached after a plugin update.
     */
    private function requestedPropertySitemapPage(): ?int
    {
        if ((string) get_query_var('homlity_properties_sitemap', '') === '1') {
            return max(1, (int) (get_query_var('homlity_sitemap_paged', 0) ?: 1));
        }

        $slug = preg_quote(self::PROPERTY_SITEMAP_SLUG, '/');
        if (!preg_match('/^' . $slug . '(?:-([0-9]+))?\.xml$/', $this->requestedRelativePath(), $matches)) {
            return null;
        }

        return max(1, isset($matches[1]) ? (int) $matches[1] : 1);
    }

    private function requestedRelativePath(): string
    {
        $requestUri = isset($_SERVER['REQUEST_URI'])
            ? esc_url_raw(wp_unslash((string) $_SERVER['REQUEST_URI']))
            : '';
        $requestPath = (string) wp_parse_url($requestUri, PHP_URL_PATH);
        $homePath = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);

        $requestPath = trim(rawurldecode($requestPath), '/');
        $homePath = trim(rawurldecode($homePath), '/');

        if ($homePath !== '' && ($requestPath === $homePath || strpos($requestPath, $homePath . '/') === 0)) {
            $requestPath = ltrim(substr($requestPath, strlen($homePath)), '/');
        }

        return $requestPath;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function addRankMathPropertySchema(array $data, $jsonld): array
    {
        if (!is_singular(PropertyPostType::POST_TYPE)) {
            return $data;
        }

        // Homlity's dedicated Schema module already prints the canonical
        // RealEstateListing graph. Do not make Rank Math emit a duplicate.
        if (
            class_exists('\\Homlity_Schema_Manager')
            && \Homlity_Schema_Manager::is_enabled()
            && \Homlity_Schema_Manager::is_property_enabled()
        ) {
            return $data;
        }

        $post = get_post();
        if (!$post instanceof \WP_Post) {
            return $data;
        }

        $meta = (new PropertyPostType())->metaKeys();
        $price = (string) (get_post_meta($post->ID, $meta['price_sale'], true) ?: get_post_meta($post->ID, $meta['price_rent'], true));
        $currency = (string) (get_post_meta($post->ID, $meta['currency_sale'], true) ?: get_post_meta($post->ID, $meta['currency_rent'], true) ?: (new CurrencyService())->baseCurrency());
        $validUntil = isset($meta['price_valid_until'])
            ? (string) get_post_meta($post->ID, $meta['price_valid_until'], true)
            : '';
        $lat = (string) get_post_meta($post->ID, $meta['latitude'], true);
        $lng = (string) get_post_meta($post->ID, $meta['longitude'], true);
        $image = get_the_post_thumbnail_url($post, 'full');

        $property = [
            '@type' => class_exists('\\Homlity_Schema_Helpers')
                ? \Homlity_Schema_Helpers::schema_type($post->ID)
                : 'Residence',
            'name' => get_the_title($post),
        ];

        if (class_exists('\\Homlity_Schema_Helpers')) {
            $code = \Homlity_Schema_Helpers::clean(\Homlity_Schema_Helpers::get_meta($post->ID, 'code'));
            if ($code !== '') {
                $property['identifier'] = $code;
            }

            $address = \Homlity_Schema_Helpers::postal_address($post->ID);
            if ($address !== []) {
                $property['address'] = $address;
            }

            $neighborhood = \Homlity_Schema_Helpers::neighborhood($post->ID);
            if ($neighborhood !== []) {
                $property['containedInPlace'] = $neighborhood;
            }

            $geo = \Homlity_Schema_Helpers::geo($post->ID);

            $bedrooms = absint(\Homlity_Schema_Helpers::get_meta($post->ID, 'bedrooms'));
            $bathrooms = absint(\Homlity_Schema_Helpers::get_meta($post->ID, 'bathrooms'));
            $floorSize = \Homlity_Schema_Helpers::floor_size($post->ID);
            $amenities = \Homlity_Schema_Helpers::amenity_features($post->ID);
            $additionalProperties = \Homlity_Schema_Helpers::additional_properties($post->ID);

            if ($bedrooms > 0) {
                $property['numberOfBedrooms'] = $bedrooms;
            }
            if ($bathrooms > 0) {
                $property['numberOfBathroomsTotal'] = $bathrooms;
            }
            if ($floorSize > 0) {
                $property['floorSize'] = [
                    '@type' => 'QuantitativeValue',
                    'value' => $floorSize,
                    'unitCode' => 'MTK',
                ];
            }
            if ($amenities !== []) {
                $property['amenityFeature'] = $amenities;
            }
            if ($additionalProperties !== []) {
                $property['additionalProperty'] = $additionalProperties;
            }

            $validUntil = \Homlity_Schema_Helpers::price_valid_until($post->ID);
        } else {
            $geo = [];
            if (is_numeric($lat) && is_numeric($lng)) {
                $precision = (int) apply_filters('homlity_schema_geo_precision', 2, $post->ID);
                $precision = max(0, min(6, $precision));
                $geo = [
                    '@type' => 'GeoCoordinates',
                    'latitude' => round((float) $lat, $precision),
                    'longitude' => round((float) $lng, $precision),
                ];
            }
        }

        if ($geo !== []) {
            $property['geo'] = $geo;
        }

        $agency = class_exists('\\Homlity_Schema_Helpers')
            ? \Homlity_Schema_Helpers::agency()
            : [];
        $offeredBy = array_filter([
            '@type' => 'RealEstateAgent',
            'name' => $agency['name'] ?? get_bloginfo('name'),
            'url' => home_url('/'),
            'telephone' => $agency['telephone'] ?? '',
        ], static fn ($value) => $value !== '');

        $schema = [
            '@type' => 'RealEstateListing',
            'name' => get_the_title($post),
            'description' => wp_strip_all_tags(get_the_excerpt($post) ?: get_post_field('post_content', $post->ID)),
            'url' => get_permalink($post),
            'image' => $image ?: '',
            'datePosted' => get_the_date('c', $post),
            'offers' => [
                '@type' => 'Offer',
                'price' => $price,
                'priceCurrency' => strtoupper($currency),
                'availability' => 'https://schema.org/InStock',
                'url' => get_permalink($post),
                'itemOffered' => $property,
                'offeredBy' => $offeredBy,
            ],
        ];

        if ($price !== '' && $validUntil !== '') {
            $schema['offers']['priceValidUntil'] = $validUntil;
        }

        $data['homlity_property'] = $schema;
        return $data;
    }
}
