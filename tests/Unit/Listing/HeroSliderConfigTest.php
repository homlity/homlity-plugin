<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Listing;

use Homlity\PluginInmobiliario\Listing\HeroSliderConfig;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

/**
 * Configuración del slider hero.
 *
 * El widget de Elementor no se puede instanciar sin Elementor delante, así que
 * toda su lógica vive aquí: qué recibe la plantilla y qué consulta se lanza.
 * Lo que se comprueba es la traducción de los ajustes crudos del constructor
 * visual —donde un interruptor es 'yes' o cadena vacía y un número puede
 * llegar como texto— a valores con los que la plantilla puede trabajar.
 */
final class HeroSliderConfigTest extends TestCase
{
    /** @param array<string,mixed> $settings */
    private function options(array $settings = []): array
    {
        return HeroSliderConfig::fromElementor($settings)->templateOptions();
    }

    // ── Diseño ───────────────────────────────────────────────────────────

    public function testElDisenoPorDefectoEsElHero(): void
    {
        self::assertSame('hero', HeroSliderConfig::fromElementor([])->layout());
    }

    /** @return array<string,array{0:string}> */
    public static function layoutProvider(): array
    {
        return ['hero' => ['hero'], 'dividido' => ['split'], 'tarjetas' => ['cards']];
    }

    /** @dataProvider layoutProvider */
    public function testAceptaLosTresDisenos(string $layout): void
    {
        self::assertSame($layout, HeroSliderConfig::fromElementor(['layout' => $layout])->layout());
    }

    /**
     * Un diseño que no existe —una plantilla vieja, un ajuste a mano— cae al
     * hero en vez de emitir una clase CSS sin hoja de estilos detrás.
     */
    public function testUnDisenoDesconocidoCaeAlHero(): void
    {
        self::assertSame('hero', HeroSliderConfig::fromElementor(['layout' => 'coverflow'])->layout());
    }

    // ── Slides por vista ─────────────────────────────────────────────────

    /**
     * Los diseños de imagen ocupan el ancho completo. Si el usuario configuró
     * 3 columnas con el carrusel y luego cambió a hero, el ajuste sigue
     * guardado y no debe partir el hero en tres.
     */
    public function testLosDisenosDeImagenMuestranUnSoloSlideAunqueHayaColumnasGuardadas(): void
    {
        foreach (HeroSliderConfig::IMAGE_LAYOUTS as $layout) {
            $options = $this->options([
                'layout' => $layout,
                'slides_desktop' => 4,
                'slides_tablet' => 3,
                'slides_mobile' => 2,
            ]);

            self::assertSame(1, $options['slides_desktop'], $layout);
            self::assertSame(1, $options['slides_tablet'], $layout);
            self::assertSame(1, $options['slides_mobile'], $layout);
        }
    }

    public function testElCarruselDeTarjetasRespetaLasColumnasPorDispositivo(): void
    {
        $options = $this->options([
            'layout' => 'cards',
            'slides_desktop' => 4,
            'slides_tablet' => 3,
            'slides_mobile' => 2,
        ]);

        self::assertSame(4, $options['slides_desktop']);
        self::assertSame(3, $options['slides_tablet']);
        self::assertSame(2, $options['slides_mobile']);
    }

    public function testNuncaMuestraMenosDeUnSlide(): void
    {
        $options = $this->options(['layout' => 'cards', 'slides_desktop' => 0, 'slides_mobile' => -2]);

        self::assertSame(1, $options['slides_desktop']);
        self::assertSame(1, $options['slides_mobile']);
    }

    // ── Ken Burns ────────────────────────────────────────────────────────

    /** @dataProvider layoutProvider */
    public function testKenBurnsSoloSeAplicaALosDisenosConImagenPropia(string $layout): void
    {
        $enabled = HeroSliderConfig::fromElementor([
            'layout' => $layout,
            'kenburns' => 'yes',
        ])->kenBurnsEnabled();

        self::assertSame($layout !== 'cards', $enabled);
    }

