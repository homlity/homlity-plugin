<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Support;

/**
 * Lee por dentro un PDF ya generado.
 *
 * Sin esto no hay forma de comprobar una maquetación: el CSS puede estar
 * perfecto y la página salir mal igualmente, porque lo que decide es cómo lo
 * compone Dompdf. Lo que se mira aquí es el resultado, no la intención.
 *
 * En el flujo de contenido cada línea de texto aparece como
 * `BT x y Td /F1 tam Tf [(texto)] TJ` y cada imagen como
 * `q ancho 0 0 alto x y cm /Img Do`. El origen del papel es la esquina
 * inferior izquierda, así que las `y` se dan la vuelta para medir desde
 * arriba, que es como está escrita la hoja. Todo sale en píxeles CSS: Dompdf
 * resuelve el px a 96 dpi, 1 px = 0,75 pt.
 */
final class PdfProbe
{
    public const PT_PER_PX = 0.75;
    public const A4_HEIGHT_PT = 841.89;
    public const A4_HEIGHT_PX = 1122.52;
    public const A4_WIDTH_PX = 793.7;

    /**
     * El PDF con los flujos descomprimidos.
     *
     * Dompdf comprime con Flate. Se descomprime aquí, en vez de pedirle que no
     * comprima, para que las pruebas miren exactamente los bytes que se
     * descargaría alguien.
     *
     * El final de cada flujo se toma de su `/Length` y no buscando el
     * `endstream` que viene detrás. Buscándolo se pierde un byte cada vez que
     * los datos comprimidos acaban en un salto de línea —el propio separador
     * se lo lleva—, y con un byte de menos la descompresión falla en silencio:
     * ese flujo se queda comprimido, y la página entera desaparece de la
     * lectura sin que nada avise.
     */
    public static function inflate(string $pdf): string
    {
        $matched = preg_match_all(
            '/<<[^<>]*\/Filter\s*\/FlateDecode[^<>]*\/Length\s+(\d+)[^<>]*>>\s*stream\r?\n/s',
            $pdf,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );
        if ($matched === false || $matched === 0) {
            return $pdf;
        }

        // De atrás hacia delante: así los desplazamientos de los flujos que
        // quedan por delante siguen siendo válidos aunque el texto crezca.
        foreach (array_reverse($matches) as $stream) {
            $from = (int) $stream[0][1] + strlen((string) $stream[0][0]);
            $length = (int) $stream[1][0];

            $plain = @gzuncompress(substr($pdf, $from, $length));
            if ($plain === false) {
                continue;
            }

            $pdf = substr($pdf, 0, $from) . $plain . substr($pdf, $from + $length);
        }

        return $pdf;
    }

    /** Cuántas páginas declara el documento. */
    public static function pageCount(string $pdf): int
    {
        // El objeto /Pages lleva la cuenta; los /Type /Page se cuentan como
        // respaldo por si el árbol viene anidado.
        if (preg_match('/\/Type\s*\/Pages\b[^>]*?\/Count\s+(\d+)/s', $pdf, $found) === 1) {
            return (int) $found[1];
        }

        return preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    }

    /**
     * El tamaño de papel declarado, en puntos.
     *
     * @return array{width:float, height:float}|null
     */
    public static function mediaBox(string $pdf): ?array
    {
        if (preg_match('/\/MediaBox\s*\[\s*([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s*\]/', $pdf, $found) !== 1) {
            return null;
        }

        return [
            'width' => (float) $found[3] - (float) $found[1],
            'height' => (float) $found[4] - (float) $found[2],
        ];
    }

    /**
     * Todos los colores con los que se pinta el documento, en hexadecimal.
     *
     * En el flujo de contenido un color aparece como `r g b rg` si es relleno
     * y `r g b RG` si es trazo, con los tres canales de 0 a 1. Hacen falta los
     * dos: el texto y los iconos se rellenan, pero el borde de un botón se
     * traza, y mirando solo el relleno un borde mal coloreado pasa
     * desapercibido.
     *
     * Sirve para comprobar que el color configurado llega hasta la tinta, y no
     * solo hasta el atributo `style`.
     *
     * @return string[]
     */
    public static function fillColors(string $pdf): array
    {
        $colors = [];

        foreach (self::contentStreams($pdf) as $stream) {
            preg_match_all('/([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+(?:rg|RG)\b/', $stream, $matches, PREG_SET_ORDER);
            foreach ($matches as $rgb) {
                $colors[] = sprintf(
                    '#%02x%02x%02x',
                    (int) round((float) $rgb[1] * 255),
                    (int) round((float) $rgb[2] * 255),
                    (int) round((float) $rgb[3] * 255)
                );
            }
        }

        return array_values(array_unique($colors));
    }

