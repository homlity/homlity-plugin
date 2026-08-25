<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Listing;

use Homlity\PluginInmobiliario\Listing\HeroSliderConfig;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;
use WP_Query;

/**
 * La plantilla del slider hero.
 *
 * Es donde se decide qué ve el visitante: qué campos salen, qué se escapa y
 * qué se omite cuando el dato no existe. Se renderiza de verdad, con la
 * configuración real del widget, en vez de comprobar cadenas sueltas.
 */
final class HeroSliderTemplateTest extends TestCase
{
    private const PROPERTY_ID = 501;

    /** Deja un inmueble completo listo para entrar en el bucle. */
    private function givenProperty(array $meta = [], array $terms = []): void
    {
        WpStubs::setPost(self::PROPERTY_ID, 'Apartamento en El Poblado', 'https://ejemplo.test/inmueble/501');
        WpStubs::$postExcerpts[self::PROPERTY_ID] = 'Descripción larga del inmueble para recortar.';
        WpStubs::setPostMeta(self::PROPERTY_ID, array_merge([
            '_property_price_sale' => '450000000',
            '_property_area' => '82',
            '_property_bedrooms' => '3',
            '_property_bathrooms' => '2',
            '_property_parking' => '1',
        ], $meta));

        foreach ($terms + [PropertyTaxonomies::TAXONOMY_OPERATION => ['Venta']] as $taxonomy => $names) {
            WpStubs::$postTerms[self::PROPERTY_ID][$taxonomy] = array_map(
                static fn(string $name): object => (object) ['name' => $name, 'slug' => sanitize_title($name)],
                $names
            );
        }
    }

    /** @param array<string,mixed> $settings */
    private function render(array $settings = [], int $slides = 1): string
    {
        $options = HeroSliderConfig::fromElementor($settings)->templateOptions();

        $query = new WP_Query();
        $query->posts = array_fill(0, $slides, self::PROPERTY_ID);
        $query->post_count = $slides;

        ob_start();

        try {
            (static function (array $args) {
                extract($args, EXTR_SKIP);
                include HOMLITY_PLUGIN_PATH . 'templates/parts/property-hero-slider.php';
            })(['query' => $query, 'options' => $options, 'card_options' => []]);
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    // ── Estructura ───────────────────────────────────────────────────────

    public function testPintaUnSlidePorInmueble(): void
    {
        $this->givenProperty();

        self::assertSame(3, substr_count($this->render([], 3), 'hml-hero-slider__slide'));
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function layoutClassProvider(): array
    {
        return [
            'hero' => ['hero', 'hml-hero-slider--hero'],
            'dividido' => ['split', 'hml-hero-slider--split'],
            'tarjetas' => ['cards', 'hml-hero-slider--cards'],
        ];
    }

    /** @dataProvider layoutClassProvider */
    public function testCadaDisenoMarcaSuClaseEnElContenedor(string $layout, string $clase): void
    {
        $this->givenProperty();

        self::assertStringContainsString($clase, $this->render(['layout' => $layout]));
    }

    /**
     * El JS lee la configuración de los data-*, así que lo que se elige en el
     * panel tiene que llegar al marcado.
     */
    public function testTrasladaLaConfiguracionDelSliderAlMarcado(): void
    {
        $this->givenProperty();

        $html = $this->render([
            'layout' => 'cards',
            'effect' => 'fade',
            'autoplay' => '',
            'pagination_type' => 'fraction',
            'slides_desktop' => 4,
        ]);

        self::assertStringContainsString('data-effect="fade"', $html);
        self::assertStringContainsString('data-autoplay="0"', $html);
        self::assertStringContainsString('data-pagination-type="fraction"', $html);
        self::assertStringContainsString('data-slides-desktop="4"', $html);
    }

    // ── Contenido ────────────────────────────────────────────────────────

    public function testMuestraLosDatosDelInmueble(): void
    {
        $this->givenProperty();

        $html = $this->render();

        self::assertStringContainsString('Apartamento en El Poblado', $html);
        self::assertStringContainsString('Venta', $html);
        self::assertStringContainsString('82 m²', $html);
        self::assertStringContainsString('https://ejemplo.test/inmueble/501', $html);
    }

    public function testOcultaLosCamposQueSeApaganEnElPanel(): void
    {
        $this->givenProperty();

        $html = $this->render(['show_title' => '', 'show_features' => '', 'show_operation' => '']);

        self::assertStringNotContainsString('hml-hero-slider__title', $html);
        self::assertStringNotContainsString('hml-hero-slider__features', $html);
        self::assertStringNotContainsString('hml-hero-slider__operation', $html);
    }

    /** Un dato que el inmueble no tiene no debe dejar un hueco vacío pintado. */
    public function testOmiteLasCaracteristicasSinValor(): void
    {
        $this->givenProperty(['_property_parking' => '', '_property_bathrooms' => '']);

        $html = $this->render();

        self::assertSame(2, substr_count($html, 'hml-hero-slider__feature-value'));
    }

    /**
     * Sin teléfono no hay enlace de WhatsApp posible: el botón se cae en vez
     * de salir apuntando a ninguna parte.
     */
    public function testNoPintaWhatsappSiElInmuebleNoTieneTelefono(): void
    {
        $this->givenProperty();

        self::assertStringNotContainsString(
            'hml-hero-slider__whatsapp',
            $this->render(['show_whatsapp' => 'yes'])
        );
    }

    // ── Enlace de slide ──────────────────────────────────────────────────

    public function testElSlideEnteroEsEnlacePorDefecto(): void
    {
        $this->givenProperty();

        self::assertStringContainsString('hml-hero-slider__overlay-link', $this->render());
    }

    public function testSePuedeQuitarElEnlaceDeTodoElSlide(): void
    {
        $this->givenProperty();

        self::assertStringNotContainsString(
            'hml-hero-slider__overlay-link',
            $this->render(['link_whole_slide' => ''])
        );
    }

    public function testAbreEnPestanaNuevaSoloSiSePide(): void
    {
        $this->givenProperty();

        self::assertStringNotContainsString('target="_blank"', $this->render());
        self::assertStringContainsString('target="_blank"', $this->render(['link_new_tab' => 'yes']));
    }

    // ── Seguridad ────────────────────────────────────────────────────────

    /** El título viene de la base de datos y se pinta escapado. */
    public function testEscapaElTituloDelInmueble(): void
    {
        $this->givenProperty();
        WpStubs::setPost(self::PROPERTY_ID, '<script>alert(1)</script>', 'https://ejemplo.test/inmueble/501');

        $html = $this->render();

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    // ── Sin resultados ───────────────────────────────────────────────────

    public function testSinInmueblesNoPintaNadaSiNoHayMensaje(): void
    {
        self::assertSame('', trim($this->render([], 0)));
    }

    public function testSinInmueblesPintaElMensajeConfigurado(): void
    {
        $html = $this->render(['empty_message' => 'No hay inmuebles publicados.'], 0);

        self::assertStringContainsString('No hay inmuebles publicados.', $html);
        self::assertStringNotContainsString('swiper-wrapper', $html);
    }
}
