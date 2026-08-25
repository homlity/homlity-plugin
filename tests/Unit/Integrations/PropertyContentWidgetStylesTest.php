<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations;

use Homlity\PluginInmobiliario\Integrations\Divi\Widgets\PropertyContentWidget;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

/**
 * Estilos del widget «Descripción completa».
 *
 * La descripción se guarda como HTML con el formato que le puso el redactor:
 * negritas, cursivas, títulos y listas. Los controles de estilo del widget
 * enumeraban cada una de esas etiquetas en su selector, así que la tipografía
 * elegida en el constructor se escribía encima del formato del texto: el peso
 * aplastaba las negritas, el `font-style` enderezaba las cursivas y el tamaño
 * dejaba los títulos con el cuerpo de un párrafo. En la vista pública el texto
 * salía plano.
 *
 * Ahora la tipografía va al contenedor y se hereda, que es como cada etiqueta
 * conserva lo suyo.
 *
 * Se prueba la variante de Divi por el mismo motivo que
 * PropertyAgentWidgetStylesTest: su capa de compatibilidad es PHP corriente y
 * las tres copias del widget son el mismo fichero.
 */
final class PropertyContentWidgetStylesTest extends TestCase
{
    /** Las etiquetas con las que el editor marca el formato del texto. */
    private const ETIQUETAS_DE_FORMATO = [
        'strong', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        require_once HOMLITY_PLUGIN_PATH . 'src/Integrations/Divi/Compatibility/DiviWidgetApi.php';
    }

    /** Todo el CSS que declara un control, venga de `selector` o de `selectors`. */
    private function selectorDe(string $control): string
    {
        $controls = (new PropertyContentWidget())->get_controls();
        self::assertArrayHasKey($control, $controls, "El control {$control} ya no existe.");

        $args = $controls[$control];
        $selector = (string) ($args['selector'] ?? '');
        $selector .= ' ' . implode(' ', array_keys($args['selectors'] ?? []));

        self::assertStringContainsString('.property-content-widget', $selector);

        return $selector;
    }

    /**
     * @dataProvider controlesQueNoDebenTocarElFormato
     */
    public function testLosEstilosNoSeEscribenSobreElFormatoDelTexto(string $control): void
    {
        $selector = $this->selectorDe($control);

        foreach (self::ETIQUETAS_DE_FORMATO as $etiqueta) {
            self::assertSame(
                0,
                preg_match('/\s' . $etiqueta . '\b/', $selector),
                "El control {$control} apunta a <{$etiqueta}> y le pisa el formato al texto."
            );
        }
    }

    /** @return array<string,array{string}> */
    public static function controlesQueNoDebenTocarElFormato(): array
    {
        return [
            'tipografía'        => ['content_typography'],
            'sombra del texto'  => ['content_text_shadow'],
            'ancho del trazo'   => ['content_stroke_width'],
            'color del trazo'   => ['content_stroke_color'],
            'trazo en hover'    => ['content_stroke_color_hover'],
        ];
    }

    /**
     * Heredar sólo funciona si el estilo cuelga del contenedor. Si alguien
     * vuelve a apuntar la tipografía a los párrafos, las etiquetas de dentro
     * dejan de heredar de quien deben.
     */
    public function testLaTipografiaCuelgaDelContenedor(): void
    {
        self::assertSame(
            '{{WRAPPER}} .property-content-widget ',
            $this->selectorDe('content_typography')
        );
    }

    /**
     * Las tres copias del widget —Elementor, WPBakery y Divi— son el mismo
     * fichero repetido, y arriba solo se prueba la de Divi. Si alguien arregla
     * una y se olvida de las otras dos, el fallo vuelve en los otros dos
     * constructores sin que nadie se entere.
     *
     * @dataProvider copiasDelWidget
     */
    public function testLasTresCopiasDelWidgetDeclaranLosMismosEstilos(string $constructor): void
    {
        $fuente = (string) file_get_contents(
            HOMLITY_PLUGIN_PATH . "src/Integrations/{$constructor}/Widgets/PropertyContentWidget.php"
        );

        $bloque = substr($fuente, (int) strpos($fuente, "\$this->start_controls_section('style_content'"));
        $bloque = substr($bloque, 0, (int) strpos($bloque, 'audio_player_typography'));
        self::assertNotSame('', trim($bloque), 'No se encontró el bloque de estilos del contenido.');

        // La tipografía y la sombra cuelgan del contenedor.
        self::assertSame(2, substr_count($bloque, "'selector' => \$contentSelector,"));
        self::assertSame(0, substr_count($bloque, "'selector' => \$contentTextSelector,"));

        // Y lo único que sigue apuntando etiqueta por etiqueta es el color.
        self::assertSame(1, substr_count($bloque, '$contentTextSelector'));
    }

    /** @return array<string,array{string}> */
    public static function copiasDelWidget(): array
    {
        return [
            'Elementor' => ['Elementor'],
            'WPBakery'  => ['WPBakery'],
            'Divi'      => ['Divi'],
        ];
    }

    /**
     * El color sí se nombra etiqueta por etiqueta, y a propósito: `color` se
     * hereda, pero los enlaces traen el suyo del tema y no lo sueltan. Aquí no
     * se pisa ningún formato —un enlace de otro color sigue siendo un enlace—.
     */
    public function testElColorSigueAlcanzandoALosEnlaces(): void
    {
        foreach (['content_color', 'content_color_hover'] as $control) {
            self::assertSame(
                1,
                preg_match('/\.property-content-widget(:hover)? a\b/', $this->selectorDe($control)),
                "El color del control {$control} ya no llega a los enlaces."
            );
        }
    }
}
