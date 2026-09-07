<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Las imágenes que van al JSON-LD del inmueble.
 *
 * `image` es un campo que Google usa para los resultados enriquecidos, y hasta
 * ahora sólo se resolvían ids de adjunto: los inmuebles traídos de un CRM
 * guardan URLs externas, así que el catálogo entero se publicaba sin imagen.
 */
final class SchemaHelpersImagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/includes/schema/class-homlity-schema-helpers.php';
    }

    public function testResuelveLosIdsDeAdjuntoDeUnaGaleriaDelEditor(): void
    {
        WpStubs::$attachmentUrls[41] = 'https://example.test/uploads/41.jpg';
        WpStubs::$attachmentUrls[42] = 'https://example.test/uploads/42.jpg';
        WpStubs::setPostMeta(30, ['_property_gallery' => '41,42']);

        $this->assertSame(
            ['https://example.test/uploads/41.jpg', 'https://example.test/uploads/42.jpg'],
            \Homlity_Schema_Helpers::images(30)
        );
    }

    public function testDevuelveLasUrlsExternasDeUnaGaleriaSincronizadaDelCrm(): void
    {
        WpStubs::setPostMeta(31, [
            '_property_gallery' => [
                'https://lh3.googleusercontent.com/primera=s1200',
                'https://lh3.googleusercontent.com/segunda=s1200',
            ],
        ]);

        $this->assertSame(
            [
                'https://lh3.googleusercontent.com/primera=s1200',
                'https://lh3.googleusercontent.com/segunda=s1200',
            ],
            \Homlity_Schema_Helpers::images(31)
        );
    }

    public function testLeeLaGaleriaGuardadaComoJsonDeObjetos(): void
    {
        WpStubs::setPostMeta(32, [
            '_property_gallery' => json_encode([
                ['url' => 'https://cdn.test/json-1.jpg'],
                ['url' => 'https://cdn.test/json-2.jpg'],
            ]),
        ]);

        $this->assertSame(
            ['https://cdn.test/json-1.jpg', 'https://cdn.test/json-2.jpg'],
            \Homlity_Schema_Helpers::images(32)
        );
    }

    public function testCaeEnLaPortadaExternaCuandoNoHayGaleria(): void
    {
        WpStubs::setPostMeta(33, [
            '_property_gallery' => '',
            '_property_featured_image_url' => 'https://cdn.test/portada.jpg',
        ]);

        $this->assertSame(['https://cdn.test/portada.jpg'], \Homlity_Schema_Helpers::images(33));
    }

    public function testDevuelveVacioCuandoElInmuebleNoTieneFotos(): void
    {
        WpStubs::setPostMeta(34, ['_property_gallery' => '']);

        $this->assertSame([], \Homlity_Schema_Helpers::images(34));
    }
}
