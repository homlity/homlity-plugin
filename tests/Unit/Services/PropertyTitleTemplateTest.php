<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class PropertyTitleTemplateTest extends TestCase
{
    private const POST_ID = 10;

    protected function setUp(): void
    {
        parent::setUp();

        WpStubs::$postTitles[self::POST_ID] = 'Apartamento en Belén';
        WpStubs::setPostMeta(self::POST_ID, [
            '_property_code' => 'INT-10',
            '_simi_sync_code' => '718-5526',
        ]);
    }

    private function render(bool $showCode = false): string
    {
        ob_start();
        TemplateService::includeComponent('property-title.php', [
            'post_id' => self::POST_ID,
            'show_code' => $showCode,
        ]);

        return (string) ob_get_clean();
    }

    public function testOcultaElCodigoPorDefecto(): void
    {
        $html = $this->render();

        self::assertStringContainsString('Apartamento en Belén', $html);
        self::assertStringNotContainsString('Código:', $html);
        self::assertStringNotContainsString('718-5526', $html);
    }

    public function testMuestraElCodigoPublicoDentroDelTituloCuandoSeActiva(): void
    {
        $html = $this->render(true);

        self::assertStringContainsString('property-title-widget__code', $html);
        self::assertStringContainsString('Código: 718-5526', $html);
    }

    public function testNoMuestraUnaEtiquetaVaciaCuandoElInmuebleNoTieneCodigo(): void
    {
        WpStubs::$postMeta[self::POST_ID] = [];

        $html = $this->render(true);

        self::assertStringContainsString('Apartamento en Belén', $html);
        self::assertStringNotContainsString('property-title-widget__code', $html);
        self::assertStringNotContainsString('Código:', $html);
    }
}
