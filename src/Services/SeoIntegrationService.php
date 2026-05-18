<?php
/**
 * Integrates CPT and taxonomies into Yoast SEO and Rank Math sitemaps.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class SeoIntegrationService implements ServiceInterface
{
    private const SEARCH_SITEMAP_SLUG = 'homlity-search-sitemap.xml';

    public function register(): void
    {
        add_filter('wpseo_sitemap_post_types', [$this, 'addYoastPostType']);
        add_filter('wpseo_sitemap_taxonomies', [$this, 'addYoastTaxonomies']);
        add_filter('wpseo_sitemap_index', [$this, 'addYoastSearchSitemapIndex']);

        add_filter('rank_math/sitemaps/post_types', [$this, 'addRankMathPostType']);
        add_filter('rank_math/sitemaps/taxonomies', [$this, 'addRankMathTaxonomies']);
        add_filter('rank_math/sitemap/index', [$this, 'addRankMathSearchSitemapIndex']);
        add_filter('rank_math/json_ld', [$this, 'addRankMathPropertySchema'], 20, 2);

        add_action('init', [$this, 'registerSearchSitemapRewrite']);
        add_action('template_redirect', [$this, 'maybeRenderSearchSitemap']);
    }

    public function addYoastPostType(array $postTypes): array
    {
        if (!in_array(PropertyPostType::POST_TYPE, $postTypes, true)) {
            $postTypes[] = PropertyPostType::POST_TYPE;
        }
        return $postTypes;
    }

    public function addYoastTaxonomies(array $taxonomies): array
    {
        $toAdd = [
            PropertyTaxonomies::TAXONOMY_TYPE,
            PropertyTaxonomies::TAXONOMY_OPERATION,
            PropertyTaxonomies::TAXONOMY_LOCATION,
            PropertyTaxonomies::TAXONOMY_CATEGORY,
            PropertyTaxonomies::TAXONOMY_TAG,
            PropertyTaxonomies::TAXONOMY_FEATURE,
            PropertyTaxonomies::TAXONOMY_COUNTRY,
            PropertyTaxonomies::TAXONOMY_STATE,
            PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            PropertyTaxonomies::TAXONOMY_NEARBY,
        ];
        foreach ($toAdd as $tax) {
            if (!in_array($tax, $taxonomies, true)) {
                $taxonomies[] = $tax;
            }
        }
        return $taxonomies;
    }

    public function addRankMathPostType(array $postTypes): array
    {
        if (!in_array(PropertyPostType::POST_TYPE, $postTypes, true)) {
            $postTypes[] = PropertyPostType::POST_TYPE;
        }
        return $postTypes;
    }

    public function addRankMathTaxonomies(array $taxonomies): array
    {
        $toAdd = [
            PropertyTaxonomies::TAXONOMY_TYPE,
            PropertyTaxonomies::TAXONOMY_OPERATION,
            PropertyTaxonomies::TAXONOMY_LOCATION,
            PropertyTaxonomies::TAXONOMY_CATEGORY,
            PropertyTaxonomies::TAXONOMY_TAG,
            PropertyTaxonomies::TAXONOMY_FEATURE,
            PropertyTaxonomies::TAXONOMY_COUNTRY,
            PropertyTaxonomies::TAXONOMY_STATE,
            PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            PropertyTaxonomies::TAXONOMY_NEARBY,
        ];
        foreach ($toAdd as $tax) {
            if (!in_array($tax, $taxonomies, true)) {
                $taxonomies[] = $tax;
            }
        }
        return $taxonomies;
    }

    public function addYoastSearchSitemapIndex(string $xml): string
    {
        $loc = esc_url(home_url('/' . self::SEARCH_SITEMAP_SLUG));
        if ($loc === '' || strpos($xml, $loc) !== false) {
            return $xml;
        }

        $xml .= '<sitemap><loc>' . $loc . '</loc><lastmod>' . esc_html(gmdate('c')) . '</lastmod></sitemap>';
        return $xml;
    }

    public function addRankMathSearchSitemapIndex(string $xml): string
    {
        $loc = esc_url(home_url('/' . self::SEARCH_SITEMAP_SLUG));
        if ($loc === '' || strpos($xml, $loc) !== false) {
            return $xml;
        }

        $xml .= '<sitemap><loc>' . $loc . '</loc><lastmod>' . esc_html(gmdate('c')) . '</lastmod></sitemap>';
        return $xml;
    }

    public function registerSearchSitemapRewrite(): void
    {
        add_rewrite_rule('^' . preg_quote(self::SEARCH_SITEMAP_SLUG, '/') . '$', 'index.php?homlity_search_sitemap=1', 'top');
        add_filter('query_vars', static function (array $vars): array {
            if (!in_array('homlity_search_sitemap', $vars, true)) {
                $vars[] = 'homlity_search_sitemap';
            }
            return $vars;
        });
    }

    public function maybeRenderSearchSitemap(): void
    {
        if ((string) get_query_var('homlity_search_sitemap', '') !== '1') {
            return;
        }

        $urls = $this->buildSearchResultUrls();
        status_header(200);
        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            echo '<url><loc>' . esc_url($url) . '</loc><lastmod>' . esc_html(gmdate('c')) . '</lastmod></url>';
        }
        echo '</urlset>';
        exit;
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
        $post = get_post();
        if (!$post instanceof \WP_Post) {
            return $data;
        }

        $meta = (new PropertyPostType())->metaKeys();
        $price = (string) (get_post_meta($post->ID, $meta['price_sale'], true) ?: get_post_meta($post->ID, $meta['price_rent'], true));
        $currency = (string) (get_post_meta($post->ID, $meta['currency_sale'], true) ?: get_post_meta($post->ID, $meta['currency_rent'], true) ?: get_option('woocommerce_currency', 'USD'));
        $address = (string) get_post_meta($post->ID, $meta['address'], true);
        $lat = (string) get_post_meta($post->ID, $meta['latitude'], true);
        $lng = (string) get_post_meta($post->ID, $meta['longitude'], true);
        $image = get_the_post_thumbnail_url($post, 'full');

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
            ],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
            ],
        ];

        if ($lat !== '' && $lng !== '') {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $lat,
                'longitude' => $lng,
            ];
        }

        $data['homlity_property'] = $schema;
        return $data;
    }

    /**
     * @return string[]
     */
    private function buildSearchResultUrls(): array
    {
        $urls = [home_url('/inmuebles/')];
        $taxToPrefix = [
            PropertyTaxonomies::TAXONOMY_OPERATION => 'gestion',
            PropertyTaxonomies::TAXONOMY_TYPE => 'tipo',
            PropertyTaxonomies::TAXONOMY_CITY => 'ciudad',
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => 'barrios',
        ];

        foreach ($taxToPrefix as $taxonomy => $prefix) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => true,
                'number' => 200,
            ]);
            if (is_wp_error($terms) || !$terms) {
                continue;
            }
            foreach ($terms as $term) {
                $urls[] = home_url('/inmuebles/' . $prefix . '/' . rawurlencode((string) $term->slug) . '/');
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }
}
