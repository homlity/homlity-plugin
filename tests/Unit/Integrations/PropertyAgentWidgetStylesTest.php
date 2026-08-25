<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations;

use Homlity\PluginInmobiliario\Integrations\Divi\Widgets\PropertyAgentWidget;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Estilos por botón del widget «Asesor del inmueble».
 *
 * Los dos CTA salían con la misma clase, así que cualquier control de estilo
 * pintaba los dos a la vez y no había forma de darle a uno un aspecto distinto
 * del otro. Ahora la plantilla marca cada botón por su posición y los controles
 * «Botón 1»/«Botón 2» se apoyan en esa marca.
 *
 * Se prueba la variante de Divi por el mismo motivo que
 * PropertyAgentsAvailableWidgetTest: su capa de compatibilidad es PHP corriente
 * y las tres copias del widget son el mismo fichero.
 */
final class PropertyAgentWidgetStylesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once HOMLITY_PLUGIN_PATH . 'src/Integrations/Divi/Compatibility/DiviWidgetApi.php';

        // Construir el widget registra sus controles, y el desplegable de asesores
        // consulta la base de datos con un REGEXP que el motor de pruebas no imita.
        // Aquí no importa qué asesores haya: lo que se comprueba son los estilos.
        WpStubs::$sqlResults['_property_agent_id'] = [];
    }

    /** @param array<string,mixed> $settings */
    private function render(array $settings = []): string
    {
        $widget = new PropertyAgentWidget();
        $widget->homlitySetSettings($settings);

        return $widget->homlityRender();
    }

    /** @return array<string,mixed> */
    private function controls(): array
    {
        return (new PropertyAgentWidget())->get_controls();
    }

    /** Dos botones propios, para no depender de que exista un inmueble. */
    private function dosBotones(): array
    {
        return [
            'data_source' => 'static',
            'static_name' => 'Elena Giraldo',
            'cta1_show'   => 'yes',
            'cta1_text'   => 'Escríbeme',
            'cta1_url'    => ['url' => 'https://inmobiliaria.test/contacto'],
            'cta2_show'   => 'yes',
            'cta2_text'   => 'Ver perfil',
            'cta2_url'    => ['url' => 'https://inmobiliaria.test/elena'],
        ];
    }

    // ── El marcado ────────────────────────────────────────────────────────

    public function testCadaBotonLlevaSuClasePosicional(): void
    {
        $html = $this->render($this->dosBotones());

        self::assertStringContainsString('property-agent-block__cta--1', $html);
        self::assertStringContainsString('property-agent-block__cta--2', $html);
    }

    public function testLaClasePosicionalSigueElOrdenEnQueSePintanLosBotones(): void
    {
        $html = $this->render($this->dosBotones());

        $primero = strpos($html, 'property-agent-block__cta--1');
        $segundo = strpos($html, 'property-agent-block__cta--2');

        self::assertIsInt($primero);
        self::assertIsInt($segundo);
        self::assertLessThan($segundo, $primero, 'El botón --1 debe ser el que se pinta antes');
        self::assertStringContainsString('Escríbeme', substr($html, $primero, $segundo - $primero));
    }

    public function testCadaBotonConservaSuClaseGeneral(): void
    {
        $html = $this->render($this->dosBotones());

        self::assertSame(
            2,
            substr_count($html, 'property-agent-block__cta '),
            'Los dos botones siguen compartiendo la clase base, que es la que estiliza la sección general'
        );
    }

    public function testUnSoloBotonNoGeneraElModificadorDelSegundo(): void
    {
        $settings = $this->dosBotones();
        unset($settings['cta2_show']);

        $html = $this->render($settings);

        self::assertStringContainsString('property-agent-block__cta--1', $html);
        self::assertStringNotContainsString('property-agent-block__cta--2', $html);
    }

    // ── Los controles ─────────────────────────────────────────────────────

    /**
     * @dataProvider controlesPorBotonProvider
     */
    public function testCadaBotonTieneSusPropiosControles(string $control): void
    {
        self::assertArrayHasKey($control, $this->controls());
    }

    /** @return iterable<string,array{string}> */
    public static function controlesPorBotonProvider(): iterable
    {
        foreach (['btn1', 'btn2'] as $prefix) {
            foreach (['width', 'typography', 'padding', 'radius', 'bg', 'color', 'bg_hover', 'color_hover', 'border'] as $suffix) {
                yield "{$prefix}_{$suffix}" => ["{$prefix}_{$suffix}"];
            }
        }
    }

    /**
     * Sin esto los controles de «Botón 1» y «Botón 2» empatarían en
     * especificidad con los de la sección general y ganaría el último que
     * Elementor imprimiera, que no está garantizado.
     */
    public function testLosControlesPorBotonGananPorEspecificidadALaSeccionGeneral(): void
    {
        $controls = $this->controls();

        $general = array_key_first($controls['btn_bg']['selectors']);
        self::assertSame(1, substr_count($general, 'property-agent-block__cta'));

        foreach (['btn1_bg', 'btn2_bg'] as $control) {
            $selector = array_key_first($controls[$control]['selectors']);
            self::assertSame(
                2,
                substr_count($selector, 'property-agent-block__cta'),
                "{$control} debe encadenar la clase base y su modificador"
            );
        }
    }

    public function testCadaBotonApuntaASuPropioModificador(): void
    {
        $controls = $this->controls();

        self::assertStringContainsString('cta--1', array_key_first($controls['btn1_bg']['selectors']));
        self::assertStringContainsString('cta--2', array_key_first($controls['btn2_bg']['selectors']));
    }

    /** Un botón sin ancho elegido no debe escribir regla: hereda el general. */
    public function testElAnchoPorBotonVieneVacioParaHeredarElGeneral(): void
    {
        $controls = $this->controls();

        foreach (['btn1_width', 'btn2_width'] as $control) {
            self::assertSame('', $controls[$control]['default']);
            self::assertArrayNotHasKey(
                '',
                $controls[$control]['selectors_dictionary'],
                'Un valor vacío no puede tener traducción, o se escribiría una regla igualmente'
            );
        }
    }

    /**
     * Antes «Ancho completo» ponía la fila en column. Eso apilaba los botones,
     * sí, pero dejaba el ancho fuera del alcance de un control por botón.
     */
    public function testElAnchoCompletoSeAplicaSobreElBotonNoSobreLaFila(): void
    {
        $control = $this->controls()['btn_width'];

        self::assertStringContainsString('property-agent-block__cta', array_key_first($control['selectors']));
        self::assertStringContainsString('width: 100%', $control['selectors_dictionary']['full']);
    }

    // ── Alineación ────────────────────────────────────────────────────────

    public function testSePuedeAlinearElNombre(): void
    {
        $control = $this->controls()['name_align'];

        self::assertArrayHasKey('center', $control['options']);
        self::assertStringContainsString(
            'text-align',
            $control['selectors']['{{WRAPPER}} .property-agent-block__name']
        );
    }

    public function testSePuedeAlinearLaFoto(): void
    {
        $control = $this->controls()['photo_align'];

        self::assertArrayHasKey('center', $control['options']);
        self::assertStringContainsString(
            'text-align',
            $control['selectors']['{{WRAPPER}} .property-agent-block__avatar']
        );
    }

    public function testSePuedeAlinearLaFilaDeBotones(): void
    {
        $control = $this->controls()['actions_align'];

        self::assertArrayHasKey('center', $control['options']);
        self::assertStringContainsString(
            'justify-content',
            $control['selectors']['{{WRAPPER}} .property-agent-block__actions']
        );
    }

    /**
     * Ni el nombre ni los botones traen alineación por defecto: si trajeran una,
     * las tarjetas ya publicadas cambiarían de aspecto al actualizar.
     */
    public function testLasAlineacionesNuevasNoTraenValorPorDefecto(): void
    {
        $controls = $this->controls();

        foreach (['name_align', 'actions_align'] as $control) {
            self::assertArrayNotHasKey('default', $controls[$control]);
        }
    }
}
