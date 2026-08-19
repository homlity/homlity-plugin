<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPUnit\Framework\TestCase;

/**
 * Maquetación del PDF de la ficha técnica.
 *
 * Dompdf no implementa grid, flexbox ni `columns`, que es como está maquetada
 * la ficha en pantalla: sin assets/css/technical-sheet-pdf.css cada sección se
 * apila en una sola columna. El fallo no se ve leyendo el CSS —solo al componer
 * la página—, así que estas pruebas renderizan con el Dompdf y las hojas de
 * verdad, y miden el resultado.
 *
 * Cómo se mide: pidiéndole a Dompdf el PDF sin comprimir, el flujo de
 * contenido queda en claro y cada línea de texto aparece como
 * `BT x y Td /F1 tam Tf [(texto)] TJ ET`, y cada imagen como
 * `q ancho 0 0 alto x y cm /Img Do`. De ahí salen las coordenadas exactas en
 * puntos, sin depender de ningún binario externo. Todo lo que sigue está en
 * píxeles CSS —Dompdf resuelve el px a 96 dpi, 1 px = 0,75 pt— y medido desde
 * el borde superior izquierdo del papel, que es como está escrita la hoja.
 */
final class TechnicalSheetPdfLayoutTest extends TestCase
{
    private const SCREEN_CSS = 'assets/css/front-components.css';
    private const PDF_CSS = 'assets/css/technical-sheet-pdf.css';

    /** Dompdf resuelve el píxel CSS a 96 dpi. */
    private const PT_PER_PX = 0.75;

    /** Alto de una A4 vertical, en puntos: el origen del PDF está abajo. */
    private const A4_HEIGHT_PT = 841.89;

    /** Margen de página (16) + borde (1) + padding (11) de la tarjeta. */
    private const FIRST_COLUMN_X = 28.0;

    /** 232 px de contenido + 8 px de aire. */
    private const COLUMN_PITCH = 240.0;

    /** Milímetro y medio: por debajo de eso es ruido de redondeo, no descuadre. */
    private const TOLERANCE = 1.0;

    private static function pluginPath(string $relative = ''): string
    {
        return dirname(__DIR__, 3) . '/' . $relative;
    }

    /**
     * Renderiza un trozo de ficha y devuelve el PDF sin comprimir.
     */
    private static function render(string $body, string $extraCss = ''): string
    {
        $css = (string) file_get_contents(self::pluginPath(self::SCREEN_CSS))
            . (string) file_get_contents(self::pluginPath(self::PDF_CSS))
            . $extraCss;

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>' . $css
            . '</style></head><body>' . self::sheet($body) . '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', [self::pluginPath()]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output(['compress' => 0]);
    }

    /**
     * El envoltorio que emite la plantilla, con las variables de color puestas
     * en el atributo `style`.
     *
     * Sin ellas no es el mismo render: los bordes de la ficha están escritos
     * como `rgba(var(--sheet-primary-rgb), …)`, y con la variable sin definir
     * Dompdf descarta la declaración entera. Una tarjeta sin borde ocupa 2 px
     * menos, que es justo lo que decide si una columna cabe o no.
     */
    private static function sheet(string $body): string
    {
        return '<main class="homlity-tech-sheet"'
            . ' style="--sheet-primary:#e0533d;--sheet-primary-rgb:224,83,61">'
            . $body
            . '</main>';
    }

