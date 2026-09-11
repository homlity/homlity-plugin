<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations\Elementor;

use Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags\PropertyFields;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Los datos del inmueble expuestos a Elementor.
 *
 * Las etiquetas no se pueden instanciar sin Elementor cargado, así que lo que
 * se prueba es el catálogo del que tiran las cuatro: de qué inmueble leen, qué
 * devuelven por cada campo y qué pasa cuando el dato no está.
 */
final class PropertyFieldsTest extends TestCase
{
    private const POST_ID = 500;

    /** @param array<string,mixed> $meta */
    private function givenProperty(array $meta = [], int $postId = self::POST_ID): int
    {
        WpStubs::$postObjects[$postId] = (object) [
            'ID' => $postId,
            'post_type' => PropertyPostType::POST_TYPE,
            'post_status' => 'publish',
        ];
        WpStubs::setPostMeta($postId, $meta);
        WpStubs::$currentPostId = $postId;

        return $postId;
    }

    private function givenTerm(int $postId, string $taxonomy, int $termId, string $slug, string $name): void
    {
        WpStubs::$postTerms[$postId][$taxonomy][] = WpStubs::setTerm($termId, $taxonomy, $slug, $name);
    }

    // ── De qué inmueble se leen los datos ─────────────────────────────────

    public function testTomaElInmuebleDelBucleCuandoNoSeFijaNinguno(): void
    {
        $this->givenProperty();

        self::assertSame(self::POST_ID, PropertyFields::resolvePropertyId());
    }

    /** El editor de Elementor no está en ninguna ficha: sin fijarlo, no hay dato. */
    public function testUnIdFijadoEnElControlManda(): void
    {
        $this->givenProperty();
        $other = $this->givenProperty([], 501);
        WpStubs::$currentPostId = self::POST_ID;

        self::assertSame($other, PropertyFields::resolvePropertyId($other));
    }

    public function testIgnoraUnIdQueNoEsDeUnInmueble(): void
    {
        $this->givenProperty();
        WpStubs::$postObjects[777] = (object) ['ID' => 777, 'post_type' => 'page', 'post_status' => 'publish'];

        self::assertSame(self::POST_ID, PropertyFields::resolvePropertyId(777));
    }

    public function testFueraDeUnInmuebleNoResuelveNada(): void
    {
        WpStubs::$postObjects[888] = (object) ['ID' => 888, 'post_type' => 'page', 'post_status' => 'publish'];
        WpStubs::$currentPostId = 888;

        self::assertSame(0, PropertyFields::resolvePropertyId());
    }

    // ── Catálogo ──────────────────────────────────────────────────────────

    /**
     * Cada campo del desplegable tiene que resolver: una entrada sin `match`
     * detrás es una opción que el maquetador elige y que sale en blanco.
     */
    public function testTodoCampoDelDesplegableTieneResolutor(): void
    {
        $postId = $this->givenProperty(['_property_code' => 'ABC-1']);

        foreach (array_keys(PropertyFields::textChoices()) as $field) {
            self::assertIsString(
                PropertyFields::text($postId, $field),
                sprintf('El campo «%s» no devuelve un valor.', $field)
            );
        }
    }

    public function testLasOpcionesAgrupadasCubrenElCatalogoCompleto(): void
    {
        $agrupadas = [];
        foreach (PropertyFields::textGroups() as $group) {
            self::assertNotSame('', $group['label']);
            $agrupadas = array_merge($agrupadas, array_keys($group['options']));
        }

        self::assertSame(array_keys(PropertyFields::textChoices()), $agrupadas);
    }

    public function testUnCampoDesconocidoNoRevienta(): void
    {
        $postId = $this->givenProperty();

        self::assertSame('', PropertyFields::text($postId, 'campo-que-ya-no-existe'));
    }

    // ── Valores ───────────────────────────────────────────────────────────

    public function testElPrecioSalePresentadoConSuMoneda(): void
    {
        $postId = $this->givenProperty([
            '_property_price_sale' => '350000000',
            '_property_currency_sale' => 'COP',
        ]);

        self::assertSame('$ 350.000.000', PropertyFields::text($postId, 'price_sale'));
    }

