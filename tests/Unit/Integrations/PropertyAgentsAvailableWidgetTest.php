<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations;

use Homlity\PluginInmobiliario\Integrations\Divi\Widgets\PropertyAgentsAvailableWidget;
use Homlity\PluginInmobiliario\Services\AgentProfileService;
use Homlity\PluginInmobiliario\Services\CapabilityService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Lo que pinta el widget «Asesores con inmuebles disponibles».
 *
 * Se prueba la variante de Divi porque es la única de las tres que se puede
 * instanciar aquí: su capa de compatibilidad es PHP corriente, mientras que la
 * de Elementor hereda de una clase que solo existe con Elementor instalado.
 * Las tres pintan el mismo marcado —el mismo fichero copiado tres veces—, así
 * que lo de abajo vale para las tres; lo que no se puede comprobar por esta
 * vía se comprueba leyendo el código, al final.
 */
final class PropertyAgentsAvailableWidgetTest extends TestCase
{
    private const CONSULTA = '_property_agent_id';

    protected function setUp(): void
    {
        parent::setUp();

        require_once HOMLITY_PLUGIN_PATH . 'src/Integrations/Divi/Compatibility/DiviWidgetApi.php';
    }

    /** @param array<int,int> $counts */
    private function givenCounts(array $counts): void
    {
        $rows = [];
        foreach ($counts as $agentId => $total) {
            $rows[] = ['agent_id' => (string) $agentId, 'total' => (string) $total];
        }

        WpStubs::$sqlResults[self::CONSULTA] = $rows;
    }

    /** @param array<string,mixed> $meta */
    private function givenAgent(int $id, string $name, array $meta = []): void
    {
        WpStubs::setUser(
            $id,
            'asesor-' . $id,
            ['display_name' => $name, 'user_email' => 'asesor' . $id . '@inmobiliaria.test'],
            [CapabilityService::ROLE_ASSESSOR],
            $meta
        );
    }

    /** @param array<string,mixed> $settings */
    private function render(array $settings = []): string
    {
        $widget = new PropertyAgentsAvailableWidget();
        $widget->homlitySetSettings($settings);

        return $widget->homlityRender();
    }

    // ── La foto ───────────────────────────────────────────────────────────

    /**
     * El fallo: el widget llamaba a get_avatar() a pelo, que solo sabe de
     * gravatar. Los asesores que llegan del CRM traen su foto en
     * `_homlity_advisor_photo` y salían todos con la silueta gris, aunque el
     * resto del sitio —la ficha del inmueble, el perfil— sí la enseñaba.
     */
    public function testLaFotoDelCrmEsLaQueSePinta(): void
    {
        $this->givenCounts([7 => 3]);
        $this->givenAgent(7, 'Elena Giraldo', [
            '_homlity_advisor_photo' => 'https://cdn.crm.test/asesores/elena.jpg',
        ]);

        $html = $this->render();

        self::assertStringContainsString('https://cdn.crm.test/asesores/elena.jpg', $html);
        self::assertStringNotContainsString('gravatar.test', $html);
    }

    /** Sin foto del CRM se sigue bajando por la cadena de siempre. */
    public function testSinFotoDelCrmSeUsaElAvatarDeWordpress(): void
    {
        $this->givenCounts([7 => 3]);
        $this->givenAgent(7, 'Elena Giraldo');

        self::assertStringContainsString('gravatar.test/7', $this->render());
    }

    public function testLaFotoVaDentroDeSuContenedor(): void
    {
        $this->givenCounts([7 => 3]);
        $this->givenAgent(7, 'Elena Giraldo', [
            '_homlity_advisor_photo' => 'https://cdn.crm.test/asesores/elena.jpg',
        ]);

        self::assertMatchesRegularExpression(
            '#<div class="hml-agents-available__avatar"><img [^>]*src="https://cdn\.crm\.test/asesores/elena\.jpg"#',
            $this->render()
        );
    }

    // ── El teléfono ───────────────────────────────────────────────────────

    /**
     * El mismo fallo que la foto: el widget miraba solo `phone` y
     * `billing_phone`, y el teléfono que sincroniza el CRM se guarda en
     * `_homlity_advisor_phone`.
     */
    public function testElTelefonoDelCrmEsElQueSePinta(): void
    {
        $this->givenCounts([7 => 3]);
        $this->givenAgent(7, 'Elena Giraldo', [
            '_homlity_advisor_phone' => '+57 300 123 4567',
            'phone' => '',
        ]);

        $html = $this->render();

        self::assertStringContainsString('+57 300 123 4567', $html);
        self::assertStringContainsString('href="tel:573001234567"', $html);
    }

    public function testSinTelefonoNoSePintaLaFila(): void
    {
        $this->givenCounts([7 => 3]);
        $this->givenAgent(7, 'Elena Giraldo');

        self::assertStringNotContainsString('href="tel:', $this->render());
    }

    public function testElTelefonoSePuedeApagarDesdeElWidget(): void
    {
        $this->givenCounts([7 => 3]);
        $this->givenAgent(7, 'Elena Giraldo', ['_homlity_advisor_phone' => '3001234567']);

        self::assertStringNotContainsString('href="tel:', $this->render(['show_phone' => 'no']));
    }

    // ── Quién sale ────────────────────────────────────────────────────────

