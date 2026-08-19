<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\TechnicalSheetData;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Datos de la ficha técnica.
 *
 * Los comparten la ficha de pantalla y la del PDF; antes estaban preparados
 * dentro del HTML de la primera y la segunda no existía.
 */
final class TechnicalSheetDataTest extends TestCase
{
    private const POST_ID = 501;

    /** @param array<string,mixed> $meta */
    private function givenProperty(array $meta = []): int
    {
        WpStubs::$postObjects[self::POST_ID] = (object) [
            'ID' => self::POST_ID,
            'post_type' => 'property',
            'post_status' => 'publish',
        ];
        WpStubs::$postTitles[self::POST_ID] = 'Apartamento en El Poblado';
        WpStubs::$permalinks[self::POST_ID] = 'https://royal.test/inmuebles/apto-4821/';
        WpStubs::setPostMeta(self::POST_ID, $meta);

        return self::POST_ID;
    }

    // ── Qué secciones se pintan ───────────────────────────────────────────

    /** Sin ajustes del widget la ficha sale completa. */
    public function testPorDefectoSePintaTodoMenosLaDireccion(): void
    {
        $visibility = TechnicalSheetData::visibility([]);

        self::assertFalse($visibility['address'], 'La dirección es sensible: va apagada salvo que se encienda.');
        foreach (['hero', 'advisor', 'finance', 'info', 'dimensions', 'description', 'features', 'media', 'legal'] as $section) {
            self::assertTrue($visibility[$section], 'La sección ' . $section . ' debería salir por defecto.');
        }
    }

    /**
     * Los interruptores llegan como 'yes'/'' de Elementor y como 'on'/'' de
     * Divi; los dos tienen que valer.
     */
    public function testEntiendeLosInterruptoresDeLosTresConstructores(): void
    {
        self::assertTrue(TechnicalSheetData::visibility(['show_address' => 'yes'])['address']);
        self::assertTrue(TechnicalSheetData::visibility(['show_address' => 'on'])['address']);
        self::assertTrue(TechnicalSheetData::visibility(['show_address' => true])['address']);
        self::assertFalse(TechnicalSheetData::visibility(['show_finance' => ''])['finance']);
        self::assertFalse(TechnicalSheetData::visibility(['show_finance' => 'no'])['finance']);
    }

    // ── Dinero ────────────────────────────────────────────────────────────

    public function testElDineroLlevaElSimboloDeSuMoneda(): void
    {
        self::assertSame('$ 890.000.000', TechnicalSheetData::formatMoney(890000000, 'COP'));
        self::assertSame('€ 350.000', TechnicalSheetData::formatMoney(350000, 'EUR'));
    }

    /** Una moneda que no está en la tabla se dice con su código. */
    public function testUnaMonedaDesconocidaSeDiceConSuCodigo(): void
    {
        self::assertSame('SEK 120.000', TechnicalSheetData::formatMoney(120000, 'SEK'));
    }

    /** Un precio a cero no es «$ 0»: es que no hay dato. */
    public function testUnPrecioACeroSeDiceComoSinDato(): void
    {
        self::assertSame('Sin dato', TechnicalSheetData::formatMoney(0, 'COP'));
    }

    public function testLasTresCifrasDeFinanzasSalenSiempre(): void
    {
        $postId = $this->givenProperty([
            '_property_price_sale' => '890000000',
            '_property_currency_sale' => 'COP',
        ]);

        $finance = TechnicalSheetData::forProperty($postId)['finance'];

        self::assertCount(3, $finance, 'Las tres cifras salen siempre, aunque no haya dato.');
        self::assertSame('$ 890.000.000', $finance[0]['value']);
        self::assertSame('Sin dato', $finance[1]['value'], 'Sin canon la tarjeta se queda, con el texto de vacío.');
    }

    // ── Dimensiones ───────────────────────────────────────────────────────

