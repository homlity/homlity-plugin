<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyMedia;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * De dónde salen las fotos de un inmueble.
 *
 * El metadato de galería llega en cuatro formas distintas según quién lo haya
 * escrito —el editor de WordPress, un CRM, un importador antiguo—, y antes
 * cada consumidor leía sólo la suya: el mismo inmueble tenía galería en la
 * ficha y ninguna imagen al compartirlo.
 */
final class PropertyMediaTest extends TestCase
{
    private const POST_ID = 600;

    /** @param array<string,mixed> $meta */
    private function givenProperty(array $meta): int
    {
        WpStubs::setPostMeta(self::POST_ID, $meta);

        return self::POST_ID;
    }

    public function testLeeUnaGaleriaDeIdsSeparadosPorComas(): void
    {
        WpStubs::$attachmentUrls[11] = 'https://example.test/uploads/11.jpg';
        WpStubs::$attachmentUrls[12] = 'https://example.test/uploads/12.jpg';
        $postId = $this->givenProperty(['_property_gallery' => '11,12']);

        self::assertSame(
            [
                ['id' => 11, 'url' => 'https://example.test/uploads/11.jpg'],
                ['id' => 12, 'url' => 'https://example.test/uploads/12.jpg'],
            ],
            PropertyMedia::galleryImages($postId)
        );
    }

    public function testLeeUnaGaleriaDeUrlsExternasDelCrm(): void
    {
        $postId = $this->givenProperty([
            '_property_gallery' => ['https://cdn.test/1.jpg', 'https://cdn.test/2.jpg'],
        ]);

        self::assertSame(
            ['https://cdn.test/1.jpg', 'https://cdn.test/2.jpg'],
            PropertyMedia::galleryUrls($postId)
        );
    }

    public function testLeeUnaGaleriaGuardadaComoJsonDeObjetos(): void
    {
        $postId = $this->givenProperty([
            '_property_gallery' => json_encode([
                ['url' => 'https://cdn.test/json-1.jpg'],
                ['url' => 'https://cdn.test/json-2.jpg'],
            ]),
        ]);

        self::assertSame(
            ['https://cdn.test/json-1.jpg', 'https://cdn.test/json-2.jpg'],
            PropertyMedia::galleryUrls($postId)
        );
    }

    public function testLeeUnaGaleriaSeparadaPorSaltosDeLinea(): void
    {
        $postId = $this->givenProperty([
            '_property_gallery' => "https://cdn.test/1.jpg\nhttps://cdn.test/2.jpg",
        ]);

        self::assertSame(
            ['https://cdn.test/1.jpg', 'https://cdn.test/2.jpg'],
            PropertyMedia::galleryUrls($postId)
        );
    }

    public function testDescartaLosAdjuntosQueYaNoExisten(): void
    {
        WpStubs::$attachmentUrls[20] = '';
        WpStubs::$attachmentUrls[21] = 'https://example.test/uploads/21.jpg';
        $postId = $this->givenProperty(['_property_gallery' => [20, 21]]);

        self::assertSame(['https://example.test/uploads/21.jpg'], PropertyMedia::galleryUrls($postId));
    }

    // ── Orden e imagen principal ──────────────────────────────────────────

    public function testLaDestacadaDeWordPressVaPrimero(): void
    {
        WpStubs::$thumbnailIds[self::POST_ID] = 30;
        WpStubs::$attachmentUrls[30] = 'https://example.test/uploads/destacada.jpg';
        $postId = $this->givenProperty([
            '_property_featured_image_url' => 'https://cdn.test/portada.jpg',
            '_property_gallery' => ['https://cdn.test/1.jpg'],
        ]);

        self::assertSame(
            ['id' => 30, 'url' => 'https://example.test/uploads/destacada.jpg'],
            PropertyMedia::mainImage($postId)
        );
    }

    /** La portada que marca el CRM manda sobre el orden de la galería. */
    public function testLaPortadaDelCrmVaAntesQueLaGaleria(): void
    {
        $postId = $this->givenProperty([
            '_property_featured_image_url' => 'https://cdn.test/portada.jpg',
            '_property_gallery' => ['https://cdn.test/1.jpg'],
        ]);

        self::assertSame(
            ['https://cdn.test/portada.jpg', 'https://cdn.test/1.jpg'],
            PropertyMedia::imageUrls($postId)
        );
    }

    public function testNoRepiteUnaFotoQueEstaEnLaGaleriaYComoPortada(): void
    {
        $postId = $this->givenProperty([
            '_property_featured_image_url' => 'https://cdn.test/1.jpg',
            '_property_gallery' => ['https://cdn.test/1.jpg', 'https://cdn.test/2.jpg'],
        ]);

        self::assertSame(
            ['https://cdn.test/1.jpg', 'https://cdn.test/2.jpg'],
            PropertyMedia::imageUrls($postId)
        );
    }

    public function testSinFotosDevuelveVacioSinReventar(): void
    {
        $postId = $this->givenProperty(['_property_gallery' => '']);

        self::assertSame([], PropertyMedia::images($postId));
        self::assertSame('', PropertyMedia::mainImageUrl($postId));
    }
}
