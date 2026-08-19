<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\TechnicalSheetService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class TechnicalSheetServiceTest extends TestCase
{
    private function givenProperty(int $id, string $slug): void
    {
        WpStubs::$postObjects[$id] = new \WP_Post([
            'ID' => $id,
            'post_type' => PropertyPostType::POST_TYPE,
            'post_name' => $slug,
        ]);
        WpStubs::$permalinks[$id] = 'https://example.test/inmueble/' . $slug . '/';
    }

    private function givenSheetPage(int $id, string $status): void
    {
        WpStubs::$postStatuses[$id] = $status;
        WpStubs::$options[TechnicalSheetService::PAGE_OPTION] = $id;
    }

    public function testSheetUrlUsesLegacyQueryArgWhenNoPageIsConfigured(): void
    {
        $this->givenProperty(10, 'apartamento-guatape');

        self::assertSame(
            'https://example.test/inmueble/apartamento-guatape/?homlity_sheet=1',
            TechnicalSheetService::sheetUrl(10)
        );
    }

    public function testSheetUrlUsesTheBuilderRouteWhenAPageIsPublished(): void
    {
        $this->givenProperty(10, 'apartamento-guatape');
        $this->givenSheetPage(55, 'publish');

        self::assertSame(
            'https://example.test/ficha-tecnica/apartamento-guatape/',
            TechnicalSheetService::sheetUrl(10)
        );
    }

    /**
     * Routing a public URL to a draft would 404 for every visitor, so an
     * unpublished selection must fall back to the plugin template.
     */
    public function testAnUnpublishedPageIsIgnored(): void
    {
        $this->givenProperty(10, 'apartamento-guatape');
        $this->givenSheetPage(55, 'draft');

        self::assertSame(0, TechnicalSheetService::pageId());
        self::assertSame(
            'https://example.test/inmueble/apartamento-guatape/?homlity_sheet=1',
            TechnicalSheetService::sheetUrl(10)
        );
    }

    public function testSheetUrlIsEmptyForAnythingThatIsNotAProperty(): void
    {
        WpStubs::$postObjects[7] = new \WP_Post([
            'ID' => 7,
            'post_type' => 'page',
            'post_name' => 'contacto',
        ]);

        self::assertSame('', TechnicalSheetService::sheetUrl(7));
        self::assertSame('', TechnicalSheetService::sheetUrl(999));
    }

    public function testPdfUrlAppendsTheDownloadFlag(): void
    {
        $this->givenProperty(10, 'apartamento-guatape');
        $this->givenSheetPage(55, 'publish');

        self::assertSame(
            'https://example.test/ficha-tecnica/apartamento-guatape/?download=1',
            TechnicalSheetService::pdfUrl(10)
        );
    }

    // ── El botón entrega un PDF ───────────────────────────────────────────────

    /**
     * Pulsar el botón debe bajar un archivo, no abrir una página con la ficha.
     */
    public function testTheButtonPointsAtThePdf(): void
    {
        $this->givenProperty(10, 'apartamento-guatape');
        $this->givenSheetPage(55, 'publish');

        self::assertTrue(TechnicalSheetService::pdfAvailable(), 'Dompdf viene en vendor/.');
        self::assertSame(
            ['url' => 'https://example.test/ficha-tecnica/apartamento-guatape/?download=1', 'is_download' => true],
            TechnicalSheetService::buttonTarget(10)
        );
    }

    public function testTheButtonPointsAtThePdfOnTheLegacyUrlToo(): void
    {
        $this->givenProperty(10, 'apartamento-guatape');

        self::assertSame(
            [
                'url' => 'https://example.test/inmueble/apartamento-guatape/?homlity_sheet=1&download=1',
                'is_download' => true,
            ],
            TechnicalSheetService::buttonTarget(10)
        );
    }

    /**
     * Sin Dompdf la URL de descarga devuelve la ficha en HTML. Prometer un
     * archivo ahí guardaría un .html en las descargas del visitante.
     */
    public function testWithoutDompdfTheButtonOpensTheHtmlSheet(): void
    {
        $this->givenProperty(10, 'apartamento-guatape');
        $this->givenSheetPage(55, 'publish');
        WpStubs::addFilter('homlity_technical_sheet_pdf_available', static fn(): bool => false);

        self::assertFalse(TechnicalSheetService::pdfAvailable());
        self::assertSame(
            ['url' => 'https://example.test/ficha-tecnica/apartamento-guatape/', 'is_download' => false],
            TechnicalSheetService::buttonTarget(10)
        );
    }

    /** El sitio puede volver a la ficha en pantalla desde el widget. */
    public function testTheSiteCanAskForTheHtmlSheetInstead(): void
    {
        $this->givenProperty(10, 'apartamento-guatape');
        $this->givenSheetPage(55, 'publish');

        self::assertSame(
            ['url' => 'https://example.test/ficha-tecnica/apartamento-guatape/', 'is_download' => false],
            TechnicalSheetService::buttonTarget(10, false)
        );
    }

    public function testTheButtonHasNoTargetForAnythingThatIsNotAProperty(): void
    {
        self::assertSame(['url' => '', 'is_download' => false], TechnicalSheetService::buttonTarget(999));
    }
}
