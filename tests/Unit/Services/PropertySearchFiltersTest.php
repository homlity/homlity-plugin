<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Every search filter translated into its WP_Query clause.
 *
 * A filter that silently produces no clause returns the whole catalogue, which
 * looks like a working search to the visitor and is why each one is asserted
 * here individually.
 */
final class PropertySearchFiltersTest extends TestCase
{
    /** Taxonomy filters and the taxonomy each one must target. */
    private const TAXONOMY_FILTERS = [
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

    /** @param array<string,mixed> $params */
    private function args(array $params): array
    {
        return (new PropertySearchService())->buildQueryArgs($params);
    }

    /**
     * Clauses of a tax_query that target one taxonomy.
     *
     * @return list<array<string,mixed>>
     */
    private function taxClauses(array $args, string $taxonomy): array
    {
        return array_values(array_filter(
            $args['tax_query'],
            static fn($clause): bool => is_array($clause) && ($clause['taxonomy'] ?? '') === $taxonomy
        ));
    }

    /**
     * Clauses of a meta_query that target one meta key.
     *
     * @return list<array<string,mixed>>
     */
    private function metaClauses(array $args, string $key): array
    {
        return array_values(array_filter(
            $args['meta_query'],
            static fn($clause): bool => is_array($clause) && ($clause['key'] ?? '') === $key
        ));
    }

    // ── Base query ───────────────────────────────────────────────────────────

    public function testLaBusquedaBaseSoloDevuelveInmueblesPublicados(): void
    {
        $args = $this->args([]);

        self::assertSame('property', $args['post_type']);
        self::assertSame('publish', $args['post_status']);
        self::assertSame('AND', $args['tax_query']['relation']);
        self::assertSame('AND', $args['meta_query']['relation']);
    }

    /**
     * A property marked inactive or unavailable by the CRM must never surface,
     * but one loaded by hand (without those meta) still has to appear.
     */
    public function testLosInmueblesRetiradosQuedanFueraSinOcultarLosCargadosAMano(): void
    {
        $args = $this->args([]);

        $status = $this->metaClausesWithin($args, '_property_status');
        self::assertContains('NOT EXISTS', array_column($status, 'compare'));
        self::assertContains('active', array_column($status, 'value'));

        $available = $this->metaClausesWithin($args, '_property_available');
        self::assertContains('NOT EXISTS', array_column($available, 'compare'));
    }

    /** @return list<array<string,mixed>> */
    private function metaClausesWithin(array $args, string $key): array
    {
        $found = [];
        foreach ($args['meta_query'] as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach ($group as $clause) {
                if (is_array($clause) && ($clause['key'] ?? '') === $key) {
                    $found[] = $clause;
                }
            }
        }

        return $found;
    }

    // ── Keyword ──────────────────────────────────────────────────────────────

    public function testLaPalabraClaveViajaComoBusquedaPriorizada(): void
    {
        $args = $this->args(['search' => 'apartamento guatapé']);

        self::assertSame('apartamento guatapé', $args['homlity_keyword_search']);
    }

    public function testSinPalabraClaveNoSeAgregaLaBusqueda(): void
    {
        self::assertArrayNotHasKey('homlity_keyword_search', $this->args(['search' => '']));
    }

    // ── Taxonomies ───────────────────────────────────────────────────────────

    /**
     * Each of the ten taxonomy filters has to reach its own taxonomy. Looping
     * here is deliberate: adding a taxonomy to the service without wiring it
     * fails this test instead of shipping a filter that returns everything.
     */
    public function testCadaFiltroDeTaxonomiaConsultaSuPropiaTaxonomia(): void
    {
        foreach (self::TAXONOMY_FILTERS as $param => $taxonomy) {
            WpStubs::reset();
            WpStubs::setTerm(31, $taxonomy);

            $clauses = $this->taxClauses($this->args([$param => 31]), $taxonomy);

            self::assertCount(1, $clauses, sprintf('El filtro "%s" no generó cláusula.', $param));
            self::assertSame([31], $clauses[0]['terms']);
            self::assertSame('term_id', $clauses[0]['field']);
        }
    }

    public function testUnFiltroDeTaxonomiaAceptaVariosTerminosSeparadosPorComa(): void
    {
        WpStubs::setTerm(10, PropertyTaxonomies::TAXONOMY_TYPE);
        WpStubs::setTerm(11, PropertyTaxonomies::TAXONOMY_TYPE);

        $clauses = $this->taxClauses($this->args(['type' => '10,11']), PropertyTaxonomies::TAXONOMY_TYPE);

        self::assertSame([10, 11], $clauses[0]['terms']);
    }

    public function testUnFiltroDeTaxonomiaAceptaUnArreglo(): void
    {
        WpStubs::setTerm(10, PropertyTaxonomies::TAXONOMY_CITY);
        WpStubs::setTerm(11, PropertyTaxonomies::TAXONOMY_CITY);

        $clauses = $this->taxClauses($this->args(['city' => [10, 11, 10]]), PropertyTaxonomies::TAXONOMY_CITY);

        self::assertSame([10, 11], $clauses[0]['terms']);
    }

    /**
     * A term id that does not exist would make WP_Query return nothing at all.
     * Dropping it keeps the rest of the search usable.
     */
    public function testUnTerminoInexistenteSeDescartaEnLugarDeVaciarLaBusqueda(): void
    {
        WpStubs::setTerm(10, PropertyTaxonomies::TAXONOMY_TYPE);

        $clauses = $this->taxClauses($this->args(['type' => '10,999']), PropertyTaxonomies::TAXONOMY_TYPE);

        self::assertSame([10], $clauses[0]['terms']);
    }

    public function testUnTerminoDeOtraTaxonomiaNoSeAcepta(): void
    {
        WpStubs::setTerm(10, PropertyTaxonomies::TAXONOMY_CITY);

        self::assertSame([], $this->taxClauses($this->args(['type' => 10]), PropertyTaxonomies::TAXONOMY_TYPE));
    }

    // ── Widget presets ───────────────────────────────────────────────────────

    /**
     * A term fixed in the widget is the author's decision and must win over
     * whatever the visitor picked for the same taxonomy.
     */
    public function testElTerminoFijadoEnElWidgetReemplazaAlDelVisitante(): void
    {
        foreach (self::TAXONOMY_FILTERS as $param => $taxonomy) {
            WpStubs::reset();
            WpStubs::setTerm(10, $taxonomy);
            WpStubs::setTerm(20, $taxonomy);

            $clauses = $this->taxClauses($this->args([$param => 10, 'preset_' . $param => 20]), $taxonomy);

            self::assertCount(1, $clauses, sprintf('El preset "%s" no reemplazó al filtro del visitante.', $param));
            self::assertSame([20], $clauses[0]['terms']);
        }
    }

    public function testVariasEtiquetasFijadasSeConsultanJuntas(): void
    {
        WpStubs::setTerm(5, PropertyTaxonomies::TAXONOMY_TAG);
        WpStubs::setTerm(6, PropertyTaxonomies::TAXONOMY_TAG);
        WpStubs::setTerm(7, PropertyTaxonomies::TAXONOMY_TAG);

        $clauses = $this->taxClauses(
            $this->args(['tag' => 7, 'preset_tag_ids' => [5, 6]]),
            PropertyTaxonomies::TAXONOMY_TAG
        );

        self::assertCount(1, $clauses);
        self::assertSame([5, 6], $clauses[0]['terms']);
        self::assertSame('IN', $clauses[0]['operator']);
    }

    // ── Locality ─────────────────────────────────────────────────────────────

    /**
     * A locality is a bridge (city → locality → neighbourhoods), never a
     * taxonomy on the property, so it has to be translated to the neighbourhood
     * terms the properties actually carry.
     */
    public function testLaLocalidadSeTraduceALosBarriosQueAgrupa(): void
    {
        WpStubs::$postObjects[70] = new \WP_Post([
            'ID' => 70, 'post_type' => 'property_locality', 'post_status' => 'publish', 'post_name' => 'el-poblado',
        ]);
        WpStubs::$localityNeighborhoods[70] = [401, 402];

        $clauses = $this->taxClauses($this->args(['locality' => 70]), PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD);

        self::assertCount(1, $clauses);
        self::assertSame([401, 402], $clauses[0]['terms']);
    }

    /**
     * An empty locality must return nothing instead of silently widening the
     * search to the whole catalogue.
     */
    public function testUnaLocalidadSinBarriosNoDevuelveInmuebles(): void
    {
        WpStubs::$postObjects[70] = new \WP_Post([
            'ID' => 70, 'post_type' => 'property_locality', 'post_status' => 'publish', 'post_name' => 'vacia',
        ]);

        self::assertSame([0], $this->args(['locality' => 70])['post__in']);
    }

    public function testUnaLocalidadNoPublicadaNoDevuelveInmuebles(): void
    {
        WpStubs::$postObjects[70] = new \WP_Post([
            'ID' => 70, 'post_type' => 'property_locality', 'post_status' => 'draft', 'post_name' => 'borrador',
        ]);

        self::assertSame([0], $this->args(['locality' => 70])['post__in']);
    }

    // ── Numeric ranges ───────────────────────────────────────────────────────

    public function testElRangoDePrecioUsaUnIntervalo(): void
    {
        $clauses = $this->metaClauses($this->args(['price_min' => 100, 'price_max' => 500]), '_property_price_sale');

        self::assertSame('BETWEEN', $clauses[0]['compare']);
        self::assertSame([100, 500], $clauses[0]['value']);
        self::assertSame('NUMERIC', $clauses[0]['type']);
    }

    public function testUnPrecioMinimoSoloAplicaLaCotaInferior(): void
    {
        $clauses = $this->metaClauses($this->args(['price_min' => 100]), '_property_price_sale');

        self::assertSame('>=', $clauses[0]['compare']);
        self::assertSame(100, $clauses[0]['value']);
    }

    public function testUnPrecioMaximoSoloAplicaLaCotaSuperior(): void
    {
        $clauses = $this->metaClauses($this->args(['price_max' => 500]), '_property_price_sale');

        self::assertSame('<=', $clauses[0]['compare']);
        self::assertSame(500, $clauses[0]['value']);
    }

    /**
     * Filtering a rent search against the sale price would return nothing: the
     * price meta has to follow the operation.
     */
    public function testUnaBusquedaDeArriendoFiltraPorElCanonYNoPorElPrecioDeVenta(): void
    {
        WpStubs::setTerm(3, PropertyTaxonomies::TAXONOMY_OPERATION, 'arriendo', 'Arriendo');

        $args = $this->args(['operation' => 3, 'price_min' => 1000]);

        self::assertCount(1, $this->metaClauses($args, '_property_price_rent'));
        self::assertSame([], $this->metaClauses($args, '_property_price_sale'));
    }

    /** The operation name may arrive accented or in English. */
    public function testElCanonSeDetectaConAcentosYSinonimos(): void
    {
        foreach (['alquiler' => 'Alquiler', 'renta' => 'Renta', 'rent' => 'Rent'] as $slug => $name) {
            WpStubs::reset();
            WpStubs::setTerm(3, PropertyTaxonomies::TAXONOMY_OPERATION, $slug, $name);

            $args = $this->args(['operation' => 3, 'price_min' => 1000]);

            self::assertCount(1, $this->metaClauses($args, '_property_price_rent'), $slug);
        }
    }

    public function testUnaBusquedaDeVentaFiltraPorElPrecioDeVenta(): void
    {
        WpStubs::setTerm(4, PropertyTaxonomies::TAXONOMY_OPERATION, 'venta', 'Venta');

        self::assertCount(1, $this->metaClauses($this->args(['operation' => 4, 'price_min' => 1000]), '_property_price_sale'));
    }

    public function testElRangoDeAreaUsaUnIntervalo(): void
    {
        $clauses = $this->metaClauses($this->args(['area_min' => 50, 'area_max' => 120]), '_property_area');

        self::assertSame('BETWEEN', $clauses[0]['compare']);
        self::assertSame([50, 120], $clauses[0]['value']);
    }

    /** Rooms, bathrooms and garages are minimums, not exact matches. */
    public function testLasHabitacionesBanosYGarajesSonMinimos(): void
    {
        foreach ([
            'bedrooms' => '_property_bedrooms',
            'bathrooms' => '_property_bathrooms',
            'parking' => '_property_parking',
        ] as $param => $metaKey) {
            $clauses = $this->metaClauses($this->args([$param => 3]), $metaKey);

            self::assertCount(1, $clauses, $param);
            self::assertSame('>=', $clauses[0]['compare']);
            self::assertSame(3, $clauses[0]['value']);
            self::assertSame('NUMERIC', $clauses[0]['type']);
        }
    }

    public function testUnValorNumericoEnCeroNoAgregaFiltro(): void
    {
        $args = $this->args([
            'price_min' => 0, 'price_max' => 0, 'area_min' => 0, 'area_max' => 0,
            'bedrooms' => 0, 'bathrooms' => 0, 'parking' => 0,
        ]);

        foreach ([
            '_property_price_sale', '_property_area',
            '_property_bedrooms', '_property_bathrooms', '_property_parking',
        ] as $metaKey) {
            self::assertSame([], $this->metaClauses($args, $metaKey), $metaKey);
        }
    }

    // ── Flags ────────────────────────────────────────────────────────────────

    public function testElFiltroDeDestacadosConsultaSuMetadato(): void
    {
        $clauses = $this->metaClauses($this->args(['featured' => true]), '_property_featured');

        self::assertCount(1, $clauses);
        self::assertSame('1', $clauses[0]['value']);
    }

    public function testSinDestacadosNoSeAgregaElFiltro(): void
    {
        self::assertSame([], $this->metaClauses($this->args(['featured' => false]), '_property_featured'));
    }

    // ── Geolocation ──────────────────────────────────────────────────────────

    public function testLaBusquedaPorRadioAcotaLatitudYLongitud(): void
    {
        $args = $this->args(['geo_latitude' => 6.23, 'geo_longitude' => -75.15, 'geo_radius_km' => 5]);

        $latitude = $this->metaClauses($args, '_property_latitude');
        $longitude = $this->metaClauses($args, '_property_longitude');

        self::assertCount(1, $latitude);
        self::assertCount(1, $longitude);
        self::assertSame('BETWEEN', $latitude[0]['compare']);
        // 5 km ≈ 0.045° of latitude; the box has to contain the centre.
        self::assertLessThan(6.23, $latitude[0]['value'][0]);
        self::assertGreaterThan(6.23, $latitude[0]['value'][1]);
        self::assertLessThan(-75.15, $longitude[0]['value'][0]);
        self::assertGreaterThan(-75.15, $longitude[0]['value'][1]);
    }

    /** Longitude degrees shrink towards the poles, so the box must widen. */
    public function testElRadioCompensaLaConvergenciaDeMeridianos(): void
    {
        $equator = $this->args(['geo_latitude' => 0, 'geo_longitude' => 0, 'geo_radius_km' => 100]);
        $north = $this->args(['geo_latitude' => 60, 'geo_longitude' => 0, 'geo_radius_km' => 100]);

        $equatorWidth = $this->metaClauses($equator, '_property_longitude')[0]['value'][1];
        $northWidth = $this->metaClauses($north, '_property_longitude')[0]['value'][1];

        self::assertGreaterThan($equatorWidth, $northWidth);
    }

    public function testSinRadioNoSeFiltraPorCoordenadas(): void
    {
        $args = $this->args(['geo_latitude' => 6.23, 'geo_longitude' => -75.15, 'geo_radius_km' => 0]);

        self::assertSame([], $this->metaClauses($args, '_property_latitude'));
    }

    public function testUnasCoordenadasIncompletasNoFiltran(): void
    {
        $args = $this->args(['geo_latitude' => 6.23, 'geo_radius_km' => 5]);

        self::assertSame([], $this->metaClauses($args, '_property_latitude'));
    }

    // ── Ordering and pagination ──────────────────────────────────────────────

    /**
     * Ordering by price no longer touches `meta_query`.
     *
     * It used to add a clause pinned to one meta key for the whole listing,
     * chosen from the operation filter. Rentals store 0 in the sale price, so
     * a mixed listing sorted them all as ties. The price is now resolved per
     * row in SQL — see PropertySearchOrderingTest for the generated clauses.
     */
    public function testElOrdenPorPrecioSeDelegaAlSqlYNoAlMetaQuery(): void
    {
        WpStubs::setTerm(3, PropertyTaxonomies::TAXONOMY_OPERATION, 'arriendo', 'Arriendo');

        foreach ([[], ['operation' => 3]] as $extra) {
            $args = $this->args(['orderby' => 'price_asc'] + $extra);

            self::assertSame('ASC', $args[PropertySearchService::PRICE_ORDER_QUERY_VAR]);
            self::assertArrayNotHasKey('meta_key', $args);
            self::assertArrayNotHasKey(PropertySearchService::PRICE_ORDER_QUERY_VAR, $args['meta_query']);
        }

        self::assertSame(
            'DESC',
            $this->args(['orderby' => 'price_desc'])[PropertySearchService::PRICE_ORDER_QUERY_VAR]
        );
    }

    public function testElOrdenPorTituloRespetaLaDireccionPedida(): void
    {
        self::assertSame('ASC', $this->args(['orderby' => 'title', 'order' => 'asc'])['order']);
        self::assertSame('DESC', $this->args(['orderby' => 'title', 'order' => 'desc'])['order']);
    }

    public function testUnOrdenDesconocidoCaeEnLosMasRecientes(): void
    {
        $args = $this->args(['orderby' => 'cualquier-cosa']);

        self::assertSame('DESC', $args['orderby']['date']);
        self::assertSame('DESC', $args['order']);
    }

    public function testLaPaginacionSeTrasladaALaConsulta(): void
    {
        self::assertSame(3, $this->args(['page' => 3])['paged']);
        self::assertSame(1, $this->args(['page' => 0])['paged']);
        self::assertSame(1, $this->args(['page' => -5])['paged']);
    }

    /**
     * The page size is clamped so a crafted request cannot ask for the whole
     * database in one query.
     */
    public function testElTamanoDePaginaSeAcota(): void
    {
        self::assertSame(12, $this->args([])['posts_per_page']);
        self::assertSame(100, $this->args(['per_page' => 5000])['posts_per_page']);
        self::assertSame(1, $this->args(['per_page' => 0])['posts_per_page']);
    }

    // ── Combined ─────────────────────────────────────────────────────────────

    /** A realistic search must keep every condition, not just the last one. */
    public function testUnaBusquedaCombinadaConservaTodasLasCondiciones(): void
    {
        WpStubs::setTerm(3, PropertyTaxonomies::TAXONOMY_OPERATION, 'arriendo', 'Arriendo');
        WpStubs::setTerm(8, PropertyTaxonomies::TAXONOMY_TYPE, 'apartamento', 'Apartamento');
        WpStubs::setTerm(12, PropertyTaxonomies::TAXONOMY_CITY, 'guatape', 'Guatapé');

        $args = $this->args([
            'operation' => 3, 'type' => 8, 'city' => 12,
            'price_min' => 1000000, 'price_max' => 3000000,
            'bedrooms' => 2, 'bathrooms' => 2, 'parking' => 1,
            'area_min' => 60, 'featured' => true, 'search' => 'balcón',
        ]);

        self::assertCount(1, $this->taxClauses($args, PropertyTaxonomies::TAXONOMY_OPERATION));
        self::assertCount(1, $this->taxClauses($args, PropertyTaxonomies::TAXONOMY_TYPE));
        self::assertCount(1, $this->taxClauses($args, PropertyTaxonomies::TAXONOMY_CITY));
        self::assertCount(1, $this->metaClauses($args, '_property_price_rent'));
        self::assertCount(1, $this->metaClauses($args, '_property_bedrooms'));
        self::assertCount(1, $this->metaClauses($args, '_property_bathrooms'));
        self::assertCount(1, $this->metaClauses($args, '_property_parking'));
        self::assertCount(1, $this->metaClauses($args, '_property_area'));
        self::assertCount(1, $this->metaClauses($args, '_property_featured'));
        self::assertSame('balcón', $args['homlity_keyword_search']);
    }
}