    /**
     * El precio lo formatea quien ya lo hace en las tarjetas: si el sitio
     * engancha su propio formateador, la etiqueta tiene que respetarlo o el
     * mismo precio saldría de dos maneras en la misma página.
     */
    public function testRespetaElFormateadorDePreciosDelSitio(): void
    {
        WpStubs::addFilter(
            'homlity_plugin_format_price',
            static fn($price, $currency = null): string => 'COP ' . (int) $price
        );
        $postId = $this->givenProperty(['_property_price_sale' => '350000000']);

        self::assertSame('COP 350000000', PropertyFields::text($postId, 'price_sale'));
    }

    /** En bruto es lo que necesitan los contadores y las condiciones. */
    public function testElPrecioEnBrutoEsElNumeroPelado(): void
    {
        $postId = $this->givenProperty(['_property_price_sale' => '350000000']);

        self::assertSame('350000000', PropertyFields::text($postId, 'price_sale', PropertyFields::FORMAT_RAW));
    }

    public function testElPrecioPrincipalCaeEnElArriendoCuandoNoHayVenta(): void
    {
        $postId = $this->givenProperty([
            '_property_price_rent' => '2500000',
            '_property_currency_rent' => 'COP',
        ]);

        self::assertSame('$ 2.500.000', PropertyFields::text($postId, 'price'));
    }

    public function testUnPrecioAceroSeQuedaEnBlancoYNoEnCeroPesos(): void
    {
        $postId = $this->givenProperty(['_property_price_sale' => '0']);

        self::assertSame('', PropertyFields::text($postId, 'price_sale'));
    }

    public function testElAreaLlevaSuUnidad(): void
    {
        $postId = $this->givenProperty(['_property_area' => '70']);

        self::assertSame('70 m²', PropertyFields::text($postId, 'area'));
        self::assertSame('70', PropertyFields::text($postId, 'area', PropertyFields::FORMAT_RAW));
    }

    public function testLosInterruptoresSeLeenComoSiONo(): void
    {
        $postId = $this->givenProperty(['_property_featured' => '1', '_property_negotiable' => '']);

        self::assertSame('Sí', PropertyFields::text($postId, 'featured'));
        self::assertSame('No', PropertyFields::text($postId, 'negotiable'));
        self::assertSame('1', PropertyFields::text($postId, 'featured', PropertyFields::FORMAT_RAW));
    }

    public function testLasTaxonomiasSalenComoNombresSeparadosPorComas(): void
    {
        $postId = $this->givenProperty();
        $this->givenTerm($postId, PropertyTaxonomies::TAXONOMY_CITY, 11, 'envigado', 'Envigado');

        self::assertSame('Envigado', PropertyFields::text($postId, 'city'));
        self::assertSame('envigado', PropertyFields::text($postId, 'city', PropertyFields::FORMAT_RAW));
    }

    public function testLaUbicacionCompletaEncadenaBarrioCiudadYDepartamento(): void
    {
        $postId = $this->givenProperty();
        $this->givenTerm($postId, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, 20, 'gualandayes', 'El Gualandayes');
        $this->givenTerm($postId, PropertyTaxonomies::TAXONOMY_CITY, 21, 'envigado', 'Envigado');

        self::assertSame('El Gualandayes, Envigado', PropertyFields::text($postId, 'location'));
    }

    /**
     * El estado vivía en un metadato antes de tener taxonomía propia y los
     * catálogos sincronizados siguen enviándolo así.
     */
    public function testElEstadoCaeAlMetadatoCuandoNoHayTermino(): void
    {
        $postId = $this->givenProperty(['_property_condition' => 'Usado']);

        self::assertSame('Usado', PropertyFields::text($postId, 'condition'));
    }

    public function testCuentaLasFotosDeUnaGaleriaExterna(): void
    {
        $postId = $this->givenProperty([
            '_property_gallery' => ['https://cdn.test/1.jpg', 'https://cdn.test/2.jpg'],
        ]);

        self::assertSame('2', PropertyFields::text($postId, 'gallery_count'));
    }

    // ── Enlaces ───────────────────────────────────────────────────────────

    public function testElMapaSeArmaConLasCoordenadasCuandoNoHayEnlaceManual(): void
    {
        $postId = $this->givenProperty([
            '_property_latitude' => '6.1600022',
            '_property_longitude' => '-75.5866764',
        ]);

        self::assertSame(
            'https://www.google.com/maps/search/?api=1&query=6.1600022%2C-75.5866764',
            PropertyFields::url($postId, 'maps')
        );
    }

