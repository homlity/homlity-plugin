<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations;

use Homlity\PluginInmobiliario\Listing\HeroSliderConfig;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

/**
 * El widget «Slider hero de inmuebles».
 *
 * Su comportamiento se prueba en HeroSliderConfigTest; el widget en sí no se
 * puede instanciar sin Elementor delante, así que aquí se cubren los acuerdos
 * entre archivos que ningún otro sitio vigila: el widget escribe variables CSS
 * que la hoja tiene que consumir, la plantilla emite atributos que el JS tiene
 * que leer, y el JS engancha un nombre de widget que tiene que coincidir. Si
 * uno de los tres cambia por su cuenta el slider deja de funcionar sin que
 * nada falle a la vista.
 */
final class PropertyHeroSliderWidgetTest extends TestCase
{
    private const WIDGET = 'src/Integrations/Elementor/Widgets/PropertyHeroSliderWidget.php';
    private const TEMPLATE = 'templates/parts/property-hero-slider.php';
    private const CSS = 'assets/css/property-hero-slider.css';
    private const JS = 'assets/js/property-hero-slider.js';

    private function source(string $relativePath): string
    {
        $path = HOMLITY_PLUGIN_PATH . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    // ── Assets ───────────────────────────────────────────────────────────

    /**
     * Elementor solo carga fuera del editor lo que cada widget declara. Otros
     * widgets del plugin ya se publicaron sin estilos por no declararlos: se
     * maquetaban en el editor y se deshacían al publicar. Este además depende
     * de Swiper, sin el cual los slides se apilan uno debajo de otro.
     */
    public function testElWidgetDeclaraSusHojasYSusScripts(): void
    {
        $fuente = $this->source(self::WIDGET);

        self::assertStringContainsString('public function get_style_depends(): array', $fuente);
        self::assertStringContainsString('public function get_script_depends(): array', $fuente);
        self::assertStringContainsString('assets/css/property-hero-slider.css', $fuente);
        self::assertStringContainsString('assets/js/property-hero-slider.js', $fuente);
        self::assertStringContainsString('assets/vendor/swiper/swiper-bundle.min.css', $fuente);
        self::assertStringContainsString('assets/vendor/swiper/swiper-bundle.min.js', $fuente);
    }

    /** Los ficheros que el widget declara tienen que existir de verdad. */
    public function testLosAssetsQueDeclaraElWidgetExisten(): void
    {
        foreach ([
            self::CSS,
            self::JS,
            'assets/vendor/swiper/swiper-bundle.min.css',
            'assets/vendor/swiper/swiper-bundle.min.js',
        ] as $asset) {
            self::assertFileExists(HOMLITY_PLUGIN_PATH . $asset);
        }
    }

    // ── Widget ↔ JS ──────────────────────────────────────────────────────

    /**
     * Elementor dispara el evento con el nombre del widget. Si get_name()
     * cambia y el JS se queda con el anterior, el slider no arranca al soltarlo
     * en el editor: se ve la lista de slides sin carrusel.
     */
    public function testElJsEnganchaElNombreRealDelWidget(): void
    {
        preg_match(
            "/public function get_name\(\): string\s*\{\s*return '([a-z_]+)'/",
            $this->source(self::WIDGET),
            $coincidencias
        );

        self::assertNotEmpty($coincidencias, 'No se pudo leer get_name() del widget.');

        self::assertStringContainsString(
            'frontend/element_ready/' . $coincidencias[1] . '.default',
            $this->source(self::JS)
        );
    }

    /**
     * El JS configura Swiper leyendo atributos data-* del contenedor. Cada uno
     * que lee tiene que salir de la plantilla; si no, se queda con su valor de
     * respaldo y el ajuste del panel de Elementor deja de tener efecto.
     */
    public function testCadaAtributoQueLeeElJsLoEmiteLaPlantilla(): void
    {
        preg_match_all('/dataset\.([a-zA-Z]+)/', $this->source(self::JS), $coincidencias);

        $plantilla = $this->source(self::TEMPLATE);

        foreach (array_unique($coincidencias[1]) as $propiedad) {
            // swiperReady lo escribe el propio JS para no inicializar dos veces.
            if ($propiedad === 'swiperReady') {
                continue;
            }

            $atributo = 'data-' . strtolower((string) preg_replace('/([A-Z])/', '-$1', $propiedad));

            self::assertStringContainsString(
                $atributo . '=',
                $plantilla,
                sprintf('El JS lee "%s" pero la plantilla no emite "%s".', $propiedad, $atributo)
            );
        }
    }

    // ── Widget ↔ CSS ─────────────────────────────────────────────────────

    /**
     * Los controles de estilo no aplican CSS directo: escriben variables que
     * la hoja consume. Una variable que el widget escribe y la hoja no lee es
     * un control que no hace nada, y el panel no da ninguna pista.
     */
    public function testLaHojaConsumeCadaVariableQueEscribeElWidget(): void
    {
        preg_match_all('/--hml-hero-[a-z-]+/', $this->source(self::WIDGET), $coincidencias);

        $css = $this->source(self::CSS);
        $js  = $this->source(self::JS);
        $variables = array_unique($coincidencias[0]);

        self::assertNotEmpty($variables);

        foreach ($variables as $variable) {
            // El espacio entre slides lo aplica Swiper, que necesita un número
            // y lo lee del estilo calculado; el resto lo consume la hoja.
            $consumida = str_contains($css, 'var(' . $variable)
                || str_contains($js, "getPropertyValue('" . $variable . "')");

            self::assertTrue(
                $consumida,
                sprintf('El widget escribe "%s" pero nadie la lee.', $variable)
            );
        }
    }

    /** Las clases que pinta la plantilla tienen que tener estilos detrás. */
    public function testLaHojaDaEstiloALosTresDisenos(): void
    {
        $css = $this->source(self::CSS);

        // El hero es el estilo base, sin modificador: los otros dos se
        // desvían de él y por eso sí tienen reglas propias.
        self::assertStringContainsString('.hml-hero-slider--split', $css);
        self::assertStringContainsString('.hml-hero-slider--cards', $css);
        self::assertStringContainsString('.hml-hero-slider--kenburns', $css);

        self::assertContains('split', HeroSliderConfig::IMAGE_LAYOUTS);
    }

    /**
     * El zoom lento se para si el visitante pidió reducir el movimiento; es un
     * ajuste de accesibilidad del sistema y se respeta sin preguntar.
     */
    public function testElZoomLentoRespetaLaPreferenciaDeMenosMovimiento(): void
    {
        $css = $this->source(self::CSS);

        self::assertStringContainsString('prefers-reduced-motion: reduce', $css);
        self::assertMatchesRegularExpression(
            '/prefers-reduced-motion: reduce\).*?animation: none/s',
            $css
        );
    }

    // ── Controles ────────────────────────────────────────────────────────

    /** Los tres diseños ofrecidos en el panel son los que la configuración acepta. */
    public function testElPanelOfreceLosTresDisenosQueLaConfiguracionEntiende(): void
    {
        $fuente = $this->source(self::WIDGET);

        foreach (['hero', 'split', 'cards'] as $layout) {
            self::assertMatchesRegularExpression(
                "/'" . $layout . "'\s*=> __\(/",
                $fuente,
                sprintf('El panel tiene que ofrecer el diseño "%s".', $layout)
            );
        }
    }

    /**
     * Los ajustes de tamaño y espaciado tienen que poder configurarse por
     * dispositivo. Registrados como add_control() solo existirían para
     * escritorio y el hero se rompería en el móvil.
     */
    public function testLosAjustesDeTamanoSeConfiguranPorDispositivo(): void
    {
        $fuente = $this->source(self::WIDGET);

        foreach ([
            'slide_height',
            'content_position',
            'content_width',
            'content_padding',
            'content_gap',
            'arrows_size',
            'bullet_size',
            'split_media_side',
            'split_media_width',
        ] as $control) {
            self::assertStringContainsString(
                "add_responsive_control('" . $control . "'",
                $fuente,
                sprintf('"%s" tiene que poder configurarse por dispositivo.', $control)
            );
        }
    }

    /**
     * La posición y el lado de la imagen se resuelven con diccionarios porque
     * un control responsive escribe una declaración por dispositivo; con CSS
     * fijo por clase solo podría haber una para las tres pantallas.
     */
    public function testLaPosicionYElLadoDeLaImagenUsanDiccionariosDeSelectores(): void
    {
        $fuente = $this->source(self::WIDGET);

        self::assertSame(
            2,
            substr_count($fuente, "'selectors_dictionary' =>"),
            'La posición del contenido y el lado de la imagen usan diccionario.'
        );
    }
}
