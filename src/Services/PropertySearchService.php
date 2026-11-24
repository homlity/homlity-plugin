<?php
// Los nombres de hook de este archivo salen de constantes de Homlity\Developer\Support\Hooks
// (o los recibe el método como argumento ya prefijado). Todas valen 'homlity/...',
// pero el sniff sólo sabe leer literales y las marca como dinámicas.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_tax_query
/**
 * Builds WP_Query args from filter params and extracts map data from query results.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\Developer\Support\Hooks;
use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Search\PropertyIndexSchema;

if (!defined('ABSPATH')) {
    exit;
}

class PropertySearchService implements ServiceInterface
{
    /** @var array<string,int> Totales de búsquedas ya ejecutadas en esta petición. */
    private static array $queryTotals = [];

    /**
     * Query var que lleva el sentido del orden cuando se ordena por precio.
     *
     * El precio por el que ordena cada inmueble no se puede decidir al armar
     * los argumentos: hay que mirarlo fila a fila en SQL. Ver applyPriceOrder().
     */
    public const PRICE_ORDER_QUERY_VAR = 'homlity_price_order';

    /**
     * Criterio de desempate, común a todos los órdenes.
     *
     * Sin él, «más nuevo a más viejo» sale desordenado: la sincronización crea
     * los inmuebles por lotes y decenas comparten el mismo segundo en
     * post_date, así que dentro de cada grupo MySQL los devuelve como quiera.
     * Y como el reparto de empates entre páginas tampoco está garantizado, el
     * visitante puede ver una ficha repetida y perderse otra al paginar.
     *
     * Se desempata por ID descendente: el último inmueble que entró va primero,
     * que es lo que espera quien pide «más recientes».
     */
    private const TIE_BREAK_ORDERBY = ['ID' => 'DESC'];

    public function register(): void
    {
        // El orden por precio va antes que el ranking de búsqueda: así, cuando
        // hay palabra clave, applyPriorityKeywordSearch() antepone su MIN(...)
        // y la relevancia manda sobre el precio, no al revés.
        add_filter('posts_clauses', [$this, 'applyPriceOrder'], 10, 2);
        add_filter('posts_clauses', [$this, 'applyPriorityKeywordSearch'], 20, 2);
        add_action('template_redirect', [$this, 'maybeRedirectExactCodeSearch'], 1);
    }

    public function maybeRedirectExactCodeSearch(): void
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (is_singular(PropertyPostType::POST_TYPE)) {
            return;
        }

        $code = $this->extractRequestedPropertyCode();
        if ($code === '') {
            return;
        }

        $postId = $this->findPropertyByExactCode($code);
        $syncResult = null;

        // q/s siguen resolviendo códigos locales, pero nunca deben bloquear la
        // búsqueda con una llamada remota: también parecen códigos años y
        // números corrientes. La sincronización queda reservada a los
        // parámetros explícitos de código.
        $explicitCode = $this->hasExplicitPropertyCodeRequest();
        $missKey = 'homlity_code_miss_' . md5($code);
        if ($postId <= 0 && $explicitCode && $this->looksLikePropertyCode($code) && get_transient($missKey) === false) {
            $syncResult = SyncRegistry::syncByCodeDetailed($code);
            $postId = (int) ($syncResult['post_id'] ?? 0);
            if ($postId <= 0) {
                set_transient($missKey, 1, HOUR_IN_SECONDS);
            }
        }

        if ($postId <= 0) {
            return;
        }

        $permalink = get_permalink($postId);
        if (!$permalink) {
            return;
        }

        wp_safe_redirect($permalink, 302);
        exit;
    }

    public function buildQueryArgs(array $params): array
    {
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 12)));
        $page    = max(1, (int) ($params['page'] ?? 1));
        $orderby = sanitize_key($params['orderby'] ?? 'date');
        $order   = strtoupper($params['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $args = [
            'post_type'      => PropertyPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'tax_query'      => ['relation' => 'AND'],
            'meta_query'     => ['relation' => 'AND'],
        ];

        // Mostrar únicamente inmuebles activos/disponibles.
        // Si el meta no existe (carga manual o integraciones antiguas), se permite.
        $args['meta_query']['homlity_status'] = [
            'relation' => 'OR',
            [
                'key' => '_property_status',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_property_status',
                'value' => 'active',
                'compare' => '=',
            ],
        ];
        $args['meta_query']['homlity_available'] = [
            'relation' => 'OR',
            [
                'key' => '_property_available',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_property_available',
                'value' => ['1', 'true', 'yes', 'active'],
                'compare' => 'IN',
            ],
        ];

        $taxMap = [
            'category'     => PropertyTaxonomies::TAXONOMY_CATEGORY,
            'operation'    => PropertyTaxonomies::TAXONOMY_OPERATION,
            'type'         => PropertyTaxonomies::TAXONOMY_TYPE,
            'tag'          => PropertyTaxonomies::TAXONOMY_TAG,
            'feature'      => PropertyTaxonomies::TAXONOMY_FEATURE,
            'country'      => PropertyTaxonomies::TAXONOMY_COUNTRY,
            'city'         => PropertyTaxonomies::TAXONOMY_CITY,
            'state'        => PropertyTaxonomies::TAXONOMY_STATE,
            'neighborhood' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            'nearby'       => PropertyTaxonomies::TAXONOMY_NEARBY,
        ];

        if (($params['query_mode'] ?? '') === 'current') {
            $params = array_merge($params, $this->currentQueryParams());
        }

        // Read after the `current` merge: otherwise a keyword coming from the
        // URL would be consumed before it was collected, and every other filter
        // of that same request would apply except the free-text one.
        if (!empty($params['search'])) {
            $args['homlity_keyword_search'] = sanitize_text_field((string) $params['search']);
        }

        // User filter terms
        foreach ($taxMap as $key => $taxonomy) {
            $termIds = $this->filterExistingTermIds(
                $this->extractTermIds($params[$key] ?? null),
                $taxonomy
            );
            if ($termIds) {
                if ($taxonomy === PropertyTaxonomies::TAXONOMY_OPERATION) {
                    $termIds = PropertyTaxonomies::expandOperationTermIds($termIds);
                }
                $args['tax_query'][] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $termIds,
                ];
            }
        }

        // Widget preset terms override user filters
        foreach ($taxMap as $key => $taxonomy) {
            $presetKey = 'preset_' . $key;
            $presetId = absint($params[$presetKey] ?? 0);
            if ($presetId && term_exists($presetId, $taxonomy)) {
                // Remove any user-supplied filter for the same taxonomy
                foreach ($args['tax_query'] as $i => $clause) {
                    if (is_array($clause) && ($clause['taxonomy'] ?? '') === $taxonomy) {
                        unset($args['tax_query'][$i]);
                    }
                }
                $args['tax_query'][] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $taxonomy === PropertyTaxonomies::TAXONOMY_OPERATION
                        ? PropertyTaxonomies::expandOperationTermIds([$presetId])
                        : [$presetId],
                ];
            }
        }

        // Locality is an optional bridge (city -> locality -> neighborhoods),
        // not a taxonomy assigned directly to properties. Translate it to the
        // neighborhood terms that are actually attached to each property.
        $localityRaw = !empty($params['preset_locality'])
            ? absint($params['preset_locality'])
            : ($params['locality'] ?? null);
        if ($localityRaw !== null && $localityRaw !== '' && $localityRaw !== []) {
            $localityIds = LocalityPostType::resolvePublishedIds($localityRaw);
            $neighborhoodIds = [];
            foreach ($localityIds as $localityId) {
                $neighborhoodIds = array_merge($neighborhoodIds, LocalityPostType::neighborhoodIds($localityId));
            }
            $neighborhoodIds = array_values(array_unique(array_filter(array_map('absint', $neighborhoodIds))));

            if ($localityIds === [] || $neighborhoodIds === []) {
                $args['post__in'] = [0];
            } else {
                $args['tax_query'][] = [
                    'taxonomy' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
                    'field' => 'term_id',
                    'terms' => $neighborhoodIds,
                    'operator' => 'IN',
                ];
            }
        }

        // Multi-tag preset from widgets/builders.
        $presetTagIds = array_values(array_filter(array_map('absint', (array) ($params['preset_tag_ids'] ?? []))));
        if (!empty($presetTagIds)) {
            $presetTagIds = $this->filterExistingTermIds($presetTagIds, PropertyTaxonomies::TAXONOMY_TAG);
            if (!empty($presetTagIds)) {
                foreach ($args['tax_query'] as $i => $clause) {
                    if (is_array($clause) && ($clause['taxonomy'] ?? '') === PropertyTaxonomies::TAXONOMY_TAG) {
                        unset($args['tax_query'][$i]);
                    }
                }
                $args['tax_query'][] = [
                    'taxonomy' => PropertyTaxonomies::TAXONOMY_TAG,
                    'field'    => 'term_id',
                    'terms'    => $presetTagIds,
                    'operator' => 'IN',
                ];
            }
        }

        // Advisor (asesor) filter
        $agentId = absint($params['preset_agent'] ?? 0);
        if ($agentId > 0) {
            $args['meta_query']['homlity_agent'] = [
                'key'     => '_property_agent_id',
                'value'   => $agentId,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        // Featured only
        if (!empty($params['featured'])) {
            $args['meta_query']['homlity_featured'] = [
                'key'     => '_property_featured',
                'value'   => '1',
                'compare' => '=',
            ];
        }

        // Price range
        $priceMin = absint($params['price_min'] ?? 0);
        $priceMax = absint($params['price_max'] ?? 0);
        if ($priceMin || $priceMax) {
            $priceMeta = $this->resolvePriceMetaKey($params);
            $priceQuery = ['key' => $priceMeta, 'type' => 'NUMERIC'];
            if ($priceMin && $priceMax) {
                $priceQuery['compare'] = 'BETWEEN';
                $priceQuery['value']   = [$priceMin, $priceMax];
            } elseif ($priceMin) {
                $priceQuery['compare'] = '>=';
                $priceQuery['value']   = $priceMin;
            } else {
                $priceQuery['compare'] = '<=';
                $priceQuery['value']   = $priceMax;
            }
            $args['meta_query']['homlity_price'] = $priceQuery;
        }

        // Bedrooms / bathrooms (minimum)
        $bedrooms = absint($params['bedrooms'] ?? 0);
        if ($bedrooms) {
            $args['meta_query']['homlity_bedrooms'] = [
                'key'     => '_property_bedrooms',
                'value'   => $bedrooms,
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ];
        }

        $bathrooms = absint($params['bathrooms'] ?? 0);
        if ($bathrooms) {
            $args['meta_query']['homlity_bathrooms'] = [
                'key'     => '_property_bathrooms',
                'value'   => $bathrooms,
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ];
        }

        $parking = absint($params['parking'] ?? 0);
        if ($parking) {
            $args['meta_query']['homlity_parking'] = [
                'key'     => '_property_parking',
                'value'   => $parking,
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ];
        }

        $areaMin = absint($params['area_min'] ?? 0);
        $areaMax = absint($params['area_max'] ?? 0);
        if ($areaMin || $areaMax) {
            $areaQuery = ['key' => '_property_area', 'type' => 'NUMERIC'];
            if ($areaMin && $areaMax) {
                $areaQuery['compare'] = 'BETWEEN';
                $areaQuery['value'] = [$areaMin, $areaMax];
            } elseif ($areaMin) {
                $areaQuery['compare'] = '>=';
                $areaQuery['value'] = $areaMin;
            } else {
                $areaQuery['compare'] = '<=';
                $areaQuery['value'] = $areaMax;
            }
            $args['meta_query']['homlity_area'] = $areaQuery;
        }

        $lat = is_numeric($params['geo_latitude'] ?? null) ? (float) $params['geo_latitude'] : null;
        $lng = is_numeric($params['geo_longitude'] ?? null) ? (float) $params['geo_longitude'] : null;
        $radiusKm = max(0, (float) ($params['geo_radius_km'] ?? 0));
        if ($lat !== null && $lng !== null && $radiusKm > 0) {
            $latDelta = $radiusKm / 111.045;
            $lngDelta = $radiusKm / max(1, 111.045 * cos(deg2rad($lat)));
            $args['meta_query']['homlity_lat'] = [
                'key' => '_property_latitude',
                'value' => [$lat - $latDelta, $lat + $latDelta],
                'type' => 'DECIMAL(10,6)',
                'compare' => 'BETWEEN',
            ];
            $args['meta_query']['homlity_lng'] = [
                'key' => '_property_longitude',
                'value' => [$lng - $lngDelta, $lng + $lngDelta],
                'type' => 'DECIMAL(10,6)',
                'compare' => 'BETWEEN',
            ];
        }

        // Orderby
        switch ($orderby) {
            case 'price_asc':
            case 'price_desc':
                // El precio por el que ordena cada inmueble se resuelve fila a
                // fila en applyPriceOrder(), no aquí. Fijar una sola clave para
                // todo el listado —lo que se hacía antes— dejaba a los
                // arriendos ordenando por _property_price_sale, que en ellos
                // vale 0: empataban todos y salían en el orden interno de
                // MySQL. Eso es lo que se veía como «de mayor a menor sale
                // desordenado» en cuanto la lista mezclaba venta y arriendo.
                $args[self::PRICE_ORDER_QUERY_VAR] = $orderby === 'price_asc' ? 'ASC' : 'DESC';

                // Base válida para que WP_Query genere un ORDER BY bien formado
                // que el filtro de abajo sustituye entero.
                $args['orderby'] = ['date' => 'DESC'] + self::TIE_BREAK_ORDERBY;
                $args['order']   = 'DESC';
                break;
            case 'title':
                // El desplegable ofrece «Nombre A–Z» y nunca manda dirección,
                // así que aquí llegaba el DESC por defecto y el listado salía
                // Z–A. Sin dirección explícita, alfabético de verdad.
                // El ID desempata; ver TIE_BREAK_ORDERBY.
                $titleOrder = strtoupper((string) ($params['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

                $args['orderby'] = ['title' => $titleOrder] + self::TIE_BREAK_ORDERBY;
                $args['order']   = $titleOrder;
                break;
            default:
                $args['orderby'] = ['date' => 'DESC'] + self::TIE_BREAK_ORDERBY;
                $args['order']   = 'DESC';
        }

        $indexFilters = [
            'price_column' => $this->resolvePriceMetaKey($params),
            'price_min' => $priceMin,
            'price_max' => $priceMax,
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'parking' => $parking,
            'area_min' => $areaMin,
            'area_max' => $areaMax,
            'featured' => !empty($params['featured']),
            'agent_id' => $agentId,
            'orderby' => $orderby,
            'order' => $order,
            'keyword' => isset($args['homlity_keyword_search']) ? (string) $args['homlity_keyword_search'] : '',
        ];
        if (isset($latDelta, $lngDelta, $lat, $lng)) {
            $indexFilters['lat'] = [$lat - $latDelta, $lat + $latDelta];
            $indexFilters['lng'] = [$lng - $lngDelta, $lng + $lngDelta];
        }
        $args['homlity_index_filters'] = $indexFilters;

        /**
         * Filters the `WP_Query` arguments used to list and search properties.
         *
         * Applies to the listing shortcode, the page-builder widgets and the
         * listing AJAX endpoint — every place the plugin queries properties on
         * behalf of a visitor. Two clauses are already in `meta_query` and are
         * what keeps withdrawn properties off the site; removing them exposes
         * inventory the agency took off the market.
         *
         * A return value that is not an array is ignored.
         *
         * @since 2.8.0
         *
         * @param array<string,mixed> $args   Query arguments, ready for `WP_Query`.
         * @param array<string,mixed> $params Normalized request parameters that produced them.
         */
        $filtered = apply_filters(Hooks::FILTER_PROPERTY_QUERY_ARGS, $args, $params);

        return is_array($filtered) ? $filtered : $args;
    }

    /**
     * Ordena por el precio que imprime cada ficha, resuelto fila a fila.
     *
     * Antes se elegía una única clave de precio para todo el listado a partir
     * del filtro de gestión. En una lista mixta —o sin filtro, que es el caso
     * por defecto del widget «Listado de inmuebles»— los arriendos se ordenaban
     * por `_property_price_sale`, que en ellos vale 0: empataban todos y MySQL
     * los devolvía en el orden que quisiera. El visitante veía una columna de
     * precios impresos sin ningún orden.
     */
    public function applyPriceOrder(array $clauses, \WP_Query $query): array
    {
        if (\Homlity\PluginInmobiliario\Search\PropertyIndexQuery::isActive($query)) {
            return $clauses;
        }
        $direction = strtoupper((string) $query->get(self::PRICE_ORDER_QUERY_VAR));
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            return $clauses;
        }

        if ($query->get('post_type') !== PropertyPostType::POST_TYPE) {
            return $clauses;
        }

        global $wpdb;

        // Si los JOIN ya están puestos, repetirlos deja la consulta con dos
        // alias iguales y MySQL la rechaza entera («Not unique table/alias»).
        if (strpos((string) ($clauses['join'] ?? ''), 'homlity_pm_sale') !== false) {
            return $clauses;
        }

        // LEFT JOIN a propósito. El `compare => EXISTS` de antes generaba un
        // INNER JOIN, así que un inmueble sin la clave de precio —una carga
        // manual, una integración antigua— desaparecía del listado al ordenar
        // por precio en lugar de quedarse al final.
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS homlity_pm_sale
            ON {$wpdb->posts}.ID = homlity_pm_sale.post_id
            AND homlity_pm_sale.meta_key = '_property_price_sale'";

        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS homlity_pm_rent
            ON {$wpdb->posts}.ID = homlity_pm_rent.post_id
            AND homlity_pm_rent.meta_key = '_property_price_rent'";

        // Mismo criterio que la tarjeta (templates/parts/property-card.php):
        // se muestra el precio de venta y, si no lo hay, el de arriendo.
        // Ordenar por otro número dejaría los precios impresos descolocados,
        // que es justo el fallo que esto arregla.
        //
        // Va agregado con MAX() porque la consulta agrupa por ID: bajo
        // ONLY_FULL_GROUP_BY —activo por defecto desde MySQL 5.7— un ORDER BY
        // sobre una columna sin agregar tumba la consulta entera.
        $sale  = "CAST(MAX(homlity_pm_sale.meta_value) AS DECIMAL(20,2))";
        $rent  = "CAST(MAX(homlity_pm_rent.meta_value) AS DECIMAL(20,2))";
        $price = "IF(COALESCE({$sale}, 0) > 0, {$sale}, COALESCE({$rent}, 0))";

        // El ID desempata. Sin un criterio estable, dos inmuebles al mismo
        // precio pueden salir en distinto orden en cada página y el visitante
        // ve fichas repetidas o se salta otras al pasar de página.
        // Se sustituye el ORDER BY, no se le añade nada: lo que WP_Query haya
        // generado es la base neutra que se pidió en buildQueryArgs().
        $clauses['orderby'] = "{$price} {$direction}, {$wpdb->posts}.ID DESC";

        $groupBy = trim((string) ($clauses['groupby'] ?? ''));
        $postIdField = "{$wpdb->posts}.ID";
        if ($groupBy === '') {
            $clauses['groupby'] = $postIdField;
        } elseif (stripos($groupBy, $postIdField) === false) {
            $clauses['groupby'] .= ', ' . $postIdField;
        }

        return $clauses;
    }

    public function applyPriorityKeywordSearch(array $clauses, \WP_Query $query): array
    {
        if (\Homlity\PluginInmobiliario\Search\PropertyIndexQuery::isActive($query)) {
            return $clauses;
        }
        $rawTerm = $query->get('homlity_keyword_search');
        if (!is_string($rawTerm) || $rawTerm === '') {
            return $clauses;
        }

        if ($query->get('post_type') !== PropertyPostType::POST_TYPE) {
            return $clauses;
        }

        global $wpdb;

        $term = sanitize_text_field($rawTerm);
        if ($term === '') {
            return $clauses;
        }

        $likeAny = '%' . $wpdb->esc_like($term) . '%';
        $likePrefix = $wpdb->esc_like($term) . '%';

        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS homlity_pm_code
            ON {$wpdb->posts}.ID = homlity_pm_code.post_id
            AND homlity_pm_code.meta_key = '_property_code'";

        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS homlity_pm_sync
            ON {$wpdb->posts}.ID = homlity_pm_sync.post_id
            AND homlity_pm_sync.meta_key = '_homlity_sync_id'";

        $includeContent = (bool) apply_filters('homlity/search/include_content', false, $term);
        $featureMatch = $wpdb->prepare(
            "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} AS homlity_feature_tr
                INNER JOIN {$wpdb->term_taxonomy} AS homlity_feature_tt
                    ON homlity_feature_tr.term_taxonomy_id = homlity_feature_tt.term_taxonomy_id
                INNER JOIN {$wpdb->terms} AS homlity_feature_terms
                    ON homlity_feature_tt.term_id = homlity_feature_terms.term_id
                WHERE homlity_feature_tr.object_id = {$wpdb->posts}.ID
                    AND homlity_feature_tt.taxonomy = %s
                    AND homlity_feature_terms.name LIKE %s)",
            PropertyTaxonomies::TAXONOMY_FEATURE,
            $likeAny
        );

        // Cada fragmento se prepara por separado. Así un término que contenga
        // paréntesis o porcentajes no altera el conteo de placeholders del OR.
        $whereParts = [
            $wpdb->prepare('homlity_pm_code.meta_value LIKE %s', $likeAny),
            $wpdb->prepare('homlity_pm_sync.meta_value LIKE %s', $likeAny),
            $wpdb->prepare("{$wpdb->posts}.post_title LIKE %s", $likeAny),
            $wpdb->prepare("{$wpdb->posts}.post_excerpt LIKE %s", $likeAny),
            $featureMatch,
        ];
        if ($includeContent) {
            $whereParts[] = $wpdb->prepare("{$wpdb->posts}.post_content LIKE %s", $likeAny);
        }
        $whereSearch = '(' . implode(' OR ', $whereParts) . ')';
        $clauses['where'] .= " AND {$whereSearch}";

        $rankSql = $wpdb->prepare(
            "CASE
                WHEN homlity_pm_code.meta_value = %s THEN 0
                WHEN homlity_pm_sync.meta_value = %s THEN 0
                WHEN homlity_pm_code.meta_value LIKE %s THEN 1
                WHEN homlity_pm_sync.meta_value LIKE %s THEN 1
                WHEN {$wpdb->posts}.post_title LIKE %s THEN 2
                WHEN {$wpdb->posts}.post_title LIKE %s THEN 3
                WHEN __HOMLITY_FEATURE_MATCH__ THEN 4
                WHEN {$wpdb->posts}.post_excerpt LIKE %s THEN 5
                " . ($includeContent ? "WHEN {$wpdb->posts}.post_content LIKE %s THEN 6" : '') . "
                ELSE 9
            END",
            $term,
            $term,
            $likePrefix,
            $likePrefix,
            $likePrefix,
            $likeAny,
            $likeAny,
            $likeAny,
            ...($includeContent ? [$likeAny] : [])
        );
        $rankSql = str_replace('__HOMLITY_FEATURE_MATCH__', $featureMatch, $rankSql);

        // The meta joins can expose more than one row for a property. MIN()
        // keeps the best relevance score and makes ORDER BY valid with the
        // GROUP BY wp_posts.ID that WP_Query adds for our non-empty meta_query
        // under MySQL's ONLY_FULL_GROUP_BY mode.
        $rankSql = 'MIN(' . $rankSql . ')';

        $existingOrderby = trim((string) ($clauses['orderby'] ?? ''));
        $clauses['orderby'] = $rankSql . ($existingOrderby !== '' ? ', ' . $existingOrderby : '');

        $postIdField = "{$wpdb->posts}.ID";
        $groupBy = trim((string) ($clauses['groupby'] ?? ''));
        if ($groupBy === '') {
            $clauses['groupby'] = $postIdField;
        } elseif (stripos($groupBy, $postIdField) === false) {
            $clauses['groupby'] .= ', ' . $postIdField;
        }

        return $clauses;
    }

    public function currentQueryParams(): array
    {
        $params = [];

        $requestedPage = max(
            1,
            (int) (
                get_query_var('paged')
                ?: get_query_var('page')
                ?: ($_GET['paged'] ?? 0)
                ?: ($_GET['page'] ?? 0)
                ?: 1
            )
        );
        $params['page'] = $requestedPage;

        if (is_search()) {
            $params['search'] = get_search_query();
        }

        if (!empty($_GET['q'])) {
            $params['search'] = sanitize_text_field(wp_unslash($_GET['q']));
        } elseif (!empty($_GET['s'])) {
            $params['search'] = sanitize_text_field(wp_unslash($_GET['s']));
        }

        foreach ([
            'gestion' => 'operation',
            'tipo' => 'type',
            'ciudad' => 'city',
            'barrios' => 'neighborhood',
        ] as $qv => $paramKey) {
            $qvValue = get_query_var($qv, '');
            if (!$qvValue) {
                continue;
            }
            if (!isset($collected[$paramKey])) {
                $collected[$paramKey] = [];
            }
            $queryVarValues = is_array($qvValue) ? $qvValue : explode(',', (string) $qvValue);
            foreach ($queryVarValues as $queryVarValue) {
                $queryVarValue = sanitize_text_field(trim((string) $queryVarValue));
                if ($queryVarValue !== '') {
                    $collected[$paramKey][] = $queryVarValue;
                }
            }
        }

        $queried = get_queried_object();
        if ($queried instanceof \WP_Term) {
            $map = [
                PropertyTaxonomies::TAXONOMY_CATEGORY => 'category',
                PropertyTaxonomies::TAXONOMY_OPERATION => 'operation',
                PropertyTaxonomies::TAXONOMY_TYPE => 'type',
                PropertyTaxonomies::TAXONOMY_TAG => 'tag',
                PropertyTaxonomies::TAXONOMY_FEATURE => 'feature',
                PropertyTaxonomies::TAXONOMY_COUNTRY => 'country',
                PropertyTaxonomies::TAXONOMY_STATE => 'state',
                PropertyTaxonomies::TAXONOMY_CITY => 'city',
                PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => 'neighborhood',
                PropertyTaxonomies::TAXONOMY_NEARBY => 'nearby',
            ];

            if (isset($map[$queried->taxonomy])) {
                $params[$map[$queried->taxonomy]] = (int) $queried->term_id;
            }
        }

        $requestToParam = [
            'categoria' => 'category',
            'property_category' => 'category',
            'gestion' => 'operation',
            'property_operation' => 'operation',
            'tipo' => 'type',
            'property_type' => 'type',
            'etiquetas' => 'tag',
            'property_tag' => 'tag',
            'caracteristica' => 'feature',
            'property_feature' => 'feature',
            'pais' => 'country',
            'property_country' => 'country',
            'departamento' => 'state',
            'property_state' => 'state',
            'ciudad' => 'city',
            'property_city' => 'city',
            'localidades' => 'locality',
            'property_locality' => 'locality',
            'barrios' => 'neighborhood',
            'property_neighborhood' => 'neighborhood',
            'cercanias' => 'nearby',
            'property_nearby' => 'nearby',
        ];

        $collected = isset($collected) && is_array($collected) ? $collected : [];
        foreach ($requestToParam as $requestKey => $paramKey) {
            $raw = $_GET[$requestKey] ?? null;
            if ($raw === null) {
                continue;
            }
            $values = is_array($raw)
                ? array_values(array_filter(array_map('sanitize_text_field', wp_unslash($raw)), static fn ($item) => $item !== ''))
                : [sanitize_text_field(wp_unslash($raw))];
            $expandedValues = [];
            foreach ($values as $valueItem) {
                $parts = array_map('trim', explode(',', (string) $valueItem));
                foreach ($parts as $part) {
                    if ($part !== '') {
                        $expandedValues[] = $part;
                    }
                }
            }
            $values = $expandedValues;
            $values = array_values(array_filter($values, static fn ($item) => $item !== ''));
            if (!$values) {
                continue;
            }

            if (!isset($collected[$paramKey])) {
                $collected[$paramKey] = [];
            }
            $collected[$paramKey] = array_merge($collected[$paramKey], $values);
        }

        foreach ($collected as $paramKey => $values) {
            $values = array_values(array_unique(array_filter($values, static fn ($item) => $item !== '')));
            if (!$values) {
                continue;
            }

            if ($paramKey === 'locality') {
                $localityIds = LocalityPostType::resolvePublishedIds($values);
                if ($localityIds) {
                    $params[$paramKey] = count($localityIds) === 1 ? $localityIds[0] : $localityIds;
                }
                continue;
            }

            $taxonomy = $this->taxonomyForParam($paramKey);
            $termIds = [];
            foreach ($values as $value) {
                if (is_numeric($value)) {
                    $termIds[] = absint($value);
                    continue;
                }
                $term = $taxonomy ? get_term_by('slug', $value, $taxonomy) : false;
                if ($term instanceof \WP_Term) {
                    $termIds[] = (int) $term->term_id;
                }
            }
            $termIds = array_values(array_unique(array_filter($termIds)));
            if ($termIds) {
                $params[$paramKey] = count($termIds) === 1 ? $termIds[0] : $termIds;
            }
        }

        $numericMap = [
            'precio_min' => 'price_min',
            'price_min' => 'price_min',
            'precio_max' => 'price_max',
            'price_max' => 'price_max',
            'alcobas' => 'bedrooms',
            'bedrooms' => 'bedrooms',
            'banos' => 'bathrooms',
            'bathrooms' => 'bathrooms',
            'garajes' => 'parking',
            'parking' => 'parking',
            'area_min' => 'area_min',
            'area_max' => 'area_max',
        ];
        foreach ($numericMap as $requestKey => $paramKey) {
            if (isset($_GET[$requestKey]) && $_GET[$requestKey] !== '') {
                $params[$paramKey] = absint($_GET[$requestKey]);
            }
        }

        return $params;
    }

    private function taxonomyForParam(string $param): ?string
    {
        return match ($param) {
            'category' => PropertyTaxonomies::TAXONOMY_CATEGORY,
            'operation' => PropertyTaxonomies::TAXONOMY_OPERATION,
            'type' => PropertyTaxonomies::TAXONOMY_TYPE,
            'tag' => PropertyTaxonomies::TAXONOMY_TAG,
            'feature' => PropertyTaxonomies::TAXONOMY_FEATURE,
            'country' => PropertyTaxonomies::TAXONOMY_COUNTRY,
            'state' => PropertyTaxonomies::TAXONOMY_STATE,
            'city' => PropertyTaxonomies::TAXONOMY_CITY,
            'neighborhood' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            'nearby' => PropertyTaxonomies::TAXONOMY_NEARBY,
            default => null,
        };
    }

    private function extractRequestedPropertyCode(): string
    {
        foreach (['codigo', 'property_code', 'code', 'q', 's'] as $param) {
            if (!isset($_GET[$param])) {
                continue;
            }

            $value = sanitize_text_field(wp_unslash((string) $_GET[$param]));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function hasExplicitPropertyCodeRequest(): bool
    {
        foreach (['codigo', 'property_code', 'code'] as $param) {
            if (isset($_GET[$param]) && sanitize_text_field(wp_unslash((string) $_GET[$param])) !== '') {
                return true;
            }
        }

        return false;
    }

    public static function rememberQueryTotal(array $args, int $total): void
    {
        self::$queryTotals[self::queryTotalKey($args)] = max(0, $total);
    }

    public static function rememberedQueryTotal(array $args): ?int
    {
        $key = self::queryTotalKey($args);
        return array_key_exists($key, self::$queryTotals) ? self::$queryTotals[$key] : null;
    }

    private static function queryTotalKey(array $args): string
    {
        foreach (['paged', 'posts_per_page', 'orderby', 'order', 'fields', 'no_found_rows', self::PRICE_ORDER_QUERY_VAR] as $key) {
            unset($args[$key]);
        }

        self::sortQueryArgs($args);

        return md5(serialize($args));
    }

    /** @param mixed $value */
    private static function sortQueryArgs(&$value): void
    {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as &$child) {
            self::sortQueryArgs($child);
        }
        unset($child);

        if (array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }
    }

    public static function looksLikePropertyCode(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || strpos($value, ' ') !== false) {
            return false;
        }

        return (bool) preg_match('/^(?:\d{3,}|(?=.*\d)[A-Za-z0-9-]{4,})$/', $value);
    }

    private function findPropertyByExactCode(string $code): int
    {
        $code = sanitize_text_field($code);
        if ($code === '') {
            return 0;
        }

        if (\Homlity\PluginInmobiliario\Search\PropertyIndexQuery::isActive()) {
            global $wpdb;
            $indexedId = $wpdb->get_var($wpdb->prepare(
                'SELECT hpi.post_id FROM ' . PropertyIndexSchema::tableName() . ' AS hpi WHERE hpi.code = %s OR hpi.sync_id = %s LIMIT 1',
                $code,
                $code
            ));
            if ($indexedId) {
                return (int) $indexedId;
            }
        }

        $posts = get_posts([
            'post_type'      => PropertyPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_property_code',
                    'value'   => $code,
                    'compare' => '=',
                ],
                [
                    'key'     => '_homlity_sync_id',
                    'value'   => $code,
                    'compare' => '=',
                ],
            ],
        ]);

        return !empty($posts) ? (int) $posts[0] : 0;
    }

    private function extractTermIds($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $values = is_array($raw) ? $raw : explode(',', (string) $raw);
        $ids = [];
        foreach ($values as $value) {
            $id = absint($value);
            if ($id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param int[] $termIds
     * @return int[]
     */
    private function filterExistingTermIds(array $termIds, string $taxonomy): array
    {
        $valid = [];
        foreach ($termIds as $termId) {
            if ($termId > 0 && term_exists($termId, $taxonomy)) {
                $valid[] = $termId;
            }
        }

        return array_values(array_unique($valid));
    }

    /**
     * Returns the price meta key to use for ordering/filtering based on the
     * operation filter. If the active operation is rent-type, returns
     * `_property_price_rent`; otherwise falls back to `_property_price_sale`.
     */
    private function resolvePriceMetaKey(array $params): string
    {
        $operationIds = $this->extractTermIds($params['operation'] ?? null);
        foreach ($operationIds as $termId) {
            $term = get_term($termId, PropertyTaxonomies::TAXONOMY_OPERATION);
            if (!($term instanceof \WP_Term)) {
                continue;
            }
            if (in_array('rent', PropertyTaxonomies::operationFamiliesForTerm($term), true)) {
                return '_property_price_rent';
            }
        }
        return '_property_price_sale';
    }

    public function getMapData(\WP_Query $query): array
    {
        if (empty($query->posts)) {
            return [];
        }

        $data            = [];
        $currencyService = new CurrencyService();

        foreach ($query->posts as $post) {
            $lat = get_post_meta($post->ID, '_property_latitude', true);
            $lng = get_post_meta($post->ID, '_property_longitude', true);

            if (!$lat || !$lng) {
                continue;
            }

            $price    = get_post_meta($post->ID, '_property_price_sale', true);
            $currency = get_post_meta($post->ID, '_property_currency_sale', true) ?: $currencyService->baseCurrency();
            $operations = wp_get_post_terms($post->ID, PropertyTaxonomies::TAXONOMY_OPERATION);
            $operationNames = !is_wp_error($operations) ? array_values(array_filter(array_map(static function ($term): string {
                return $term instanceof \WP_Term ? trim((string) $term->name) : '';
            }, $operations))) : [];
            $priceLines = array_map(static function (array $line): array {
                return [
                    'label' => (string) ($line['label'] ?? ''),
                    'amount' => (string) ($line['amount'] ?? ''),
                ];
            }, CardPriceResolver::forPost((int) $post->ID));
            $location = [];
            foreach ([PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, PropertyTaxonomies::TAXONOMY_CITY] as $taxonomy) {
                $terms = wp_get_post_terms($post->ID, $taxonomy);
                if (!is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        if ($term instanceof \WP_Term && trim((string) $term->name) !== '') {
                            $location[] = trim((string) $term->name);
                        }
                    }
                }
            }

            $data[] = [
                'id'        => $post->ID,
                'title'     => get_the_title($post->ID),
                'permalink' => get_permalink($post->ID),
                'thumbnail' => PropertyMedia::mainImageUrl((int) $post->ID),
                'lat'       => (float) $lat,
                'lng'       => (float) $lng,
                'price'     => $price ? homlity_plugin_apply_filters('homlity_plugin_format_price', null, $price, $currency) : '',
                'operations' => $operationNames,
                'prices'    => $priceLines,
                'bedrooms'  => get_post_meta($post->ID, '_property_bedrooms', true),
                'bathrooms' => get_post_meta($post->ID, '_property_bathrooms', true),
                'parking'   => get_post_meta($post->ID, '_property_parking', true),
                'area'      => get_post_meta($post->ID, '_property_area', true),
                'location'  => array_values(array_unique($location)),
            ];
        }

        return $data;
    }
}
