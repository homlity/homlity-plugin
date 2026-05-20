<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
/**
 * Outputs basic structured data for property pages and SEO helpers.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class SeoService implements ServiceInterface
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'renderOpenGraphTags'], 4);
        add_action('wp_head', [$this, 'renderStructuredData'], 5);
    }

    public function renderOpenGraphTags(): void
    {
        if (is_admin()) {
            return;
        }

        if (is_singular(PropertyPostType::POST_TYPE)) {
            $this->renderPropertyOpenGraph();
            return;
        }

        $isArchiveSearch = is_post_type_archive(PropertyPostType::POST_TYPE)
            || ((string) get_query_var('homlity_property_archive', '') === '1');
        if ($isArchiveSearch) {
            $this->renderPropertyArchiveOpenGraph();
        }
    }

    public function renderStructuredData(): void
    {
        if (!is_singular(PropertyPostType::POST_TYPE)) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $meta = (new PropertyPostType())->metaKeys();
        $currencyService = new CurrencyService();
        $price = $this->primaryPrice($post->ID, $meta);
        $currency = $this->primaryCurrency($post->ID, $meta) ?: $currencyService->baseCurrency();
        $area = get_post_meta($post->ID, $meta['area'], true);
        $bedrooms = get_post_meta($post->ID, $meta['bedrooms'], true);
        $bathrooms = get_post_meta($post->ID, $meta['bathrooms'], true);
        $address = get_post_meta($post->ID, $meta['address'], true);
        $lat = get_post_meta($post->ID, $meta['latitude'], true);
        $lng = get_post_meta($post->ID, $meta['longitude'], true);
        $image = get_the_post_thumbnail_url($post, 'full');

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Residence',
            'name' => get_the_title($post),
            'description' => wp_strip_all_tags(get_the_excerpt($post) ?: get_the_content(null, false, $post)),
            'url' => get_permalink($post),
            'image' => $image ?: '',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
            ],
            'numberOfRooms' => $bedrooms ?: '',
            'numberOfBathroomsTotal' => $bathrooms ?: '',
            'floorSize' => [
                '@type' => 'QuantitativeValue',
                'value' => $area ?: '',
                'unitCode' => 'MTK',
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => $price ?: '',
                'priceCurrency' => $currency,
                'availability' => 'https://schema.org/InStock',
                'url' => get_permalink($post),
            ],
        ];

        if ($lat && $lng) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $lat,
                'longitude' => $lng,
            ];
        }

        /**
         * Allow other plugins/themes to adjust the schema.
         */
        $schema = \homlity_plugin_apply_filters('homlity_plugin_schema', null, $schema, $post);

        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }

    private function primaryPrice(int $postId, array $meta)
    {
        $order = ['price_sale', 'price_rent', 'price_admin'];
        foreach ($order as $key) {
            $value = get_post_meta($postId, $meta[$key], true);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private function primaryCurrency(int $postId, array $meta): string
    {
        $order = ['currency_sale', 'currency_rent', 'currency_admin'];
        foreach ($order as $key) {
            $value = get_post_meta($postId, $meta[$key], true);
            if ($value) {
                return $value;
            }
        }
        return '';
    }

    private function renderPropertyOpenGraph(): void
    {
        $post = get_post();
        if (!$post instanceof \WP_Post) {
            return;
        }

        $title = get_the_title($post);
        $description = wp_strip_all_tags(get_the_excerpt($post) ?: get_post_field('post_content', $post->ID));
        $url = get_permalink($post);
        $image = get_the_post_thumbnail_url($post, 'large') ?: get_site_icon_url(512);

        $this->printOg([
            'og:type' => 'product',
            'og:title' => $title,
            'og:description' => wp_trim_words($description, 35, '...'),
            'og:url' => $url,
            'og:image' => $image ?: '',
            'og:site_name' => get_bloginfo('name'),
        ]);
    }

    private function renderPropertyArchiveOpenGraph(): void
    {
        $titleParts = ['Inmuebles'];
        $gestion = sanitize_text_field((string) get_query_var('gestion', ''));
        $tipo = sanitize_text_field((string) get_query_var('tipo', ''));
        $ciudad = sanitize_text_field((string) get_query_var('ciudad', ''));
        $barrio = sanitize_text_field((string) get_query_var('barrios', ''));

        if ($gestion !== '') {
            $titleParts[] = 'Gestión ' . ucwords(str_replace('-', ' ', $gestion));
        }
        if ($tipo !== '') {
            $titleParts[] = ucwords(str_replace('-', ' ', $tipo));
        }
        if ($ciudad !== '') {
            $titleParts[] = ucwords(str_replace('-', ' ', $ciudad));
        }
        if ($barrio !== '') {
            $titleParts[] = ucwords(str_replace('-', ' ', $barrio));
        }

        $title = implode(' | ', $titleParts);
        $description = 'Resultados de búsqueda de inmuebles en ' . get_bloginfo('name') . '.';
        $image = get_site_icon_url(512) ?: '';
        $url = home_url(add_query_arg([], (string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH)));
        if ((string) ($_SERVER['REQUEST_URI'] ?? '') !== '') {
            $url = home_url((string) $_SERVER['REQUEST_URI']);
        }

        $this->printOg([
            'og:type' => 'website',
            'og:title' => $title,
            'og:description' => $description,
            'og:url' => $url,
            'og:image' => $image,
            'og:site_name' => get_bloginfo('name'),
        ]);
    }

    /**
     * @param array<string,string> $tags
     */
    private function printOg(array $tags): void
    {
        foreach ($tags as $property => $content) {
            if ($content === '') {
                continue;
            }
            echo '<meta property="' . esc_attr($property) . '" content="' . esc_attr($content) . "\" />\n";
        }
    }
}
