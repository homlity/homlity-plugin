<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Tests\Support\PdfProbe;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;
use WP_Term;

/**
 * Que el PDF de la ficha técnica se genere bien.
 *
 * Esto es lo que comprueba el generador entero —TemplateService::technicalSheetPdf()—
 * y no la maquetación: que enchufa la plantilla del PDF y no la de pantalla,
 * que le pasa su hoja de estilos, y que lo que sale es un archivo que un lector
 * de PDF puede abrir y que lleva dentro los datos del inmueble.
 *
 * La maquetación —columnas, cabecera repetida, tope de fotos— la miden aparte
 * en TechnicalSheetPdfLayoutTest, que monta el PDF por su cuenta; si solo
 * existieran esas, romper el cableado del generador no lo notaría nadie.
 *
 * Se mira el PDF tal cual sale, comprimido incluido: PdfProbe descomprime los
 * flujos en vez de pedirle a Dompdf que no comprima, para que lo que se
 * comprueba sean los bytes que se descargaría alguien.
 */
final class TechnicalSheetPdfGenerationTest extends TestCase
{
    private const POST_ID = 501;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('\Dompdf\Dompdf')) {
            self::markTestSkipped('Dompdf no está instalado.');
        }
    }

    /** @param array<string,mixed> $meta */
    private function givenProperty(array $meta = []): int
    {
        WpStubs::$postObjects[self::POST_ID] = (object) [
            'ID' => self::POST_ID,
            'post_type' => 'property',
            'post_status' => 'publish',
        ];
        WpStubs::$postTitles[self::POST_ID] = 'Apartamento en El Poblado con vista a la ciudad';
        WpStubs::$permalinks[self::POST_ID] = 'https://royal.test/inmuebles/apto-4821/';
        WpStubs::$currentPostId = self::POST_ID;
        WpStubs::$postContent[self::POST_ID] = '<p>Espectacular apartamento con acabados en porcelanato.</p>';

        WpStubs::$postTerms[self::POST_ID][PropertyTaxonomies::TAXONOMY_CITY] = [(object) ['name' => 'Medellín']];
        WpStubs::$postTerms[self::POST_ID][PropertyTaxonomies::TAXONOMY_FEATURE] = [
            new WP_Term(601, PropertyTaxonomies::TAXONOMY_FEATURE, 'ascensor', 'Ascensor'),
        ];

        WpStubs::setPostMeta(self::POST_ID, array_merge([
            '_property_code' => 'RPR-4821',
            '_property_price_sale' => '890000000',
            '_property_currency_sale' => 'COP',
            '_property_area' => '132',
            '_property_bedrooms' => '3',
            '_property_agent_id' => '7',
        ], $meta));

        WpStubs::setUser(7, 'joquendo', [
            'display_name' => 'Jorge Oquendo',
            'user_email' => 'jorge@royal.test',
        ], ['homlity_asesor'], ['_homlity_advisor_phone' => '+57 300 123 4567']);

        WpStubs::setOption('homlity_seo_settings', [
            'company_name' => 'Royal Propiedad Raíz',
            'company_nit' => 'NIT 901.234.567-8',
        ]);
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['primary_color' => '#e0533d']);

        return self::POST_ID;
    }

    // ── Es un PDF de verdad ───────────────────────────────────────────────

    /**
     * La comprobación mínima: cabecera, cierre y tabla de referencias cruzadas
     * apuntando dentro del archivo. Un PDF sin `startxref` válido no lo abre
     * ningún lector, y por bytes sueltos parecería correcto.
     */
    public function testLoQueSaleEsUnArchivoPdfBienFormado(): void
    {
        $pdf = TemplateService::technicalSheetPdf($this->givenProperty());

        self::assertNotSame('', $pdf, 'El generador no devolvió nada.');
        self::assertStringStartsWith('%PDF-1.', $pdf, 'Falta la cabecera del formato.');
        self::assertStringEndsWith("%%EOF\n", $pdf, 'El archivo no está cerrado.');

        self::assertStringContainsString('/Type /Catalog', $pdf, 'No hay catálogo: el documento no tiene raíz.');
        self::assertStringContainsString('trailer', $pdf);

        self::assertSame(1, preg_match('/startxref\s+(\d+)/', $pdf, $found), 'Falta startxref.');
        $offset = (int) $found[1];
        self::assertGreaterThan(0, $offset);
        self::assertLessThan(strlen($pdf), $offset, 'startxref apunta fuera del archivo.');
        self::assertStringStartsWith('xref', substr($pdf, $offset, 4), 'startxref no apunta a la tabla xref.');
    }

    public function testTieneAlMenosUnaPagina(): void
    {
        $pdf = TemplateService::technicalSheetPdf($this->givenProperty());

        self::assertGreaterThanOrEqual(1, PdfProbe::pageCount(PdfProbe::inflate($pdf)));
    }

    /**
     * Todas las páginas se pueden leer.
     *
     * Suena a comprobar el instrumento, y lo es a propósito: cuando un flujo
     * no se descomprime, esa página desaparece de la lectura sin que nada
     * falle, y las pruebas que cuentan páginas siguen pasando midiendo de
     * menos. Pasó: un flujo cuyos datos acababan en salto de línea perdía un
     * byte y se quedaba comprimido.
     */
    public function testTodasLasPaginasSePuedenLeer(): void
    {
        $this->givenProperty();
        $terms = [];
        foreach (range(1, 40) as $n) {
            $terms[] = new WP_Term(600 + $n, PropertyTaxonomies::TAXONOMY_FEATURE, 'c' . $n, 'Característica ' . $n);
        }
        WpStubs::$postTerms[self::POST_ID][PropertyTaxonomies::TAXONOMY_FEATURE] = $terms;

        $pdf = PdfProbe::inflate(TemplateService::technicalSheetPdf(self::POST_ID));

        $declared = PdfProbe::pageCount($pdf);
        self::assertGreaterThan(1, $declared, 'La ficha de prueba tenía que ocupar más de una página.');
        self::assertCount(
            $declared,
            PdfProbe::textByPage($pdf),
            'Hay páginas que no se pudieron leer: alguna quedó sin descomprimir.'
        );
    }

    public function testElPapelEsA4Vertical(): void
    {
        $pdf = PdfProbe::inflate(TemplateService::technicalSheetPdf($this->givenProperty()));

        $box = PdfProbe::mediaBox($pdf);

        self::assertNotNull($box, 'El documento no declara tamaño de papel.');
        self::assertEqualsWithDelta(595.28, $box['width'], 1.0, 'El ancho no es el de una A4.');
        self::assertEqualsWithDelta(841.89, $box['height'], 1.0, 'El alto no es el de una A4.');
        self::assertGreaterThan($box['width'], $box['height'], 'Salió apaisado.');
    }

    /**
     * Sin la fuente incrustada las tildes y el símbolo del euro se ven mal en
     * cualquier lector que no tenga DejaVu instalada, que son casi todos.
     */
    public function testLleveLasFuentesIncrustadas(): void
    {
        $pdf = TemplateService::technicalSheetPdf($this->givenProperty());

        self::assertTrue(PdfProbe::embedsFonts($pdf), 'El PDF no incrusta ninguna fuente.');
    }

    // ── No sale en blanco ─────────────────────────────────────────────────

    /**
     * Un PDF válido y vacío pasaría todas las comprobaciones de formato. Lo
     * que hace que la ficha sirva es que lleve dentro los datos del inmueble.
     */
    public function testLlevaDentroLosDatosDelInmueble(): void
    {
        $pdf = PdfProbe::inflate(TemplateService::technicalSheetPdf($this->givenProperty()));
        $text = PdfProbe::text($pdf);

        foreach ([
            'APARTAMENTO EN EL POBLADO' => 'el nombre del inmueble',
            'RPR-4821' => 'el código',
            '$ 890.000.000' => 'el valor de venta',
            '132 m²' => 'el área',
            'Jorge Oquendo' => 'el asesor',
            'Royal Propiedad Raíz' => 'la inmobiliaria',
            'Ascensor' => 'las características',
        ] as $needle => $what) {
            self::assertStringContainsString($needle, $text, 'Falta ' . $what . ' en el PDF.');
        }
    }

    /** Y que las tildes lleguen enteras, que es lo que rompe una codificación mal puesta. */
    public function testLasTildesLleganEnteras(): void
    {
        $pdf = PdfProbe::inflate(TemplateService::technicalSheetPdf($this->givenProperty()));
        $text = PdfProbe::text($pdf);

        self::assertStringContainsString('Medellín', $text);
        self::assertStringContainsString('FICHA TÉCNICA', $text);
        self::assertStringContainsString('DESCRIPCIÓN DEL INMUEBLE', $text);
    }

    /**
     * La tarjeta de descripción lleva el texto del inmueble, no la página.
     *
     * Es el caso que se vio en producción: con el inmueble montado en
     * Elementor, `the_content` devuelve el documento entero y la ficha salía
     * con toda la página metida en «Descripción del inmueble».
     */
    public function testLaDescripcionNoTraeLaPaginaDelConstructor(): void
    {
        $postId = $this->givenProperty();
        WpStubs::$postContent[$postId] = '<p>Apartamento con vista al valle de Aburrá.</p>';
        WpStubs::addFilter(
            'the_content',
            static fn(): string => '<div>MENU PRINCIPAL Inicio Inmuebles Contacto PIE DE PAGINA</div>'
        );

        $text = PdfProbe::text(PdfProbe::inflate(TemplateService::technicalSheetPdf($postId)));

        self::assertStringNotContainsString('MENU PRINCIPAL', $text);
        self::assertStringNotContainsString('PIE DE PAGINA', $text);
        self::assertStringContainsString('Apartamento con vista al valle', $text);
    }

    // ── Enchufa lo que debe ───────────────────────────────────────────────

    /**
     * La ficha de pantalla y la del PDF son dos plantillas. Si el generador
     * apunta a la de pantalla el PDF sale con los botones de navegación, que
     * en un archivo no llevan a ninguna parte.
     */
    public function testUsaLaPlantillaDelPdfYNoLaDePantalla(): void
    {
        $pdf = PdfProbe::inflate(TemplateService::technicalSheetPdf($this->givenProperty()));
        $text = PdfProbe::text($pdf);

        self::assertStringNotContainsString('Volver al inmueble', $text);
        self::assertStringNotContainsString('Imprimir ficha', $text);
        self::assertStringContainsString('INFORMACIÓN PÚBLICA DE LA INMOBILIARIA', $text);
    }

    /**
     * Y que le llega su hoja de estilos.
     *
     * La cabecera repetida en todas las páginas solo pasa por el
     * `position: fixed` de la hoja del PDF: sin ella el rótulo saldría una vez
     * y el resto de páginas irían huérfanas. Es la señal más barata de que el
     * CSS entró.
     */
    public function testLaHojaDelPdfSeAplica(): void
    {
        $this->givenProperty();
        // Suficientes características para que la ficha pase de una página.
        $terms = [];
        foreach (range(1, 40) as $n) {
            $terms[] = new WP_Term(600 + $n, PropertyTaxonomies::TAXONOMY_FEATURE, 'c' . $n, 'Característica ' . $n);
        }
        WpStubs::$postTerms[self::POST_ID][PropertyTaxonomies::TAXONOMY_FEATURE] = $terms;

        $pdf = PdfProbe::inflate(TemplateService::technicalSheetPdf(self::POST_ID));
        $pages = PdfProbe::textByPage($pdf);

        self::assertGreaterThan(1, count($pages), 'La ficha de prueba tenía que ocupar más de una página.');
        foreach ($pages as $number => $runs) {
            $texts = array_column($runs, 'text');
            self::assertContains(
                'FICHA TÉCNICA',
                $texts,
                'Página ' . ($number + 1) . ': sin cabecera, así que la hoja del PDF no se aplicó.'
            );
        }
    }

    /** Los ajustes del widget llegan a la plantilla. */
    public function testLosAjustesDelWidgetApaganSuSeccion(): void
    {
        $postId = $this->givenProperty();

        $conFinanzas = PdfProbe::text(PdfProbe::inflate(TemplateService::technicalSheetPdf($postId)));
        self::assertStringContainsString('FINANZAS', $conFinanzas);

        $sinFinanzas = PdfProbe::text(PdfProbe::inflate(
            TemplateService::technicalSheetPdf($postId, ['show_finance' => ''])
        ));
        self::assertStringNotContainsString('FINANZAS', $sinFinanzas);
    }

    // ── Color de la marca ─────────────────────────────────────────────────

    /**
     * El color configurado en SEO & GEO → Marca visual llega hasta la tinta.
     *
     * Se mira el color de relleno del flujo de contenido y no el atributo
     * `style` del HTML: que el color esté escrito en la plantilla no prueba
     * que Dompdf lo haya aplicado.
     */
    public function testElColorDeLaMarcaLlegaAlPdf(): void
    {
        $this->givenProperty();
        WpStubs::setOption('homlity_seo_settings', [
            'company_name' => 'Royal Propiedad Raíz',
            'brand_color_primary' => '#1f3c88',
        ]);

        $colors = PdfProbe::fillColors(PdfProbe::inflate(TemplateService::technicalSheetPdf(self::POST_ID)));

        self::assertContains('#1f3c88', $colors, 'El PDF no se pintó con el color de la inmobiliaria.');
        self::assertNotContains('#ff6752', $colors, 'Sigue apareciendo el color de fábrica.');
    }

    /** Y el par de los botones también. */
    public function testElColorDeBotonConfiguradoLlegaAlPdf(): void
    {
        $this->givenProperty();
        WpStubs::setOption('homlity_seo_settings', [
            'company_name' => 'Royal Propiedad Raíz',
            'brand_color_primary' => '#1f3c88',
            'brand_color_button' => '#0a7d3b',
        ]);

        $colors = PdfProbe::fillColors(PdfProbe::inflate(TemplateService::technicalSheetPdf(self::POST_ID)));

        self::assertContains('#0a7d3b', $colors, 'Los botones no usan su color configurado.');
    }

    // ── Cuando no hay nada que componer ───────────────────────────────────

    /**
     * Mejor no devolver nada que devolver un PDF con una ficha vacía: quien
     * llama distingue el caso y deja seguir a WordPress.
     */
    public function testSinInmuebleNoDevuelvePdf(): void
    {
        self::assertSame('', TemplateService::technicalSheetPdf(0));
        self::assertSame('', TemplateService::technicalSheetPdf(-1));
        self::assertSame('', TemplateService::technicalSheetPdf(9999), 'Un id que no existe no puede dar PDF.');
    }

    public function testUnPostQueNoEsUnInmuebleNoDevuelvePdf(): void
    {
        WpStubs::$postObjects[700] = (object) ['ID' => 700, 'post_type' => 'page', 'post_status' => 'publish'];
        WpStubs::$postTitles[700] = 'Quiénes somos';

        self::assertSame('', TemplateService::technicalSheetPdf(700));
    }

    // ── Nombre del archivo ────────────────────────────────────────────────

    public function testElArchivoSeLlamaComoElInmueble(): void
    {
        $postId = $this->givenProperty();

        self::assertSame(
            'apartamento-en-el-poblado-con-vista-a-la-ciudad.pdf',
            TemplateService::technicalSheetPdfFilename($postId)
        );
    }

    /** Un inmueble sin título no puede acabar en un archivo llamado «.pdf». */
    public function testUnInmuebleSinTituloUsaUnNombreDeReserva(): void
    {
        $postId = $this->givenProperty();
        WpStubs::$postTitles[$postId] = '';

        self::assertSame('ficha-tecnica.pdf', TemplateService::technicalSheetPdfFilename($postId));
    }
}
