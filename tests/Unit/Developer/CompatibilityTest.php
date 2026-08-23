<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Developer;

use Homlity\Developer\Api;
use Homlity\Developer\Extension\Requirements;
use Homlity\Developer\Homlity;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Las comprobaciones de compatibilidad que hace una extensión externa.
 *
 * Son lo primero que ejecuta cualquier plugin de terceros y lo único que hay
 * entre una instalación desactualizada y un fatal error en producción. Si
 * `isVersionSupported()` dice que sí cuando debía decir que no, el sitio del
 * cliente se cae al actualizar; si dice que no cuando debía decir que sí, la
 * integración desaparece sin explicación.
 */
final class CompatibilityTest extends TestCase
{
    public function testLaVersionDelPluginEsLaDeLaCabecera(): void
    {
        self::assertSame(HOMLITY_PLUGIN_VERSION, Api::pluginVersion());
        self::assertSame(HOMLITY_PLUGIN_VERSION, Homlity::version());
        self::assertSame(HOMLITY_PLUGIN_VERSION, homlity_version());
    }

    public function testLaVersionDeLaApiEsSemver(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Api::VERSION);
        self::assertSame(Api::VERSION, homlity_api_version());
        self::assertSame(Api::VERSION, HOMLITY_API_VERSION);
    }

    public function testElPluginSeAnunciaComoDisponible(): void
    {
        self::assertTrue(Api::isAvailable());
        self::assertTrue(homlity_is_available());
    }

    public function testUnaVersionMinimaAntiguaEstaSoportada(): void
    {
        self::assertTrue(Api::isVersionSupported('2.0.0'));
        self::assertTrue(homlity_is_version_supported('2.0.0'));
    }

    public function testLaVersionExactaEstaSoportada(): void
    {
        self::assertTrue(Api::isVersionSupported(HOMLITY_PLUGIN_VERSION));
    }

    public function testUnaVersionFuturaNoEstaSoportada(): void
    {
        self::assertFalse(Api::isVersionSupported('99.0.0'));
        self::assertFalse(homlity_is_version_supported('99.0.0'));
    }

    public function testUnaVersionMinimaVaciaNoEstaSoportada(): void
    {
        // Preguntar "¿soportas ''?" es un error del que pregunta; responder
        // que sí dejaría pasar una extensión que no declaró nada.
        self::assertFalse(Api::isVersionSupported(''));
    }

    public function testLaComparacionEsPorNumeroDeVersionNoAlfabetica(): void
    {
        // '2.10.0' es posterior a '2.9.0' aunque ordene antes como texto.
        self::assertTrue(version_compare('2.10.0', '2.9.0', '>='));
        self::assertFalse(Api::isApiVersionSupported('99.0.0'));
        self::assertTrue(Api::isApiVersionSupported('1.0.0'));
    }

    public function testUnRequisitoVacioLoCumpleCualquierInstalacion(): void
    {
        self::assertTrue(Requirements::none()->areSatisfied());
        self::assertSame([], Requirements::none()->unmetRequirements());
        self::assertTrue(Requirements::create([])->areSatisfied());
    }

    public function testUnRequisitoDePhpImposibleNoSeCumpleYSeExplica(): void
    {
        $requirements = Requirements::create(['php' => '99.0']);

        self::assertFalse($requirements->areSatisfied());
        self::assertCount(1, $requirements->unmetRequirements());
        self::assertStringContainsString('99.0', $requirements->unmetRequirements()[0]);
    }

    public function testUnRequisitoDeHomlityFuturoNoSeCumple(): void
    {
        self::assertFalse(Requirements::create(['homlity' => '99.0.0'])->areSatisfied());
        self::assertTrue(Requirements::create(['homlity' => '2.0.0'])->areSatisfied());
    }

    public function testUnRequisitoDeApiFuturoNoSeCumple(): void
    {
        self::assertFalse(Requirements::create(['api' => '99.0.0'])->areSatisfied());
        self::assertTrue(Requirements::create(['api' => '1.0.0'])->areSatisfied());
    }

    public function testUnaClaveDesconocidaSeIgnoraEnVezDeBloquear(): void
    {
        // Una extensión escrita para una versión posterior declara un requisito
        // que esta no entiende. Ignorarlo la deja arrancar; tratarlo como
        // incumplido la dejaría fuera sin motivo.
        self::assertTrue(Requirements::create(['galaxia' => '4'])->areSatisfied());
    }

    public function testUnPluginRequeridoQueNoEstaActivoSeReporta(): void
    {
        WpStubs::$options['active_plugins'] = ['otro/otro.php'];

        $requirements = Requirements::create(['plugins' => ['woocommerce/woocommerce.php']]);

        self::assertFalse($requirements->areSatisfied());
        self::assertStringContainsString('woocommerce/woocommerce.php', $requirements->unmetRequirements()[0]);
    }

    public function testUnPluginRequeridoActivoSeAcepta(): void
    {
        WpStubs::$options['active_plugins'] = ['woocommerce/woocommerce.php'];

        self::assertTrue(Requirements::create(['plugins' => ['woocommerce/woocommerce.php']])->areSatisfied());
    }

    public function testVariosIncumplimientosSeReportanTodos(): void
    {
        WpStubs::$options['active_plugins'] = [];

        $requirements = Requirements::create([
            'php'     => '99.0',
            'homlity' => '99.0.0',
            'plugins' => ['x/x.php'],
        ]);

        self::assertCount(3, $requirements->unmetRequirements());
    }

    public function testLosRequisitosDeclaradosSeLeenTalCualSeEscribieron(): void
    {
        $requirements = Requirements::create([
            'homlity'   => '2.8.0',
            'api'       => '1.0.0',
            'php'       => '8.1',
            'wordpress' => '6.4',
            'plugins'   => ['a/a.php', '  ', 'b/b.php'],
        ]);

        self::assertSame('2.8.0', $requirements->homlityVersion());
        self::assertSame('1.0.0', $requirements->apiVersion());
        self::assertSame('8.1', $requirements->phpVersion());
        self::assertSame('6.4', $requirements->wordPressVersion());
        self::assertSame(['a/a.php', 'b/b.php'], $requirements->plugins());
    }
}
