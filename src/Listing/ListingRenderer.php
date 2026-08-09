<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
/**
 * Central renderer for property listings.
 *
 * All page-builder integrations (Elementor, WPBakery, Divi, shortcode) converge
 * here. They each produce a ListingConfig and call ListingRenderer::render().
 * The renderer is completely unaware of which builder triggered it.
 */

namespace Homlity\PluginInmobiliario\Listing;

use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\RelatedPropertiesQueryBuilder;
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
        // ── Related-properties mode: fully independent query path ─────────────
        if ($config->queryMode() === 'related_current') {
            self::enqueueAssets();
            $this->renderRelated($config);
            return;
        }

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
            'localidades',
            'property_locality',
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
                if ($key === 'page') {
                    $params['page'] = $value;
                    continue;
                }

                if ($key === 'search') {
                    $params['search'] = $value;
                    continue;
                }

                if (in_array($key, ['price_min', 'price_max', 'bedrooms', 'bathrooms', 'parking', 'area_min', 'area_max'], true)) {
                    $params[$key] = $value;
                    continue;
                }

                if (in_array($key, ['category', 'operation', 'type', 'tag', 'feature', 'country', 'state', 'city', 'locality', 'neighborhood', 'nearby'], true)) {
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

        // Sorting and pagination changed by the AJAX listing are mirrored in
        // the URL so the browser can rebuild the same result after returning
        // from a property detail page.
        if (isset($_GET['orden']) && $_GET['orden'] !== '') {
            $requestedOrder = sanitize_key(wp_unslash((string) $_GET['orden']));
            if (in_array($requestedOrder, ['date', 'price_asc', 'price_desc', 'title'], true)) {
                $params['orderby'] = $requestedOrder;
            }
        }

        if (isset($_GET['pagina']) && $_GET['pagina'] !== '') {
            $params['page'] = max(1, absint($_GET['pagina']));
        }

        if (empty($params['page'])) {
            $params['page'] = max(
                1,
                (int) (
                    get_query_var('paged')
                    ?: get_query_var('page')
                    ?: ($_GET['paged'] ?? 0)
                    ?: ($_GET['page'] ?? 0)
                    ?: 1
                )
            );
        }

        if (
            !empty($params['use_current_property_tags'])
            && is_singular(PropertyPostType::POST_TYPE)
            && empty($params['preset_tag'])
            && empty($params['preset_tag_ids'])
        ) {
            $currentPropertyId = (int) get_queried_object_id();
            if ($currentPropertyId > 0) {
                $relatedTagIds = wp_get_post_terms($currentPropertyId, PropertyTaxonomies::TAXONOMY_TAG, ['fields' => 'ids']);
                if (is_array($relatedTagIds)) {
                    $relatedTagIds = array_values(array_filter(array_map('absint', $relatedTagIds)));
                    if ($relatedTagIds) {
                        $params['preset_tag_ids'] = $relatedTagIds;
                    }
                }
            }
        }

        // En el archivo principal (/inmuebles/) sin filtros explícitos en URL,
        // ignorar presets heredados del widget para no vaciar el listado por
        // configuraciones antiguas o términos eliminados.
        $isPropertyArchive = is_post_type_archive('property')
            || ((string) get_query_var('homlity_property_archive', '') === '1');
        if ($isPropertyArchive && !$hasRequestFilters) {
            $params['search'] = '';
            $params['preset_category'] = 0;
            $params['preset_operation'] = 0;
            $params['preset_type'] = 0;
            $params['preset_tag'] = 0;
            $params['preset_tag_ids'] = [];
            $params['preset_feature'] = 0;
            $params['preset_country'] = 0;
            $params['preset_state'] = 0;
            $params['preset_city'] = 0;
            $params['preset_locality'] = 0;
            $params['preset_neighborhood'] = 0;
            $params['preset_nearby'] = 0;
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
     * Render the related-properties listing.
     * Called exclusively when query_mode === 'related_current'.
     */
    private function renderRelated(ListingConfig $config): void
    {
        $propertyId = $this->resolveRelatedPropertyId($config);

        if ($propertyId <= 0) {
            // No property context. In the Elementor editor, show a placeholder.
            if (
                \defined('ELEMENTOR_VERSION')
                && \class_exists('\Elementor\Plugin')
                && \Elementor\Plugin::$instance->editor->is_edit_mode()
            ) {
                echo '<p class="homlity-related-placeholder" style="padding:16px;background:#fff3cd;border-radius:4px;">'
                    . esc_html__('Vista previa: no hay inmueble activo. Selecciona un inmueble de referencia en las opciones del widget.', 'homlity-real-estate')
                    . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            return;
        }

        $builder = new RelatedPropertiesQueryBuilder();
        $result  = $builder->build(
            $propertyId,
            $config->postsPerPage(),
            $config->relatedTaxonomies(),
            $config->relatedStrategy(),
            $config->relatedFallback(),
            true // always exclude the source property
        );

        // hide / empty: do not run a WP_Query at all
        if ($result['fallback_type'] === RelatedPropertiesQueryBuilder::FALLBACK_HIDE) {
            return;
        }

        if ($result['fallback_type'] === RelatedPropertiesQueryBuilder::FALLBACK_EMPTY) {
            $msg = $config->relatedEmptyMessage();
            if ($msg !== '') {
                echo '<p class="homlity-related-empty">' . esc_html($msg) . '</p>';
            }
            return;
        }

        $query = new \WP_Query($result['args']);

        TemplateService::includeComponent($config->listingTemplate(), [
            'config' => $config,
            'query'  => $query,
            'search' => $this->search,
            'params' => [], // no active URL filter params for related mode
        ]);

        wp_reset_postdata();
    }

    /**
     * Resolve the source property ID for the related-properties query.
     *
     * Priority:
     *   1. Explicit override from widget settings (editor preview).
     *   2. Queried object when it is a published property post.
     *   3. Loop context (get_the_ID()).
     *   4. Global $post.
     */
    private function resolveRelatedPropertyId(ListingConfig $config): int
    {
        // 1. Explicit override (set in the widget for editor preview)
        if ($config->relatedPropertyId() > 0) {
            return $config->relatedPropertyId();
        }

        // 2. Queried object on a single property page
        $queriedId = (int) get_queried_object_id();
        if ($queriedId > 0 && get_post_type($queriedId) === PropertyPostType::POST_TYPE) {
            return $queriedId;
        }

        // 3. Inside a WordPress loop (e.g., single template)
        $loopId = (int) get_the_ID();
        if ($loopId > 0 && get_post_type($loopId) === PropertyPostType::POST_TYPE) {
            return $loopId;
        }

        // 4. Global $post as last resort
        global $post;
        if (
            isset($post)
            && $post instanceof \WP_Post
            && get_post_type($post->ID) === PropertyPostType::POST_TYPE
        ) {
            return (int) $post->ID;
        }

        return 0;
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
            'homlity-real-estate-front-components',
            HOMLITY_PLUGIN_URL . 'assets/css/front-components.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );

        wp_enqueue_style(
            'homlity-real-estate-listing',
            HOMLITY_PLUGIN_URL . 'assets/css/property-listing.css',
            ['homlity-real-estate-front-components'],
            HOMLITY_PLUGIN_VERSION
        );

        wp_enqueue_style(
            'homlity-real-estate-swiper',
            HOMLITY_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.css',
            [],
            '11.1.4'
        );

        wp_enqueue_style(
            'homlity-real-estate-leaflet',
            HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.min.css',
            [],
            '1.9.4'
        );

        wp_enqueue_script(
            'homlity-real-estate-leaflet',
            HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.min.js',
            [],
            '1.9.4',
            true
        );

        wp_enqueue_script(
            'homlity-real-estate-swiper',
            HOMLITY_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.js',
            [],
            '11.1.4',
            true
        );

        wp_enqueue_script(
            'homlity-real-estate-listing',
            HOMLITY_PLUGIN_URL . 'assets/js/property-listing.js',
            ['homlity-real-estate-leaflet', 'homlity-real-estate-swiper'],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        wp_enqueue_script(
            'homlity-real-estate-contact-tracking',
            HOMLITY_PLUGIN_URL . 'assets/js/property-contact-tracking.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );
        wp_localize_script('homlity-real-estate-contact-tracking', 'homlityContactTracking', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('homlity_contact_click_nonce'),
        ]);

        wp_localize_script('homlity-real-estate-listing', 'homlityListingI18n', [
            'noResults' => __('No se han encontrado inmuebles para esta consulta.', 'homlity-real-estate'),
            'loading'   => __('Cargando...', 'homlity-real-estate'),
            'paginationLabel' => __('Paginación de inmuebles', 'homlity-real-estate'),
            'first'     => __('Inicio', 'homlity-real-estate'),
            'firstAria' => __('Ir al inicio', 'homlity-real-estate'),
            'previous'  => __('Anterior', 'homlity-real-estate'),
            'previousAria' => __('Página anterior', 'homlity-real-estate'),
            'next'      => __('Siguiente', 'homlity-real-estate'),
            'nextAria'  => __('Página siguiente', 'homlity-real-estate'),
            'last'      => __('Final', 'homlity-real-estate'),
            'lastAria'  => __('Ir al final', 'homlity-real-estate'),
            'pageAria'  => __('Página %d', 'homlity-real-estate'),
        ]);
    }
}
