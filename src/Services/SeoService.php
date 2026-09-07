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
    /**
     * True once the active SEO plugin has emitted its own Open Graph block for
     * this request, so the plugin does not print a second, conflicting one.
     */
    private bool $seoPluginOwnsOpenGraph = false;

    public function register(): void
    {
        add_action('wp_head', [$this, 'renderOpenGraphTags'], 4);
        add_action('wp_head', [$this, 'renderStructuredData'], 5);

        // Los inmuebles sincronizados desde el CRM no tienen imagen destacada
        // en la biblioteca de medios: sus fotos son URLs externas guardadas en
        // post meta. Sin estos filtros el plugin SEO no encuentra imagen y cae
        // en la imagen por defecto del sitio, que es la que acaban mostrando
        // WhatsApp y las redes al compartir la ficha.
        add_filter('rank_math/opengraph/facebook/image', [$this, 'filterSeoPluginImage'], 20);
        add_filter('rank_math/opengraph/twitter/image', [$this, 'filterSeoPluginImage'], 20);
        add_filter('wpseo_opengraph_image', [$this, 'filterSeoPluginImage'], 20);
        add_filter('wpseo_twitter_image', [$this, 'filterSeoPluginImage'], 20);
    }

    /**
     * Feeds the property photo to Yoast/Rank Math when WordPress has no
     * featured image for it.
     *
     * @param mixed $image
     * @return mixed
     */
    public function filterSeoPluginImage($image)
    {
        // Estos filtros corren durante el head del plugin SEO (wp_head:1), antes
        // de que este servicio imprima nada: sirven además como señal de que las
        // etiquetas Open Graph ya tienen dueño en esta petición.
        $this->seoPluginOwnsOpenGraph = true;

        if (!empty($GLOBALS['homlity_property_recovery_context'])) {
            return $image;
        }
        if (!is_singular(PropertyPostType::POST_TYPE)) {
            return $image;
        }

        $post = get_post();
        if (!$post instanceof \WP_Post) {
            return $image;
        }

        // Una imagen elegida a mano en el plugin SEO manda sobre la galería.
        if ($this->hasSeoPluginImageOverride($post->ID)) {
            return $image;
        }

        $resolved = self::propertyImageUrl($post->ID);

        return $resolved !== '' ? $resolved : $image;
    }

    public function renderOpenGraphTags(): void
    {
        if (is_admin()) {
            return;
        }

        // Recovery pages must not output property OG tags — the property is not available.
        if (!empty($GLOBALS['homlity_property_recovery_context'])) {
            return;
        }

        // Yoast/Rank Math ya imprimieron su bloque Open Graph (con la imagen del
        // inmueble gracias a filterSeoPluginImage). Duplicar og:image y og:title
        // deja a los crawlers eligiendo entre dos juegos de etiquetas.
        if ($this->seoPluginOwnsOpenGraph) {
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
        // Recovery pages must not output InStock/Residence schema for a retired property.
        if (!empty($GLOBALS['homlity_property_recovery_context'])) {
            return;
        }

        // The dedicated Schema module emits the canonical RealEstateListing
        // graph. Avoid a second, conflicting Residence block on the same page.
        if (class_exists('\\Homlity_Schema_Manager')) {
            return;
        }

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
        $lat = get_post_meta($post->ID, $meta['latitude'], true);
        $lng = get_post_meta($post->ID, $meta['longitude'], true);
        $image = self::propertyImageUrl($post->ID);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Residence',
            'name' => get_the_title($post),
            'description' => wp_strip_all_tags(get_the_excerpt($post) ?: get_the_content(null, false, $post)),
            'url' => get_permalink($post),
            'image' => $image ?: '',
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

        if (is_numeric($lat) && is_numeric($lng)) {
            $precision = (int) apply_filters('homlity_schema_geo_precision', 2, $post->ID);
            $precision = max(0, min(6, $precision));
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => round((float) $lat, $precision),
                'longitude' => round((float) $lng, $precision),
            ];
        }

        /**
         * Allow other plugins/themes to adjust the schema.
         */
        $schema = \homlity_plugin_apply_filters('homlity_plugin_schema', null, $schema, $post);

        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }

    /**
     * Resolves the image that represents a property when shared or indexed.
     *
     * Only a fraction of the catalogue has a real WordPress featured image:
     * properties imported from a CRM keep their photos as external URLs, so
     * falling back to the gallery is what keeps the share preview from going
     * blank. [PropertyMedia] knows every shape that meta can take.
     */
    public static function propertyImageUrl(int $postId): string
    {
        return PropertyMedia::mainImageUrl($postId);
    }

    /**
     * Whether the editor picked a social image for this property inside the SEO
     * plugin; that choice must win over the gallery fallback.
     */
    private function hasSeoPluginImageOverride(int $postId): bool
    {
        foreach ([
            'rank_math_facebook_image_id',
            'rank_math_facebook_image',
            'rank_math_twitter_image_id',
            'rank_math_twitter_image',
            '_yoast_wpseo_opengraph-image-id',
            '_yoast_wpseo_opengraph-image',
            '_yoast_wpseo_twitter-image-id',
            '_yoast_wpseo_twitter-image',
        ] as $metaKey) {
            $value = get_post_meta($postId, $metaKey, true);
            if (!empty($value)) {
                return true;
            }
        }

        return false;
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
        $image = self::propertyImageUrl($post->ID) ?: get_site_icon_url(512);

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
