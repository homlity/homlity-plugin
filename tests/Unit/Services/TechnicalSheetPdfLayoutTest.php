<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\TechnicalSheetData;
use Homlity\PluginInmobiliario\Tests\Support\PdfProbe;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;
use WP_Term;

/**
 * Maquetación de la ficha técnica en PDF.
 *
 * La ficha es un port del diseño del sistema
 * (resources/views/mails/sistema/layouts/fichaTecnicaLabel.blade.php), que lo
 * compone Html2Pdf; aquí lo compone Dompdf, que no tiene ni `<page_header>` ni
 * `box-sizing`. Lo que sustituye a esas dos cosas —cabecera y pie en
 * `position: fixed`, y la retícula en tablas— no se puede comprobar leyendo el
 * CSS: solo al componer la página. Así que estas pruebas renderizan con el
 * Dompdf y la hoja de verdad, y miden el resultado.
 *
 * Cómo se mide: pidiéndole a Dompdf el PDF sin comprimir, cada línea de texto
 * queda en claro como `BT x y Td /F1 tam Tf [(texto)] TJ`, y cada imagen como
 * `q ancho 0 0 alto x y cm /Img Do`. Todo lo que sigue está en píxeles CSS
 * —Dompdf resuelve el px a 96 dpi, 1 px = 0,75 pt— y medido desde el borde
 * superior izquierdo del papel, que es como está escrita la hoja.
 */
final class TechnicalSheetPdfLayoutTest extends TestCase
{
    private const PDF_CSS = HOMLITY_PLUGIN_PATH . 'assets/css/technical-sheet-pdf.css';
    private const TEMPLATE = HOMLITY_PLUGIN_PATH . 'templates/parts/property-technical-sheet-pdf.php';

    private const A4_HEIGHT_PX = PdfProbe::A4_HEIGHT_PX;
    private const A4_WIDTH_PX = PdfProbe::A4_WIDTH_PX;
    private const TOLERANCE = 1.5;

