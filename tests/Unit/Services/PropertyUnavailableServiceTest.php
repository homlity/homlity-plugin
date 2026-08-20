<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyUnavailableService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * El desvío a la página de recuperación cuando el inmueble ya no está.
 *
 * Aquí se decide si una URL entra en el modo recuperación y qué se le cuenta a
 * los buscadores. Lo que decide la entrada son tres cosas: que el inmueble esté
 * marcado como no disponible, que la URL de un 404 corresponda a una ficha
 * despublicada, y que quien mira no sea del equipo.
 *
 * Nota de alcance: `maybeShowUnavailable()` termina en `exit` en todos sus
 * caminos salvo el de redirección, y `exit` no se puede interceptar desde una
 * prueba. Se cubren, por tanto, las salidas tempranas, la redirección 301 y
 * todo lo que el método deja escrito para `wp_head` y para los plugins de SEO.
 */
final class PropertyUnavailableServiceTest extends TestCase
{
    private const INMUEBLE = 321;

    private PropertyUnavailableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PropertyUnavailableService();
        unset($GLOBALS['homlity_property_recovery_context']);
        $GLOBALS['wp_query'] = new \WP_Query();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['homlity_property_recovery_context'], $_SERVER['REQUEST_URI']);
        parent::tearDown();
    }

    /** Contexto de recuperación como lo deja RetiredPropertyRecoveryService. */
    private function contexto(array $overrides = []): array
    {
        return array_replace([
            'is_retired' => true,
            'property_id' => self::INMUEBLE,
            'status_code' => 200,
            'robots' => 'index,follow',
            'canonical' => 'self',
            'redirect_url' => '',
            'meta_title' => 'Propiedades similares en El Poblado, Medellín',
            'meta_description' => 'Este inmueble ya no está disponible.',
        ], $overrides);
    }

    private function publicarContexto(array $overrides = []): void
    {
        $GLOBALS['homlity_property_recovery_context'] = $this->contexto($overrides);
    }

    private function invocarPrivado(string $metodo, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod(PropertyUnavailableService::class, $metodo);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->service, ...$args);
    }

    // ── Salidas tempranas ────────────────────────────────────────────────────

    /**
     * Las dos pruebas siguientes montan un inmueble con sustituto publicado —el
     * único camino observable, porque termina en redirección y no en `exit`— y
     * comprueban que la salida temprana corta ANTES de llegar a él.
     */
    public function testUnaPaginaQueNoEsNi404NiInmuebleNoDisponibleNoHaceNada(): void
    {
        $this->prepararInmuebleConSustituto();
        // Ni 404 ni inmueble marcado como no disponible: una página cualquiera.
        WpStubs::$postMeta[self::INMUEBLE]['_property_status'] = 'active';
        // La URL sí encajaría con una ficha despublicada y ésta sí tiene
        // sustituto: si la guarda no cortara, acabaríamos redirigiendo desde
        // una página que no tiene nada que ver.
        $_SERVER['REQUEST_URI'] = '/inmueble/apartamento-poblado/';
        WpStubs::$posts = [[WpStubs::$postObjects[self::INMUEBLE]]];

        $this->service->maybeShowUnavailable();

        self::assertSame([], WpStubs::$redirects);
        self::assertSame([], WpStubs::$statusHeaders);
    }

    /**
     * El equipo tiene que poder previsualizar un borrador. Sin esta salida, un
     * comercial revisando una ficha inactiva sería redirigido al sustituto en
     * vez de ver lo que estaba revisando.
     */
    public function testQuienPuedeEditarVeLaFichaEnLugarDeLaRecuperacion(): void
    {
        $this->prepararInmuebleConSustituto();
        WpStubs::$capabilities = ['edit_posts'];

        $this->service->maybeShowUnavailable();

        self::assertSame([], WpStubs::$redirects);
        self::assertSame([], WpStubs::$statusHeaders);
    }

    private function prepararInmuebleConSustituto(): void
    {
        $this->prepararInmuebleNoDisponible();
        WpStubs::$postMeta[self::INMUEBLE]['_homlity_replacement_property_id'] = '777';
        WpStubs::$postObjects[777] = new \WP_Post(['ID' => 777, 'post_type' => 'property']);
        WpStubs::$postStatuses[777] = 'publish';
        WpStubs::$permalinks[777] = 'https://example.test/inmuebles/el-sustituto/';
    }

    /** Un 404 que no corresponde a ninguna ficha sigue siendo un 404 normal. */
    public function testUn404QueNoCoincideConNingunInmuebleSeDejaPasar(): void
    {
        WpStubs::$is404 = true;
        $_SERVER['REQUEST_URI'] = '/quienes-somos/';

        $this->service->maybeShowUnavailable();

        self::assertSame([], WpStubs::$statusHeaders);
    }

    // ── Redirección al sustituto ─────────────────────────────────────────────

    /**
     * Es el único camino que no termina en `exit`, porque la redirección corta
     * antes. Y es el que más importa: un 301 mal montado manda al visitante a
     * otra página muerta.
     */
    public function testUnInmuebleConSustitutoRedirigeConUn301(): void
    {
        $this->prepararInmuebleConSustituto();

        try {
            $this->service->maybeShowUnavailable();
            self::fail('debería haber redirigido');
        } catch (\HomlityTestRedirect $redirect) {
            self::assertSame('https://example.test/inmuebles/el-sustituto/', $redirect->location);
            self::assertSame(301, $redirect->status);
        }
    }

    private function prepararInmuebleNoDisponible(): void
    {
        WpStubs::$singularPostType = PropertyPostType::POST_TYPE;
        WpStubs::$currentPostId = self::INMUEBLE;
        WpStubs::$postObjects[self::INMUEBLE] = new \WP_Post([
            'ID' => self::INMUEBLE,
            'post_type' => PropertyPostType::POST_TYPE,
        ]);
        WpStubs::$postMeta[self::INMUEBLE]['_property_status'] = 'inactive';
    }

    // ── Detección de "no disponible" ─────────────────────────────────────────

    /**
     * La ficha sigue publicada en WordPress; lo que la retira es el metadato
     * que escribe el CRM. Interpretarlo mal deja inmuebles vendidos visibles,
     * o esconde inmuebles que sí están a la venta.
     *
     * @dataProvider estadosDelInmueble
     */
    public function testElEstadoYLaDisponibilidadDecidenSiElInmuebleSigueVisible(
        string $status,
        string $available,
        bool $noDisponible
    ): void {
        WpStubs::$currentPostId = self::INMUEBLE;
        WpStubs::$postMeta[self::INMUEBLE] = [
            '_property_status' => $status,
            '_property_available' => $available,
        ];

        self::assertSame($noDisponible, $this->invocarPrivado('isCurrentPropertyUnavailable'));
    }

    /** @return array<string,array{0:string,1:string,2:bool}> */
    public static function estadosDelInmueble(): array
    {
        return [
            'sin metadatos'          => ['', '', false],
            'activo'                 => ['active', '', false],
            'activo en mayúsculas'   => ['ACTIVE', '', false],
            'activo con espacios'    => ['  active  ', '', false],
            'inactivo'               => ['inactive', '', true],
            'vendido'                => ['sold', '', true],
            'disponible con 1'       => ['', '1', false],
            'disponible con true'    => ['', 'true', false],
            'disponible con yes'     => ['', 'yes', false],
            'disponible con active'  => ['', 'active', false],
            'no disponible con 0'    => ['', '0', true],
            'no disponible con no'   => ['', 'no', true],
            'activo pero no disponible' => ['active', '0', true],
        ];
    }

    public function testSinInmuebleConsultadoNoSeConsideraNoDisponible(): void
    {
        WpStubs::$currentPostId = 0;

        self::assertFalse($this->invocarPrivado('isCurrentPropertyUnavailable'));
    }

    // ── Reconocimiento de la URL ─────────────────────────────────────────────

    /**
     * Sólo `/inmueble/{slug}` cuenta. Cualquier otro 404 del sitio no puede
     * acabar buscando fichas despublicadas en la base de datos.
     *
     * @dataProvider urlsDePrueba
     */
    public function testSoloLaRutaDeInmuebleProduceUnSlug(string $uri, string $esperado): void
    {
        $_SERVER['REQUEST_URI'] = $uri;

        self::assertSame($esperado, $this->invocarPrivado('extractSlugFromRequest'));
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function urlsDePrueba(): array
    {
        return [
            'ruta canónica'        => ['/inmueble/apartamento-poblado/', 'apartamento-poblado'],
            'sin barra final'      => ['/inmueble/apartamento-poblado', 'apartamento-poblado'],
            'con parámetros'       => ['/inmueble/apartamento-poblado/?utm_source=x', 'apartamento-poblado'],
            'otra sección'         => ['/blog/apartamento-poblado/', ''],
            'sólo el prefijo'      => ['/inmueble/', ''],
            'un nivel de más'      => ['/inmueble/medellin/apartamento/', ''],
            'la portada'           => ['/', ''],
        ];
    }

    public function testSinPeticionNoHaySlug(): void
    {
        self::assertSame('', $this->invocarPrivado('extractSlugFromRequest'));
    }

    /** La ficha se busca sólo entre las despublicadas. */
    public function testLaFichaDespublicadaSeBuscaEnLosEstadosCorrectos(): void
    {
        $consultas = [];
        WpStubs::$getPostsCalls = [];
        WpStubs::$posts = [];

        $this->invocarPrivado('findUnpublishedProperty', 'apartamento-poblado');

        $consultas = WpStubs::$getPostsCalls;
        self::assertNotEmpty($consultas);
        self::assertSame(PropertyPostType::POST_TYPE, $consultas[0]['post_type']);
        self::assertSame('apartamento-poblado', $consultas[0]['name']);
        self::assertSame(['draft', 'pending', 'private', 'future'], $consultas[0]['post_status']);
    }

    // ── Etiquetas para buscadores ────────────────────────────────────────────

    /** Sin contexto publicado, el `wp_head` no puede ensuciarse. */
    public function testSinContextoNoSeImprimeNadaEnLaCabecera(): void
    {
        ob_start();
        $this->service->renderRecoverySeoHead();

        self::assertSame('', (string) ob_get_clean());
    }

    public function testLaCabeceraDeclaraLosRobotsYLaDescripcion(): void
    {
        $this->publicarContexto();

        ob_start();
        $this->service->renderRecoverySeoHead();
        $salida = (string) ob_get_clean();

        self::assertStringContainsString('<meta name="robots" content="index, follow">', $salida);
        self::assertStringContainsString('name="description"', $salida);
    }

    /**
     * El canónico apunta a sí mismo sólo cuando la página tiene contenido que
     * merezca indexarse. Emitirlo siempre le diría a Google que indexe páginas
     * que el propio plugin acaba de marcar como `noindex`.
     */
    public function testElCanonicoSoloSeEmiteCuandoLaPaginaEsIndexable(): void
    {
        WpStubs::$permalinks[self::INMUEBLE] = 'https://example.test/inmueble/apartamento/';

        $this->publicarContexto();
        ob_start();
        $this->service->renderRecoverySeoHead();
        self::assertStringContainsString('rel="canonical"', (string) ob_get_clean());

        $this->publicarContexto(['canonical' => '', 'robots' => 'noindex,follow']);
        ob_start();
        $this->service->renderRecoverySeoHead();
        self::assertStringNotContainsString('rel="canonical"', (string) ob_get_clean());
    }

    public function testUnaDescripcionVaciaNoEmiteLaEtiqueta(): void
    {
        $this->publicarContexto(['meta_description' => '   ']);

        ob_start();
        $this->service->renderRecoverySeoHead();

        self::assertStringNotContainsString('name="description"', (string) ob_get_clean());
    }

    // ── Filtros de los plugins de SEO ────────────────────────────────────────

    /**
     * Yoast y Rank Math reescriben el título en el `wp_head`. Sin estos
     * filtros, la página de recuperación saldría en Google con el título del
     * inmueble retirado.
     */
    public function testLosFiltrosDeSeoDevuelvenLoDelContexto(): void
    {
        $this->publicarContexto();

        self::assertSame(
            'Propiedades similares en El Poblado, Medellín',
            $this->service->filterRecoveryTitle('Título del inmueble')
        );
        self::assertSame(
            'Este inmueble ya no está disponible.',
            $this->service->filterRecoveryDescription('Descripción vieja')
        );
        self::assertSame('index,follow', $this->service->filterRecoveryRobots('noindex'));
    }

    /** Sin contexto, los filtros no pueden tocar el resto del sitio. */
    public function testSinContextoLosFiltrosDevuelvenLoQueRecibieron(): void
    {
        self::assertSame('Título original', $this->service->filterRecoveryTitle('Título original'));
        self::assertSame('Descripción original', $this->service->filterRecoveryDescription('Descripción original'));
        self::assertSame('index', $this->service->filterRecoveryRobots('index'));
        self::assertSame('Documento', $this->service->filterRecoveryDocumentTitle('Documento'));
    }

    /** Un contexto que no es de recuperación tampoco los activa. */
    public function testUnContextoQueNoEsDeRetiradoNoActivaLosFiltros(): void
    {
        $GLOBALS['homlity_property_recovery_context'] = $this->contexto(['is_retired' => false]);

        self::assertSame('Título original', $this->service->filterRecoveryTitle('Título original'));
    }

    /** El título nativo de WordPress lleva el nombre del sitio detrás. */
    public function testElTituloNativoAniadeElNombreDelSitio(): void
    {
        $this->publicarContexto();
        WpStubs::setOption('blogname', 'Royal Propiedad Raíz');

        self::assertSame(
            'Propiedades similares en El Poblado, Medellín | Royal Propiedad Raíz',
            $this->service->filterRecoveryDocumentTitle('Título viejo')
        );
    }

    /** Con un título vacío en el contexto se respeta el que ya había. */
    public function testUnTituloVacioEnElContextoNoPisaElOriginal(): void
    {
        $this->publicarContexto(['meta_title' => '   ']);

        self::assertSame('Título original', $this->service->filterRecoveryTitle('Título original'));
        self::assertSame('Documento', $this->service->filterRecoveryDocumentTitle('Documento'));
    }
}
