<?php
/**
 * Outputs basic structured data for property pages and SEO helpers.
 */

namespace Codwelt\PluginInmobiliario\Services;

use Codwelt\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class SeoService implements ServiceInterface
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'renderStructuredData'], 5);
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
        $schema = apply_filters('plugin_inmobiliario_schema', $schema, $post);

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
}
