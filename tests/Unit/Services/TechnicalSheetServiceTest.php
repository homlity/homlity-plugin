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
}