    public function testKenBurnsEstaApagadoSalvoQueSePida(): void
    {
        self::assertFalse(HeroSliderConfig::fromElementor(['layout' => 'hero'])->kenBurnsEnabled());
    }

    // ── Interruptores ────────────────────────────────────────────────────

    /**
     * Un widget recién soltado en la página no tiene ajustes guardados: cada
     * campo debe salir con el valor por defecto de su control, no apagado.
     */
    public function testUnWidgetSinAjustesUsaLosValoresPorDefectoDeSusControles(): void
    {
        $options = $this->options();

        self::assertTrue($options['show_title']);
        self::assertTrue($options['show_price']);
        self::assertTrue($options['autoplay']);
        self::assertTrue($options['loop']);
        self::assertTrue($options['show_arrows']);
        self::assertTrue($options['link_whole_slide']);

        // Estos sí nacen apagados.
        self::assertFalse($options['show_excerpt']);
        self::assertFalse($options['show_code']);
        self::assertFalse($options['show_whatsapp']);
        self::assertFalse($options['link_new_tab']);
    }

    /** Elementor guarda un interruptor apagado como cadena vacía, no como false. */
    public function testUnInterruptorApagadoLlegaComoCadenaVaciaYSeLeeComoApagado(): void
    {
        $options = $this->options(['show_title' => '', 'autoplay' => '']);

        self::assertFalse($options['show_title']);
        self::assertFalse($options['autoplay']);
    }

    public function testUnInterruptorEncendidoSoloValeConElValorYes(): void
    {
        self::assertTrue($this->options(['show_code' => 'yes'])['show_code']);
        self::assertFalse($this->options(['show_code' => '1'])['show_code']);
    }

    // ── Valores con mínimos ──────────────────────────────────────────────

    public function testElTiempoEntreSlidesNoBajaDeUnSegundo(): void
    {
        self::assertSame(1000, $this->options(['autoplay_delay' => 10])['autoplay_delay']);
        self::assertSame(7500, $this->options(['autoplay_delay' => 7500])['autoplay_delay']);
    }

    public function testLaVelocidadDeTransicionTieneUnMinimo(): void
    {
        self::assertSame(100, $this->options(['speed' => 0])['speed']);
    }

    public function testLasPalabrasDeLaDescripcionNuncaSonCero(): void
    {
        self::assertSame(1, $this->options(['excerpt_words' => 0])['excerpt_words']);
    }

    /** Los números llegan como texto desde el constructor visual. */
    public function testConvierteLosNumerosQueLleganComoTexto(): void
    {
        self::assertSame(9000, $this->options(['autoplay_delay' => '9000'])['autoplay_delay']);
        self::assertSame(3, $this->options(['layout' => 'cards', 'slides_desktop' => '3'])['slides_desktop']);
    }

    // ── Listas cerradas ──────────────────────────────────────────────────

    public function testLaTransicionSoloPuedeSerDeslizarODesvanecer(): void
    {
        self::assertSame('fade', $this->options(['effect' => 'fade'])['effect']);
        self::assertSame('slide', $this->options(['effect' => 'cube'])['effect']);
        self::assertSame('slide', $this->options([])['effect']);
    }

    public function testLaPaginacionCaeAPuntosSiElTipoNoExiste(): void
    {
        self::assertSame('fraction', $this->options(['pagination_type' => 'fraction'])['pagination_type']);
        self::assertSame('bullets', $this->options(['pagination_type' => 'inventado'])['pagination_type']);
    }

    // ── Íconos ───────────────────────────────────────────────────────────

    public function testConservaLaConfiguracionDeIconoTalComoLlega(): void
    {
        $icon = ['value' => 'fas fa-bed', 'library' => 'fa-solid'];

        self::assertSame($icon, $this->options(['feature_icon_bedrooms' => $icon])['feature_icon_bedrooms']);
    }