    private const POST_ID = 501;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('\Dompdf\Dompdf')) {
            self::markTestSkipped('Dompdf no está instalado.');
        }
    }

    // ── El inmueble de las pruebas ────────────────────────────────────────

    /** @param array<string,mixed> $overrides */
    private function givenProperty(array $overrides = []): int
    {
        $postId = self::POST_ID;

        WpStubs::$postObjects[$postId] = (object) [
            'ID' => $postId,
            'post_type' => 'property',
            'post_status' => 'publish',
        ];
        WpStubs::$postTitles[$postId] = 'Apartamento en El Poblado con vista a la ciudad';
        WpStubs::$permalinks[$postId] = 'https://royal.test/inmuebles/apartamento-el-poblado-4821/';
        WpStubs::$currentPostId = $postId;
        WpStubs::$postContent[$postId] = '<p>Espectacular apartamento en el corazón de El Poblado, '
            . 'con acabados en porcelanato y ventanales de piso a techo.</p>';

        foreach ([
            PropertyTaxonomies::TAXONOMY_OPERATION => 'Venta',
            PropertyTaxonomies::TAXONOMY_TYPE => 'Apartamento',
            PropertyTaxonomies::TAXONOMY_CITY => 'Medellín',
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => 'El Poblado',
        ] as $taxonomy => $name) {
            WpStubs::$postTerms[$postId][$taxonomy] = [(object) ['name' => $name, 'slug' => sanitize_title($name)]];
        }

        WpStubs::setPostMeta($postId, array_merge([
            '_property_code' => 'RPR-4821',
            '_property_price_sale' => '890000000',
            '_property_price_rent' => '4200000',
            '_property_price_admin' => '620000',
            '_property_area' => '132',
            '_property_bedrooms' => '3',
            '_property_bathrooms' => '3',
            '_property_parking' => '2',
            '_property_agent_id' => '7',
        ], $overrides));

        WpStubs::setUser(7, 'joquendo', [
            'display_name' => 'Jorge Oquendo',
            'user_email' => 'jorge@royal.test',
        ], ['homlity_asesor'], [
            '_homlity_advisor_phone' => '+57 300 123 4567',
        ]);

        WpStubs::setOption('homlity_seo_settings', [
            'company_name' => 'Royal Propiedad Raíz',
            'contact_website' => 'https://royal.test',
            'contact_address' => 'Calle 10 # 43C - 20, Oficina 501',
            'geo_city' => 'Medellín',
        ]);
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['primary_color' => '#e0533d']);

        return $postId;
    }

    /** @param string[] $names */
    private function givenFeatures(array $names): void
    {
        $terms = [];
        foreach ($names as $index => $name) {
            $terms[] = new WP_Term(600 + $index, PropertyTaxonomies::TAXONOMY_FEATURE, sanitize_title($name), $name);
        }
        WpStubs::$postTerms[self::POST_ID][PropertyTaxonomies::TAXONOMY_FEATURE] = $terms;
    }

    /** @param string[] $urls */
    private function givenPhotos(array $urls): void
    {
        WpStubs::setPostMeta(self::POST_ID, ['_property_gallery' => implode(',', $urls)]);
    }

    // ── Render y medición ─────────────────────────────────────────────────

    private function renderHtml(int $postId): string
    {
        ob_start();
        $post_id = $postId; // phpcs:ignore
        $settings = []; // phpcs:ignore
        require self::TEMPLATE;

        return (string) ob_get_clean();
    }

    private function render(int $postId): string
    {
        $content = $this->renderHtml($postId);

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
            . (string) file_get_contents(self::PDF_CSS)
            . '</style></head><body>' . $content . '</body></html>';

        $options = new \Dompdf\Options();
        // Apagado a propósito: ninguna prueba debe salir a la red. Los
        // iconos son data URI y Dompdf los dibuja igual.
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // PdfProbe descomprime los flujos, así que no hace falta pedirle a
        // Dompdf que no comprima.
        return PdfProbe::inflate((string) $dompdf->output());
    }

    /**
     * Márgenes declarados en @page, leídos de la hoja.
     *
     * Se leen y no se copian aquí para que la prueba del desborde siga
     * midiendo contra el hueco que la hoja reserva de verdad.
     *
     * @return array{top:float, side:float, bottom:float}
     */
    private static function pageMargins(): array
    {
        preg_match(
            '/@page\s*\{[^}]*margin:\s*([\d.]+)px\s+([\d.]+)px\s+([\d.]+)px/',
            (string) file_get_contents(self::PDF_CSS),
            $found
        );

        self::assertNotEmpty($found, 'La hoja del PDF ya no declara los tres márgenes de @page.');

        return ['top' => (float) $found[1], 'side' => (float) $found[2], 'bottom' => (float) $found[3]];
    }

    /**
     * Separa lo repetido de lo que fluye.
     *
     * La cabecera y el pie son las únicas líneas que salen en todas las
     * páginas en la misma posición: eso los identifica sin tener que codificar
     * aquí ni el margen ni el alto que ocupan, que es justo lo que la prueba
     * tiene que poder ver mal.
     *
     * @param array<int, array<int, array{x:float, y:float, text:string}>> $pages
     * @return array{header:array<int,array{x:float,y:float,text:string}>, footer:array<int,array{x:float,y:float,text:string}>, body:array<int,array<int,array{x:float,y:float,text:string}>>}
     */
    private static function split(array $pages): array
    {
        $key = static fn(array $run): string => round($run['x']) . '|' . round($run['y']) . '|' . $run['text'];

        $seen = [];
        foreach ($pages as $runs) {
            foreach (array_unique(array_map($key, $runs)) as $id) {
                $seen[$id] = ($seen[$id] ?? 0) + 1;
            }
        }

        $everywhere = static fn(array $run): bool => ($seen[$key($run)] ?? 0) === count($pages);

        $header = [];
        $footer = [];
        $body = [];
        foreach ($pages as $number => $runs) {
            $body[$number] = [];
            foreach ($runs as $run) {
                if (!$everywhere($run)) {
                    $body[$number][] = $run;
                    continue;
                }
                // Lo fijo de arriba es la cabecera; lo de abajo, el pie.
                if ($run['y'] < self::A4_HEIGHT_PX / 2) {
                    $header[$key($run)] = $run;
                } else {
                    $footer[$key($run)] = $run;
                }
            }
        }

        return ['header' => array_values($header), 'footer' => array_values($footer), 'body' => $body];
    }

    /** @param array<int, array{x:float, y:float, text:string}> $runs */
    private static function lowest(array $runs): float
    {
        return $runs === [] ? 0.0 : max(array_column($runs, 'y'));
    }

    /** @param array<int, array{x:float, y:float, text:string}> $runs */
    private static function highest(array $runs): float
    {
        return $runs === [] ? self::A4_HEIGHT_PX : min(array_column($runs, 'y'));
    }

    /** @param array<int, array{x:float, y:float, text:string}> $runs */
    private static function find(array $runs, string $needle): ?array
    {
        foreach ($runs as $run) {
            if (str_contains($run['text'], $needle)) {
                return $run;
            }
        }

        return null;
    }

    /**
     * Las x distintas en las que empieza algo, redondeadas: es la retícula.
     *
     * @param array<int, array{x:float, y:float, text:string}> $runs
     * @return float[]
     */
    private static function columnOrigins(array $runs, float $fromY, float $toY): array
    {
        $origins = [];
        foreach ($runs as $run) {
            if ($run['y'] < $fromY || $run['y'] > $toY) {
                continue;
            }
            $origins[(string) round($run['x'])] = round($run['x'], 1);
        }

        $origins = array_values($origins);
        sort($origins);

        // Dos rótulos de la misma columna pueden salir con un pelo de
        // diferencia; lo que interesa son los puntos de arranque.
        $columns = [];
        foreach ($origins as $x) {
            if ($columns === [] || $x - end($columns) > 20) {
                $columns[] = $x;
            }
        }

        return $columns;
    }

    // ── Cabecera y pie repetidos ──────────────────────────────────────────

    /**
     * Html2Pdf repite `<page_header>` solo; Dompdf necesita `position: fixed`.
     * Si alguien lo pasa a estático, la cabecera sale una vez y las páginas
     * siguientes quedan huérfanas.
     */
    public function testLaCabeceraSeRepiteEnTodasLasPaginas(): void
    {
        $this->givenProperty();
        $this->givenFeatures(array_map(static fn(int $n): string => 'Característica ' . $n, range(1, 30)));

        $pages = PdfProbe::textByPage($this->render(self::POST_ID));

        self::assertGreaterThan(1, count($pages), 'La ficha de prueba tiene que ocupar más de una página.');
        foreach ($pages as $number => $runs) {
            self::assertNotNull(
                self::find($runs, 'FICHA'),
                'Página ' . ($number + 1) . ': falta la cabecera.'
            );
        }
    }

    public function testElPieSeRepiteEnTodasLasPaginas(): void
    {
        $this->givenProperty();
        $this->givenFeatures(array_map(static fn(int $n): string => 'Característica ' . $n, range(1, 30)));

        $pages = PdfProbe::textByPage($this->render(self::POST_ID));

        self::assertGreaterThan(1, count($pages));
        foreach ($pages as $number => $runs) {
            self::assertNotNull(
                self::find($runs, 'Ficha t'),
                'Página ' . ($number + 1) . ': falta la banda del pie.'
            );
        }
    }

    /**
     * Una caja fija no ocupa sitio en el flujo: el hueco lo tiene que reservar
     * el margen de @page. Cuando se queda corto la cabecera se pinta encima de
     * la primera tarjeta y no se ve —fue exactamente lo que pasó al portarla—.
     *
     * Se compara contra dónde cae la cabecera de verdad y no contra el margen
     * declarado: medir contra el margen hace que encogerlo relaje la prueba en
     * vez de romperla, que es lo contrario de lo que hace falta.
     */
    public function testLaCabeceraNoSePintaEncimaDelCuerpo(): void
    {
        $this->givenProperty();
        $this->givenFeatures(array_map(static fn(int $n): string => 'Característica ' . $n, range(1, 30)));

        $parts = self::split(PdfProbe::textByPage($this->render(self::POST_ID)));

        self::assertNotEmpty($parts['header'], 'No hay cabecera repetida que medir.');

        // Que no solape no basta: encogiendo el margen la cabecera se va por
        // encima del borde del papel y deja de solapar porque deja de verse.
        self::assertGreaterThan(
            0.0,
            self::highest($parts['header']),
            'La cabecera se sale por arriba del papel.'
        );

        $headerBottom = self::lowest($parts['header']);
        foreach ($parts['body'] as $number => $runs) {
            if ($runs === []) {
                continue;
            }
            self::assertGreaterThan(
                $headerBottom,
                self::highest($runs),
                sprintf('Página %d: el cuerpo empieza dentro de la cabecera.', $number + 1)
            );
        }
    }

    /**
     * Y por abajo lo mismo: el cuerpo no puede meterse en la banda del pie.
     *
     * También medido contra la banda y no contra el margen, por lo mismo.
     */
    public function testNadaDelCuerpoSeMeteEnLaBandaDelPie(): void
    {
        $this->givenProperty();
        $this->givenFeatures(array_map(static fn(int $n): string => 'Característica ' . $n, range(1, 30)));

        $parts = self::split(PdfProbe::textByPage($this->render(self::POST_ID)));

        self::assertNotEmpty($parts['footer'], 'No hay banda del pie que medir.');

        // Y por lo mismo: un margen inferior demasiado corto empuja la banda
        // fuera del papel, donde tampoco choca con nada.
        self::assertLessThan(
            self::A4_HEIGHT_PX,
            self::lowest($parts['footer']),
            'La banda del pie se sale por abajo del papel.'
        );

        $footerTop = self::highest($parts['footer']);
        foreach ($parts['body'] as $number => $runs) {
            if ($runs === []) {
                continue;
            }
            self::assertLessThan(
                $footerTop,
                self::lowest($runs),
                sprintf('Página %d: el cuerpo llega hasta la banda del pie.', $number + 1)
            );
        }
    }

    // ── La retícula de tres columnas ──────────────────────────────────────

    /**
     * Las cifras van a tres columnas iguales. En Dompdf esto solo sale con
     * tablas: sin `box-sizing` una caja con padding no cabe tres veces.
     */
    public function testLasFinanzasVanATresColumnasIguales(): void
    {
        $this->givenProperty();

        $pages = PdfProbe::textByPage($this->render(self::POST_ID));
        $anchor = self::find($pages[0], 'FINANZAS');
        self::assertNotNull($anchor);

        $columns = self::columnOrigins($pages[0], $anchor['y'] + 5, $anchor['y'] + 45);

        self::assertCount(3, $columns, 'Las finanzas no salen a tres columnas.');
        self::assertEqualsWithDelta(
            $columns[1] - $columns[0],
            $columns[2] - $columns[1],
            self::TOLERANCE,
            'Las tres columnas de finanzas no están igual de separadas.'
        );
    }

    public function testLasDimensionesVanATresColumnasIguales(): void
    {
        $this->givenProperty();

        $pages = PdfProbe::textByPage($this->render(self::POST_ID));
        $anchor = self::find($pages[0], 'DIMENSIONES');
        self::assertNotNull($anchor);

        $columns = self::columnOrigins($pages[0], $anchor['y'] + 5, $anchor['y'] + 45);

        self::assertCount(3, $columns);
        self::assertEqualsWithDelta($columns[1] - $columns[0], $columns[2] - $columns[1], self::TOLERANCE);
    }

    /**
     * Las características se agrupan de tres en tres y la cuarta abre fila.
     */
    public function testLasCaracteristicasVanDeTresEnTres(): void
    {
        $this->givenProperty();
        $this->givenFeatures(['Ascensor', 'Balcón', 'Gimnasio', 'Depósito']);

        $pdf = $this->render(self::POST_ID);
        $runs = array_merge(...PdfProbe::textByPage($pdf));

        $cells = [];
        foreach (['Ascensor', 'Balc', 'Gimnasio', 'Dep'] as $needle) {
            $cell = self::find($runs, $needle);
            self::assertNotNull($cell, 'Falta la característica ' . $needle);
            $cells[$needle] = $cell;
        }

        self::assertEqualsWithDelta($cells['Ascensor']['y'], $cells['Balc']['y'], self::TOLERANCE, 'Las tres primeras no van en la misma fila.');
        self::assertEqualsWithDelta($cells['Ascensor']['y'], $cells['Gimnasio']['y'], self::TOLERANCE);
        self::assertGreaterThan($cells['Ascensor']['x'] + 20, $cells['Balc']['x'], 'La segunda no arranca en su columna.');
        self::assertGreaterThan($cells['Balc']['x'] + 20, $cells['Gimnasio']['x']);

        self::assertGreaterThan($cells['Ascensor']['y'] + 5, $cells['Dep']['y'], 'La cuarta no bajó de fila.');
        self::assertEqualsWithDelta($cells['Ascensor']['x'], $cells['Dep']['x'], self::TOLERANCE, 'La cuarta no vuelve a la primera columna.');
    }

    // ── Sin repeticiones ──────────────────────────────────────────────────

    /**
     * Lo que sale en la cabecera o en el pie no se repite en el cuerpo.
     *
     * Las dos se repintan en todas las páginas, así que volver a poner el
     * teléfono del asesor o el nombre de la inmobiliaria dentro de una tarjeta
     * es gastar sitio en algo que el lector ya tiene delante. Llegaron a salir
     * tres veces cada uno.
     */
    public function testElCuerpoNoRepiteLoQueYaDiceLaCabeceraOElPie(): void
    {
        $this->givenProperty();
        $this->givenFeatures(array_map(static fn(int $n): string => 'Característica ' . $n, range(1, 30)));

        $parts = self::split(PdfProbe::textByPage($this->render(self::POST_ID)));

        $fixed = array_unique(array_merge(
            array_column($parts['header'], 'text'),
            array_column($parts['footer'], 'text')
        ));

        foreach ($parts['body'] as $number => $runs) {
            foreach ($runs as $run) {
                foreach ($fixed as $repeated) {
                    $repeated = trim($repeated);
                    // Los rótulos cortos y los de sección no cuentan: repetir
                    // la palabra «Correo» como etiqueta no es repetir un dato.
                    //
                    // Un rótulo es texto con letras y todo en mayúsculas. La
                    // condición lleva lo de las letras porque sin ella un
                    // teléfono o una fecha —que no tienen may/min— pasaban por
                    // rótulo y se colaban sin comprobar.
                    $isLabel = preg_match('/\p{L}/u', $repeated) === 1
                        && mb_strtoupper($repeated) === $repeated;
                    if (mb_strlen($repeated) < 8 || $isLabel) {
                        continue;
                    }
                    self::assertStringNotContainsString(
                        $repeated,
                        $run['text'],
                        sprintf('Página %d: «%s» ya sale en la cabecera o el pie de cada página.', $number + 1, $repeated)
                    );
                }
            }
        }
    }

    /**
     * Y dentro del cuerpo tampoco se repite un mismo dato en dos tarjetas.
     *
     * Se comprueba contra los valores que produce TechnicalSheetData y sobre
     * el texto de la página con los espacios quitados, no línea a línea: una
     * URL larga parte en dos en una celda estrecha y entera en una ancha, así
     * que comparando líneas sueltas la repetición pasaba desapercibida.
     */
    public function testElCuerpoNoRepiteUnDatoEnDosTarjetas(): void
    {
        $postId = $this->givenProperty();
        $this->givenFeatures(['Ascensor', 'Balcón', 'Gimnasio']);

        $parts = self::split(PdfProbe::textByPage($this->render($postId)));

        $flat = '';
        foreach ($parts['body'] as $runs) {
            $flat .= implode('', array_column($runs, 'text'));
        }
        $flat = (string) preg_replace('/\s+/u', '', $flat);

        $data = TechnicalSheetData::forProperty($postId);
        $values = [
            'la URL del inmueble' => $data['permalink'],
            'el nombre del asesor' => $data['agent']['name'],
            'el teléfono del asesor' => $data['agent']['phone'],
            'el correo del asesor' => $data['agent']['email'],
            'el nombre de la inmobiliaria' => $data['company']['name'],
            'el documento de la inmobiliaria' => $data['company']['document'],
        ];
        // El sitio web de la inmobiliaria se queda fuera: es un prefijo de la
        // URL del inmueble, así que contarlo daría por repetido algo que
        // ningún lector ve dos veces.

        foreach ($values as $what => $value) {
            $value = (string) preg_replace('/\s+/u', '', $value);
            if ($value === '') {
                continue;
            }
            self::assertLessThanOrEqual(
                1,
                substr_count($flat, $value),
                'El cuerpo repite ' . $what . '.'
            );
        }
    }

    // ── Catálogo de fotos ─────────────────────────────────────────────────

    /**
     * Nueve fotos como mucho, de tres en tres. El tope es lo que evita que un
     * inmueble con cincuenta fotos convierta la ficha en un álbum.
     *
     * Esto se comprueba sobre el HTML y no sobre el PDF por una limitación del
     * banco de pruebas, no del diseño: sin red, Dompdf no dibuja nada por una
     * foto que no puede descargar —ni siquiera el marcador de rota—, así que
     * en el PDF no habría qué medir. Que esa misma tabla reparta tres columnas
     * de verdad ya lo miden las pruebas de finanzas, dimensiones y
     * características, que usan celdas idénticas.
     */
    public function testLasFotosVanDeTresEnTresYSeCortanEnNueve(): void
    {
        $this->givenProperty();
        $this->givenPhotos(array_map(
            static fn(int $n): string => 'https://royal.test/fotos/rpr-4821-' . $n . '.jpg',
            range(1, 14)
        ));

        $html = $this->renderHtml(self::POST_ID);

        self::assertSame(9, substr_count($html, 'class="photo-frame"'), 'El catálogo no se corta en nueve fotos.');

        preg_match('/<table>((?:(?!<\/table>).)*photo-frame.*?)<\/table>/s', $html, $found);
        self::assertNotEmpty($found, 'No se encontró la tabla del catálogo.');

        $rows = preg_split('/<tr>/', $found[1]);
        array_shift($rows);
        self::assertCount(3, $rows, 'Las nueve fotos no salen en tres filas.');
        foreach ($rows as $index => $row) {
            self::assertSame(3, substr_count($row, '<td'), 'La fila ' . ($index + 1) . ' no tiene tres celdas.');
        }
    }

    // ── Sin asesor ────────────────────────────────────────────────────────

    /**
     * Un inmueble sin asesor asignado no puede dejar la cabecera vacía: pasa a
     * enseñar los datos de la inmobiliaria, como en el original.
     */
    public function testSinAsesorLaCabeceraEnsenaALaInmobiliaria(): void
    {
        $this->givenProperty(['_property_agent_id' => '0']);
        WpStubs::$users = [];

        $pages = PdfProbe::textByPage($this->render(self::POST_ID));
        $runs = $pages[0];

        self::assertNotNull(self::find($runs, 'FICHA'), 'La cabecera desapareció sin asesor.');
        self::assertNotNull(self::find($runs, 'Royal'), 'La cabecera no cae en la inmobiliaria.');
        self::assertNull(self::find($runs, 'Jorge Oquendo'), 'Sin asesor no debería aparecer ningún asesor.');
    }

    /** Y los botones de WhatsApp, que son el teléfono del asesor, desaparecen. */
    public function testSinAsesorNoHayBotonesDeWhatsApp(): void
    {
        $this->givenProperty(['_property_agent_id' => '0']);
        WpStubs::$users = [];

        $runs = array_merge(...PdfProbe::textByPage($this->render(self::POST_ID)));

        self::assertNull(self::find($runs, 'SEGUIMOS'), 'Sin teléfono no hay a quién escribir.');
    }

    // ── Contenido ─────────────────────────────────────────────────────────

    /** Las secciones del original tienen que estar todas. */
    public function testEstanTodasLasSeccionesDelDiseno(): void
    {
        $this->givenProperty();
        $this->givenFeatures(['Ascensor']);

        $runs = array_merge(...PdfProbe::textByPage($this->render(self::POST_ID)));

        foreach ([
            'FICHA',
            'INFORMACI',
            'FINANZAS',
            'DIMENSIONES',
            'DETALLES CLAVE',
            'DESCRIPCI',
            'CARACTER',
            'SEGUIMOS',
        ] as $heading) {
            self::assertNotNull(self::find($runs, $heading), 'Falta la sección ' . $heading);
        }
    }

    /**
     * La ficha no se sale del papel por los lados: con `table-layout: fixed`
     * una celda estrecha reparte, sin él una URL larga empuja a la de al lado.
     */
    public function testNadaSeSaleDelPapelPorLosLados(): void
    {
        $this->givenProperty();
        $this->givenFeatures(['Ascensor', 'Balcón', 'Gimnasio']);

        $runs = array_merge(...PdfProbe::textByPage($this->render(self::POST_ID)));
        $margins = self::pageMargins();
        $rightEdge = self::A4_WIDTH_PX - $margins['side'];

        foreach ($runs as $run) {
            self::assertGreaterThanOrEqual(
                $margins['side'] - self::TOLERANCE,
                $run['x'],
                'Se sale por la izquierda: ' . $run['text']
            );
            self::assertLessThan(
                $rightEdge,
                $run['x'],
                'Empieza fuera del margen derecho: ' . $run['text']
            );
        }
    }
}
