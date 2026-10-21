<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\SearchRobotsService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Services\SeoGeoSettingsService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/** Los archivos útiles siguen indexables; filtros, combinaciones y vacíos obedecen los ajustes. */
final class SearchRobotsServiceTest extends TestCase
{
    private mixed $originalQuery;
    private array $originalGet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalQuery = $GLOBALS['wp_query'] ?? null;
        $this->originalGet = $_GET;
        $_GET = [];
        WpStubs::$postTypeArchive = 'property';
        $GLOBALS['wp_query'] = new \WP_Query();
        $GLOBALS['wp_query']->found_posts = 10;
    }

    protected function tearDown(): void
    {
        $GLOBALS['wp_query'] = $this->originalQuery;
        $_GET = $this->originalGet;
        parent::tearDown();
    }

    private function settings(array $settings): void
    {
        WpStubs::setOption(SeoGeoSettingsService::OPTION_NAME, $settings);
    }

    private function assertNoindex(bool $expected): void
    {
        $service = new SearchRobotsService();
        $rankMath = ['index' => 'index', 'follow' => 'nofollow', 'max-image-preview' => 'large'];
        $yoast = 'index, nofollow, max-image-preview:large';
        $core = ['index' => true, 'nofollow' => true, 'max-image-preview' => 'large'];
        self::assertSame($expected ? ['index' => 'noindex', 'follow' => 'follow', 'max-image-preview' => 'large'] : $rankMath, $service->filterRankMathRobots($rankMath));
        self::assertSame($expected ? 'noindex, follow, max-image-preview:large' : $yoast, $service->filterYoastRobots($yoast));
        self::assertSame($expected ? ['max-image-preview' => 'large', 'noindex' => true, 'follow' => true] : $core, $service->filterCoreRobots($core));
    }

    public function testArchivoYUnSoloSegmentoSonIndexablesPorDefecto(): void
    {
        $this->assertNoindex(false);
        foreach (['gestion', 'tipo', 'ciudad', 'barrios'] as $segment) {
            WpStubs::$queryVars = [$segment => 'bogota'];
            $this->assertNoindex(false);
        }
    }

    public function testCombinacionesSoloSeIndexanSiSeActivaElAjuste(): void
    {
        foreach ([['gestion' => 'venta', 'tipo' => 'casa'], ['tipo' => 'casa', 'ciudad' => 'bogota'],
            ['gestion' => 'venta', 'tipo' => 'casa', 'ciudad' => 'bogota', 'barrios' => 'chico']] as $vars) {
            WpStubs::$queryVars = $vars;
            $this->settings(['seo_index_search' => '']);
            $this->assertNoindex(true);
            $this->settings(['seo_index_search' => '1']);
            $this->assertNoindex(false);
        }
    }

    public function testFiltrosGetObedecenElInterruptorInclusoSiSePermitenCombinaciones(): void
    {
        foreach (['price_min', 'precio_max', 'habitaciones', 'alcobas', 'bedrooms', 'banos', 'area_min',
            'operacion', 'gestion', 'property_type', 'barrios', 'orden', 'orderby', 'pagina', 'paged', 'page', 'q'] as $key) {
            $_GET = [$key => '2'];
            $this->settings(['seo_index_search' => '1', 'seo_noindex_filtered' => '1']);
            $this->assertNoindex(true);
            $this->settings(['seo_index_search' => '1', 'seo_noindex_filtered' => '']);
            $this->assertNoindex(false);
        }
    }

    public function testParametrosVaciosYDeSeguimientoNoActivanNoindex(): void
    {
        $_GET = ['utm_source' => 'newsletter', 'gclid' => 'abc', 'price_min' => '', 'tipo' => []];
        $this->assertNoindex(false);
        $_GET = ['price_min' => '0'];
        $this->assertNoindex(true);
    }

    public function testResultadosVaciosObedecenSuPropioInterruptor(): void
    {
        $GLOBALS['wp_query']->found_posts = 0;
        $this->assertNoindex(true);
        $this->settings(['seo_noindex_empty' => '']);
        $this->assertNoindex(false);
    }

    public function testNoSuponeQueLaConsultaEstaVaciaCuandoNoExiste(): void
    {
        $GLOBALS['wp_query'] = null;
        $this->assertNoindex(false);
    }

    public function testDetectaLaPaginaConfiguradaYLosSegmentosDeLasRewrites(): void
    {
        WpStubs::$postTypeArchive = '';
        WpStubs::$currentPageId = 123;
        WpStubs::$queryVars = ['homlity_property_archive' => '1'];
        $_GET = ['price_min' => '1000'];
        $this->assertNoindex(true);
        $_GET = [];
        $this->assertNoindex(false);
        WpStubs::$queryVars = ['gestion' => 'venta', 'ciudad' => 'bogota'];
        $this->assertNoindex(true);
    }

    public function testPaginaConfiguradaConsultaInmueblesConLosCriteriosDelListado(): void
    {
        WpStubs::setOption('homlity_plugin_archive_page_id', 123);
        WpStubs::$postTypeArchive = '';
        WpStubs::$currentPageId = 123;
        $GLOBALS['wp_query']->found_posts = 1;
        $this->settings(['seo_index_search' => '1', 'seo_noindex_filtered' => '']);
        foreach ([PropertyTaxonomies::TAXONOMY_OPERATION, PropertyTaxonomies::TAXONOMY_TYPE,
            PropertyTaxonomies::TAXONOMY_CITY, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD] as $taxonomy) {
            WpStubs::$registeredTaxonomies[] = $taxonomy;
        }
        WpStubs::setTerm(1, PropertyTaxonomies::TAXONOMY_OPERATION, 'venta', 'Venta');
        WpStubs::setTerm(2, PropertyTaxonomies::TAXONOMY_TYPE, 'casa', 'Casa');
        WpStubs::setTerm(3, PropertyTaxonomies::TAXONOMY_CITY, 'bogota', 'Bogotá');
        WpStubs::setTerm(4, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, 'chico', 'Chicó');
        WpStubs::$queryVars = ['homlity_property_archive' => '1', 'gestion' => 'venta',
            'tipo' => 'casa', 'ciudad' => 'bogota', 'barrios' => 'chico'];
        $_GET = ['precio_min' => '1000', 'alcobas' => '3', 'area_min' => '50', 'q' => 'terraza'];
        $queries = [];
        $found = 0;
        WpStubs::$queryResolver = static function (array $args) use (&$queries, &$found): array {
            $queries[] = $args;
            return ['posts' => $found > 0 ? [501] : [], 'found_posts' => $found];
        };
        $this->assertNoindex(true);
        self::assertCount(1, $queries, 'Los tres filtros comparten una única consulta.');
        $search = new PropertySearchService();
        $expected = $search->buildQueryArgs(array_merge($search->currentQueryParams(), ['per_page' => 1, 'page' => 1]));
        $expected['fields'] = 'ids';
        $expected['no_found_rows'] = false;
        self::assertSame($expected, $queries[0]);
        self::assertCount(5, $queries[0]['tax_query']);
        self::assertSame('terraza', $queries[0]['homlity_keyword_search']);
        self::assertSame(1, $queries[0]['posts_per_page']);
        self::assertSame(1, $GLOBALS['wp_query']->found_posts, 'No sustituye la consulta de la página.');

        // Una instancia nueva representa otra petición, esta vez con inmuebles.
        $found = 7;
        $this->assertNoindex(false);
        self::assertCount(2, $queries, 'También memoiza la decisión indexable.');
    }

    public function testPaginaConfiguradaSinSegmentosTambienCompruebaElListado(): void
    {
        WpStubs::setOption('homlity_plugin_archive_page_id', 123);
        WpStubs::$postTypeArchive = '';
        WpStubs::$currentPageId = 123;
        $GLOBALS['wp_query']->found_posts = 1;
        WpStubs::$queryResolver = static fn(array $args): array => ['found_posts' => 0];
        $this->assertNoindex(true);
    }

    public function testNoConsultaInmueblesSiSeDesactivaNoindexDeVaciosOEsArchivoCpt(): void
    {
        WpStubs::setOption('homlity_plugin_archive_page_id', 123);
        $queries = 0;
        WpStubs::$queryResolver = static function (array $args) use (&$queries): array {
            $queries++;
            return ['found_posts' => 0];
        };
        $GLOBALS['wp_query']->found_posts = 10;
        $this->assertNoindex(false);
        $GLOBALS['wp_query']->found_posts = 0;
        $this->assertNoindex(true);
        self::assertSame(0, $queries, 'El archivo CPT conserva el total de la consulta principal.');
        WpStubs::$postTypeArchive = '';
        WpStubs::$currentPageId = 123;
        $this->settings(['seo_noindex_empty' => '']);
        $this->assertNoindex(false);
        self::assertSame(0, $queries);
    }

    public function testNoAlteraOtrasPaginasNiFichas(): void
    {
        $_GET = ['price_min' => '1000'];
        WpStubs::$postTypeArchive = '';
        $this->assertNoindex(false);
        WpStubs::$postTypeArchive = 'post';
        $this->assertNoindex(false);
        WpStubs::$singularPostType = 'property';
        WpStubs::$queryVars = ['gestion' => 'venta', 'tipo' => 'casa'];
        $this->assertNoindex(false);
    }

    public function testRegistraLosFiltrosDeLosTresProveedoresYLaCabecera(): void
    {
        (new SearchRobotsService())->register();
        foreach (['rank_math/robots', 'wpseo_robots', 'wp_robots', 'template_redirect'] as $hook) {
            self::assertArrayHasKey($hook, WpStubs::$filters);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCoreDejaLosRobotsAlPluginSeoActivo(): void
    {
        define('WPSEO_VERSION', 'test');
        $_GET = ['price_min' => '1000'];
        self::assertSame(['index' => true], (new SearchRobotsService())->filterCoreRobots(['index' => true]));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCabeceraYFiltrosCompartenLaConsultaYLaDecision(): void
    {
        eval('namespace Homlity\\PluginInmobiliario\\Services; function header($value, $replace = true) { $GLOBALS["homlity_test_headers"][] = [$value, $replace]; }');
        $GLOBALS['homlity_test_headers'] = [];
        WpStubs::setOption('homlity_plugin_archive_page_id', 123);
        WpStubs::$postTypeArchive = '';
        WpStubs::$currentPageId = 123;
        $GLOBALS['wp_query']->found_posts = 1;
        $queries = 0;
        WpStubs::$queryResolver = static function (array $args) use (&$queries): array {
            $queries++;
            return ['found_posts' => 0];
        };
        $service = new SearchRobotsService();
        $service->sendRobotsHeader();
        self::assertSame([['X-Robots-Tag: noindex, follow', true]], $GLOBALS['homlity_test_headers']);
        self::assertSame('noindex, follow', $service->filterYoastRobots('index, follow'));
        self::assertSame('noindex', $service->filterRankMathRobots(['index' => 'index'])['index']);
        self::assertTrue($service->filterCoreRobots([])['noindex']);
        self::assertSame(1, $queries);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCabeceraSoloSeEmiteCuandoCorrespondeNoindex(): void
    {
        // Interceptar header permite comprobar el contrato HTTP sin depender de un servidor web.
        eval('namespace Homlity\\PluginInmobiliario\\Services; function header($value, $replace = true) { $GLOBALS["homlity_test_headers"][] = [$value, $replace]; }');
        $GLOBALS['homlity_test_headers'] = [];
        $service = new SearchRobotsService();
        $service->sendRobotsHeader();
        self::assertSame([], $GLOBALS['homlity_test_headers']);
        $_GET = ['price_min' => '1000'];
        $service = new SearchRobotsService();
        $service->sendRobotsHeader();
        self::assertSame([['X-Robots-Tag: noindex, follow', true]], $GLOBALS['homlity_test_headers']);
        $this->settings(['seo_noindex_filtered' => '']);
        $service = new SearchRobotsService();
        $service->sendRobotsHeader();
        self::assertCount(1, $GLOBALS['homlity_test_headers']);
    }
}
