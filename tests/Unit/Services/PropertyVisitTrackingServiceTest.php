<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\BotDetector;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyVisitTrackingService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * El registro de visitas a la ficha de un inmueble.
 *
 * Dos cosas que se rompen en silencio: contar de más —cada recarga, cada
 * rastreador— convierte el informe del comercial en ruido; y contar de menos
 * lo deja a cero sin que nadie lo note. Además aquí se guardan datos del
 * visitante, así que hay que comprobar que la IP y el agente de usuario se
 * almacenan cifrados y no en claro.
 */
final class PropertyVisitTrackingServiceTest extends TestCase
{
    private const INMUEBLE = 321;
    private const TABLA = 'wp_homlity_property_visits';

    private PropertyVisitTrackingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Una petición normal: visitante anónimo en la ficha de un inmueble,
        // con la analítica activada.
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['enable_analytics' => '1']);
        WpStubs::$singularPostType = PropertyPostType::POST_TYPE;
        WpStubs::$currentPostId = self::INMUEBLE;
        $_COOKIE = [];
        $_SERVER['REMOTE_ADDR'] = '190.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Safari/605';
        $this->olvidarSiEsRobot();

        $this->service = new PropertyVisitTrackingService();
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        $this->olvidarSiEsRobot();
        parent::tearDown();
    }

    /** BotDetector memoriza el veredicto para toda la petición. */
    private function olvidarSiEsRobot(): void
    {
        $cache = new \ReflectionProperty(BotDetector::class, 'cached');
        $cache->setAccessible(true);
        $cache->setValue(null, null);
    }

    /** @return list<array<string,mixed>> */
    private function visitas(): array
    {
        return $GLOBALS['wpdb']->engine->rows(self::TABLA);
    }

    /** Deja registrada una visita previa en la base de datos. */
    private function visitaPrevia(int $propertyId, string $visitorId, int $haceSegundos): void
    {
        $GLOBALS['wpdb']->engine->insert(self::TABLA, [
            'property_id' => $propertyId,
            'visitor_id'  => $visitorId,
            'ip_hash'     => str_repeat('a', 64),
            'ua_hash'     => str_repeat('b', 64),
            'visited_at'  => gmdate('Y-m-d H:i:s', time() - $haceSegundos),
            'created_ts'  => time() - $haceSegundos,
        ]);
    }

    // ── Guardas: cuándo NO se cuenta ─────────────────────────────────────────

    /**
     * El interruptor de analítica es lo que hace que un sitio pueda no guardar
     * nada del visitante. Si no se respetara, se estarían recogiendo datos sin
     * base legal.
     */
    public function testConLaAnaliticaDesactivadaNoSeRegistraNada(): void
    {
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['enable_analytics' => '']);

        $this->service->trackPropertyVisit();

        self::assertSame([], $this->visitas());
    }

    public function testSinAjustesGuardadosNoSeRegistraNada(): void
    {
        WpStubs::$options = [];

        $this->service->trackPropertyVisit();

        self::assertSame([], $this->visitas());
    }

    /** El comercial abriendo su propia ficha no es una visita. */
    public function testUnUsuarioIdentificadoNoCuentaComoVisita(): void
    {
        WpStubs::$userLoggedIn = true;

        $this->service->trackPropertyVisit();

        self::assertSame([], $this->visitas());
    }

    /**
     * `template_redirect` se dispara también en peticiones que no son la
     * página: contarlas duplicaría cada visita real.
     */
    public function testLasPeticionesQueNoSonLaPaginaNoCuentan(): void
    {
        WpStubs::$doingAjax = true;
        $this->service->trackPropertyVisit();
        self::assertSame([], $this->visitas(), 'ajax');

        WpStubs::$doingAjax = false;
        WpStubs::$isAdminScreen = true;
        $this->service->trackPropertyVisit();
        self::assertSame([], $this->visitas(), 'administración');
    }

    public function testUnaPaginaQueNoEsFichaDeInmuebleNoCuenta(): void
    {
        WpStubs::$singularPostType = 'page';

        $this->service->trackPropertyVisit();

        self::assertSame([], $this->visitas());
    }

    /** Sin esto, el informe mide sobre todo a Googlebot. */
    public function testUnRastreadorNoCuentaComoVisita(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
        $this->olvidarSiEsRobot();

        $this->service->trackPropertyVisit();

        self::assertSame([], $this->visitas());
    }

    public function testSinInmuebleIdentificadoNoSeRegistraNada(): void
    {
        WpStubs::$currentPostId = 0;

        $this->service->trackPropertyVisit();

        self::assertSame([], $this->visitas());
    }

    // ── El registro ──────────────────────────────────────────────────────────

    public function testUnaVisitaNuevaSeRegistra(): void
    {
        $this->service->trackPropertyVisit();

        self::assertCount(1, $this->visitas());
        self::assertSame(self::INMUEBLE, $this->visitas()[0]['property_id']);
    }

    /**
     * La IP y el agente de usuario identifican a una persona. Guardarlos en
     * claro convertiría la tabla en un registro de navegación nominal; el
     * resumen sirve igual para distinguir visitantes y no se puede revertir.
     */
    public function testLaIpYElAgenteSeGuardanCifradosYNoEnClaro(): void
    {
        $this->service->trackPropertyVisit();

        $visita = $this->visitas()[0];
        self::assertSame(hash('sha256', '190.0.0.1'), $visita['ip_hash']);
        self::assertSame(hash('sha256', 'Mozilla/5.0 (Macintosh) Safari/605'), $visita['ua_hash']);
        self::assertStringNotContainsString('190.0.0.1', implode('|', array_map('strval', $visita)));
        self::assertStringNotContainsString('Macintosh', implode('|', array_map('strval', $visita)));
    }

    public function testSeGuardaLaMarcaDeTiempoDeLaVisita(): void
    {
        $antes = time();

        $this->service->trackPropertyVisit();

        $visita = $this->visitas()[0];
        self::assertGreaterThanOrEqual($antes, (int) $visita['created_ts']);
        self::assertNotSame('', (string) $visita['visited_at']);
    }

    // ── Identificación del visitante ─────────────────────────────────────────

    /** Sin cookie previa se emite un identificador nuevo y se recuerda un año. */
    public function testAlPrimerVisitanteSeLeAsignaUnIdentificador(): void
    {
        $this->service->trackPropertyVisit();

        $cookie = WpStubs::$cookiesSet['homlity_visitor_id'];
        self::assertNotSame('', $cookie['value']);
        self::assertSame($cookie['value'], $this->visitas()[0]['visitor_id']);
        self::assertGreaterThan(time() + (360 * 86400), $cookie['options']['expires']);
    }

    /** Y si ya la trae, se reutiliza: es lo que permite contar visitantes únicos. */
    public function testUnVisitanteConocidoConservaSuIdentificador(): void
    {
        $_COOKIE['homlity_visitor_id'] = 'visitante-de-siempre';

        $this->service->trackPropertyVisit();

        self::assertSame('visitante-de-siempre', $this->visitas()[0]['visitor_id']);
        self::assertArrayNotHasKey('homlity_visitor_id', WpStubs::$cookiesSet, 'no se reemite');
    }

    /** La cookie del visitante no puede quedar al alcance de JavaScript. */
    public function testLaCookieDelVisitanteEsInaccesibleDesdeJavascript(): void
    {
        $this->service->trackPropertyVisit();

        $opciones = WpStubs::$cookiesSet['homlity_visitor_id']['options'];
        self::assertTrue($opciones['httponly']);
        self::assertSame('Lax', $opciones['samesite']);
        self::assertTrue($opciones['secure']);
    }

    /**
     * Si otro plugin ya envió la cabecera no se puede poner cookie. La visita
     * se cuenta igual —es real—, pero sin cookie no hay ventana de 24 h: la
     * segunda comprobación, la de la base de datos, es la que evita el
     * duplicado en la recarga siguiente.
     */
    public function testConLasCabecerasYaEnviadasLaVisitaSeCuentaIgual(): void
    {
        WpStubs::$headersSent = true;

        $this->service->trackPropertyVisit();

        self::assertSame([], WpStubs::$cookiesSet, 'no se emite ninguna cookie');
        self::assertCount(1, $this->visitas());
    }

    // ── Ventana de 24 horas ──────────────────────────────────────────────────

    /**
     * Recargar la página no es una visita nueva. La cookie por inmueble es la
     * comprobación barata: evita ir a la base de datos en cada recarga.
     */
    public function testRecargarLaPaginaNoCuentaDosVeces(): void
    {
        $this->service->trackPropertyVisit();
        $this->service->trackPropertyVisit();

        self::assertCount(1, $this->visitas());
    }

    /**
     * Y la cookie corta antes de consultar. Es la razón de que exista además
     * de la comprobación en base de datos: sin ella, cada recarga de cada
     * visitante en cada ficha es una consulta más contra una tabla que crece
     * sin parar.
     */
    public function testConLaCookieDelInmuebleNoSeConsultaLaBaseDeDatos(): void
    {
        $_COOKIE['homlity_visitor_id'] = 'visitante-de-siempre';
        $_COOKIE['homlity_property_visit_' . self::INMUEBLE] = (string) time();
        $GLOBALS['wpdb']->engine->queries = [];

        $this->service->trackPropertyVisit();

        self::assertSame([], $GLOBALS['wpdb']->engine->queries);
        self::assertSame([], $this->visitas());
    }

    public function testLaCookieDelInmuebleCaducaALas24Horas(): void
    {
        $this->service->trackPropertyVisit();

        $cookie = WpStubs::$cookiesSet['homlity_property_visit_' . self::INMUEBLE];
        self::assertEqualsWithDelta(time() + 86400, $cookie['options']['expires'], 5);
    }

    /** Pasada la ventana, la misma persona vuelve a contar. */
    public function testPasadas24HorasLaVisitaVuelveAContar(): void
    {
        $_COOKIE['homlity_visitor_id'] = 'visitante-de-siempre';
        $_COOKIE['homlity_property_visit_' . self::INMUEBLE] = (string) (time() - 86401);
        $this->visitaPrevia(self::INMUEBLE, 'visitante-de-siempre', 86401);

        $this->service->trackPropertyVisit();

        self::assertCount(2, $this->visitas());
    }

    /**
     * La cookie por inmueble se puede borrar desde el navegador. La segunda
     * comprobación, contra la base de datos, es la que aguanta ese caso.
     */
    public function testSinCookieDeInmuebleLaBaseDeDatosSigueFrenandoElDuplicado(): void
    {
        $_COOKIE['homlity_visitor_id'] = 'visitante-de-siempre';
        $this->visitaPrevia(self::INMUEBLE, 'visitante-de-siempre', 3600);

        $this->service->trackPropertyVisit();

        self::assertCount(1, $this->visitas(), 'no se añade una segunda');
        self::assertArrayHasKey(
            'homlity_property_visit_' . self::INMUEBLE,
            WpStubs::$cookiesSet,
            'y se repone la cookie para no volver a consultar'
        );
    }

    /** La ventana es por inmueble: visitar otra ficha sí cuenta. */
    public function testVisitarOtroInmuebleCuentaAparte(): void
    {
        $_COOKIE['homlity_visitor_id'] = 'visitante-de-siempre';
        $this->visitaPrevia(999, 'visitante-de-siempre', 3600);
        $_COOKIE['homlity_property_visit_999'] = (string) time();

        $this->service->trackPropertyVisit();

        self::assertCount(2, $this->visitas());
        self::assertSame(self::INMUEBLE, $this->visitas()[1]['property_id']);
    }

    /** Y por visitante: dos personas en la misma ficha son dos visitas. */
    public function testDosVisitantesEnLaMismaFichaCuentanPorSeparado(): void
    {
        $this->visitaPrevia(self::INMUEBLE, 'otra-persona', 60);
        $_COOKIE['homlity_visitor_id'] = 'yo';

        $this->service->trackPropertyVisit();

        self::assertCount(2, $this->visitas());
    }

    // ── Informe por inmueble ─────────────────────────────────────────────────

    public function testElInformeDeUnInmuebleSinVisitasEstaVacio(): void
    {
        $informe = PropertyVisitTrackingService::getReportForProperty(self::INMUEBLE);

        self::assertSame(0, $informe['totals']['all']);
        self::assertSame([], $informe['daily']);
        self::assertSame([], $informe['recent']);
    }

    public function testUnIdentificadorInvalidoDevuelveUnInformeVacio(): void
    {
        $informe = PropertyVisitTrackingService::getReportForProperty(0);

        self::assertSame(0, $informe['totals']['all']);
        self::assertSame(0, $informe['totals']['unique_visitors']);
    }

    /** Cada total mira su propia ventana; confundirlas falsea el informe entero. */
    public function testCadaTotalCuentaSuPropiaVentana(): void
    {
        $this->visitaPrevia(self::INMUEBLE, 'a', 60);            // hoy
        $this->visitaPrevia(self::INMUEBLE, 'b', 3 * 86400);     // hace 3 días
        $this->visitaPrevia(self::INMUEBLE, 'c', 20 * 86400);    // hace 20 días
        $this->visitaPrevia(self::INMUEBLE, 'd', 100 * 86400);   // hace 100 días
        $this->visitaPrevia(999, 'e', 60);                       // otro inmueble

        $totales = PropertyVisitTrackingService::getReportForProperty(self::INMUEBLE)['totals'];

        self::assertSame(4, $totales['all'], 'todas las de este inmueble');
        self::assertSame(1, $totales['today']);
        self::assertSame(2, $totales['last7']);
        self::assertSame(3, $totales['last30']);
    }

    /** Los visitantes únicos no son las visitas: una persona puede volver. */
    public function testLosVisitantesUnicosNoSonLasVisitas(): void
    {
        $this->visitaPrevia(self::INMUEBLE, 'a', 60);
        $this->visitaPrevia(self::INMUEBLE, 'a', 90000);
        $this->visitaPrevia(self::INMUEBLE, 'b', 60);

        $totales = PropertyVisitTrackingService::getReportForProperty(self::INMUEBLE)['totals'];

        self::assertSame(3, $totales['all']);
        self::assertSame(2, $totales['unique_visitors']);
    }

    public function testLaSerieDiariaAgrupaPorDiaYVaEnOrden(): void
    {
        $this->visitaPrevia(self::INMUEBLE, 'a', 60);
        $this->visitaPrevia(self::INMUEBLE, 'b', 120);
        $this->visitaPrevia(self::INMUEBLE, 'c', 2 * 86400);

        $daily = PropertyVisitTrackingService::getReportForProperty(self::INMUEBLE)['daily'];

        self::assertCount(2, $daily);
        self::assertSame(gmdate('Y-m-d', time() - 2 * 86400), $daily[0]['date'], 'de más antiguo a más reciente');
        self::assertSame(1, $daily[0]['visits']);
        self::assertSame(2, $daily[1]['visits']);
    }

    /**
     * El listado de visitas recientes se muestra en el editor del inmueble. El
     * identificador del visitante va recortado: entero permitiría cruzarlo
     * entre inmuebles y seguir a una persona por el catálogo.
     */
    public function testElListadoRecienteNoExponeElIdentificadorCompleto(): void
    {
        $completo = str_repeat('f', 32);
        $this->visitaPrevia(self::INMUEBLE, $completo, 60);

        $recientes = PropertyVisitTrackingService::getReportForProperty(self::INMUEBLE)['recent'];

        self::assertCount(1, $recientes);
        self::assertSame(substr($completo, 0, 10) . '…', $recientes[0]['visitor']);
        self::assertNotSame($completo, $recientes[0]['visitor']);
    }

    /** Lo reciente primero, y con tope: el editor no puede cargar 40.000 filas. */
    public function testElListadoRecienteVaDeLoNuevoALoViejoYEstaAcotado(): void
    {
        // De la más antigua a la más reciente, para que los ids crezcan con el
        // tiempo igual que en producción.
        for ($i = 59; $i >= 0; $i--) {
            $this->visitaPrevia(self::INMUEBLE, 'visitante-' . $i, $i * 60);
        }

        $recientes = PropertyVisitTrackingService::getReportForProperty(self::INMUEBLE)['recent'];

        self::assertCount(50, $recientes);
        self::assertStringStartsWith('visitante-', $recientes[0]['visitor']);
        self::assertGreaterThan($recientes[1]['visited_at'], $recientes[0]['visited_at']);
    }
}
