<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyDescription;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * El texto descriptivo de un inmueble.
 *
 * Lo usan la ficha técnica —en pantalla y en PDF— y los widgets de contenido y
 * de resumen. Está aparte de `the_content` a propósito: ese filtro devuelve la
 * página entera del constructor cuando Elementor está por medio.
 */
final class PropertyDescriptionTest extends TestCase
{
    private const POST_ID = 501;

    private function givenProperty(): int
    {
        WpStubs::$postObjects[self::POST_ID] = (object) [
            'ID' => self::POST_ID,
            'post_type' => 'property',
            'post_status' => 'publish',
        ];

        return self::POST_ID;
    }

    /**
     * Elementor engancha `the_content` y, cuando el post está montado con el
     * constructor, devuelve el documento completo se le pase lo que se le
     * pase. Por ahí se colaba la página entera en la descripción.
     */
    public function testNoPasaPorElContentYAsiNoArrastraLaPaginaDelConstructor(): void
    {
        $postId = $this->givenProperty();
        WpStubs::$postContent[$postId] = '<p>Apartamento con vista al valle.</p>';
        WpStubs::addFilter('the_content', static fn(): string => '<div class="elementor">PÁGINA ENTERA</div>');

        $description = PropertyDescription::text($postId);

        self::assertStringNotContainsString('PÁGINA ENTERA', $description);
        self::assertStringContainsString('Apartamento con vista al valle.', $description);
    }

    /**
     * Un inmueble montado con un constructor no guarda nada en el contenido:
     * el extracto es lo único que queda como descripción.
     */
    public function testSinContenidoSeUsaElExtracto(): void
    {
        $postId = $this->givenProperty();
        WpStubs::$postContent[$postId] = '';
        WpStubs::$postExcerpt[$postId] = 'Apartamento de 132 m² en El Poblado.';

        self::assertStringContainsString(
            'Apartamento de 132 m² en El Poblado.',
            PropertyDescription::text($postId)
        );
    }

    /** Y un contenido con solo etiquetas vacías tampoco cuenta como texto. */
    public function testUnContenidoVacioDeTextoCaeAlExtracto(): void
    {
        $postId = $this->givenProperty();
        WpStubs::$postContent[$postId] = '<p></p><div>  </div>';
        WpStubs::$postExcerpt[$postId] = 'Texto de reserva.';

        self::assertStringContainsString('Texto de reserva.', PropertyDescription::text($postId));
    }

    /**
     * Los shortcodes se quitan en lugar de ejecutarse: en un archivo no hay
     * nada que un formulario o una galería puedan hacer, y algunos sacan la
     * página entera igual que `the_content`.
     */
    public function testLosShortcodesSeQuitan(): void
    {
        $postId = $this->givenProperty();
        WpStubs::$postContent[$postId] = 'Antes [contact-form-7 id="9"] después.';

        $description = PropertyDescription::text($postId);

        self::assertStringNotContainsString('contact-form-7', $description);
        self::assertStringContainsString('Antes', $description);
        self::assertStringContainsString('después', $description);
    }

    /** Los párrafos del texto plano se conservan. */
    public function testElTextoPlanoConservaSusParrafos(): void
    {
        $postId = $this->givenProperty();
        WpStubs::$postContent[$postId] = "Primer párrafo.\n\nSegundo párrafo.";

        $description = PropertyDescription::text($postId);

        self::assertSame(2, substr_count($description, '<p>'), 'Los dos párrafos tienen que sobrevivir.');
    }

    /** Quien necesite otra cosa tiene el filtro. */
    public function testElFiltroPermiteSustituirLaDescripcion(): void
    {
        $postId = $this->givenProperty();
        WpStubs::$postContent[$postId] = 'Original.';
        WpStubs::addFilter(
            'homlity_property_description',
            static fn(string $text, int $id): string => 'Reemplazo para ' . $id
        );

        self::assertSame('Reemplazo para ' . $postId, PropertyDescription::text($postId));
    }

    public function testSinInmuebleNoHayDescripcion(): void
    {
        self::assertSame('', PropertyDescription::text(0));
    }
}