    /** La unidad solo tiene sentido detrás de un número. */
    public function testLaUnidadNoSePegaAUnDatoQueNoExiste(): void
    {
        $postId = $this->givenProperty(['_property_area' => '132']);

        $dimensions = TechnicalSheetData::forProperty($postId)['dimensions'];
        $byLabel = array_column($dimensions, 'value', 'label');

        self::assertSame('132 m²', $byLabel['Área total']);
        self::assertSame('Sin dato', $byLabel['Área lote'], 'Un área vacía no puede salir como «Sin dato m²».');
    }

    // ── Descripción ───────────────────────────────────────────────────────

    /**
     * La descripción es el texto del inmueble, no la página entera.
     *
     * Elementor engancha `the_content` y, cuando el post está montado con el
     * constructor, devuelve el documento completo se le pase lo que se le
     * pase. Pasando la descripción por ese filtro, la ficha acababa con toda
     * la página de Elementor dentro de la tarjeta «Descripción del inmueble».
     */
    public function testLaDescripcionNoArrastraLaPaginaDelConstructor(): void
    {
        $postId = $this->givenProperty();
        WpStubs::$postContent[$postId] = '<p>Apartamento con vista al valle.</p>';

        // Así se comporta Elementor: ignora lo que recibe y devuelve lo suyo.
        WpStubs::addFilter('the_content', static fn(): string => '<div class="elementor">PÁGINA ENTERA</div>');

        $description = TechnicalSheetData::forProperty($postId)['description'];

        self::assertStringNotContainsString('PÁGINA ENTERA', $description);
        self::assertStringContainsString('Apartamento con vista al valle.', $description);
    }

    // ── Asesor ────────────────────────────────────────────────────────────

    public function testElAsesorSaleDelUsuarioDeWordPress(): void
    {
        $postId = $this->givenProperty(['_property_agent_id' => '7']);
        WpStubs::setUser(7, 'joquendo', [
            'display_name' => 'Jorge Oquendo',
            'user_email' => 'jorge@royal.test',
        ], [], ['_homlity_advisor_phone' => '+57 300 123 4567']);

        $agent = TechnicalSheetData::forProperty($postId)['agent'];

        self::assertSame('Jorge Oquendo', $agent['name']);
        self::assertSame('+57 300 123 4567', $agent['phone']);
        self::assertSame('jorge@royal.test', $agent['email']);
        self::assertStringContainsString('573001234567', $agent['whatsapp']);
    }

    /**
     * Un inmueble sincronizado desde el CRM puede traer al asesor solo en los
     * metadatos, sin usuario de WordPress detrás.
     */
    public function testSinUsuarioElAsesorSaleDeLosMetadatosDelInmueble(): void
    {
        $postId = $this->givenProperty([
            '_property_agent_id' => '0',
            '_property_agent_name' => 'María Restrepo',
            '_property_agent_phone' => '3009998877',
            '_property_agent_email' => 'maria@royal.test',
        ]);

        $agent = TechnicalSheetData::forProperty($postId)['agent'];

        self::assertSame('María Restrepo', $agent['name']);
        self::assertSame('3009998877', $agent['phone']);
        self::assertSame('maria@royal.test', $agent['email']);
    }

    // ── Multimedia ────────────────────────────────────────────────────────

    /**
     * Cada CRM guarda la galería a su manera: JSON, lista separada por comas o
     * por saltos de línea, o un array de arrays.
     */
    public function testLaGaleriaSeLeeEnCualquieraDeLosFormatosDelCrm(): void
    {
        $urls = ['https://royal.test/a.jpg', 'https://royal.test/b.jpg'];

        self::assertSame($urls, TechnicalSheetData::urls(implode(',', $urls)));
        self::assertSame($urls, TechnicalSheetData::urls(implode("\n", $urls)));
        self::assertSame($urls, TechnicalSheetData::urls(wp_json_encode($urls)));
        self::assertSame($urls, TechnicalSheetData::urls([['url' => $urls[0]], ['url' => $urls[1]]]));
    }

