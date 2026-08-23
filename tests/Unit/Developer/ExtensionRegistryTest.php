<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Developer;

use Homlity\Developer\Contracts\ExtensionInterface;
use Homlity\Developer\Extension\ExtensionRegistry;
use Homlity\Developer\Extension\Requirements;
use Homlity\Developer\Homlity;
use Homlity\Developer\Support\Hooks;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;
use RuntimeException;

/**
 * El registro de extensiones.
 *
 * Es la puerta por la que entra código de terceros al plugin. Un registro
 * permisivo de más deja que dos extensiones se pisen el slug o que una rota
 * tumbe el sitio entero; uno estricto de más deja fuera integraciones que
 * funcionaban. Las dos cosas se descubren en producción, no al programar.
 */
final class ExtensionRegistryTest extends TestCase
{
    private ExtensionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ExtensionRegistry();
        Homlity::setExtensionRegistry($this->registry);
    }

    protected function tearDown(): void
    {
        Homlity::setExtensionRegistry(null);
        parent::tearDown();
    }

    public function testUnaExtensionValidaSeRegistraYArranca(): void
    {
        $extension = new FakeExtension('mi-crm');

        self::assertTrue($this->registry->register($extension));

        $this->registry->bootAll();

        self::assertTrue($extension->booted, 'La extensión debía arrancar.');
        self::assertTrue($this->registry->has('mi-crm'));
        self::assertSame($extension, $this->registry->get('mi-crm'));
        self::assertSame(['mi-crm' => $extension], $this->registry->all());
    }

    public function testLaExtensionNoArrancaHastaQueSeDespachaElRegistro(): void
    {
        $extension = new FakeExtension('mi-crm');
        $this->registry->register($extension);

        self::assertFalse($extension->booted, 'Registrar no es arrancar.');
        self::assertFalse($this->registry->has('mi-crm'));

        $this->registry->bootAll();

        self::assertTrue($extension->booted);
    }

    public function testUnaExtensionRegistradaTardeArrancaEnElActo(): void
    {
        $this->registry->bootAll();

        $tardia = new FakeExtension('llego-tarde');
        self::assertTrue($this->registry->register($tardia));
        self::assertTrue($tardia->booted, 'Ya no habrá otro despacho: hay que arrancarla ahora.');
    }

    public function testDosExtensionesNoPuedenCompartirSlug(): void
    {
        $primera = new FakeExtension('mi-crm');
        $segunda = new FakeExtension('mi-crm');

        self::assertTrue($this->registry->register($primera));
        self::assertFalse($this->registry->register($segunda));

        $this->registry->bootAll();

        self::assertSame($primera, $this->registry->get('mi-crm'));
        self::assertFalse($segunda->booted, 'La segunda no debe arrancar con el slug ocupado.');
        self::assertArrayHasKey('mi-crm', $this->registry->failures());
    }

    public function testUnSlugVacioSeRechaza(): void
    {
        self::assertFalse($this->registry->register(new FakeExtension('')));
        self::assertNotSame([], $this->registry->failures());
    }

    public function testUnSlugConCaracteresProhibidosSeNormalizaAlRegistrarYAlConsultar(): void
    {
        // sanitize_key() deja 'micrm', no 'Mi CRM!'. Consultar por el slug
        // original tiene que seguir encontrándola.
        $extension = new FakeExtension('MiCRM');
        self::assertTrue($this->registry->register($extension));
        $this->registry->bootAll();

        self::assertTrue($this->registry->has('MiCRM'));
        self::assertSame($extension, $this->registry->get('micrm'));
    }

    public function testUnaExtensionIncompatibleNoArrancaYExplicaPorQue(): void
    {
        $extension = new FakeExtension('futura', Requirements::create(['php' => '99.0']));

        self::assertFalse($this->registry->register($extension));
        $this->registry->bootAll();

        self::assertFalse($extension->booted);
        self::assertArrayHasKey('futura', $this->registry->failures());
        self::assertStringContainsString('PHP', $this->registry->failures()['futura'][0]);
    }

    public function testElRechazoAvisaPorElHookPublico(): void
    {
        $this->registry->register(new FakeExtension('futura', Requirements::create(['php' => '99.0'])));

        self::assertArrayHasKey(Hooks::EXTENSION_FAILED, WpStubs::$actions);
    }

    public function testUnaExtensionQueRevientaAlArrancarNoTumbaElResto(): void
    {
        $rota = new ExplodingExtension();
        $sana = new FakeExtension('sana');

        $this->registry->register($rota);
        $this->registry->register($sana);
        $this->registry->bootAll();

        self::assertFalse($this->registry->has('rota'), 'Una extensión que lanzó no está registrada.');
        self::assertTrue($this->registry->has('sana'), 'La siguiente extensión sigue arrancando.');
        self::assertArrayHasKey('rota', $this->registry->failures());
    }

    public function testElFiltroDeCompatibilidadPuedeForzarUnaExtensionIncompatible(): void
    {
        add_filter(Hooks::FILTER_EXTENSION_IS_COMPATIBLE, static fn(): bool => true);

        $extension = new FakeExtension('futura', Requirements::create(['php' => '99.0']));

        self::assertTrue($this->registry->register($extension));
        $this->registry->bootAll();
        self::assertTrue($extension->booted);
    }

    public function testElFiltroDeCompatibilidadPuedeVetarUnaExtensionCompatible(): void
    {
        add_filter(Hooks::FILTER_EXTENSION_IS_COMPATIBLE, static fn(): bool => false);

        self::assertFalse($this->registry->register(new FakeExtension('vetada')));
    }

    public function testElArranqueSoloSeDespachaUnaVez(): void
    {
        $extension = new CountingExtension();
        $this->registry->register($extension);

        $this->registry->bootAll();
        $this->registry->bootAll();

        self::assertSame(1, $extension->boots);
    }

    public function testElHelperGlobalRegistraEnElMismoRegistro(): void
    {
        $extension = new FakeExtension('via-helper');

        self::assertTrue(homlity_register_extension($extension));
        self::assertSame($this->registry, homlity_extensions());

        $this->registry->bootAll();
        self::assertTrue($this->registry->has('via-helper'));
    }

    public function testCadaArranqueAnunciaLaExtensionPorElHookPublico(): void
    {
        $this->registry->register(new FakeExtension('anunciada'));
        $this->registry->bootAll();

        self::assertArrayHasKey(Hooks::EXTENSION_REGISTERED, WpStubs::$actions);
        self::assertSame('anunciada', WpStubs::$actions[Hooks::EXTENSION_REGISTERED][0][1]);
    }
}

/** Extensión mínima y obediente. */
final class FakeExtension implements ExtensionInterface
{
    public bool $booted = false;

    public function __construct(
        private string $slug,
        private ?Requirements $requirements = null
    ) {
    }

    public function getName(): string
    {
        return 'Extensión ' . $this->slug;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getRequirements(): Requirements
    {
        return $this->requirements ?? Requirements::none();
    }

    public function boot(): void
    {
        $this->booted = true;
    }
}

/** Extensión que lanza al arrancar. */
final class ExplodingExtension implements ExtensionInterface
{
    public function getName(): string
    {
        return 'Rota';
    }

    public function getSlug(): string
    {
        return 'rota';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getRequirements(): Requirements
    {
        return Requirements::none();
    }

    public function boot(): void
    {
        throw new RuntimeException('boom');
    }
}

/** Extensión que cuenta cuántas veces la arrancan. */
final class CountingExtension implements ExtensionInterface
{
    public int $boots = 0;

    public function getName(): string
    {
        return 'Contadora';
    }

    public function getSlug(): string
    {
        return 'contadora';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getRequirements(): Requirements
    {
        return Requirements::none();
    }

    public function boot(): void
    {
        $this->boots++;
    }
}