    /** Un ícono vacío o mal formado no debe llegar al renderizador. */
    public function testUnIconoQueNoEsArregloSeDescarta(): void
    {
        self::assertSame([], $this->options(['button_icon' => 'fas fa-arrow-right'])['button_icon']);
        self::assertSame([], $this->options([])['button_icon']);
    }

    // ── Consulta ─────────────────────────────────────────────────────────

    /** @param array<string,mixed> $settings */
    private function params(array $settings = []): array
    {
        return HeroSliderConfig::fromElementor($settings)->queryParams();
    }

    /** @param array<string,mixed> $settings */
    private function args(array $settings = []): array
    {
        return HeroSliderConfig::fromElementor($settings)->queryArgs();
    }

    public function testPideSeisInmueblesSiNadieConfiguraLaCantidad(): void
    {
        self::assertSame(6, $this->params()['per_page']);
    }

    /**
     * Un hero no es un catálogo: pedir 500 slides sería una consulta cara para
     * una cabecera que nadie va a deslizar entera.
     */
    public function testLimitaLaCantidadDeSlides(): void
    {
        self::assertSame(30, $this->params(['posts_per_page' => 500])['per_page']);
        self::assertSame(1, $this->params(['posts_per_page' => 0])['per_page']);
    }

    /** El hero muestra siempre la primera página; nunca hereda la del archivo. */
    public function testSiempreConsultaLaPrimeraPagina(): void
    {
        self::assertSame(1, $this->params()['page']);
    }

    public function testTrasladaLosFiltrosDelWidgetALaConsulta(): void
    {
        $params = $this->params([
            'featured_only' => 'yes',
            'preset_operation' => 31,
            'preset_type' => 32,
            'preset_category' => 33,
            'preset_city' => 34,
            'preset_tag' => 35,
        ]);

        self::assertTrue($params['featured']);
        self::assertSame(31, $params['preset_operation']);
        self::assertSame(32, $params['preset_type']);
        self::assertSame(33, $params['preset_category']);
        self::assertSame(34, $params['preset_city']);
        self::assertSame(35, $params['preset_tag']);
    }

    public function testSinFiltrosNoFijaNingunTermino(): void
    {
        $params = $this->params();

        self::assertFalse($params['featured']);
        foreach (['preset_operation', 'preset_type', 'preset_category', 'preset_city', 'preset_tag'] as $key) {
            self::assertSame(0, $params[$key], $key);
        }
    }

    /** El hero no pagina, así que contar el total sobra en cada petición. */
    public function testNoCuentaElTotalDeResultados(): void
    {
        self::assertTrue($this->args()['no_found_rows']);
    }

    public function testConsultaSoloInmueblesPublicados(): void
    {
        $args = $this->args();

        self::assertSame('property', $args['post_type']);
        self::assertSame('publish', $args['post_status']);
    }

    /**
     * 'rand' no forma parte del vocabulario de orden compartido, así que el
     * servicio lo ignora y hay que aplicarlo después; si no, el orden
     * aleatorio salía siempre por fecha.
     */
    public function testElOrdenAleatorioLlegaHastaLaConsulta(): void
    {
        $args = $this->args(['orderby' => 'rand']);

        self::assertSame('rand', $args['orderby']);
        self::assertArrayNotHasKey('order', $args);
    }

    public function testElRestoDeOrdenesLosResuelveElServicioDeBusqueda(): void
    {
        self::assertSame('DESC', $this->args(['orderby' => 'date'])['orderby']['date']);
        self::assertSame('ASC', $this->args(['orderby' => 'title'])['orderby']['title']);
    }

    /**
     * La consulta pasa por el mismo servicio que el listado, que es lo que
     * mantiene fuera los inmuebles retirados del mercado.
     */
    public function testHeredaElResguardoDeInmueblesRetirados(): void
    {
        $encoded = (string) wp_json_encode($this->args()['meta_query']);

        self::assertStringContainsString('_property_status', $encoded);
        self::assertStringContainsString('_property_available', $encoded);
    }
}
