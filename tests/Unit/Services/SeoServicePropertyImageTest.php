<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\SeoService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * La imagen con la que se comparte una ficha de inmueble.
 *
 * Casi ningún inmueble importado desde un CRM tiene imagen destacada en la
 * biblioteca de medios: sus fotos son URLs externas guardadas en post meta. Si
 * la resolución se queda en `get_the_post_thumbnail_url()`, WhatsApp y las
 * redes acaban mostrando el logo del sitio en lugar del inmueble.
 */
final class SeoServicePropertyImageTest extends TestCase
{
    public function testUsaLaImagenDestacadaDeWordPressCuandoExiste(): void
    {
        WpStubs::$thumbnailIds[10] = 99;
        WpStubs::$attachmentUrls[99] = 'https://cdn.test/destacada.jpg';
        WpStubs::setPostMeta(10, [
            '_property_gallery' => ['https://cdn.test/galeria-1.jpg'],
        ]);

        $this->assertSame('https://cdn.test/destacada.jpg', SeoService::propertyImageUrl(10));
    }

    public function testCaeEnLaPortadaExternaCuandoNoHayImagenDestacada(): void
    {
        WpStubs::setPostMeta(11, [
            '_property_featured_image_url' => 'https://cdn.test/portada.jpg',
            '_property_gallery' => ['https://cdn.test/galeria-1.jpg'],
        ]);

        $this->assertSame('https://cdn.test/portada.jpg', SeoService::propertyImageUrl(11));
    }

    public function testUsaLaPrimeraFotoDeLaGaleriaGuardadaComoArrayDeUrls(): void
    {
        WpStubs::setPostMeta(12, [
            '_property_gallery' => [
                'https://lh3.googleusercontent.com/primera=s1200',
                'https://lh3.googleusercontent.com/segunda=s1200',
            ],
        ]);

        $this->assertSame(
            'https://lh3.googleusercontent.com/primera=s1200',
            SeoService::propertyImageUrl(12)
        );
    }

    public function testUsaLaPrimeraFotoDeUnaGaleriaGuardadaComoJson(): void
    {
        WpStubs::setPostMeta(13, [
            '_property_gallery' => json_encode([
                ['url' => 'https://cdn.test/json-1.jpg'],
                ['url' => 'https://cdn.test/json-2.jpg'],
            ]),
        ]);

        $this->assertSame('https://cdn.test/json-1.jpg', SeoService::propertyImageUrl(13));
    }

    /**
     * Una galería del editor de WordPress son ids de adjunto, no URLs: pasarlos
     * por `esc_url_raw()` produciría "http://123" y una previsualización rota.
     */
    public function testResuelveLosIdsDeAdjuntoDeUnaGaleriaDelEditor(): void
    {
        WpStubs::$attachmentUrls[77] = 'https://example.test/uploads/foto-77.jpg';
        WpStubs::setPostMeta(14, ['_property_gallery' => '77,78']);

        $this->assertSame('https://example.test/uploads/foto-77.jpg', SeoService::propertyImageUrl(14));
    }

    public function testSaltaLosAdjuntosBorradosYSigueConElSiguiente(): void
    {
        WpStubs::$attachmentUrls[80] = '';
        WpStubs::$attachmentUrls[81] = 'https://example.test/uploads/foto-81.jpg';
        WpStubs::setPostMeta(15, ['_property_gallery' => [80, 81]]);

        $this->assertSame('https://example.test/uploads/foto-81.jpg', SeoService::propertyImageUrl(15));
    }

    public function testDevuelveVacioCuandoElInmuebleNoTieneNingunaFoto(): void
    {
        WpStubs::setPostMeta(16, ['_property_gallery' => '']);

        $this->assertSame('', SeoService::propertyImageUrl(16));
    }

    private function propertyRequest(int $postId): void
    {
        WpStubs::$singularPostType = 'property';
        $post = new \WP_Post(['ID' => $postId, 'post_type' => 'property']);
        // El stub de get_post() sin argumentos lee la clave 0, que es como
        // WordPress expone el post de la consulta actual.
        WpStubs::$postObjects[$postId] = $post;
        WpStubs::$postObjects[0] = $post;
    }

    public function testEntregaLaFotoDelInmuebleAlPluginSeoQueNoEncontroNinguna(): void
    {
        $this->propertyRequest(20);
        WpStubs::setPostMeta(20, [
            '_property_gallery' => ['https://cdn.test/inmueble-20.jpg'],
        ]);

        $filtered = (new SeoService())->filterSeoPluginImage('https://cdn.test/logo-por-defecto.png');

        $this->assertSame('https://cdn.test/inmueble-20.jpg', $filtered);
    }

    /**
     * Una imagen elegida a mano en Rank Math o Yoast es una decisión editorial:
     * la galería no debe pisarla.
     */
    public function testRespetaLaImagenSocialElegidaAManoEnElPluginSeo(): void
    {
        $this->propertyRequest(21);
        WpStubs::setPostMeta(21, [
            '_property_gallery' => ['https://cdn.test/inmueble-21.jpg'],
            'rank_math_facebook_image_id' => 512,
        ]);

        $filtered = (new SeoService())->filterSeoPluginImage('https://cdn.test/elegida-a-mano.jpg');

        $this->assertSame('https://cdn.test/elegida-a-mano.jpg', $filtered);
    }

    public function testNoTocaLaImagenFueraDeLasFichasDeInmueble(): void
    {
        WpStubs::$singularPostType = 'page';

        $filtered = (new SeoService())->filterSeoPluginImage('https://cdn.test/pagina.jpg');

        $this->assertSame('https://cdn.test/pagina.jpg', $filtered);
    }

    /**
     * Dos juegos de etiquetas Open Graph en el mismo head dejan al crawler
     * eligiendo: si el plugin SEO ya imprimió el suyo, este servicio se calla.
     */
    public function testNoDuplicaLasEtiquetasCuandoElPluginSeoYaImprimioLasSuyas(): void
    {
        $this->propertyRequest(22);
        WpStubs::setPostMeta(22, ['_property_gallery' => ['https://cdn.test/inmueble-22.jpg']]);

        $service = new SeoService();
        $service->filterSeoPluginImage('https://cdn.test/logo-por-defecto.png');

        ob_start();
        $service->renderOpenGraphTags();

        $this->assertSame('', (string) ob_get_clean());
    }

    public function testImprimeSusEtiquetasCuandoNoHayPluginSeo(): void
    {
        $this->propertyRequest(23);
        WpStubs::setPostMeta(23, ['_property_gallery' => ['https://cdn.test/inmueble-23.jpg']]);

        ob_start();
        (new SeoService())->renderOpenGraphTags();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString(
            '<meta property="og:image" content="https://cdn.test/inmueble-23.jpg" />',
            $output
        );
    }
}
