<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\BotDetector;
use Homlity\PluginInmobiliario\Services\PropertyAnalyticsCleanupService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTechnicalSheetDownloadTrackingService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * La descarga de la ficha técnica y el borrado de la analítica.
 *
 * Van juntas porque son los dos extremos de la vida del dato: una lo escribe y
 * la otra lo borra. La segunda es la de más riesgo del módulo — un `DELETE`
 * mal acotado se lleva la analítica de inmuebles que siguen publicados, y eso
 * no se recupera.
 */
final class PropertyAnalyticsRetentionTest extends TestCase
{
    private const INMUEBLE = 321;
    private const VISITAS = 'wp_homlity_property_visits';
    private const CLICS = 'wp_homlity_property_contact_clicks';
    private const DESCARGAS = 'wp_homlity_property_sheet_downloads';

    protected function setUp(): void
    {
        parent::setUp();

        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['enable_analytics' => '1']);
        WpStubs::$existingTables = [self::VISITAS, self::CLICS, self::DESCARGAS];
        $_COOKIE = ['homlity_visitor_id' => 'visitante-de-siempre'];
        $_SERVER['REMOTE_ADDR'] = '190.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Safari/605';
        $this->olvidarSiEsRobot();
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        $this->olvidarSiEsRobot();
        parent::tearDown();
    }

    private function olvidarSiEsRobot(): void
    {
        $cache = new \ReflectionProperty(BotDetector::class, 'cached');
        $cache->setAccessible(true);
        $cache->setValue(null, null);
    }

    /** @return list<array<string,mixed>> */
    private function filas(string $tabla): array
    {
        return $GLOBALS['wpdb']->engine->rows($tabla);
    }

    private function inmueble(int $postId, string $tipo = PropertyPostType::POST_TYPE): void
    {
        WpStubs::$postObjects[$postId] = new \WP_Post(['ID' => $postId, 'post_type' => $tipo]);
    }

    /** Deja una fila de analítica en cada una de las tres tablas. */
    private function analiticaDe(int $propertyId): void
    {
        foreach ([self::VISITAS, self::CLICS, self::DESCARGAS] as $tabla) {
            $GLOBALS['wpdb']->engine->insert($tabla, [
                'property_id' => $propertyId,
                'visitor_id'  => 'alguien',
                'created_ts'  => time(),
            ]);
        }
    }

    // ── Descarga de la ficha técnica ─────────────────────────────────────────

    public function testUnaDescargaSeRegistra(): void
    {
        PropertyTechnicalSheetDownloadTrackingService::trackDownload(self::INMUEBLE);

        $descarga = $this->filas(self::DESCARGAS)[0];
        self::assertSame(self::INMUEBLE, $descarga['property_id']);
        self::assertSame('visitante-de-siempre', $descarga['visitor_id']);
    }

    /**
     * A diferencia de los clics de contacto, aquí sí se cuenta cada descarga:
     * bajar la ficha dos veces son dos descargas. La tabla no tiene clave
     * única, así que el comportamiento tiene que quedar por escrito.
     */
    public function testCadaDescargaCuentaAunqueSeaElMismoVisitante(): void
    {
        PropertyTechnicalSheetDownloadTrackingService::trackDownload(self::INMUEBLE);
        PropertyTechnicalSheetDownloadTrackingService::trackDownload(self::INMUEBLE);

        self::assertCount(2, $this->filas(self::DESCARGAS));
    }

    public function testLaIpYElAgenteDeLaDescargaSeGuardanCifrados(): void
    {
        PropertyTechnicalSheetDownloadTrackingService::trackDownload(self::INMUEBLE);

        $descarga = $this->filas(self::DESCARGAS)[0];
        self::assertSame(hash('sha256', '190.0.0.1'), $descarga['ip_hash']);
        self::assertStringNotContainsString('190.0.0.1', implode('|', array_map('strval', $descarga)));
    }

    public function testConLaAnaliticaDesactivadaNoSeRegistraLaDescarga(): void
    {
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['enable_analytics' => '']);

        PropertyTechnicalSheetDownloadTrackingService::trackDownload(self::INMUEBLE);

        self::assertSame([], $this->filas(self::DESCARGAS));
    }

    public function testUnRastreadorNoRegistraDescargas(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1)';
        $this->olvidarSiEsRobot();

        PropertyTechnicalSheetDownloadTrackingService::trackDownload(self::INMUEBLE);

        self::assertSame([], $this->filas(self::DESCARGAS));
    }

    public function testSinInmuebleNoSeRegistraLaDescarga(): void
    {
        PropertyTechnicalSheetDownloadTrackingService::trackDownload(0);
        PropertyTechnicalSheetDownloadTrackingService::trackDownload(-5);

        self::assertSame([], $this->filas(self::DESCARGAS));
    }

    // ── Borrado al eliminar un inmueble ──────────────────────────────────────

    /** Al borrar un inmueble no puede quedar su rastro de visitas. */
    public function testAlBorrarUnInmuebleSeBorraSuAnaliticaEnLasTresTablas(): void
    {
        $this->inmueble(self::INMUEBLE);
        $this->analiticaDe(self::INMUEBLE);

        (new PropertyAnalyticsCleanupService())->cleanupByPostId(self::INMUEBLE);

        self::assertSame([], $this->filas(self::VISITAS));
        self::assertSame([], $this->filas(self::CLICS));
        self::assertSame([], $this->filas(self::DESCARGAS));
    }

    /**
     * Y sólo la suya. Un `DELETE` sin acotar por inmueble vaciaría la analítica
     * de todo el catálogo cada vez que alguien manda una ficha a la papelera.
     */
    public function testBorrarUnInmuebleNoTocaLaAnaliticaDeLosDemas(): void
    {
        $this->inmueble(self::INMUEBLE);
        $this->inmueble(999);
        $this->analiticaDe(self::INMUEBLE);
        $this->analiticaDe(999);

        (new PropertyAnalyticsCleanupService())->cleanupByPostId(self::INMUEBLE);

        self::assertCount(1, $this->filas(self::VISITAS));
        self::assertSame(999, $this->filas(self::VISITAS)[0]['property_id']);
    }

    /**
     * Los ganchos `trashed_post` y `before_delete_post` se disparan para
     * cualquier contenido. Sin la comprobación de tipo, borrar una entrada del
     * blog cuyo id coincida con el de un inmueble se llevaría su analítica.
     */
    public function testBorrarUnContenidoQueNoEsInmuebleNoTocaNada(): void
    {
        $this->inmueble(self::INMUEBLE, 'post');
        $this->analiticaDe(self::INMUEBLE);

        (new PropertyAnalyticsCleanupService())->cleanupByPostId(self::INMUEBLE);

        self::assertCount(1, $this->filas(self::VISITAS));
    }

    public function testUnIdentificadorInvalidoNoBorraNada(): void
    {
        $this->analiticaDe(self::INMUEBLE);

        (new PropertyAnalyticsCleanupService())->cleanupByPostId(0);
        (new PropertyAnalyticsCleanupService())->cleanupByPostId(-1);

        self::assertCount(1, $this->filas(self::VISITAS));
    }

    /**
     * Las tablas se crean al vuelo la primera vez. Si alguna no existe todavía
     * —un sitio recién instalado—, el borrado tiene que saltársela en vez de
     * fallar con un error de SQL en mitad de un `wp_delete_post()`.
     */
    public function testUnaTablaQueNoExisteSeSaltaSinRomper(): void
    {
        $this->inmueble(self::INMUEBLE);
        $this->analiticaDe(self::INMUEBLE);
        WpStubs::$existingTables = [self::VISITAS];

        (new PropertyAnalyticsCleanupService())->cleanupByPostId(self::INMUEBLE);

        self::assertSame([], $this->filas(self::VISITAS), 'la que existe sí se limpia');
        self::assertCount(1, $this->filas(self::CLICS), 'la que no, se deja en paz');
    }

    // ── Purga de huérfanos ───────────────────────────────────────────────────

    /**
     * La purga usa un `DELETE` con `LEFT JOIN` que el motor de pruebas no
     * ejecuta, así que aquí se comprueba la sentencia: que apunta a las tres
     * tablas y que conserva las dos condiciones. Perder la de `post_type`
     * borraría la analítica de cualquier inmueble cuyo id coincida con el de
     * otro contenido; perder la de `p.ID IS NULL` haría que la purga no
     * purgara nada.
     */
    public function testLaPurgaDeHuerfanosApuntaALasTresTablasConSusDosCondiciones(): void
    {
        (new PropertyAnalyticsCleanupService())->purgeOrphanAnalytics();

        $sentencias = $GLOBALS['wpdb']->rawQueries;
        self::assertCount(3, $sentencias);

        foreach ([self::VISITAS, self::CLICS, self::DESCARGAS] as $indice => $tabla) {
            self::assertStringContainsString('DELETE t FROM ' . $tabla, $sentencias[$indice]);
            self::assertStringContainsString('LEFT JOIN wp_posts p ON p.ID = t.property_id', $sentencias[$indice]);
            self::assertStringContainsString("WHERE p.ID IS NULL OR p.post_type <> 'property'", $sentencias[$indice]);
        }
    }

    public function testLaPurgaSeSaltaLasTablasQueNoExisten(): void
    {
        WpStubs::$existingTables = [self::VISITAS];

        (new PropertyAnalyticsCleanupService())->purgeOrphanAnalytics();

        self::assertCount(1, $GLOBALS['wpdb']->rawQueries);
    }

    // ── Programación ─────────────────────────────────────────────────────────

    public function testLaPurgaSeProgramaADiario(): void
    {
        (new PropertyAnalyticsCleanupService())->maybeScheduleOrphanCleanup();

        self::assertArrayHasKey(PropertyAnalyticsCleanupService::CRON_HOOK, WpStubs::$cronEvents);
        self::assertSame('daily', WpStubs::$cronRecurrences[PropertyAnalyticsCleanupService::CRON_HOOK]);
    }

    /** Programarla dos veces dejaría la purga corriendo por duplicado. */
    public function testLaPurgaNoSeProgramaDosVeces(): void
    {
        $servicio = new PropertyAnalyticsCleanupService();
        $servicio->maybeScheduleOrphanCleanup();
        $primera = WpStubs::$cronEvents[PropertyAnalyticsCleanupService::CRON_HOOK];

        $servicio->maybeScheduleOrphanCleanup();

        self::assertSame($primera, WpStubs::$cronEvents[PropertyAnalyticsCleanupService::CRON_HOOK]);
        // Comparar la marca de tiempo no basta: dos programaciones en el mismo
        // segundo son idénticas. Lo que importa es que no se vuelva a llamar.
        self::assertCount(1, WpStubs::$scheduleCalls);
    }

    /**
     * La limpieza inicial es de una sola vez: los sitios que ya tenían filas
     * huérfanas antes de que existiera el cron. Repetirla en cada carga de
     * página sería un `DELETE` con `JOIN` en cada petición.
     */
    public function testLaLimpiezaInicialSeEjecutaUnaSolaVez(): void
    {
        $servicio = new PropertyAnalyticsCleanupService();

        $servicio->maybeScheduleOrphanCleanup();
        self::assertCount(3, $GLOBALS['wpdb']->rawQueries);

        $servicio->maybeScheduleOrphanCleanup();
        self::assertCount(3, $GLOBALS['wpdb']->rawQueries, 'no vuelve a purgar');
    }
}
