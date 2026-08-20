<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\SimilarPropertiesQueryBuilder;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * La búsqueda de inmuebles parecidos a uno retirado.
 *
 * Es lo que ve quien llega desde Google a un inmueble que ya no está: si esta
 * cascada falla, la página de "ya no disponible" se queda sin alternativas y
 * la visita se pierde. El descenso por niveles no lanza errores nunca —
 * simplemente devuelve resultados peores o ninguno—, así que hay que afirmar
 * sobre el nivel alcanzado y sobre los argumentos de cada consulta.
 */
final class SimilarPropertiesQueryBuilderTest extends TestCase
{
    private const EXCLUDE_ID = 900;

    /** @var array<int,array<string,mixed>> Argumentos de cada sonda, en orden. */
    private array $probes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolveCounts([]);
    }

    /**
     * Declara cuántos resultados encuentra cada sonda, en el orden en que el
     * constructor las lanza. Lo que no se declare devuelve cero.
     *
     * @param array<int,int> $counts
     */
    private function resolveCounts(array $counts): void
    {
        $this->probes = [];
        $probes = &$this->probes;
        WpStubs::$queryResolver = static function (array $args) use (&$probes, $counts): array {
            $index = count($probes);
            $probes[] = $args;
            $found = (int) ($counts[$index] ?? 0);

            return ['posts' => $found > 0 ? range(1, $found) : []];
        };
    }

    /** Datos de un inmueble completo: sirve para llegar al nivel 1. */
    private function property(array $overrides = []): array
    {
        return array_merge([
            'operation_term_ids'    => [10],
            'type_term_ids'         => [20],
            'city_term_ids'         => [30],
            'neighborhood_term_ids' => [40],
            'operation_slugs'       => ['venta'],
            'price_sale'            => 200000000,
            'price_rent'            => 0,
            'area'                  => 100,
            'type_name'             => 'Apartamento',
            'operation_name'        => 'Venta',
            'city_name'             => 'Medellín',
            'neighborhood_name'     => 'El Poblado',
        ], $overrides);
    }

    /** @param array<string,mixed> $options */
    private function find(array $property, array $options = []): array
    {
        return (new SimilarPropertiesQueryBuilder())->findSimilar(
            $property,
            array_merge(['exclude_id' => self::EXCLUDE_ID], $options)
        );
    }

    /** Cláusulas de taxonomía indexadas por taxonomía. */
    private function taxClauses(array $args): array
    {
        $clauses = $args['tax_query'] ?? [];
        unset($clauses['relation']);
        $byTaxonomy = [];
        foreach ($clauses as $clause) {
            $byTaxonomy[(string) $clause['taxonomy']] = $clause;
        }

        return $byTaxonomy;
    }

    /** Cláusulas de meta añadidas encima de la guarda de disponibilidad. */
    private function extraMetaClauses(array $args): array
    {
        $found = [];
        foreach ($args['meta_query'] ?? [] as $key => $clause) {
            if ($key === 'relation' || !isset($clause['key'])) {
                continue;
            }
            $found[(string) $clause['key']] = $clause;
        }

        return $found;
    }

    // ── Acotado de las opciones ──────────────────────────────────────────────

    /**
     * `posts_per_page` sale de los ajustes del widget. El tope de 48 evita que
     * la página de un inmueble retirado cargue el catálogo entero, y el mínimo
     * de 1 evita el valor 0, que en WP_Query no significa "ninguno" sino "los
     * que diga el ajuste del sitio".
     */
    public function testElMaximoDeResultadosSeAcotaEntreUnoY48(): void
    {
        foreach ([500 => 48, 0 => 1, 6 => 6] as $pedido => $esperado) {
            $this->resolveCounts([5]);
            self::assertSame(
                $esperado,
                $this->find($this->property(), ['max_results' => $pedido])['args']['posts_per_page'],
                (string) $pedido
            );
        }
    }

    /**
     * Una tolerancia de 0 dejaría el rango de precio reducido al valor exacto
     * y el nivel 1 no encontraría nunca nada; una de 5 traería cualquier cosa.
     */
    public function testLaToleranciaDePrecioSeAcotaEntreCincoPorCientoYNoventa(): void
    {
        $this->resolveCounts([5]);

        $precio = $this->extraMetaClauses(
            $this->find($this->property(), ['price_tolerance' => 0.0])['args']
        )['_property_price_sale'];
        self::assertSame([190000000, 210000000], $precio['value'], 'tolerancia mínima del 5 %');

        $this->resolveCounts([5]);
        $precio = $this->extraMetaClauses(
            $this->find($this->property(), ['price_tolerance' => 5.0])['args']
        )['_property_price_sale'];
        self::assertSame([20000000, 380000000], $precio['value'], 'tolerancia máxima del 90 %');
    }

    public function testLaToleranciaDeAreaSeAcotaIgual(): void
    {
        $this->resolveCounts([5]);

        $area = $this->extraMetaClauses(
            $this->find($this->property(), ['area_tolerance' => 0.0])['args']
        )['_property_area'];
        self::assertSame([95, 105], $area['value']);

        $this->resolveCounts([5]);
        $area = $this->extraMetaClauses(
            $this->find($this->property(), ['area_tolerance' => 5.0])['args']
        )['_property_area'];
        self::assertSame([10, 190], $area['value']);
    }

    // ── Nivel 1: coincidencia fuerte ─────────────────────────────────────────

    public function testElNivelFuerteExigeOperacionTipoCiudadYBarrio(): void
    {
        $this->resolveCounts([3]);

        $result = $this->find($this->property());

        self::assertSame(SimilarPropertiesQueryBuilder::LEVEL_STRONG, $result['fallback_level']);
        self::assertSame(3, $result['count']);
        $clauses = $this->taxClauses($result['args']);
        self::assertSame([10], $clauses[PropertyTaxonomies::TAXONOMY_OPERATION]['terms']);
        self::assertSame([20], $clauses[PropertyTaxonomies::TAXONOMY_TYPE]['terms']);
        self::assertSame([30], $clauses[PropertyTaxonomies::TAXONOMY_CITY]['terms']);
        self::assertSame([40], $clauses[PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD]['terms']);
        self::assertSame('AND', $result['args']['tax_query']['relation'], 'todas las condiciones a la vez');
    }

    /** Con precio y área a la vez: es lo que distingue el nivel 1 del 2. */
    public function testElNivelFuerteFiltraPorPrecioYPorArea(): void
    {
        $this->resolveCounts([3]);

        $meta = $this->extraMetaClauses($this->find($this->property())['args']);

        self::assertArrayHasKey('_property_price_sale', $meta);
        self::assertArrayHasKey('_property_area', $meta);
        foreach (['_property_price_sale', '_property_area'] as $key) {
            self::assertSame('BETWEEN', $meta[$key]['compare'], $key);
            // Sin el tipo NUMERIC, MySQL compara las metas como texto y
            // '90000000' queda "entre" '1' y '2': el rango deja de significar
            // nada sin que nada falle.
            self::assertSame('NUMERIC', $meta[$key]['type'] ?? null, $key);
        }
    }

    /** Sin barrio en el inmueble de origen, el nivel 1 sigue siendo válido. */
    public function testSinBarrioElNivelFuerteNoAniadeEsaCondicion(): void
    {
        $this->resolveCounts([3]);

        $result = $this->find($this->property(['neighborhood_term_ids' => []]));

        self::assertSame(SimilarPropertiesQueryBuilder::LEVEL_STRONG, $result['fallback_level']);
        self::assertArrayNotHasKey(PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, $this->taxClauses($result['args']));
    }

    // ── Descenso por niveles ─────────────────────────────────────────────────

    /**
     * El umbral es `min_results`, no "algún resultado": mostrar un solo
     * inmueble parecido no compensa, es mejor abrir el filtro.
     */
    public function testConMenosResultadosQueElMinimoSeBajaDeNivel(): void
    {
        // Nivel 1 encuentra 2, por debajo del mínimo de 3; nivel 2 encuentra 4.
        $this->resolveCounts([2, 4]);

        $result = $this->find($this->property());

        self::assertSame(SimilarPropertiesQueryBuilder::LEVEL_MEDIUM, $result['fallback_level']);
        self::assertSame(4, $result['count']);
    }

    /** El mínimo es configurable y decide de verdad el nivel elegido. */
    public function testElMinimoConfiguradoDecideDondeSeDetieneLaCascada(): void
    {
        $this->resolveCounts([2, 4]);
        self::assertSame(
            SimilarPropertiesQueryBuilder::LEVEL_STRONG,
            $this->find($this->property(), ['min_results' => 1])['fallback_level']
        );

        $this->resolveCounts([2, 4, 6]);
        self::assertSame(
            SimilarPropertiesQueryBuilder::LEVEL_LOCAL,
            $this->find($this->property(), ['min_results' => 5])['fallback_level']
        );
    }

    /** El nivel 2 ensancha el precio un 50 % y suelta el área. */
    public function testElNivelMedioEnsanchaElPrecioYSueltaElArea(): void
    {
        $this->resolveCounts([0, 4]);

        $meta = $this->extraMetaClauses($this->find($this->property(), ['price_tolerance' => 0.20])['args']);

        self::assertArrayNotHasKey('_property_area', $meta, 'el área deja de filtrar en el nivel medio');
        self::assertSame([140000000, 260000000], $meta['_property_price_sale']['value'], '±30 %');
    }

    /** Ensanchar el precio no puede pasar del tope: 0,8 × 1,5 sería 1,2. */
    public function testElEnsanchadoDelNivelMedioNoPasaDelNoventaPorCiento(): void
    {
        $this->resolveCounts([0, 4]);

        $meta = $this->extraMetaClauses($this->find($this->property(), ['price_tolerance' => 0.8])['args']);

        self::assertSame([20000000, 380000000], $meta['_property_price_sale']['value']);
    }

    /** El nivel 3 mantiene la ubicación y el tipo, pero ya no mira dinero. */
    public function testElNivelLocalNoFiltraPorPrecioNiPorArea(): void
    {
        $this->resolveCounts([0, 0, 4]);

        $result = $this->find($this->property());

        self::assertSame(SimilarPropertiesQueryBuilder::LEVEL_LOCAL, $result['fallback_level']);
        self::assertSame([], $this->extraMetaClauses($result['args']));
        self::assertArrayHasKey(PropertyTaxonomies::TAXONOMY_TYPE, $this->taxClauses($result['args']));
    }

    /**
     * El nivel 4 es el comercial: en esta ciudad y esta operación hay algo,
     * aunque sea de otro tipo. Aquí basta con un resultado, porque la
     * alternativa es no enseñar nada.
     */
    public function testElNivelComercialSueltaElTipoYSeConformaConUnResultado(): void
    {
        $this->resolveCounts([0, 0, 0, 1]);

        $result = $this->find($this->property());

        self::assertSame(SimilarPropertiesQueryBuilder::LEVEL_FALLBACK, $result['fallback_level']);
        self::assertSame(1, $result['count']);
        $clauses = $this->taxClauses($result['args']);
        self::assertArrayNotHasKey(PropertyTaxonomies::TAXONOMY_TYPE, $clauses);
        self::assertArrayHasKey(PropertyTaxonomies::TAXONOMY_CITY, $clauses);
        self::assertArrayHasKey(PropertyTaxonomies::TAXONOMY_OPERATION, $clauses);
    }

    /** Sin nada que ofrecer, `args` es null: la plantilla no debe consultar. */
    public function testSinResultadosEnNingunNivelNoSeDevuelvenArgumentos(): void
    {
        $result = $this->find($this->property());

        self::assertSame(SimilarPropertiesQueryBuilder::LEVEL_NONE, $result['fallback_level']);
        self::assertNull($result['args']);
        self::assertSame(0, $result['count']);
        self::assertSame([], $result['applied_filters']);
    }

    /**
     * La ciudad es el ancla mínima de los niveles 1 a 3. Sin ella no se
     * sondean siquiera: buscar "cualquier apartamento en venta del país" no es
     * un parecido, es ruido.
     */
    public function testSinCiudadLosTresPrimerosNivelesNiSeIntentan(): void
    {
        $this->resolveCounts([9]);

        $result = $this->find($this->property(['city_term_ids' => []]));

        self::assertSame(SimilarPropertiesQueryBuilder::LEVEL_FALLBACK, $result['fallback_level']);
        self::assertCount(1, $this->probes, 'sólo debería sondearse el nivel comercial');
    }

    /** Sin ciudad ni operación no queda ningún ancla y no hay consulta posible. */
    public function testSinCiudadNiOperacionNoHayConsulta(): void
    {
        $this->resolveCounts([9]);

        $result = $this->find($this->property(['city_term_ids' => [], 'operation_term_ids' => []]));

        self::assertSame(SimilarPropertiesQueryBuilder::LEVEL_NONE, $result['fallback_level']);
        self::assertNull($result['args']);
        self::assertSame([], $this->probes);
    }

    // ── Elección de la clave de precio ───────────────────────────────────────

    /**
     * Un arriendo comparado contra `_property_price_sale` no encuentra nada, y
     * peor: si lo encuentra, compara 2.500.000 al mes contra 250.000.000 de
     * venta. La detección va por el slug de la operación.
     *
     * @dataProvider slugsDeArriendo
     */
    public function testElArriendoSeComparaContraElPrecioDeArriendo(string $slug): void
    {
        $this->resolveCounts([3]);

        // Los dos precios vienen informados a propósito: con uno solo, el
        // respaldo a la clave contraria taparía un fallo de detección.
        $meta = $this->extraMetaClauses($this->find($this->property([
            'operation_slugs' => [$slug],
            'price_rent'      => 2000000,
            'price_sale'      => 300000000,
        ]))['args']);

        self::assertArrayHasKey('_property_price_rent', $meta, $slug);
        self::assertArrayNotHasKey('_property_price_sale', $meta, $slug);
        self::assertSame([1600000, 2400000], $meta['_property_price_rent']['value'], $slug);
    }

    /** @return array<string,array{0:string}> */
    public static function slugsDeArriendo(): array
    {
        return [
            'arriendo'  => ['arriendo'],
            'alquiler'  => ['alquiler'],
            'renta'     => ['renta'],
            'rent'      => ['rent'],
            'mayúsculas' => ['Arriendo-Comercial'],
        ];
    }

    public function testLaVentaSeComparaContraElPrecioDeVenta(): void
    {
        $this->resolveCounts([3]);

        $meta = $this->extraMetaClauses($this->find($this->property([
            'operation_slugs' => ['venta'],
            'price_rent'      => 2000000,
        ]))['args']);

        self::assertArrayHasKey('_property_price_sale', $meta);
        self::assertArrayNotHasKey('_property_price_rent', $meta);
    }

    /**
     * Un inmueble de arriendo al que el CRM sólo mandó precio de venta: mejor
     * comparar contra la clave que sí tiene valor que quedarse sin filtro.
     */
    public function testSiLaClavePropiaVineVaciaSeUsaLaOtra(): void
    {
        $this->resolveCounts([3]);

        $meta = $this->extraMetaClauses($this->find($this->property([
            'operation_slugs' => ['arriendo'],
            'price_rent'      => 0,
            'price_sale'      => 300000000,
        ]))['args']);

        self::assertArrayHasKey('_property_price_sale', $meta);
    }

    /** Sin ningún precio no se puede filtrar por precio, pero el nivel sigue valiendo. */
    public function testSinPrecioNoSeAniadeLaCondicionDePrecio(): void
    {
        $this->resolveCounts([3]);

        $result = $this->find($this->property(['price_sale' => 0, 'price_rent' => 0]));

        self::assertSame(SimilarPropertiesQueryBuilder::LEVEL_STRONG, $result['fallback_level']);
        $meta = $this->extraMetaClauses($result['args']);
        self::assertArrayNotHasKey('_property_price_sale', $meta);
        self::assertArrayNotHasKey('_property_price_rent', $meta);
        self::assertArrayHasKey('_property_area', $meta, 'el área sí sigue filtrando');
    }

    // ── Área ─────────────────────────────────────────────────────────────────

    /** Cada CRM manda el área en una clave distinta; se prueban en orden. */
    public function testElAreaSeBuscaEnTresClavesEnOrden(): void
    {
        $this->resolveCounts([3]);
        $meta = $this->extraMetaClauses($this->find($this->property([
            'area' => 0, 'area_built' => 80, 'area_private' => 70,
        ]))['args']);
        self::assertSame([64, 96], $meta['_property_area']['value'], 'construida antes que privada');

        $this->resolveCounts([3]);
        $meta = $this->extraMetaClauses($this->find($this->property([
            'area' => 0, 'area_built' => 0, 'area_private' => 70,
        ]))['args']);
        self::assertSame([56, 84], $meta['_property_area']['value']);
    }

    /**
     * El extremo inferior nunca baja de 1: con un área de 3 m² y una
     * tolerancia alta, `round(3 * 0.1)` da 0 y `BETWEEN 0 AND …` deja pasar
     * los inmuebles sin área registrada.
     */
    public function testElExtremoInferiorDelAreaNuncaEsCero(): void
    {
        $this->resolveCounts([3]);

        $meta = $this->extraMetaClauses(
            $this->find($this->property(['area' => 3]), ['area_tolerance' => 0.9])['args']
        );

        self::assertSame(1, $meta['_property_area']['value'][0]);
    }

    public function testSinAreaNoSeAniadeEsaCondicion(): void
    {
        $this->resolveCounts([3]);

        $meta = $this->extraMetaClauses($this->find($this->property(['area' => 0]))['args']);

        self::assertArrayNotHasKey('_property_area', $meta);
    }

    // ── Argumentos base ──────────────────────────────────────────────────────

    /**
     * Ofrecer como alternativa otro inmueble retirado sería el mismo error dos
     * veces. La guarda replica la de PropertySearchService: el meta puede no
     * existir —inmuebles anteriores al campo— y eso cuenta como disponible.
     */
    public function testLaConsultaExcluyeInmueblesRetiradosONoDisponibles(): void
    {
        $this->resolveCounts([3]);

        $metaQuery = $this->find($this->property())['args']['meta_query'];

        self::assertSame('AND', $metaQuery['relation']);
        $keys = [];
        foreach ($metaQuery as $group) {
            foreach (is_array($group) ? $group : [] as $clause) {
                if (is_array($clause) && isset($clause['key'])) {
                    $keys[] = $clause['key'];
                }
            }
        }
        self::assertContains('_property_status', $keys);
        self::assertContains('_property_available', $keys);
        self::assertSame('NOT EXISTS', $metaQuery[0][0]['compare'], 'sin el meta el inmueble cuenta como activo');
    }

    public function testLaConsultaExcluyeElInmuebleDeOrigen(): void
    {
        $this->resolveCounts([3]);

        self::assertSame([self::EXCLUDE_ID], $this->find($this->property())['args']['post__not_in']);

        $this->resolveCounts([3]);
        self::assertArrayNotHasKey(
            'post__not_in',
            $this->find($this->property(), ['exclude_id' => 0])['args']
        );
    }

    public function testLaConsultaSeLimitaAInmueblesPublicados(): void
    {
        $this->resolveCounts([3]);

        $args = $this->find($this->property())['args'];

        self::assertSame(PropertyPostType::POST_TYPE, $args['post_type']);
        self::assertSame('publish', $args['post_status']);
    }

    /** Los ids llegan de datos guardados: hay que filtrar basura. */
    public function testLosIdentificadoresDeTerminoSeSaneanACeroPositivos(): void
    {
        $this->resolveCounts([3]);

        $clauses = $this->taxClauses($this->find($this->property([
            'city_term_ids' => ['30', 0, '', 'abc', 31],
        ]))['args']);

        self::assertSame([30, 31], $clauses[PropertyTaxonomies::TAXONOMY_CITY]['terms']);
    }

    /** La sonda cuenta hasta 48 sin traer objetos ni calentar cachés. */
    public function testLaSondaSoloPideIdentificadores(): void
    {
        $this->resolveCounts([3]);

        $this->find($this->property(), ['max_results' => 6]);

        self::assertSame(48, $this->probes[0]['posts_per_page']);
        self::assertSame('ids', $this->probes[0]['fields']);
        self::assertFalse($this->probes[0]['update_post_meta_cache']);
        self::assertFalse($this->probes[0]['update_post_term_cache']);
    }

    // ── Etiquetas mostradas al visitante ─────────────────────────────────────

    /**
     * "Resultados basados en: …" tiene que decir la verdad. Prometer "área
     * similar" en un nivel que ya no filtra por área es engañar al visitante.
     */
    public function testLasEtiquetasDescribenLoQueRealmenteSeFiltro(): void
    {
        $this->resolveCounts([3]);
        $fuerte = $this->find($this->property())['applied_filters'];
        self::assertContains('El Poblado', $fuerte);
        self::assertContains('precio aproximado', $fuerte);
        self::assertContains('área similar', $fuerte);

        $this->resolveCounts([0, 3]);
        $medio = $this->find($this->property())['applied_filters'];
        self::assertNotContains('área similar', $medio);
        self::assertNotContains('El Poblado', $medio, 'el nivel medio ya no exige el barrio');
        self::assertContains('precio aproximado', $medio);

        $this->resolveCounts([0, 0, 3]);
        $local = $this->find($this->property())['applied_filters'];
        self::assertNotContains('precio aproximado', $local);
        self::assertContains('Medellín', $local);
    }

    public function testLasEtiquetasOmitenLosDatosQueElInmuebleNoTiene(): void
    {
        $this->resolveCounts([3]);

        $filters = $this->find($this->property([
            'type_name' => '', 'operation_name' => '', 'neighborhood_name' => '',
        ]))['applied_filters'];

        self::assertSame(['Medellín', 'precio aproximado', 'área similar'], $filters);
    }
}