    /** Si el documento lleva las fuentes incrustadas. */
    public static function embedsFonts(string $pdf): bool
    {
        return str_contains($pdf, '/FontFile2') || str_contains($pdf, '/FontFile');
    }

    /**
     * Las líneas de texto, agrupadas por página.
     *
     * @return array<int, array<int, array{x:float, y:float, text:string}>>
     */
    public static function textByPage(string $pdf): array
    {
        $pages = [];

        foreach (self::contentStreams($pdf) as $stream) {
            // El `Tc` opcional es el interletraje de los rótulos en mayúsculas.
            //
            // La clase negada y no `.*?`: sobre el megabyte que ocupa un PDF
            // con fotos, un cuantificador perezoso agota el límite de retroceso
            // de PCRE y preg_match_all devuelve cero coincidencias sin avisar.
            // La alternativa con la barra es para los paréntesis escapados.
            preg_match_all(
                '/BT\s+([\d.-]+)\s+([\d.-]+)\s+Td\s+(?:[\d.-]+\s+Tc\s+)?\/\w+\s+[\d.]+\s+Tf\s+\[\(((?:[^()\\\\]|\\\\.)*)\)\]\s+TJ/',
                $stream,
                $matches,
                PREG_SET_ORDER
            );

            $runs = [];
            foreach ($matches as $run) {
                $runs[] = [
                    'x' => (float) $run[1] / self::PT_PER_PX,
                    'y' => (self::A4_HEIGHT_PT - (float) $run[2]) / self::PT_PER_PX,
                    'text' => self::decode($run[3]),
                ];
            }

            $pages[] = $runs;
        }

        return $pages;
    }

    /** Todo el texto del documento, en una cadena. */
    public static function text(string $pdf): string
    {
        $lines = [];
        foreach (self::textByPage($pdf) as $runs) {
            foreach ($runs as $run) {
                $lines[] = $run['text'];
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Las imágenes, agrupadas por página.
     *
     * @return array<int, array<int, array{x:float, y:float, width:float, height:float}>>
     */
    public static function imagesByPage(string $pdf): array
    {
        $pages = [];

        foreach (self::contentStreams($pdf) as $stream) {
            preg_match_all(
                '/q\s+([\d.-]+)\s+0\s+0\s+([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s+cm\s*\/\w+\s+Do/',
                $stream,
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

            $pages[] = $images;
        }

        return $pages;
    }

    /**
     * Un flujo de contenido por página, en orden.
     *
     * @return string[]
     */
    private static function contentStreams(string $pdf): array
    {
        $streams = [];
        foreach (preg_split('/\bendstream\b/', $pdf) ?: [] as $chunk) {
            if (str_contains($chunk, ' Td ')) {
                $streams[] = $chunk;
            }
        }

        return $streams;
    }

    /**
     * Según la fuente, Dompdf escribe la cadena con un byte por carácter o en
     * UTF-16BE.
     *
     * El indicio de UTF-16BE es el byte alto a cero del primer carácter, no
     * que todo sea ASCII: «FICHA TÉCNICA» lleva una É, que en UTF-16BE es
     * 00 C9, y con la comprobación restringida a ASCII imprimible el rótulo se
     * quedaba en crudo y no lo encontraba ninguna prueba.
     */
    public static function decode(string $glyphs): string
    {
        if ($glyphs === '' || strlen($glyphs) % 2 !== 0 || $glyphs[0] !== "\x00") {
            return $glyphs;
        }

        $decoded = mb_convert_encoding($glyphs, 'UTF-8', 'UTF-16BE');

        return is_string($decoded) ? $decoded : $glyphs;
    }
}