    public function testElEnlaceManualDelMapaMandaSobreLasCoordenadas(): void
    {
        $postId = $this->givenProperty([
            '_property_maps_url' => 'https://maps.app.goo.gl/abc',
            '_property_latitude' => '6.16',
            '_property_longitude' => '-75.58',
        ]);

        self::assertSame('https://maps.app.goo.gl/abc', PropertyFields::url($postId, 'maps'));
    }

    /** Un `tel:` sin número es un botón que no lleva a ninguna parte. */
    public function testSinTelefonoNoSeDevuelveUnEnlaceVacio(): void
    {
        $postId = $this->givenProperty(['_property_contact_phone' => '']);

        self::assertSame('', PropertyFields::url($postId, 'contact_phone'));
    }

    public function testElTelefonoDeContactoSeLimpiaParaElEnlace(): void
    {
        $postId = $this->givenProperty(['_property_contact_phone' => '+57 (300) 123 45 67']);

        self::assertSame('tel:+573001234567', PropertyFields::url($postId, 'contact_phone'));
    }

    public function testElVideoTomaLaPrimeraUrlDeLaLista(): void
    {
        $postId = $this->givenProperty([
            '_property_videos' => "https://youtu.be/uno\nhttps://youtu.be/dos",
        ]);

        self::assertSame('https://youtu.be/uno', PropertyFields::url($postId, 'video'));
    }

    public function testUnEnlaceDesconocidoNoRevienta(): void
    {
        $postId = $this->givenProperty();

        self::assertSame('', PropertyFields::url($postId, 'enlace-inventado'));
    }

    // ── Imágenes ──────────────────────────────────────────────────────────

    public function testLaImagenPrincipalCaeEnLaGaleriaExterna(): void
    {
        $postId = $this->givenProperty([
            '_property_gallery' => ['https://cdn.test/1.jpg', 'https://cdn.test/2.jpg'],
        ]);

        self::assertSame(
            ['id' => 0, 'url' => 'https://cdn.test/1.jpg'],
            PropertyFields::image($postId, 'featured')
        );
    }

    public function testLaGaleriaLlevaElIdCuandoLaFotoEstaEnLaBiblioteca(): void
    {
        WpStubs::$attachmentUrls[61] = 'https://example.test/uploads/61.jpg';
        $postId = $this->givenProperty(['_property_gallery' => '61']);

        self::assertSame(
            [['id' => 61, 'url' => 'https://example.test/uploads/61.jpg']],
            PropertyFields::gallery($postId)
        );
    }

    public function testFueraDeUnInmuebleNoDevuelveNiDatoNiImagen(): void
    {
        WpStubs::$postObjects[888] = (object) ['ID' => 888, 'post_type' => 'page', 'post_status' => 'publish'];

        self::assertSame('', PropertyFields::text(888, 'code'));
        self::assertSame('', PropertyFields::url(888, 'permalink'));
        self::assertSame(['id' => 0, 'url' => ''], PropertyFields::image(888, 'featured'));
        self::assertSame([], PropertyFields::gallery(888));
    }
    public function testWhatsappDinamicoUsaElAsesorDeCadaInmuebleDelBucle(): void
    {
        $first = $this->givenProperty(['_property_agent_id' => 77]);
        $second = $this->givenProperty(['_property_agent_id' => 78], 501);
        WpStubs::setUser(77, 'asesor', [], ['author'], ['_homlity_advisor_phone' => '+57 301 555 4433']);
        WpStubs::setUser(78, 'otro-asesor', [], ['author'], ['phone' => '+57 302 555 4433']);
        WpStubs::$postTypes[] = 'whatsapp-accounts';
        WpStubs::$posts[] = [WpStubs::makePost(900, [
            'nta_wa_account_info' => ['number' => '573001112233'],
        ])];

        foreach ([$first => '573015554433', $second => '573025554433'] as $postId => $phone) {
            WpStubs::$currentPostId = $postId;
            $url = PropertyFields::url(PropertyFields::resolvePropertyId(), 'whatsapp');
            self::assertStringContainsString('phone=' . $phone, $url);
            self::assertStringNotContainsString('phone=573001112233', $url);
        }
    }

}
