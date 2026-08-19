<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Reading the filters out of the public URL.
 *
 * The archive is indexed and shared with filters in the query string, so every
 * alias below is a live URL somewhere. Dropping one turns a shared link into an
 * unfiltered catalogue without any visible error.
 */
final class PropertySearchRequestParamsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    /** @return array<string,mixed> */
    private function params(): array
    {
        return (new PropertySearchService())->currentQueryParams();
    }

    // ── Keyword ──────────────────────────────────────────────────────────────

    public function testLaBusquedaLibreSeLeeDeQ(): void
    {
        $_GET['q'] = 'apartamento con balcón';

        self::assertSame('apartamento con balcón', $this->params()['search']);
    }

    /** WordPress' own search parameter has to keep working. */
    public function testLaBusquedaLibreTambienSeLeeDeS(): void
    {
        $_GET['s'] = 'finca';

        self::assertSame('finca', $this->params()['search']);
    }

    public function testQTienePrioridadSobreS(): void
    {
        $_GET['q'] = 'lo que pidió el visitante';
        $_GET['s'] = 'otra cosa';

        self::assertSame('lo que pidió el visitante', $this->params()['search']);
    }

    public function testEnUnaBusquedaDeWordPressSeTomaElTerminoBuscado(): void
    {
        WpStubs::$isSearch = true;
        WpStubs::$searchQuery = 'penthouse';

        self::assertSame('penthouse', $this->params()['search']);
    }

    // ── Taxonomy aliases ─────────────────────────────────────────────────────

    /**
     * Every public alias — the Spanish one used in the pretty URLs and the
     * English one used by the widgets — must resolve to the same filter.
     *
     * @return array<string,array{0:string,1:string,2:string}>
     */
    public static function aliasProvider(): array
    {
        return [
            'categoria'             => ['categoria', 'category', PropertyTaxonomies::TAXONOMY_CATEGORY],
            'property_category'     => ['property_category', 'category', PropertyTaxonomies::TAXONOMY_CATEGORY],
            'gestion'               => ['gestion', 'operation', PropertyTaxonomies::TAXONOMY_OPERATION],
            'property_operation'    => ['property_operation', 'operation', PropertyTaxonomies::TAXONOMY_OPERATION],
            'tipo'                  => ['tipo', 'type', PropertyTaxonomies::TAXONOMY_TYPE],
            'property_type'         => ['property_type', 'type', PropertyTaxonomies::TAXONOMY_TYPE],
            'etiquetas'             => ['etiquetas', 'tag', PropertyTaxonomies::TAXONOMY_TAG],
            'property_tag'          => ['property_tag', 'tag', PropertyTaxonomies::TAXONOMY_TAG],
            'caracteristica'        => ['caracteristica', 'feature', PropertyTaxonomies::TAXONOMY_FEATURE],
            'property_feature'      => ['property_feature', 'feature', PropertyTaxonomies::TAXONOMY_FEATURE],
            'pais'                  => ['pais', 'country', PropertyTaxonomies::TAXONOMY_COUNTRY],
            'property_country'      => ['property_country', 'country', PropertyTaxonomies::TAXONOMY_COUNTRY],
            'departamento'          => ['departamento', 'state', PropertyTaxonomies::TAXONOMY_STATE],
            'property_state'        => ['property_state', 'state', PropertyTaxonomies::TAXONOMY_STATE],
            'ciudad'                => ['ciudad', 'city', PropertyTaxonomies::TAXONOMY_CITY],
            'property_city'         => ['property_city', 'city', PropertyTaxonomies::TAXONOMY_CITY],
            'barrios'               => ['barrios', 'neighborhood', PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD],
            'property_neighborhood' => ['property_neighborhood', 'neighborhood', PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD],
            'cercanias'             => ['cercanias', 'nearby', PropertyTaxonomies::TAXONOMY_NEARBY],
            'property_nearby'       => ['property_nearby', 'nearby', PropertyTaxonomies::TAXONOMY_NEARBY],
        ];
    }

    /** @dataProvider aliasProvider */
    public function testCadaAliasPublicoResuelveASuFiltro(string $requestKey, string $paramKey, string $taxonomy): void
    {
        WpStubs::setTerm(41, $taxonomy, 'termino-41');
        $_GET[$requestKey] = '41';

        self::assertSame(41, $this->params()[$paramKey] ?? null);
    }

    /** URLs share terms by slug, not only by id. */
    public function testUnSlugSeResuelveASuIdentificadorDeTermino(): void
    {
        WpStubs::setTerm(55, PropertyTaxonomies::TAXONOMY_CITY, 'guatape', 'Guatapé');
        $_GET['ciudad'] = 'guatape';

        self::assertSame(55, $this->params()['city']);
    }

    public function testUnSlugInexistenteNoAgregaFiltro(): void
    {
        $_GET['ciudad'] = 'ciudad-que-no-existe';

        self::assertArrayNotHasKey('city', $this->params());
    }

    public function testVariosTerminosSeparadosPorComaSeLeenJuntos(): void
    {
        WpStubs::setTerm(1, PropertyTaxonomies::TAXONOMY_TYPE, 'casa');
        WpStubs::setTerm(2, PropertyTaxonomies::TAXONOMY_TYPE, 'apartamento');
        $_GET['tipo'] = '1,2';

        self::assertSame([1, 2], $this->params()['type']);
    }

    public function testUnArregloDeTerminosSeLeeCompleto(): void
    {
        WpStubs::setTerm(1, PropertyTaxonomies::TAXONOMY_TAG);
        WpStubs::setTerm(2, PropertyTaxonomies::TAXONOMY_TAG);
        $_GET['etiquetas'] = ['1', '2'];

        self::assertSame([1, 2], $this->params()['tag']);
    }

    /** Both aliases of the same filter must accumulate, not overwrite. */
    public function testLosDosAliasDeUnMismoFiltroSeSuman(): void
    {
        WpStubs::setTerm(1, PropertyTaxonomies::TAXONOMY_TYPE);
        WpStubs::setTerm(2, PropertyTaxonomies::TAXONOMY_TYPE);
        $_GET['tipo'] = '1';
        $_GET['property_type'] = '2';

        self::assertSame([1, 2], $this->params()['type']);
    }

    // ── Pretty URLs ──────────────────────────────────────────────────────────

    /**
     * The SEO archive routes (/inmuebles/venta/apartamento/…) arrive as query
     * vars rather than query-string parameters.
     */
    public function testLasRutasSeoLleganComoVariablesDeConsulta(): void
    {
        WpStubs::setTerm(7, PropertyTaxonomies::TAXONOMY_OPERATION, 'venta');
        WpStubs::$queryVars['gestion'] = 'venta';

        self::assertSame(7, $this->params()['operation']);
    }

    /** A taxonomy archive filters by the term being browsed. */
    public function testUnArchivoDeTaxonomiaFiltraPorSuTermino(): void
    {
        WpStubs::$queriedObject = new \WP_Term(88, PropertyTaxonomies::TAXONOMY_CITY, 'medellin', 'Medellín');

        self::assertSame(88, $this->params()['city']);
    }

    // ── Numeric aliases ──────────────────────────────────────────────────────

    /** @return array<string,array{0:string,1:string}> */
    public static function numericAliasProvider(): array
    {
        return [
            'precio_min' => ['precio_min', 'price_min'],
            'price_min'  => ['price_min', 'price_min'],
            'precio_max' => ['precio_max', 'price_max'],
            'price_max'  => ['price_max', 'price_max'],
            'alcobas'    => ['alcobas', 'bedrooms'],
            'bedrooms'   => ['bedrooms', 'bedrooms'],
            'banos'      => ['banos', 'bathrooms'],
            'bathrooms'  => ['bathrooms', 'bathrooms'],
            'garajes'    => ['garajes', 'parking'],
            'parking'    => ['parking', 'parking'],
            'area_min'   => ['area_min', 'area_min'],
            'area_max'   => ['area_max', 'area_max'],
        ];
    }

    /** @dataProvider numericAliasProvider */
    public function testCadaAliasNumericoResuelveASuFiltro(string $requestKey, string $paramKey): void
    {
        $_GET[$requestKey] = '7';

        self::assertSame(7, $this->params()[$paramKey] ?? null);
    }

    public function testUnValorNumericoVacioNoAgregaFiltro(): void
    {
        $_GET['precio_min'] = '';

        self::assertArrayNotHasKey('price_min', $this->params());
    }

    /** A crafted value must not reach the query as text. */
    public function testUnValorNumericoInvalidoSeNeutraliza(): void
    {
        $_GET['alcobas'] = '3; DROP TABLE wp_posts';

        self::assertSame(3, $this->params()['bedrooms']);
    }

    // ── Locality ─────────────────────────────────────────────────────────────

    public function testLaLocalidadSeResuelveALosIdentificadoresPublicados(): void
    {
        WpStubs::$postObjects[70] = new \WP_Post([
            'ID' => 70, 'post_type' => 'property_locality', 'post_status' => 'publish', 'post_name' => 'el-poblado',
        ]);
        $_GET['localidades'] = 'el-poblado';

        self::assertSame(70, $this->params()['locality']);
    }

    public function testUnaLocalidadNoPublicadaNoLlegaAlFiltro(): void
    {
        WpStubs::$postObjects[70] = new \WP_Post([
            'ID' => 70, 'post_type' => 'property_locality', 'post_status' => 'draft', 'post_name' => 'borrador',
        ]);
        $_GET['localidades'] = 'borrador';

        self::assertArrayNotHasKey('locality', $this->params());
    }

    // ── Pagination ───────────────────────────────────────────────────────────

    public function testLaPaginaSeLeeDeLaVariableDeConsulta(): void
    {
        WpStubs::$queryVars['paged'] = 4;

        self::assertSame(4, $this->params()['page']);
    }

    public function testSinPaginaSeAsumeLaPrimera(): void
    {
        self::assertSame(1, $this->params()['page']);
    }

    // ── End to end ───────────────────────────────────────────────────────────

    /**
     * A shared URL has to survive the whole trip: query string → params →
     * WP_Query arguments.
     */
    public function testUnaUrlCompartidaLlegaCompletaHastaLaConsulta(): void
    {
        WpStubs::setTerm(3, PropertyTaxonomies::TAXONOMY_OPERATION, 'arriendo', 'Arriendo');
        WpStubs::setTerm(8, PropertyTaxonomies::TAXONOMY_TYPE, 'apartamento', 'Apartamento');
        WpStubs::setTerm(12, PropertyTaxonomies::TAXONOMY_CITY, 'guatape', 'Guatapé');
        $_GET = [
            'gestion' => 'arriendo',
            'tipo' => 'apartamento',
            'ciudad' => 'guatape',
            'precio_min' => '1000000',
            'alcobas' => '2',
            'q' => 'balcón',
        ];

        $service = new PropertySearchService();
        $args = $service->buildQueryArgs(['query_mode' => 'current']);

        $taxonomies = array_column(
            array_filter($args['tax_query'], 'is_array'),
            'taxonomy'
        );
        self::assertContains(PropertyTaxonomies::TAXONOMY_OPERATION, $taxonomies);
        self::assertContains(PropertyTaxonomies::TAXONOMY_TYPE, $taxonomies);
        self::assertContains(PropertyTaxonomies::TAXONOMY_CITY, $taxonomies);

        $metaKeys = array_column(array_filter($args['meta_query'], 'is_array'), 'key');
        // Rent search, so the price bound must land on the rent meta.
        self::assertContains('_property_price_rent', $metaKeys);
        self::assertContains('_property_bedrooms', $metaKeys);
        self::assertSame('balcón', $args['homlity_keyword_search']);
    }
}
