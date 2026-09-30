<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
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

    private function render(bool $showCode = false, bool $showOperation = false): string
    {
        ob_start();
        TemplateService::includeComponent('property-title.php', [
            'post_id' => self::POST_ID,
            'show_code' => $showCode,
            'show_operation' => $showOperation,
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

    public function testIntegraLaGestionEnLaFraseDelTitulo(): void
    {
        WpStubs::$postTerms[self::POST_ID][PropertyTaxonomies::TAXONOMY_OPERATION] = [
            WpStubs::setTerm(3, PropertyTaxonomies::TAXONOMY_OPERATION, 'venta', 'Venta'),
        ];

        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($this->render(true, true))));

        self::assertSame('Apartamento en venta en Belén Código: 718-5526', $text);
        self::assertStringContainsString('<span class="property-title-widget__operation">en venta</span>', $this->render(false, true));
    }

    public function testSinActivarLaGestionElTituloNoCambia(): void
    {
        WpStubs::$postTerms[self::POST_ID][PropertyTaxonomies::TAXONOMY_OPERATION] = [
            WpStubs::setTerm(3, PropertyTaxonomies::TAXONOMY_OPERATION, 'venta', 'Venta'),
        ];

        $html = $this->render();

        self::assertStringContainsString('Apartamento en Belén', $html);
        self::assertStringNotContainsString('venta', $html);
    }
}
