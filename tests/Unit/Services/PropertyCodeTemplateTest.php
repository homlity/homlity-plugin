<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * El componente del código del inmueble.
 *
 * Es un texto compuesto por tres partes que el editor escribe a mano, y las
 * dos de los extremos pueden quedar vacías: lo que se comprueba aquí es que
 * nunca se pinta un «Código: » sin código, que el espacio que se escribe en el
 * prefijo sobrevive al HTML, y que el enlace envuelve lo que se pidió.
 */
final class PropertyCodeTemplateTest extends TestCase
{
    private const POST_ID = 44;

    protected function setUp(): void
    {
        parent::setUp();

        WpStubs::setPost(self::POST_ID, 'Apartamento en Laureles', 'https://inmobiliaria.test/apto/', [
            '_property_code' => 'HOM-880',
        ]);
    }

    /** @param array<string,mixed> $args */
    private function render(array $args = []): string
    {
        ob_start();
        TemplateService::includeComponent('property-code.php', array_merge([
            'post_id'     => self::POST_ID,
            'before_text' => 'Código: ',
            'after_text'  => '',
        ], $args));

        return (string) ob_get_clean();
    }

    public function testPintaElCodigoConSuTextoDeAntesYDeDespues(): void
    {
        $html = $this->render(['after_text' => ' (ref.)']);

        self::assertStringContainsString('homlity-property-code__before', $html);
        self::assertStringContainsString('HOM-880', $html);
        self::assertStringContainsString('homlity-property-code__after', $html);
        self::assertStringContainsString('(ref.)', $html);
    }

    /** El espacio de «Código: » se colapsa en HTML y las partes quedarían pegadas. */
    public function testConservaElEspacioEscritoEnElTexto(): void
    {
        $html = $this->render();

        self::assertStringContainsString('Código:&nbsp;', $html);
    }

    public function testSinConservarEspaciosSeparaLasPartesConUnoSimple(): void
    {
        $html = $this->render(['keep_spaces' => false]);

        self::assertStringNotContainsString('&nbsp;', $html);
        self::assertStringContainsString('</span> <span class="homlity-property-code__value">', $html);
    }

    public function testSinCodigoNiRespaldoNoSePintaNada(): void
    {
        WpStubs::$postMeta[self::POST_ID] = [];

        self::assertSame('', $this->render());
    }

    /** «Código: consúltanos» no dice lo que se quiso decir. */
    public function testElTextoDeRespaldoSustituyeAlCodigoYSilenciaLosExtremos(): void
    {
        WpStubs::$postMeta[self::POST_ID] = [];

        $html = $this->render([
            'after_text'    => ' (ref.)',
            'fallback_text' => 'Consúltalo con un asesor',
        ]);

        self::assertStringContainsString('Consúltalo con un asesor', $html);
        self::assertStringNotContainsString('Código:', $html);
        self::assertStringNotContainsString('(ref.)', $html);
    }

    public function testElEnlaceEnvuelveTodoElTexto(): void
    {
        $html = $this->render(['link_url' => 'https://api.whatsapp.com/send?phone=573001112233']);

        self::assertStringContainsString('<a class="homlity-property-code__link" href="https://api.whatsapp.com/send?phone=573001112233"', $html);
        self::assertStringContainsString('target="_blank"', $html);
        self::assertStringContainsString('rel="noopener noreferrer"', $html);
        self::assertMatchesRegularExpression('/<a [^>]*>.*__before.*__value.*<\/a>/s', $html);
    }

    public function testElEnlaceSoloSobreElCodigoDejaFueraLosExtremos(): void
    {
        $html = $this->render([
            'link_url'   => 'https://api.whatsapp.com/send?phone=573001112233',
            'link_scope' => 'code',
        ]);

        self::assertMatchesRegularExpression('/__before.*<a [^>]*>.*__value.*<\/a>/s', $html);
        self::assertDoesNotMatchRegularExpression('/<a [^>]*>[^<]*<span class="homlity-property-code__before"/s', $html);
    }

    public function testNoAbreEnNuevaPestanaCuandoSeDesactiva(): void
    {
        $html = $this->render([
            'link_url'        => 'https://api.whatsapp.com/send?phone=573001112233',
            'open_in_new_tab' => false,
        ]);

        self::assertStringNotContainsString('target="_blank"', $html);
    }

    public function testLaEtiquetaHtmlSeLimitaALasPermitidas(): void
    {
        self::assertStringStartsWith('<h3 ', $this->render(['html_tag' => 'h3']));
        self::assertStringStartsWith('<div ', $this->render(['html_tag' => 'script']));
    }

    public function testElCodigoPublicoDeSimiTienePrioridad(): void
    {
        WpStubs::setPostMeta(self::POST_ID, [
            '_property_code'  => 'HOM-880',
            '_simi_sync_code' => '718-5526',
        ]);

        self::assertStringContainsString('718-5526', $this->render());
    }
}
