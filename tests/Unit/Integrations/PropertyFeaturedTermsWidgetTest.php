<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations;

use Homlity\PluginInmobiliario\Integrations\Divi\Widgets\PropertyFeaturedTermsWidget;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * El widget «Destacados por ubicación y tipo».
 *
 * Tenía el mismo fallo que el de asesores: todo su CSS vive en
 * property-listing.css y el widget de Elementor no la pedía, así que se
 * maquetaba en el editor —donde el plugin mete las hojas en el iframe de
 * previsualización— y se deshacía al publicar. Sin la hoja los grupos se
 * apilan en vez de repartirse en columnas y las listas salen con las viñetas
 * del navegador.
 *
 * Se renderiza la variante de Divi, la única de las tres instanciable aquí; la
 * de Elementor hereda de una clase que solo existe con Elementor instalado y
 * se comprueba leyendo el código.
 */
final class PropertyFeaturedTermsWidgetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once HOMLITY_PLUGIN_PATH . 'src/Integrations/Divi/Compatibility/DiviWidgetApi.php';
    }

    /** @param array<int,array{0:string,1:string,2:int}> $terms */
    private function givenTerms(string $taxonomy, array $terms): void
    {
        // La taxonomía va interpolada en la sentencia ya preparada, así que
        // sirve para distinguir la consulta de cada grupo.
        WpStubs::$sqlResults["tt.taxonomy = '{$taxonomy}'"] = array_map(
            static fn(array $term): array => [
                'name' => $term[0],
                'slug' => $term[1],
                'total' => (string) $term[2],
            ],
            $terms
        );
    }

    /** @param array<string,mixed> $settings */
    private function render(array $settings = []): string
    {
        $widget = new PropertyFeaturedTermsWidget();
        $widget->homlitySetSettings(array_merge([
            'show_city' => 'yes',
            'show_neighborhood' => '',
            'show_operation' => '',
            'show_type' => '',
        ], $settings));

        return $widget->homlityRender();
    }

    // ── Lo que la hoja de estilos necesita encontrar ──────────────────────

    public function testElMarcadoLlevaLasClasesQueElCssEstiliza(): void
    {
        $this->givenTerms(PropertyTaxonomies::TAXONOMY_CITY, [['Medellín', 'medellin', 42]]);

        $html = $this->render();

        // La raíz se comprueba con el atributo entero: como cadena suelta es
        // prefijo de todas las demás y la comprobación pasaría sin ella.
        self::assertStringContainsString('class="hml-featured-terms"', $html);

        foreach ([
            'hml-featured-terms__group',
            'hml-featured-terms__title',
            'hml-featured-terms__list',
            'hml-featured-terms__item',
            'hml-featured-terms__link',
            'hml-featured-terms__count',
        ] as $clase) {
            self::assertStringContainsString($clase, $html, "Falta la clase {$clase}.");
        }
    }

    /**
     * A diferencia del widget por taxonomía, este no lleva la maquetación en
     * atributos `style`: si la hoja no llega, no hay nada que lo sostenga.
     * Es lo que hace que declararla sea obligatorio y no un adorno.
     */
    public function testElMarcadoNoLlevaMaquetacionEnLinea(): void
    {
        $this->givenTerms(PropertyTaxonomies::TAXONOMY_CITY, [['Medellín', 'medellin', 42]]);

        self::assertStringNotContainsString('style=', $this->render());
    }

    public function testCadaGrupoTraeSuTituloYSusTerminos(): void
    {
        $this->givenTerms(PropertyTaxonomies::TAXONOMY_CITY, [
            ['Medellín', 'medellin', 42],
            ['Envigado', 'envigado', 7],
        ]);

        $html = $this->render(['title_city' => 'Ciudades destacadas']);

        self::assertStringContainsString('Ciudades destacadas', $html);
        self::assertStringContainsString('/inmuebles/ciudad/medellin/', $html);
        self::assertStringContainsString('(42)', $html);
        self::assertStringContainsString('Envigado', $html);
    }

    /** Sin términos no se pinta una rejilla vacía. */
    public function testSinTerminosNoSePintaElGrupo(): void
    {
        $this->givenTerms(PropertyTaxonomies::TAXONOMY_CITY, []);

        self::assertStringNotContainsString('hml-featured-terms__group', $this->render());
    }

    // ── La hoja ───────────────────────────────────────────────────────────

    /**
     * No hay forma de instanciar el widget de Elementor sin Elementor, así que
     * esto lee el código. Es poco, pero es lo que separa el fallo de volver.
     */
    public function testElWidgetDeElementorPideLaHojaDeListados(): void
    {
        $fuente = (string) file_get_contents(
            HOMLITY_PLUGIN_PATH . 'src/Integrations/Elementor/Widgets/PropertyFeaturedTermsWidget.php'
        );

        self::assertStringContainsString('public function get_style_depends(): array', $fuente);
        self::assertStringContainsString('assets/css/property-listing.css', $fuente);
        self::assertStringContainsString('return [self::LISTING_STYLE_HANDLE];', $fuente);
    }

    public function testElCssDelWidgetViveEnLaHojaQuePide(): void
    {
        $css = (string) file_get_contents(HOMLITY_PLUGIN_PATH . 'assets/css/property-listing.css');

        self::assertStringContainsString('.hml-featured-terms {', $css);
        self::assertStringContainsString('.hml-featured-terms__list {', $css);
    }

    /**
     * El widget por taxonomía —«Ciudades destacadas» y sus hermanos— pinta
     * otras clases, que no tienen hoja ninguna: lleva su maquetación en
     * atributos `style` a propósito. Esto lo deja escrito para que nadie le
     * añada una dependencia que no necesita, ni le quite los `style` creyendo
     * que hay una hoja detrás.
     */
    public function testElWidgetPorTaxonomiaSeMaquetaSolo(): void
    {
        $fuente = (string) file_get_contents(
            HOMLITY_PLUGIN_PATH . 'src/Integrations/Elementor/Widgets/PropertyFeaturedTermsBaseWidget.php'
        );
        $css = (string) file_get_contents(HOMLITY_PLUGIN_PATH . 'assets/css/property-listing.css');

        self::assertStringContainsString('hml-featured-terms-widget', $fuente);
        self::assertStringNotContainsString('hml-featured-terms-widget', $css);
        self::assertStringContainsString('list-style:none', $fuente);
    }
}