    private static function pageCount(string $body): int
    {
        // Contar páginas necesita el objeto, no el flujo; se renderiza aparte.
        $css = (string) file_get_contents(self::pluginPath(self::SCREEN_CSS))
            . (string) file_get_contents(self::pluginPath(self::PDF_CSS));

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', [self::pluginPath()]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(
            '<!doctype html><html><head><meta charset="utf-8"><style>' . $css
                . '</style></head><body>' . self::sheet($body) . '</body></html>',
            'UTF-8'
        );
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->getCanvas()->get_page_count();
    }

    /**
     * Dónde arranca cada marca de texto, en píxeles desde el borde de la hoja.
     *
     * Las marcas son las palabras C1, C2… que llevan las celdas de las
     * plantillas de prueba; el resto del texto —viñetas de lista, rótulos— se
     * descarta, que para situar una columna basta con su primera palabra.
     *
     * @return array<string,array{x:float,y:float}>
     */
    private static function marks(string $pdf): array
    {
        // El `Tc` opcional es el interletraje, que algunos rótulos llevan.
        //
        // `[^)]*` y no `.*?`: sobre el megabyte largo que ocupa un PDF, un
        // cuantificador perezoso agota el límite de retroceso de PCRE y
        // preg_match_all devuelve cero coincidencias sin avisar.
        preg_match_all(
            '/BT\s+([\d.-]+)\s+([\d.-]+)\s+Td\s+(?:[\d.-]+\s+Tc\s+)?\/\w+\s+[\d.]+\s+Tf\s+\[\(([^)]*)\)\]\s+TJ/',
            $pdf,
            $matches,
            PREG_SET_ORDER
        );

        $marks = [];
        foreach ($matches as $run) {
            // Según la fuente, Dompdf escribe la cadena con un byte por
            // carácter o en UTF-16BE, dos bytes con el alto a cero: la marca
            // C1 puede llegar como "C1" o como los cuatro bytes 00 43 00 31.
            $text = $run[3];
            if (preg_match('/^(?:\x00[\x20-\x7e])+$/', $text) === 1) {
                $text = str_replace("\x00", '', $text);
            }
            if (!preg_match('/^C\d+$/', $text)) {
                continue;
            }

            $marks[$text] = [
                'x' => (float) $run[1] / self::PT_PER_PX,
                'y' => (self::A4_HEIGHT_PT - (float) $run[2]) / self::PT_PER_PX,
            ];
        }

        ksort($marks, SORT_NATURAL);

        return $marks;
    }

    /**
     * Dónde y de qué tamaño se dibuja cada imagen, en píxeles.
     *
     * @return list<array{width:float,height:float,x:float,y:float}>
     */
    private static function images(string $pdf): array
    {
        preg_match_all(
            '/q\s+([\d.-]+)\s+0\s+0\s+([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s+cm\s*\/\w+\s+Do/',
            $pdf,
            $matches,
            PREG_SET_ORDER
        );

        $images = [];
        foreach ($matches as $box) {
            $height = (float) $box[2];
            $images[] = [
                'width' => (float) $box[1] / self::PT_PER_PX,
                'height' => $height / self::PT_PER_PX,
                'x' => (float) $box[3] / self::PT_PER_PX,
                // El origen de una imagen es su esquina inferior izquierda.
                'y' => (self::A4_HEIGHT_PT - (float) $box[4] - $height) / self::PT_PER_PX,
            ];
        }

        return $images;
    }

    /**
     * Tres columnas de un paso dado, y la cuarta celda vuelve al principio en
     * una fila nueva. Fijar el paso y el origen es lo que distingue una
     * retícula de que, sin más, quepan tres cosas por casualidad.
     */
    private static function assertGrid(string $body, float $originX, float $pitch, string $what): void
    {
        $marks = self::marks(self::render($body));

        self::assertSame(['C1', 'C2', 'C3', 'C4'], array_keys($marks), $what . ': faltan celdas en el render.');

        foreach (['C1' => 0.0, 'C2' => $pitch, 'C3' => 2 * $pitch] as $mark => $offset) {
            self::assertEqualsWithDelta(
                $originX + $offset,
                $marks[$mark]['x'],
                self::TOLERANCE,
                $what . ": la celda $mark no arranca donde le toca."
            );
            self::assertEqualsWithDelta(
                $marks['C1']['y'],
                $marks[$mark]['y'],
                self::TOLERANCE,
                $what . ": la celda $mark no está en la primera fila."
            );
        }

        self::assertEqualsWithDelta(
            $originX,
            $marks['C4']['x'],
            self::TOLERANCE,
            $what . ': la cuarta celda no vuelve a la primera columna, hay más de tres.'
        );
        self::assertGreaterThan(
            $marks['C1']['y'] + self::TOLERANCE,
            $marks['C4']['y'],
            $what . ': la cuarta celda no baja de fila.'
        );
    }

    private static function card(string $inner): string
    {
        return '<section class="homlity-tech-sheet__card">' . $inner . '</section>';
    }

    /** Un asset propio de 256 px, más ancho que el tope de 150 px. */
    private static function photo(): string
    {
        return self::pluginPath('assets/img/icon-256x256.png');
    }

    // ── Tres columnas por sección ─────────────────────────────────────────

    /**
     * La misma clase maqueta «Información general del inmueble» y
     * «Dimensiones y ambientes».
     */
    public function testLaInformacionGeneralVaATresColumnasDe240Px(): void
    {
        $cells = '';
        foreach (range(1, 4) as $n) {
            $cells .= '<div><strong>C' . $n . '</strong> valor</div>';
        }

        self::assertGrid(
            self::card('<div class="homlity-tech-sheet__grid">' . $cells . '</div>'),
            self::FIRST_COLUMN_X,
            self::COLUMN_PITCH,
            'La información general'
        );
    }

    public function testLasFinanzasVanATresColumnasDe240Px(): void
    {
        $cells = '';
        foreach (range(1, 4) as $n) {
            $cells .= '<article class="homlity-tech-sheet__stat">'
                . '<span class="homlity-tech-sheet__stat-label">Concepto</span>'
                . '<strong class="homlity-tech-sheet__stat-value">C' . $n . '</strong>'
                . '</article>';
        }

        // La tarjeta de cada cifra trae borde y padding propios, así que su
        // texto arranca 8 px más adentro que el de una celda de texto suelto.
        self::assertGrid(
            self::card('<div class="homlity-tech-sheet__stats">' . $cells . '</div>'),
            self::FIRST_COLUMN_X + 8.0,
            self::COLUMN_PITCH,
            'Las finanzas'
        );
    }

    public function testLasCaracteristicasVanATresColumnasDe240Px(): void
    {
        $cells = '';
        foreach (range(1, 4) as $n) {
            $cells .= '<li>C' . $n . '</li>';
        }

        // La viñeta de la lista mide 7 px y deja otros 8 px de aire.
        self::assertGrid(
            self::card('<ul class="homlity-tech-sheet__features-list">' . $cells . '</ul>'),
            self::FIRST_COLUMN_X + 15.0,
            self::COLUMN_PITCH,
            'Las características'
        );
    }

    public function testLosEnlacesMultimediaVanATresColumnasDe240Px(): void
    {
        $cells = '';
        foreach (range(1, 4) as $n) {
            $cells .= '<div><h3>C' . $n . '</h3><ul><li><a href="#">enlace</a></li></ul></div>';
        }

        self::assertGrid(
            self::card('<div class="homlity-tech-sheet__media-links">' . $cells . '</div>'),
            self::FIRST_COLUMN_X,
            self::COLUMN_PITCH,
            'Los enlaces multimedia'
        );
    }

    /**
     * Junto a la foto del asesor el ancho útil baja, y la retícula de esa fila
     * se estrecha a 220 px para que los datos sigan cabiendo a tres columnas.
     */
    public function testLosDatosDelAsesorVanAlLadoDeLaFotoYATresColumnas(): void
    {
        $cells = '';
        foreach (range(1, 4) as $n) {
            $cells .= '<div><strong>C' . $n . '</strong> valor</div>';
        }

        $body = self::card(
            '<div class="homlity-tech-sheet__advisor-row">'
            . '<div class="homlity-tech-sheet__advisor-avatar-wrap">'
            . '<img src="' . self::photo() . '" alt="" class="homlity-tech-sheet__advisor-avatar"></div>'
            . '<div class="homlity-tech-sheet__grid homlity-tech-sheet__grid--advisor">' . $cells . '</div>'
            . '</div>'
        );

        self::assertGrid($body, self::FIRST_COLUMN_X + 68.0, 220.0, 'Los datos del asesor');

        $avatar = self::images(self::render($body))[0];
        self::assertEqualsWithDelta(60.0, $avatar['width'], self::TOLERANCE, 'La foto del asesor no mide 60 px.');
        self::assertEqualsWithDelta(
            self::FIRST_COLUMN_X,
            $avatar['x'],
            self::TOLERANCE,
            'La foto del asesor no abre la fila.'
        );
    }

    /** El logo va al lado del título, no encima. */
    public function testElLogoVaAlLadoDelTitulo(): void
    {
        $body = '<div class="homlity-tech-sheet__hero"><div class="homlity-tech-sheet__hero-brand">'
            . '<img src="' . self::photo() . '" alt="" class="homlity-tech-sheet__hero-logo">'
            . '<div class="homlity-tech-sheet__hero-text"><h1>C1</h1></div></div></div>';

        $pdf = self::render($body);
        $logo = self::images($pdf)[0];
        $title = self::marks($pdf)['C1'];

        self::assertEqualsWithDelta(52.0, $logo['width'], self::TOLERANCE, 'El logo no mide 52 px.');
        self::assertGreaterThan($logo['x'] + $logo['width'], $title['x'], 'El título no arranca a la derecha del logo.');
        self::assertLessThan(
            $logo['y'] + $logo['height'],
            $title['y'],
            'El título ha caído por debajo del logo en vez de ponerse a su lado.'
        );
    }

    /**
     * Sin logo el título recupera los 77 px que ocupaba la foto. El mismo
     * título parte en dos líneas con logo y entra en una sin él.
     */
    public function testSinLogoElTituloOcupaLaTarjetaEntera(): void
    {
        $titulo = '<strong>C1</strong> ' . implode(' ', array_fill(0, 5, 'Apartamento')) . ' <strong>C2</strong>';

        $hero = static fn(string $logo): string => '<div class="homlity-tech-sheet__hero">'
            . '<div class="homlity-tech-sheet__hero-brand">' . $logo
            . '<div class="homlity-tech-sheet__hero-text"><h1>' . $titulo . '</h1></div></div></div>';

        $conLogo = self::marks(self::render(
            $hero('<img src="' . self::photo() . '" alt="" class="homlity-tech-sheet__hero-logo">')
        ));
        $sinLogo = self::marks(self::render($hero('')));

        self::assertGreaterThan(
            $conLogo['C1']['y'] + self::TOLERANCE,
            $conLogo['C2']['y'],
            'Con logo el título ya cabía en una línea: la prueba no distingue nada.'
        );
        self::assertEqualsWithDelta(
            $sinLogo['C1']['y'],
            $sinLogo['C2']['y'],
            self::TOLERANCE,
            'Sin logo el título sigue partiéndose: no está aprovechando el ancho que deja la foto.'
        );
    }

    // ── Las fotos ─────────────────────────────────────────────────────────

    /**
     * Las fotos se acotan a 150 px de ancho y van de tres en tres, en la misma
     * retícula de 240 px que el resto de la ficha.
     */
    public function testLasFotosMiden150PxYVanDeTresEnTres(): void
    {
        $gallery = str_repeat('<a href="#"><img src="' . self::photo() . '" alt=""></a>', 4);
        $images = self::images(self::render(
            self::card('<div class="homlity-tech-sheet__gallery">' . $gallery . '</div>')
        ));

        self::assertCount(4, $images);

        foreach ($images as $n => $image) {
            self::assertEqualsWithDelta(
                150.0,
                $image['width'],
                self::TOLERANCE,
                'La foto ' . ($n + 1) . ' no mide 150 px de ancho.'
            );
        }

        foreach ([0, 1, 2] as $n) {
            self::assertEqualsWithDelta(
                self::FIRST_COLUMN_X + $n * self::COLUMN_PITCH,
                $images[$n]['x'],
                self::TOLERANCE,
                'La foto ' . ($n + 1) . ' no arranca donde le toca.'
            );
            self::assertEqualsWithDelta($images[0]['y'], $images[$n]['y'], self::TOLERANCE, 'La primera fila se ha roto.');
        }

        self::assertEqualsWithDelta(
            self::FIRST_COLUMN_X,
            $images[3]['x'],
            self::TOLERANCE,
            'La cuarta foto no vuelve a la primera columna, caben más de tres por fila.'
        );
        self::assertGreaterThan($images[0]['y'], $images[3]['y'], 'La cuarta foto no baja de fila.');
    }

    // ── Que la retícula aguante ───────────────────────────────────────────

    /**
     * Un correo o una dirección larga no pueden empujar la columna siguiente:
     * con anchos fijos el texto parte, y es la única forma de que la ficha
     * salga igual con cualquier inmueble.
     */
    public function testUnTextoLargoNoDescuadraLaReticula(): void
    {
        $cells = '<div><strong>C1</strong> unaDireccionDeCorreoDesmesuradamenteLargaQueNoTieneEspacios@inmobiliaria.test</div>'
            . '<div><strong>C2</strong> valor</div>'
            . '<div><strong>C3</strong> valor</div>'
            . '<div><strong>C4</strong> valor</div>';

        $marks = self::marks(self::render(
            self::card('<div class="homlity-tech-sheet__grid">' . $cells . '</div>')
        ));

        foreach ([1 => 'C2', 2 => 'C3'] as $n => $mark) {
            self::assertEqualsWithDelta(
                self::FIRST_COLUMN_X + $n * self::COLUMN_PITCH,
                $marks[$mark]['x'],
                self::TOLERANCE,
                "La celda $mark se ha desplazado por el texto largo de la primera."
            );
        }
    }

    /**
     * Control: la retícula la pone esta hoja y nada más. Si el reparto viniera
     * de la maquetación de pantalla, quitar el `inline-block` no cambiaría
     * nada y las pruebas de arriba no estarían midiendo lo que dicen.
     */
    public function testSinLaHojaDelPdfLasSeccionesSeApilan(): void
    {
        $cells = '';
        foreach (range(1, 4) as $n) {
            $cells .= '<div><strong>C' . $n . '</strong> valor</div>';
        }

        $marks = self::marks(self::render(
            self::card('<div class="homlity-tech-sheet__grid">' . $cells . '</div>'),
            '.homlity-tech-sheet__grid > div { display: block; width: auto; }'
        ));

        self::assertEqualsWithDelta(
            $marks['C1']['x'],
            $marks['C2']['x'],
            self::TOLERANCE,
            'Sin el inline-block las celdas siguen repartidas: el reparto no lo hace esta hoja.'
        );
        self::assertGreaterThan($marks['C1']['y'], $marks['C2']['y'], 'Sin el inline-block las celdas no se apilan.');
    }

    // ── El resultado que se busca ─────────────────────────────────────────

    /**
     * Una ficha completa —15 datos, 6 cifras, 20 características y las 18 fotos
     * que como mucho publica la plantilla— cabe en dos páginas A4.
     */
    public function testUnaFichaCompletaCabeEnDosPaginas(): void
    {
        self::assertLessThanOrEqual(2, self::pageCount(self::fullSheet()));
    }

    private static function fullSheet(): string
    {
        $grid = '';
        foreach (range(1, 15) as $n) {
            $grid .= '<div><strong>Dato ' . $n . ':</strong> valor</div>';
        }

        $stats = '';
        foreach (range(1, 6) as $n) {
            $stats .= '<article class="homlity-tech-sheet__stat">'
                . '<span class="homlity-tech-sheet__stat-label">Concepto ' . $n . '</span>'
                . '<strong class="homlity-tech-sheet__stat-value">$ 850.000.000</strong>'
                . '</article>';
        }

        $features = '';
        foreach (range(1, 20) as $n) {
            $features .= '<li>Característica ' . $n . '</li>';
        }

        $gallery = str_repeat('<a href="#"><img src="' . self::photo() . '" alt=""></a>', 18);

        return self::card('<h2>Finanzas</h2><div class="homlity-tech-sheet__stats">' . $stats . '</div>')
            . self::card('<h2>Información general del inmueble</h2>'
                . '<div class="homlity-tech-sheet__grid">' . $grid . '</div>')
            . self::card('<h2>Características del inmueble</h2>'
                . '<ul class="homlity-tech-sheet__features-list">' . $features . '</ul>')
            . self::card('<h2>Catálogo multimedia</h2>'
                . '<div class="homlity-tech-sheet__gallery">' . $gallery . '</div>');
    }
}
