<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\BotDetector;
use Homlity\PluginInmobiliario\Services\PropertyContactClickTrackingService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * El registro de clics en los botones de contacto.
 *
 * Es un endpoint AJAX abierto —tiene su variante `nopriv`—, así que además de
 * contar bien tiene que rechazar lo que no sea una petición legítima: sin
 * nonce, con un tipo de evento inventado o sin inmueble. Y cuenta *personas
 * interesadas*, no clics: el mismo visitante pulsando cinco veces WhatsApp es
 * un interesado, no cinco.
 */
final class PropertyContactClickTrackingServiceTest extends TestCase
{
    private const INMUEBLE = 321;
    private const TABLA = 'wp_homlity_property_contact_clicks';

    private PropertyContactClickTrackingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['enable_analytics' => '1']);
        $_COOKIE = ['homlity_visitor_id' => 'visitante-de-siempre'];
        $_POST = ['property_id' => (string) self::INMUEBLE, 'event_type' => 'whatsapp'];
        $_SERVER['REMOTE_ADDR'] = '190.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Safari/605';
        $this->olvidarSiEsRobot();

        // La deduplicación la hace la clave única de la tabla, no el PHP.
        $GLOBALS['wpdb']->engine->declareUniqueKey(
            self::TABLA,
            ['property_id', 'visitor_id', 'event_type']
        );

        $this->service = new PropertyContactClickTrackingService();
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        $_POST = [];
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

    /** Ejecuta el endpoint y devuelve la respuesta JSON que habría enviado. */
    private function llamar(): \HomlityTestJsonResponse
    {
        try {
            $this->service->trackContactClick();
        } catch (\HomlityTestJsonResponse $response) {
            return $response;
        }

        self::fail('el endpoint debería haber respondido en JSON');
    }

    /** @return list<array<string,mixed>> */
    private function clics(): array
    {
        return $GLOBALS['wpdb']->engine->rows(self::TABLA);
    }

    // ── Seguridad ────────────────────────────────────────────────────────────

    /**
     * Sin nonce, cualquiera podría inflar las estadísticas de un inmueble —o
     * las de la competencia alojada en el mismo sitio— con un bucle de peticiones.
     */
    public function testSinNonceValidoNoSeRegistraNada(): void
    {
        WpStubs::$nonceValid = false;

        try {
            $this->service->trackContactClick();
            self::fail('debería haberse cortado');
        } catch (\HomlityTestDie) {
            self::assertSame([], $this->clics());
        }
    }

    public function testSeComprueboElNonceEsperado(): void
    {
        $this->llamar();

        self::assertSame(
            [['action' => 'homlity_contact_click_nonce', 'field' => 'nonce']],
            WpStubs::$checkedNonces
        );
    }

    // ── Validación de la carga ───────────────────────────────────────────────

    /** El tipo de evento viene del navegador: sólo valen los tres conocidos. */
    public function testSoloSeAceptanLosTiposDeEventoConocidos(): void
    {
        foreach (['whatsapp', 'phone', 'email'] as $tipo) {
            $_POST['event_type'] = $tipo;
            $_POST['property_id'] = (string) (self::INMUEBLE + array_search($tipo, ['whatsapp', 'phone', 'email'], true));
            self::assertTrue($this->llamar()->success, $tipo);
        }

        $_POST['event_type'] = 'telepatia';
        $respuesta = $this->llamar();
        self::assertFalse($respuesta->success);
        self::assertSame(400, $respuesta->status);
    }

    public function testSinInmuebleSeRechazaLaPeticion(): void
    {
        $_POST['property_id'] = '0';

        $respuesta = $this->llamar();

        self::assertFalse($respuesta->success);
        self::assertSame(400, $respuesta->status);
        self::assertSame([], $this->clics());
    }

    /** Una carga inválida no puede dejar rastro en la tabla. */
    public function testUnaCargaInvalidaNoEscribeNada(): void
    {
        $_POST = ['property_id' => 'abc', 'event_type' => '<script>'];

        $this->llamar();

        self::assertSame([], $this->clics());
    }

    // ── Guardas ──────────────────────────────────────────────────────────────

    /**
     * Con la analítica apagada la respuesta sigue siendo correcta: el
     * JavaScript del botón no tiene por qué enterarse, y un error ahí llenaría
     * la consola del visitante.
     */
    public function testConLaAnaliticaDesactivadaSeRespondeBienPeroNoSeGuarda(): void
    {
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['enable_analytics' => '']);

        self::assertTrue($this->llamar()->success);
        self::assertSame([], $this->clics());
    }

    public function testUnRastreadorNoRegistraClics(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1)';
        $this->olvidarSiEsRobot();

        self::assertTrue($this->llamar()->success);
        self::assertSame([], $this->clics());
    }

    // ── El registro ──────────────────────────────────────────────────────────

    public function testUnClicSeRegistraConSuTipoYSuInmueble(): void
    {
        $this->llamar();

        $clic = $this->clics()[0];
        self::assertSame(self::INMUEBLE, $clic['property_id']);
        self::assertSame('whatsapp', $clic['event_type']);
        self::assertSame('visitante-de-siempre', $clic['visitor_id']);
    }

    /** Los datos del visitante van resumidos, igual que en las visitas. */
    public function testLaIpYElAgenteSeGuardanCifrados(): void
    {
        $this->llamar();

        $clic = $this->clics()[0];
        self::assertSame(hash('sha256', '190.0.0.1'), $clic['ip_hash']);
        self::assertStringNotContainsString('190.0.0.1', implode('|', array_map('strval', $clic)));
    }

    // ── Deduplicación ────────────────────────────────────────────────────────

    /**
     * La métrica es "cuántas personas quisieron contactar", no "cuántas veces
     * pulsaron". Quien duda y pulsa tres veces sigue siendo un interesado.
     */
    public function testElMismoVisitantePulsandoVariasVecesCuentaUnaSola(): void
    {
        $this->llamar();
        $this->llamar();
        $this->llamar();

        self::assertCount(1, $this->clics());
    }

    /** Pero WhatsApp y teléfono son intenciones distintas y cuentan aparte. */
    public function testDosTiposDeContactoDelMismoVisitanteCuentanPorSeparado(): void
    {
        $this->llamar();
        $_POST['event_type'] = 'phone';
        $this->llamar();

        self::assertCount(2, $this->clics());
    }

    public function testDosVisitantesDistintosCuentanPorSeparado(): void
    {
        $this->llamar();
        $_COOKIE['homlity_visitor_id'] = 'otra-persona';
        $this->llamar();

        self::assertCount(2, $this->clics());
    }

    public function testElMismoVisitanteEnDosInmueblesCuentaEnCadaUno(): void
    {
        $this->llamar();
        $_POST['property_id'] = '999';
        $this->llamar();

        self::assertCount(2, $this->clics());
    }

    // ── Identificación del visitante ─────────────────────────────────────────

    /** Sin cookie previa se emite una, para poder deduplicar en el siguiente clic. */
    public function testAlVisitanteSinCookieSeLeAsignaUnIdentificador(): void
    {
        $_COOKIE = [];

        $this->llamar();

        self::assertArrayHasKey('homlity_visitor_id', WpStubs::$cookiesSet);
        self::assertSame(
            WpStubs::$cookiesSet['homlity_visitor_id']['value'],
            $this->clics()[0]['visitor_id']
        );
    }

    public function testLaCookieDelVisitanteEsInaccesibleDesdeJavascript(): void
    {
        $_COOKIE = [];

        $this->llamar();

        $opciones = WpStubs::$cookiesSet['homlity_visitor_id']['options'];
        self::assertTrue($opciones['httponly']);
        self::assertSame('Lax', $opciones['samesite']);
    }
}
