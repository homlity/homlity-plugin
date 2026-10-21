<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Evita indexar combinaciones, filtros y resultados vacíos sin valor SEO.
 *
 * @since 2.9.15
 */
class SearchRobotsService implements ServiceInterface
{
    // La instancia vive durante la petición y comparte la decisión entre cabecera y filtros.
    private ?bool $noindex = null;

    private const SEO_SEGMENTS = ['gestion', 'tipo', 'ciudad', 'barrios'];

    private const FILTER_KEYS = [
        'q', 's', 'categoria', 'property_category', 'gestion', 'operacion', 'property_operation',
        'tipo', 'property_type', 'etiquetas', 'property_tag', 'caracteristica', 'property_feature',
        'pais', 'property_country', 'departamento', 'property_state', 'ciudad', 'property_city',
        'localidades', 'property_locality', 'barrios', 'property_neighborhood', 'cercanias', 'property_nearby',
        'precio_min', 'price_min', 'precio_max', 'price_max', 'alcobas', 'habitaciones', 'bedrooms',
        'banos', 'bathrooms', 'garajes', 'parking', 'area_min', 'area_max', 'date_from', 'date_to',
        'orden', 'orderby', 'order', 'sort', 'pagina', 'paged', 'page',
    ];

    /** Registra las reglas de indexación y la cabecera HTTP. @since 2.9.15 */
    public function register(): void
    {
        add_filter('rank_math/robots', [$this, 'filterRankMathRobots'], 20);
        add_filter('wpseo_robots', [$this, 'filterYoastRobots'], 20);
        add_filter('wp_robots', [$this, 'filterCoreRobots'], 20);
        // En este punto la consulta principal ya está resuelta y aún no hay salida.
        add_action('template_redirect', [$this, 'sendRobotsHeader'], 1);
    }

    /** Conserva las demás directivas de Rank Math. @since 2.9.15 */
    public function filterRankMathRobots(array $robots): array
    {
        if (!$this->shouldNoindex()) {
            return $robots;
        }

        $robots['index'] = 'noindex';
        $robots['follow'] = 'follow';
        return $robots;
    }

    /** Conserva las demás directivas de Yoast. @since 2.9.15 */
    public function filterYoastRobots(string $robots): string
    {
        if (!$this->shouldNoindex()) {
            return $robots;
        }

        // Conservar directivas adicionales, como max-image-preview.
        $directives = array_filter(array_map('trim', explode(',', $robots)), static function (string $directive): bool {
            return !in_array(strtolower($directive), ['index', 'noindex', 'follow', 'nofollow', 'none', 'all'], true);
        });
        return implode(', ', array_merge(['noindex', 'follow'], array_values($directives)));
    }

    /** Usa el formato core cuando no hay un proveedor SEO activo. @since 2.9.15 */
    public function filterCoreRobots(array $robots): array
    {
        if (defined('RANK_MATH_VERSION') || class_exists('\\RankMath\\Helper') || defined('WPSEO_VERSION') || !$this->shouldNoindex()) {
            return $robots;
        }

        unset($robots['index'], $robots['nofollow']);
        $robots['noindex'] = true;
        $robots['follow'] = true;
        return $robots;
    }

    /** Publica la misma decisión en la respuesta HTTP. @since 2.9.15 */
    public function sendRobotsHeader(): void
    {
        if ($this->shouldNoindex() && !headers_sent()) {
            header('X-Robots-Tag: noindex, follow', true);
        }
    }

    private function shouldNoindex(): bool
    {
        return $this->noindex ??= $this->resolveNoindex();
    }

    private function resolveNoindex(): bool
    {
        if (is_admin() || is_singular(PropertyPostType::POST_TYPE)) {
            return false;
        }

        $segments = 0;
        foreach (self::SEO_SEGMENTS as $segment) {
            if (get_query_var($segment, '') !== '') {
                $segments++;
            }
        }

        $archivePageId = (int) get_option('homlity_plugin_archive_page_id', 0);
        $isArchivePage = $archivePageId > 0 && is_page($archivePageId);
        if (!is_post_type_archive(PropertyPostType::POST_TYPE) && !$isArchivePage && !get_query_var('homlity_property_archive') && $segments === 0) {
            return false;
        }

        $settings = SeoGeoSettingsService::getSettings();
        if (!empty($settings['seo_noindex_filtered'])) {
            foreach (self::FILTER_KEYS as $key) {
                if (isset($_GET[$key]) && $_GET[$key] !== '' && $_GET[$key] !== []) {
                    return true;
                }
            }
        }

        if (!empty($settings['seo_noindex_empty'])) {
            global $wp_query;
            $resultsQuery = $wp_query;
            if ($archivePageId > 0 && !is_post_type_archive(PropertyPostType::POST_TYPE)) {
                // La consulta principal contiene la página de Elementor, no sus inmuebles.
                // Reutilizar los parámetros del listado mantiene segmentos SEO y filtros GET.
                $search = new PropertySearchService();
                $params = array_merge($search->currentQueryParams(), ['per_page' => 1, 'page' => 1]);
                $args = $search->buildQueryArgs($params);
                $args['fields'] = 'ids';
                $args['no_found_rows'] = false;
                $resultsQuery = new \WP_Query($args);
            }
            if ($resultsQuery instanceof \WP_Query && (int) $resultsQuery->found_posts === 0) {
                return true;
            }
        }

        return $segments >= 2 && empty($settings['seo_index_search']);
    }
}