    public function testUnAsesorApagadoNoSePinta(): void
    {
        $this->givenCounts([7 => 3, 8 => 1]);
        $this->givenAgent(7, 'Elena Giraldo', [AgentProfileService::PUBLIC_META => '0']);
        $this->givenAgent(8, 'Marta Ruiz');

        $html = $this->render();

        self::assertStringNotContainsString('Elena Giraldo', $html);
        self::assertStringContainsString('Marta Ruiz', $html);
    }

    /** Sin nadie a quien enseñar el widget no deja una rejilla vacía. */
    public function testSinAsesoresNoSePintaNada(): void
    {
        $this->givenCounts([7 => 3]);
        $this->givenAgent(7, 'Elena Giraldo', [AgentProfileService::PUBLIC_META => '0']);

        self::assertSame('', $this->render());
    }

    // ── El resto de la tarjeta ────────────────────────────────────────────

    public function testElRecuentoSeConcuerdaEnSingularYPlural(): void
    {
        $this->givenCounts([7 => 1, 8 => 4]);
        $this->givenAgent(7, 'Elena Giraldo');
        $this->givenAgent(8, 'Marta Ruiz');

        $html = $this->render();

        self::assertStringContainsString('1 inmueble disponible', $html);
        self::assertStringContainsString('4 inmuebles disponibles', $html);
    }

    public function testLaTarjetaLlevaLasClasesQueElCssEstiliza(): void
    {
        $this->givenCounts([7 => 3]);
        $this->givenAgent(7, 'Elena Giraldo');

        $html = $this->render();

        // La raíz se comprueba con el atributo entero: como cadena suelta es
        // prefijo de todas las demás y la comprobación pasaría sin ella.
        self::assertStringContainsString('class="hml-agents-available"', $html);

        foreach ([
            'hml-agents-available__card',
            'hml-agents-available__avatar',
            'hml-agents-available__name',
            'hml-agents-available__count',
            'hml-agents-available__meta',
            'hml-agents-available__cta',
        ] as $clase) {
            self::assertStringContainsString($clase, $html, "Falta la clase {$clase}.");
        }
    }

    public function testElCorreoSalePorDefectoYSePuedeApagar(): void
    {
        $this->givenCounts([7 => 3]);
        $this->givenAgent(7, 'Elena Giraldo');

        self::assertStringContainsString('mailto:asesor7@inmobiliaria.test', $this->render());
        self::assertStringNotContainsString('mailto:', $this->render(['show_email' => 'no']));
    }

    public function testElBotonDePerfilSePuedeApagar(): void
    {
        $this->givenCounts([7 => 3]);
        $this->givenAgent(7, 'Elena Giraldo');

        self::assertStringContainsString('hml-agents-available__cta', $this->render());
        self::assertStringNotContainsString(
            'hml-agents-available__cta',
            $this->render(['show_profile_button' => 'no'])
        );
    }

    /** El nombre lo escribe quien administra el sitio, no el plugin. */
    public function testElNombreDelAsesorSeEscapa(): void
    {
        $this->givenCounts([7 => 3]);
        $this->givenAgent(7, '<script>alert(1)</script>');

        $html = $this->render();

        self::assertStringNotContainsString('<script>alert(1)', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    // ── Lo que no se puede instanciar aquí ────────────────────────────────

    /**
     * El widget de Elementor salía sin estilos en la web pública y con ellos
     * en el editor: enqueuePreviewAssets() mete las hojas dentro del iframe de
     * previsualización, pero fuera de ahí Elementor solo carga lo que cada
     * widget declara, y este no declaraba nada. Se maquetaba mientras se
     * editaba y se deshacía al publicar.
     *
     * No hay forma de instanciarlo sin Elementor delante, así que esto lee el
     * código. Es poco, pero es lo que separa el fallo de volver.
     */
    public function testElWidgetDeElementorPideLaHojaDeListados(): void
    {
        $fuente = (string) file_get_contents(
            HOMLITY_PLUGIN_PATH . 'src/Integrations/Elementor/Widgets/PropertyAgentsAvailableWidget.php'
        );

        self::assertStringContainsString('public function get_style_depends(): array', $fuente);
        self::assertStringContainsString('assets/css/property-listing.css', $fuente);
        self::assertStringContainsString('return [self::LISTING_STYLE_HANDLE];', $fuente);
    }

    /**
     * El CSS que da forma a la tarjeta tiene que estar en la hoja que el
     * widget pide. Si alguien lo mueve a otra, el widget vuelve a salir
     * desnudo y nada más se entera.
     */
    public function testElCssDeLaTarjetaViveEnLaHojaQueElWidgetPide(): void
    {
        $css = (string) file_get_contents(HOMLITY_PLUGIN_PATH . 'assets/css/property-listing.css');

        self::assertStringContainsString('.hml-agents-available__card', $css);
        self::assertStringContainsString('.hml-agents-available__cta', $css);
    }

    /** Las tres variantes pintan lo mismo; el arreglo tiene que estar en las tres. */
    public function testLasTresVariantesUsanLaCadenaDeFotoDelPlugin(): void
    {
        foreach (['Elementor', 'Divi', 'WPBakery'] as $constructor) {
            $fuente = (string) file_get_contents(
                HOMLITY_PLUGIN_PATH . "src/Integrations/{$constructor}/Widgets/PropertyAgentsAvailableWidget.php"
            );

            self::assertStringContainsString(
                'AgentProfileService::avatarHtml($user, 96)',
                $fuente,
                "La variante de {$constructor} no usa la cadena de foto del plugin."
            );
            self::assertStringNotContainsString('get_avatar($user->ID', $fuente);
            self::assertStringContainsString('AvailableAgentsService::agents(', $fuente);
        }
    }
}
