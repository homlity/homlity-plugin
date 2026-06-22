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
    private const SEARCH_SITEMAP_SLUG   = 'homlity-search-sitemap.xml';
    private const PROPERTY_SITEMAP_SLUG = 'homlity-properties-sitemap';
    private const PROPERTY_SITEMAP_PER_PAGE = 1000;

    public function register(): void
    {
        // Yoast SEO
        add_filter('wpseo_sitemap_post_types', [$this, 'addYoastPostType']);
        add_filter('wpseo_sitemap_taxonomies', [$this, 'addYoastTaxonomies']);
        add_filter('wpseo_sitemap_index', [$this, 'addYoastSearchSitemapIndex']);
        add_filter('wpseo_sitemap_index', [$this, 'addYoastPropertySitemapIndex']);

        // Rank Math
        add_filter('rank_math/sitemaps/post_types', [$this, 'addRankMathPostType']);
        add_filter('rank_math/sitemaps/taxonomies', [$this, 'addRankMathTaxonomies']);
        add_filter('rank_math/sitemap/index', [$this, 'addRankMathSearchSitemapIndex']);
        add_filter('rank_math/sitemap/index', [$this, 'addRankMathPropertySitemapIndex']);
        add_filter('rank_math/json_ld', [$this, 'addRankMathPropertySchema'], 20, 2);

        // WordPress core sitemaps (WP 5.5+)
        add_filter('wp_sitemaps_post_types', [$this, 'addWpCoreSitemapPostType']);

        // Custom standalone sitemaps
        add_action('init', [$this, 'registerSearchSitemapRewrite']);
        add_action('init', [$this, 'registerPropertySitemapRewrite']);
        add_action('template_redirect', [$this, 'maybeRenderSearchSitemap']);
        add_action('template_redirect', [$this, 'maybeRenderPropertySitemap']);
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

    // ── Property sitemap index entries ───────────────────────────────────────

    public function addYoastPropertySitemapIndex(string $xml): string
    {
        return $this->appendPropertySitemapEntries($xml);
    }

    public function addRankMathPropertySitemapIndex(string $xml): string
    {
        return $this->appendPropertySitemapEntries($xml);
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

    private function appendPropertySitemapEntries(string $xml): string
    {
        $total = (int) wp_count_posts(PropertyPostType::POST_TYPE)->publish;
        if ($total === 0) {
            return $xml;
        }

        $pages    = (int) ceil($total / self::PROPERTY_SITEMAP_PER_PAGE);
        $lastmod  = esc_html(gmdate('c'));
        $baseSlug = self::PROPERTY_SITEMAP_SLUG;

        for ($page = 1; $page <= $pages; $page++) {
            $slug = $page === 1 ? $baseSlug . '.xml' : $baseSlug . '-' . $page . '.xml';
            $loc  = esc_url(home_url('/' . $slug));
            if ($loc === '' || strpos($xml, $loc) !== false) {
                continue;
            }
            $xml .= '<sitemap><loc>' . $loc . '</loc><lastmod>' . $lastmod . '</lastmod></sitemap>';
        }

        return $xml;
    }

    // ── Property sitemap rewrite & render ────────────────────────────────────

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

        add_filter('query_vars', static function (array $vars): array {
            foreach (['homlity_properties_sitemap', 'homlity_sitemap_paged'] as $var) {
                if (!in_array($var, $vars, true)) {
                    $vars[] = $var;
                }
            }
            return $vars;
        });
    }

    public function maybeRenderPropertySitemap(): void
    {
        if ((string) get_query_var('homlity_properties_sitemap', '') !== '1') {
            return;
        }

        $page    = max(1, (int) (get_query_var('homlity_sitemap_paged', 0) ?: 1));
        $perPage = self::PROPERTY_SITEMAP_PER_PAGE;

        $query = new \WP_Query([
            'post_type'      => PropertyPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);

        status_header(200);
        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        while ($query->have_posts()) {
            $query->the_post();
            echo '<url>'
                . '<loc>' . esc_url((string) get_permalink()) . '</loc>'
                . '<lastmod>' . esc_html((string) get_the_modified_date('c')) . '</lastmod>'
                . '<changefreq>weekly</changefreq>'
                . '<priority>0.8</priority>'
                . '</url>';
        }
        wp_reset_postdata();

        echo '</urlset>';
        exit;
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
     * Builds all search result URLs that have at least one published property.
     * Covers individual terms plus 2-way and 3-way combinations of
     * gestión × tipo × ciudad, using direct SQL to avoid N+1 WP_Query calls.
     *
     * @return string[]
     */
    private function buildSearchResultUrls(): array
    {
        global $wpdb;

        $urls     = [home_url('/inmuebles/')];
        $pt       = PropertyPostType::POST_TYPE;
        $taxOp    = PropertyTaxonomies::TAXONOMY_OPERATION;
        $taxType  = PropertyTaxonomies::TAXONOMY_TYPE;
        $taxCity  = PropertyTaxonomies::TAXONOMY_CITY;
        $taxNhood = PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD;

        // Inline subquery: IDs of all published properties.
        $pubSub = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $pt
        );

        // ── 1. Individual term URLs ───────────────────────────────────────────
        foreach ([
            $taxOp    => 'gestion',
            $taxType  => 'tipo',
            $taxCity  => 'ciudad',
            $taxNhood => 'barrios',
        ] as $taxonomy => $prefix) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $slugs = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT t.slug
                     FROM {$wpdb->terms} t
                     INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = %s
                     INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                     WHERE tr.object_id IN ($pubSub)
                     ORDER BY t.slug
                     LIMIT 500",
                    $taxonomy
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            foreach ((array) $slugs as $slug) {
                $urls[] = home_url('/inmuebles/' . $prefix . '/' . rawurlencode((string) $slug) . '/');
            }
        }

        // ── 2. Two-way combinations ───────────────────────────────────────────
        // Pattern: /inmuebles/{prefA}/{slugA}/{prefB}/{slugB}/
        // Order must match rewrite rule: gestion → tipo → ciudad → barrios.
        $twoPairs = [
            [$taxOp, 'gestion', $taxType,  'tipo'],
            [$taxOp, 'gestion', $taxCity,  'ciudad'],
            [$taxType, 'tipo',  $taxCity,  'ciudad'],
        ];

        foreach ($twoPairs as [$taxA, $prefA, $taxB, $prefB]) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT tA.slug AS slug_a, tB.slug AS slug_b
                     FROM {$wpdb->term_relationships} trA
                     INNER JOIN {$wpdb->term_relationships} trB ON trA.object_id = trB.object_id
                     INNER JOIN {$wpdb->term_taxonomy} ttA ON trA.term_taxonomy_id = ttA.term_taxonomy_id AND ttA.taxonomy = %s
                     INNER JOIN {$wpdb->term_taxonomy} ttB ON trB.term_taxonomy_id = ttB.term_taxonomy_id AND ttB.taxonomy = %s
                     INNER JOIN {$wpdb->terms} tA ON ttA.term_id = tA.term_id
                     INNER JOIN {$wpdb->terms} tB ON ttB.term_id = tB.term_id
                     WHERE trA.object_id IN ($pubSub)
                     ORDER BY tA.slug, tB.slug
                     LIMIT 2000",
                    $taxA, $taxB
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            foreach ((array) $rows as $row) {
                $urls[] = home_url(
                    '/inmuebles/' . $prefA . '/' . rawurlencode((string) $row->slug_a)
                    . '/' . $prefB . '/' . rawurlencode((string) $row->slug_b) . '/'
                );
            }
        }

        // ── 3. Three-way combination: gestion + tipo + ciudad ─────────────────
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT tOp.slug AS g, tType.slug AS t, tCity.slug AS c
                 FROM {$wpdb->term_relationships} trOp
                 INNER JOIN {$wpdb->term_relationships} trType ON trOp.object_id = trType.object_id
                 INNER JOIN {$wpdb->term_relationships} trCity ON trOp.object_id = trCity.object_id
                 INNER JOIN {$wpdb->term_taxonomy} ttOp   ON trOp.term_taxonomy_id   = ttOp.term_taxonomy_id   AND ttOp.taxonomy   = %s
                 INNER JOIN {$wpdb->term_taxonomy} ttType ON trType.term_taxonomy_id = ttType.term_taxonomy_id AND ttType.taxonomy = %s
                 INNER JOIN {$wpdb->term_taxonomy} ttCity ON trCity.term_taxonomy_id = ttCity.term_taxonomy_id AND ttCity.taxonomy = %s
                 INNER JOIN {$wpdb->terms} tOp   ON ttOp.term_id   = tOp.term_id
                 INNER JOIN {$wpdb->terms} tType ON ttType.term_id = tType.term_id
                 INNER JOIN {$wpdb->terms} tCity ON ttCity.term_id = tCity.term_id
                 WHERE trOp.object_id IN ($pubSub)
                 ORDER BY tOp.slug, tType.slug, tCity.slug
                 LIMIT 5000",
                $taxOp, $taxType, $taxCity
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        foreach ((array) $rows as $row) {
            $urls[] = home_url(
                '/inmuebles/gestion/' . rawurlencode((string) $row->g)
                . '/tipo/' . rawurlencode((string) $row->t)
                . '/ciudad/' . rawurlencode((string) $row->c) . '/'
            );
        }

        return array_values(array_unique(array_filter($urls)));
    }
}
