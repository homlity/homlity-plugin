<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\RetiredPropertyRecoveryService;
use Homlity\PluginInmobiliario\Services\SimilarPropertiesQueryBuilder;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * La página de un inmueble retirado.
 *
 * Es lo que ve quien llega desde Google a una ficha que ya no existe, y lo que
 * ve Google mismo. Aquí se decide el código HTTP, el `robots` y el canónico:
 * indexar una página sin contenido útil resta posiciones a todo el dominio, y
 * devolver 404 cuando sí había alternativas tira una visita que estaba a un
 * clic de convertirse en contacto. Nada de eso lanza un error.
 */
final class RetiredPropertyRecoveryServiceTest extends TestCase
{
    private const INMUEBLE = 321;

    private RetiredPropertyRecoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RetiredPropertyRecoveryService();
        $this->inmuebleRetirado();
    }

    /** Deja en la base un inmueble retirado con datos completos. */
    private function inmuebleRetirado(int $postId = self::INMUEBLE): void
    {
        WpStubs::$postObjects[$postId] = new \WP_Post(['ID' => $postId, 'post_type' => 'property']);
        WpStubs::$postTitles[$postId] = 'Apartamento en El Poblado';
        WpStubs::$postMeta[$postId] = [
            '_property_price_sale' => '250000000',
            '_property_currency_sale' => 'COP',
            '_property_area' => '85',
            '_property_bedrooms' => '3',
            '_property_code' => 'HOM-123',
        ];

        foreach ([
            PropertyTaxonomies::TAXONOMY_OPERATION => [10 => 'Venta'],
            PropertyTaxonomies::TAXONOMY_TYPE => [20 => 'Apartamento'],
            PropertyTaxonomies::TAXONOMY_CITY => [30 => 'Medellín'],
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => [40 => 'El Poblado'],
        ] as $taxonomy => $terms) {
            WpStubs::$postTerms[$postId][$taxonomy] = array_map(
                static fn(string $name, int $id): object => WpStubs::setTerm($id, $taxonomy, sanitize_title($name), $name),
                $terms,
                array_keys($terms)
            );
        }
    }

    /** Declara cuántos inmuebles parecidos encuentra cada sonda. */
    private function parecidos(int $cuantos): void
    {
        WpStubs::$queryResolver = static fn(array $args): array => [
            'posts' => $cuantos > 0 ? range(1, $cuantos) : [],
        ];
    }

    // ── Casos sin inmueble ───────────────────────────────────────────────────

    public function testUnIdentificadorInvalidoDevuelveUnContextoVacio(): void
    {
        $contexto = $this->service->buildRecoveryContext(0);

        self::assertFalse($contexto['is_retired']);
        self::assertSame(404, $contexto['status_code']);
        self::assertSame([], $contexto['internal_links']);
    }

    /** Un id que nunca existió: no hay nada que recuperar ni que indexar. */
    public function testUnInmuebleQueNoEstaEnLaBaseDevuelve404(): void
    {
        $contexto = $this->service->buildRecoveryContext(9999);

        self::assertFalse($contexto['is_retired']);
        self::assertSame(404, $contexto['status_code']);
        self::assertSame('noindex,follow', $contexto['robots']);
    }

    // ── Sustituto explícito ──────────────────────────────────────────────────

    /**
     * Cuando el comercial señala a mano el inmueble que sustituye al retirado,
     * lo correcto es un 301: traslada el posicionamiento acumulado en vez de
     * mostrar una página intermedia.
     */
    public function testUnSustitutoPublicadoProduceUnaRedireccionPermanente(): void
    {
        WpStubs::$postMeta[self::INMUEBLE]['_homlity_replacement_property_id'] = '777';
        WpStubs::$postObjects[777] = new \WP_Post(['ID' => 777, 'post_type' => 'property']);
        WpStubs::$postStatuses[777] = 'publish';
        WpStubs::$permalinks[777] = 'https://example.test/inmuebles/el-sustituto/';

        $contexto = $this->service->buildRecoveryContext(self::INMUEBLE);

        self::assertSame(301, $contexto['status_code']);
        self::assertSame('https://example.test/inmuebles/el-sustituto/', $contexto['redirect_url']);
        self::assertSame('noindex,follow', $contexto['robots']);
    }

    /**
     * Si el sustituto también se retiró, redirigir llevaría a otra página
     * muerta. Mejor seguir con el flujo normal de parecidos.
     */
    public function testUnSustitutoNoPublicadoSeIgnora(): void
    {
        WpStubs::$postMeta[self::INMUEBLE]['_homlity_replacement_property_id'] = '777';
        WpStubs::$postObjects[777] = new \WP_Post(['ID' => 777, 'post_type' => 'property']);
        WpStubs::$postStatuses[777] = 'draft';
        $this->parecidos(5);

        $contexto = $this->service->buildRecoveryContext(self::INMUEBLE);

        self::assertSame(200, $contexto['status_code']);
        self::assertSame('', $contexto['redirect_url']);
    }

    // ── Código HTTP y robots ─────────────────────────────────────────────────

    /**
     * Con alternativas de sobra la página tiene contenido propio y merece
     * indexarse: es la que va a posicionar por "apartamentos en El Poblado".
     */
    public function testConSuficientesParecidosLaPaginaSeIndexa(): void
    {
        $this->parecidos(5);

        $contexto = $this->service->buildRecoveryContext(self::INMUEBLE);

        self::assertSame(200, $contexto['status_code']);
        self::assertSame('index,follow', $contexto['robots']);
        self::assertSame('self', $contexto['canonical']);
        self::assertTrue($contexto['has_enough_results']);
    }

    /**
     * Sin alternativas suficientes la página no aporta nada. Qué hacer es
     * configurable, y las tres opciones tienen consecuencias distintas en
     * Search Console.
     *
     * @dataProvider accionesSinResultados
     */
    public function testSinSuficientesParecidosSeAplicaLaAccionConfigurada(
        string $accion,
        int $codigo,
        string $robots
    ): void {
        WpStubs::setOption('homlity_recovery_no_results_action', $accion);
        $this->parecidos(1);

        $contexto = $this->service->buildRecoveryContext(self::INMUEBLE);

        self::assertSame($codigo, $contexto['status_code']);
        self::assertSame($robots, $contexto['robots']);
        self::assertSame('', $contexto['canonical'], 'sin contenido propio no hay canónico');
        self::assertFalse($contexto['has_enough_results']);
    }

    /** @return array<string,array{0:string,1:int,2:string}> */
    public static function accionesSinResultados(): array
    {
        return [
            'mostrar sin indexar' => ['noindex', 200, 'noindex,follow'],
            'no encontrado'       => ['404', 404, 'noindex,follow'],
            'desaparecido'        => ['410', 410, 'noindex,follow'],
            'valor inventado'     => ['lo-que-sea', 200, 'noindex,follow'],
        ];
    }

    /** El umbral es configurable y decide de verdad qué se indexa. */
    public function testElMinimoConfiguradoDecideSiLaPaginaSeIndexa(): void
    {
        $this->parecidos(2);
        self::assertSame('noindex,follow', $this->service->buildRecoveryContext(self::INMUEBLE)['robots']);

        RetiredPropertyRecoveryService::invalidateCache(self::INMUEBLE);
        WpStubs::setOption('homlity_recovery_min_results', 2);

        self::assertSame('index,follow', $this->service->buildRecoveryContext(self::INMUEBLE)['robots']);
    }

    /** Un mínimo de 0 haría que cualquier página vacía se indexara. */
    public function testElMinimoNuncaBajaDeUno(): void
    {
        WpStubs::setOption('homlity_recovery_min_results', 0);
        $this->parecidos(0);

        self::assertFalse($this->service->buildRecoveryContext(self::INMUEBLE)['has_enough_results']);
    }

    // ── Caché ────────────────────────────────────────────────────────────────

    /**
     * El contexto cuesta varias consultas por nivel de la cascada. Sin caché,
     * cada visita a una URL muerta —y las hay a cientos tras un cambio de
     * CRM— las repite todas.
     */
    public function testElContextoSeMemoriza(): void
    {
        $consultas = 0;
        WpStubs::$queryResolver = static function (array $args) use (&$consultas): array {
            $consultas++;

            return ['posts' => range(1, 5)];
        };

        $this->service->buildRecoveryContext(self::INMUEBLE);
        $primeras = $consultas;
        $this->service->buildRecoveryContext(self::INMUEBLE);

        self::assertGreaterThan(0, $primeras);
        self::assertSame($primeras, $consultas, 'la segunda vez no consulta');
    }

    /** Al republicar el inmueble hay que olvidar lo memorizado. */
    public function testInvalidarLaCacheObligaARecalcular(): void
    {
        $this->parecidos(5);
        $this->service->buildRecoveryContext(self::INMUEBLE);

        RetiredPropertyRecoveryService::invalidateCache(self::INMUEBLE);
        $this->parecidos(1);

        self::assertFalse($this->service->buildRecoveryContext(self::INMUEBLE)['has_enough_results']);
    }

    /** La caché es por inmueble: invalidar uno no puede vaciar la de los demás. */
    public function testInvalidarUnInmuebleNoAfectaAOtro(): void
    {
        $this->inmuebleRetirado(888);
        $this->parecidos(5);
        $this->service->buildRecoveryContext(self::INMUEBLE);
        $this->service->buildRecoveryContext(888);

        RetiredPropertyRecoveryService::invalidateCache(self::INMUEBLE);
        $this->parecidos(0);

        self::assertTrue(
            $this->service->buildRecoveryContext(888)['has_enough_results'],
            'el otro sigue respondiendo desde la caché'
        );
    }

    // ── Datos extraídos de la ficha ──────────────────────────────────────────

    public function testSeExtraenLosIdentificadoresYLosNombresDeCadaTaxonomia(): void
    {
        $datos = $this->service->extractPropertyData(self::INMUEBLE);

        self::assertSame([30], $datos['city_term_ids']);
        self::assertSame('Medellín', $datos['city_name']);
        self::assertSame([10], $datos['operation_term_ids']);
        self::assertSame(['venta'], $datos['operation_slugs'], 'los slugs eligen la clave de precio');
    }

    public function testSeExtraenLosMetadatosDelInmueble(): void
    {
        $datos = $this->service->extractPropertyData(self::INMUEBLE);

        self::assertSame('250000000', $datos['price_sale']);
        self::assertSame('3', $datos['bedrooms']);
        self::assertSame('HOM-123', $datos['code']);
    }

    /**
     * Una taxonomía sin registrar devuelve WP_Error. Tratarlo como lista haría
     * que `absint()` operase sobre un objeto y tumbara la página.
     */
    public function testUnErrorDeTaxonomiaSeTrataComoAusencia(): void
    {
        WpStubs::$postTermsError[PropertyTaxonomies::TAXONOMY_CITY] = 'invalid taxonomy';

        $datos = $this->service->extractPropertyData(self::INMUEBLE);

        self::assertSame([], $datos['city_term_ids']);
        self::assertSame('', $datos['city_name']);
    }

    public function testUnIdentificadorInvalidoNoExtraeNada(): void
    {
        self::assertSame([], $this->service->extractPropertyData(0));
    }

    // ── Etiquetas para buscadores ────────────────────────────────────────────

    /** El título es lo que se ve en el resultado de búsqueda. */
    public function testElTituloCombinaTipoOperacionYUbicacion(): void
    {
        $this->parecidos(5);

        $titulo = $this->service->buildRecoveryContext(self::INMUEBLE)['meta_title'];

        self::assertStringContainsString('El Poblado, Medellín', $titulo);
        self::assertStringContainsString('Apartamento en venta', $titulo);
    }

    /** Sin tipo ni operación, al menos la ubicación. */
    public function testSinTipoNiOperacionElTituloUsaSoloLaUbicacion(): void
    {
        unset(
            WpStubs::$postTerms[self::INMUEBLE][PropertyTaxonomies::TAXONOMY_TYPE],
            WpStubs::$postTerms[self::INMUEBLE][PropertyTaxonomies::TAXONOMY_OPERATION]
        );
        $this->parecidos(5);

        self::assertSame(
            'Propiedades similares en El Poblado, Medellín',
            $this->service->buildRecoveryContext(self::INMUEBLE)['meta_title']
        );
    }

    /** Y sin nada, un título genérico: no puede quedar vacío. */
    public function testSinNingunDatoElTituloEsGenerico(): void
    {
        WpStubs::$postTerms[self::INMUEBLE] = [];
        $this->parecidos(5);

        self::assertSame(
            'Propiedades similares disponibles',
            $this->service->buildRecoveryContext(self::INMUEBLE)['meta_title']
        );
    }

    public function testLaDescripcionMencionaLaUbicacionCuandoLaHay(): void
    {
        $this->parecidos(5);

        $descripcion = $this->service->buildRecoveryContext(self::INMUEBLE)['meta_description'];

        self::assertStringContainsString('El Poblado, Medellín', $descripcion);
        self::assertStringContainsString('ya no está disponible', $descripcion);
    }

    public function testSinUbicacionLaDescripcionSigueSiendoUtil(): void
    {
        WpStubs::$postTerms[self::INMUEBLE] = [];
        $this->parecidos(5);

        $descripcion = $this->service->buildRecoveryContext(self::INMUEBLE)['meta_description'];

        self::assertStringContainsString('ya no está disponible', $descripcion);
        self::assertStringNotContainsString(' en .', $descripcion, 'sin huecos de ubicación');
    }

    // ── Enlaces internos ─────────────────────────────────────────────────────

    /**
     * Los enlaces son la razón de que la página valga la pena: reparten hacia
     * los archivos el posicionamiento que apuntaba a la ficha muerta.
     */
    public function testSeConstruyenLosCuatroEnlacesDeArchivoMasElCatalogo(): void
    {
        $this->parecidos(5);

        $enlaces = $this->service->buildRecoveryContext(self::INMUEBLE)['internal_links'];

        self::assertSame([
            'https://example.test/inmuebles/ciudad/medellin/',
            'https://example.test/inmuebles/barrios/el-poblado/',
            'https://example.test/inmuebles/gestion/venta/',
            'https://example.test/inmuebles/tipo/apartamento/',
            'https://example.test/inmuebles/',
        ], array_column($enlaces, 'url'));
    }

    /** El enlace al catálogo va siempre, aunque el inmueble no tenga taxonomías. */
    public function testElEnlaceAlCatalogoSiempreEsta(): void
    {
        WpStubs::$postTerms[self::INMUEBLE] = [];
        $this->parecidos(5);

        $enlaces = $this->service->buildRecoveryContext(self::INMUEBLE)['internal_links'];

        self::assertCount(1, $enlaces);
        self::assertSame('https://example.test/inmuebles/', $enlaces[0]['url']);
    }

    /**
     * Si el término se borró, enlazar su archivo llevaría a un 404: justo lo
     * que esta página existe para evitar.
     */
    public function testUnTerminoBorradoNoGeneraEnlace(): void
    {
        unset(WpStubs::$terms[PropertyTaxonomies::TAXONOMY_CITY][30]);
        $this->parecidos(5);

        $urls = array_column($this->service->buildRecoveryContext(self::INMUEBLE)['internal_links'], 'url');

        // La lista completa, no sólo la ausencia de la URL correcta: emitir el
        // enlace con el slug vacío —`/ciudad//`— sería igual de roto.
        self::assertSame([
            'https://example.test/inmuebles/barrios/el-poblado/',
            'https://example.test/inmuebles/gestion/venta/',
            'https://example.test/inmuebles/tipo/apartamento/',
            'https://example.test/inmuebles/',
        ], $urls);
    }

    // ── Resumen del inmueble ─────────────────────────────────────────────────

    /** El visitante tiene que reconocer qué inmueble buscaba. */
    public function testElResumenDescribeElInmuebleRetirado(): void
    {
        $this->parecidos(5);

        $resumen = $this->service->buildRecoveryContext(self::INMUEBLE)['property_summary'];

        self::assertSame('Apartamento en El Poblado', $resumen['title']);
        self::assertSame('Medellín', $resumen['city_name']);
        self::assertSame('3', $resumen['bedrooms']);
        self::assertSame('HOM-123', $resumen['code']);
    }

    public function testElResumenFormateaElPrecioDeVenta(): void
    {
        $this->parecidos(5);

        $resumen = $this->service->buildRecoveryContext(self::INMUEBLE)['property_summary'];

        self::assertNotSame('', $resumen['price_formatted']);
    }

    /** Un inmueble sólo de arriendo tiene que mostrar su canon. */
    public function testElResumenUsaElCanonCuandoNoHayPrecioDeVenta(): void
    {
        WpStubs::$postMeta[self::INMUEBLE]['_property_price_sale'] = '0';
        WpStubs::$postMeta[self::INMUEBLE]['_property_price_rent'] = '2000000';
        WpStubs::addFilter(
            'homlity_plugin_format_price',
            static fn($precio, $moneda = null): string => 'CANON:' . $precio
        );
        $this->parecidos(5);

        $resumen = $this->service->buildRecoveryContext(self::INMUEBLE)['property_summary'];

        self::assertSame('CANON:2000000', $resumen['price_formatted']);
    }

    public function testSinPrecioElResumenNoInventaUno(): void
    {
        WpStubs::$postMeta[self::INMUEBLE]['_property_price_sale'] = '0';
        $this->parecidos(5);

        self::assertSame('', $this->service->buildRecoveryContext(self::INMUEBLE)['property_summary']['price_formatted']);
    }

    // ── Traspaso a la búsqueda de parecidos ──────────────────────────────────

    /** El inmueble retirado no puede aparecer entre sus propias alternativas. */
    public function testElInmuebleRetiradoSeExcluyeDeSusParecidos(): void
    {
        $sondas = [];
        WpStubs::$queryResolver = static function (array $args) use (&$sondas): array {
            $sondas[] = $args;

            return ['posts' => range(1, 5)];
        };

        $this->service->buildRecoveryContext(self::INMUEBLE);

        self::assertSame([self::INMUEBLE], $sondas[0]['post__not_in']);
    }

    public function testElContextoDevuelveLaConsultaYElNivelAlcanzado(): void
    {
        $this->parecidos(5);

        $contexto = $this->service->buildRecoveryContext(self::INMUEBLE);

        self::assertSame(SimilarPropertiesQueryBuilder::LEVEL_STRONG, $contexto['fallback_level']);
        self::assertIsArray($contexto['similar_args']);
        self::assertSame(5, $contexto['similar_count']);
        self::assertNotEmpty($contexto['applied_filters']);
    }
}
