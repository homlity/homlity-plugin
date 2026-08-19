<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\ErrorReporting;

use Homlity\PluginInmobiliario\ErrorReporting\ErrorSanitizer;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;
use RuntimeException;
use WP_Error;

final class ErrorSanitizerTest extends TestCase
{
    private ErrorSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new ErrorSanitizer();
    }

    /** @dataProvider textosSensibles */
    public function testTextRedactaDatosSensibles(string $input, string $noDebeAparecer): void
    {
        $clean = $this->sanitizer->text($input);

        self::assertStringNotContainsString($noDebeAparecer, $clean);
        self::assertStringContainsString('[redacted]', $clean);
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function textosSensibles(): array
    {
        return [
            'bearer'   => ['Authorization: Bearer abc123DEF456ghi', 'abc123DEF456ghi'],
            'jwt'      => ['token eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.dBjftJeZ4CVPmB92K27uhbUJU1p1r_wW1gFWFOEjXk', 'eyJhbGciOiJIUzI1NiJ9'],
            'email'    => ['Fallo al notificar a cliente@ejemplo.com', 'cliente@ejemplo.com'],
            'telefono' => ['Contacto +57 301 555 4433 sin respuesta', '3015554433'],
            'api key'  => ['api_key=SUPERSECRETO123', 'SUPERSECRETO123'],
            'license'  => ['license_key: XXXX-YYYY-ZZZZ', 'XXXX-YYYY-ZZZZ'],
        ];
    }

    public function testTextConservaLosMensajesInofensivos(): void
    {
        $message = 'La sincronización terminó con 3 inmuebles omitidos';

        self::assertSame($message, $this->sanitizer->text($message));
    }

    public function testTextTruncaLosMensajesLargos(): void
    {
        $clean = $this->sanitizer->text(str_repeat('a', 500), 100);

        self::assertLessThanOrEqual(100, strlen($clean));
        self::assertStringEndsWith('…[truncated]', $clean);
    }

    public function testPathRelativizaRutasDelPlugin(): void
    {
        $path = WP_PLUGIN_DIR . '/homlity-real-estate/src/Services/SeoService.php';

        self::assertSame('wp-content/plugins/homlity-real-estate/src/Services/SeoService.php', $this->sanitizer->path($path));
    }

    public function testPathRelativizaRutasDeWpContentYDelCore(): void
    {
        self::assertSame('wp-content/themes/mi-tema/functions.php', $this->sanitizer->path(WP_CONTENT_DIR . '/themes/mi-tema/functions.php'));
        self::assertSame('wordpress/wp-includes/post.php', $this->sanitizer->path(ABSPATH . 'wp-includes/post.php'));
    }

    public function testPathOcultaRutasExternasDejandoSoloElArchivo(): void
    {
        self::assertSame('script.php', $this->sanitizer->path('/home/usuario-real/secreto/script.php'));
    }

    public function testRequestPathConservaSoloLaRuta(): void
    {
        self::assertSame(
            '/inmuebles/apartamento',
            $this->sanitizer->requestPath('https://inmobiliaria.test/inmuebles/apartamento?token=abc#top')
        );
    }

    public function testRequestPathUsaLaRaizCuandoNoHayRuta(): void
    {
        self::assertSame('/', $this->sanitizer->requestPath('https://inmobiliaria.test'));
    }

    public function testValueRedactaSegunElNombreDeLaClave(): void
    {
        self::assertSame('[redacted]', $this->sanitizer->value('abc', 'wasi_token'));
        self::assertSame('[redacted]', $this->sanitizer->value('abc', 'API-Key'));
        self::assertSame('[redacted]', $this->sanitizer->value('abc', 'crm_password'));
    }

    public function testValueConservaEscalares(): void
    {
        self::assertSame(42, $this->sanitizer->value(42, 'property_id'));
        self::assertTrue($this->sanitizer->value(true, 'negotiable'));
        self::assertNull($this->sanitizer->value(null, 'reason'));
    }

    public function testValueRecorreArreglosYLimpiaLasClaves(): void
    {
        $clean = $this->sanitizer->value(['Provider Name' => 'wasi', 'token' => 'abc'], 'contexto');

        self::assertSame(['providername' => 'wasi', 'token' => '[redacted]'], $clean);
    }

    public function testValueLimitaElNumeroDeElementos(): void
    {
        $clean = $this->sanitizer->value(range(1, 80), 'lista');

        self::assertTrue($clean['_truncated']);
        self::assertCount(51, $clean);
    }

    public function testValueLimitaLaProfundidad(): void
    {
        $deep = ['n1' => ['n2' => ['n3' => ['n4' => ['n5' => ['n6' => 'fondo']]]]]];

        self::assertSame('[depth-limited]', $this->sanitizer->value($deep)['n1']['n2']['n3']['n4']['n5']['n6']);
    }

    public function testValueSerializaWpError(): void
    {
        $clean = $this->sanitizer->value(new WP_Error('http_request_failed', 'Tiempo de espera agotado'));

        self::assertSame('WP_Error', $clean['type']);
        self::assertSame('http_request_failed', $clean['code']);
        self::assertSame('Tiempo de espera agotado', $clean['message']);
    }

    public function testValueSerializaExcepciones(): void
    {
        $clean = $this->sanitizer->value(new RuntimeException('Fallo con token=SECRETO'));

        self::assertSame(RuntimeException::class, $clean['type']);
        self::assertStringNotContainsString('SECRETO', $clean['message']);
    }

    public function testValueResumeObjetosArbitrarios(): void
    {
        self::assertStringStartsWith('[object:', $this->sanitizer->value(new \stdClass()));
    }

    public function testSyncContextSoloDejaPasarLasClavesPermitidas(): void
    {
        $clean = $this->sanitizer->syncContext([
            'operation'   => 'pull',
            'provider'    => 'wasi',
            'property_id' => 15,
            'wasi_token'  => 'abc',
            'cliente'     => 'Juan Pérez',
        ]);

        self::assertSame(['operation' => 'pull', 'provider' => 'wasi', 'property_id' => 15], $clean);
    }

    public function testSyncContextRespetaElFiltroDeClavesPermitidas(): void
    {
        WpStubs::addFilter(
            'homlity_error_reporter_safe_context_keys',
            static fn (array $keys): array => array_merge($keys, ['portal'])
        );

        self::assertArrayHasKey('portal', $this->sanitizer->syncContext(['portal' => 'fincaraiz']));
    }

    public function testBreadcrumbsNormalizaYLimitaAlUltimoTramo(): void
    {
        $breadcrumbs = [];
        for ($i = 0; $i < 60; $i++) {
            $breadcrumbs[] = [
                'timestamp' => '2026-08-19T10:00:00+00:00',
                'category'  => 'CRM Sync',
                'message'   => 'paso ' . $i,
                'data'      => ['status' => 'ok', 'cliente' => 'Juan'],
            ];
        }
        $breadcrumbs[] = 'no es un arreglo';

        $clean = $this->sanitizer->breadcrumbs($breadcrumbs);

        // Se conservan los últimos 50 elementos y se descarta el que no es arreglo.
        self::assertCount(49, $clean);
        self::assertSame('paso 59', end($clean)['message']);
        self::assertSame('crmsync', $clean[0]['category']);
        self::assertSame(['status' => 'ok'], $clean[0]['data']);
    }

    public function testBreadcrumbsAplicaValoresPorDefecto(): void
    {
        $clean = $this->sanitizer->breadcrumbs([[]]);

        self::assertSame('application', $clean[0]['category']);
        self::assertSame('', $clean[0]['message']);
        self::assertNotSame('', $clean[0]['timestamp']);
    }
}
