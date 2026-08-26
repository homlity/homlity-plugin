<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\SimulatorService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class SimulatorServiceTest extends TestCase
{
    public function testRegistraElShortcodeHistoricoDeVisualInmueble(): void
    {
        $service = new SimulatorService();

        $service->registerShortcodes();

        self::assertArrayHasKey('visualinmu_simulador_shortcode', WpStubs::$shortcodes);
        self::assertSame(
            [$service, 'renderLegacyShortcode'],
            WpStubs::$shortcodes['visualinmu_simulador_shortcode']
        );
    }

    public function testElShortcodeHistoricoRenderizaArriendoYCargaSusRecursos(): void
    {
        $service = new SimulatorService();
        $service->registerShortcodes();

        $render = WpStubs::$shortcodes['visualinmu_simulador_shortcode'];
        $html = $render(['tipo' => '”arriendo”']);

        self::assertStringContainsString('homlity-simulator--arriendo', $html);
        self::assertStringContainsString('<codwelt-simulador', $html);
        self::assertStringContainsString('var mode = "arriendo";', $html);
        self::assertArrayHasKey(SimulatorService::SCRIPT_HANDLE, WpStubs::$registeredScripts);
        self::assertArrayHasKey(SimulatorService::STYLE_HANDLE, WpStubs::$registeredStyles);
        self::assertArrayHasKey(SimulatorService::SCRIPT_HANDLE, WpStubs::$enqueuedScripts);
        self::assertArrayHasKey(SimulatorService::STYLE_HANDLE, WpStubs::$enqueuedStyles);
        self::assertSame(
            HOMLITY_PLUGIN_URL . 'assets/js/simulator.js',
            WpStubs::$registeredScripts[SimulatorService::SCRIPT_HANDLE]['src']
        );
        self::assertSame(
            HOMLITY_PLUGIN_URL . 'assets/css/simulator.css',
            WpStubs::$registeredStyles[SimulatorService::STYLE_HANDLE]['src']
        );
    }

    public function testElShortcodeHistoricoRenderizaVenta(): void
    {
        $service = new SimulatorService();

        $html = $service->renderLegacyShortcode(['tipo' => '“venta”']);

        self::assertStringContainsString('homlity-simulator--venta', $html);
        self::assertStringContainsString('var mode = "venta";', $html);
    }

    /** @dataProvider modosHistoricos */
    public function testNormalizaLosModosDelShortcodeHistorico(string $input, string $expected): void
    {
        self::assertSame($expected, SimulatorService::normalizeMode($input));
    }

    /** @return iterable<string,array{string,string}> */
    public static function modosHistoricos(): iterable
    {
        yield 'arriendo normal' => ['arriendo', 'arriendo'];
        yield 'arriendo con comillas tipograficas' => ['”arriendo”', 'arriendo'];
        yield 'arrendamiento' => ['arrendamiento', 'arriendo'];
        yield 'venta normal' => ['venta', 'venta'];
        yield 'venta con comillas tipograficas' => ['“venta”', 'venta'];
        yield 'valor desconocido conserva el fallback de venta' => ['otro', 'venta'];
    }
}