    public function testLaGaleriaNoRepiteLaMismaFoto(): void
    {
        self::assertSame(
            ['https://royal.test/a.jpg'],
            TechnicalSheetData::urls('https://royal.test/a.jpg,https://royal.test/a.jpg')
        );
    }

    /** Sin galería, la imagen destacada es mejor que ninguna foto. */
    public function testSinGaleriaSeUsaLaImagenDestacada(): void
    {
        $postId = $this->givenProperty();
        WpStubs::$thumbnails[$postId] = 'https://royal.test/destacada.jpg';

        self::assertSame(
            ['https://royal.test/destacada.jpg'],
            TechnicalSheetData::forProperty($postId)['media']['images']
        );
    }

    /** Un brochure que no es una URL no se enlaza. */
    public function testUnBrochureQueNoEsUnaUrlSeDescarta(): void
    {
        $postId = $this->givenProperty(['_property_brochure' => 'pendiente de subir']);

        self::assertSame('', TechnicalSheetData::forProperty($postId)['media']['brochure']);
    }

    // ── Inmobiliaria y color ──────────────────────────────────────────────

    public function testLosDatosDeLaInmobiliariaSalenDelPanelSeoGeo(): void
    {
        $postId = $this->givenProperty();
        WpStubs::setOption('homlity_seo_settings', [
            'company_name' => 'Royal Propiedad Raíz',
            'company_nit' => 'NIT 901.234.567-8',
            'contact_email' => 'contacto@royal.test',
            'geo_city' => 'Medellín',
        ]);

        $company = TechnicalSheetData::forProperty($postId)['company'];

        self::assertSame('Royal Propiedad Raíz', $company['name']);
        self::assertSame('NIT 901.234.567-8', $company['document']);
        self::assertSame('contacto@royal.test', $company['email']);
        self::assertSame('Medellín', $company['city']);
    }

    /** El color del widget manda sobre el global del plugin. */
    public function testElColorDelWidgetGanaAlDelPlugin(): void
    {
        self::assertSame('#123456', TechnicalSheetData::primaryColor(
            ['sheet_primary' => '#123456'],
            ['primary_color' => '#abcdef']
        ));
        self::assertSame('#abcdef', TechnicalSheetData::primaryColor([], ['primary_color' => '#abcdef']));
        self::assertSame('#ff6752', TechnicalSheetData::primaryColor([], []));
    }

    /** Un color inventado no puede colarse en el atributo `style`. */
    public function testUnColorInvalidoCaeAlPorDefecto(): void
    {
        self::assertSame('#ff6752', TechnicalSheetData::primaryColor(['sheet_primary' => 'rojo'], []));
    }

    // ── Detalles ──────────────────────────────────────────────────────────

    public function testLosTerminosDeCadaTaxonomiaSalenEnDetallesClave(): void
    {
        $postId = $this->givenProperty(['_property_code' => 'RPR-4821']);
        WpStubs::$postTerms[$postId][PropertyTaxonomies::TAXONOMY_CITY] = [(object) ['name' => 'Medellín']];

        $info = array_column(TechnicalSheetData::forProperty($postId)['info'], 'value', 'label');

        self::assertSame('RPR-4821', $info['Código']);
        self::assertSame('Medellín', $info['Ciudad']);
        self::assertSame('Sin dato', $info['Gestión'], 'Una taxonomía vacía se dice, no se deja en blanco.');
    }

    /** La dirección solo entra en la lista cuando se enciende. */
    public function testLaDireccionSoloApareceSiSePide(): void
    {
        $postId = $this->givenProperty(['_property_address' => 'Carrera 43A # 7 Sur - 170']);

        $sinPedir = array_column(TechnicalSheetData::forProperty($postId)['info'], 'label');
        self::assertNotContains('Dirección', $sinPedir);

        $pidiendo = array_column(
            TechnicalSheetData::forProperty($postId, ['show_address' => 'yes'])['info'],
            'value',
            'label'
        );
        self::assertSame('Carrera 43A # 7 Sur - 170', $pidiendo['Dirección']);
    }
}
