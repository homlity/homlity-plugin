<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Developer;

use Homlity\Developer\Events\PropertyContext;
use Homlity\Developer\Extension\ExtensionRegistry;
use Homlity\Developer\Homlity;
use Homlity\PluginInmobiliario\Core\PropertyEventDispatcher;
use Homlity\PluginInmobiliario\Integrations\CRM\PropertyUpsertService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * La extensión de ejemplo que se publica en la documentación.
 *
 * Un ejemplo que no compila es peor que ninguno: el desarrollador que lo copia
 * pierde la tarde averiguando que el error no era suyo. Esta prueba arranca la
 * extensión tal cual está en docs/examples/ contra la Developer API real, para
 * que la documentación no pueda quedarse atrás del código.
 */
final class ExampleExtensionTest extends TestCase
{
    private ExtensionRegistry $registry;

    public static function setUpBeforeClass(): void
    {
        if (!defined('HOMLITY_EXAMPLE_VERSION')) {
            define('HOMLITY_EXAMPLE_VERSION', '1.0.0');
        }
        if (!defined('HOMLITY_EXAMPLE_REQUIRES_HOMLITY')) {
            define('HOMLITY_EXAMPLE_REQUIRES_HOMLITY', '2.8.0');
        }

        require_once dirname(__DIR__, 3)
            . '/docs/examples/basic-extension/homlity-example-extension/src/Plugin.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        PropertyEventDispatcher::reset();
        Homlity::setPropertyRepository(null);

        $this->registry = new ExtensionRegistry();
        Homlity::setExtensionRegistry($this->registry);

        WpStubs::$registeredTaxonomies = [
            PropertyTaxonomies::TAXONOMY_OPERATION,
            PropertyTaxonomies::TAXONOMY_TYPE,
            PropertyTaxonomies::TAXONOMY_LOCATION,
        ];
    }

    protected function tearDown(): void
    {
        Homlity::setExtensionRegistry(null);
        PropertyEventDispatcher::reset();
        parent::tearDown();
    }

    private function bootExample(): \HomlityExample\Plugin
    {
        $extension = new \HomlityExample\Plugin();

        self::assertTrue(
            $this->registry->register($extension),
            'La extensión de ejemplo debe cumplir sus propios requisitos: '
                . implode(' ', $this->registry->failures()['homlity-example'] ?? [])
        );

        $this->registry->bootAll();

        return $extension;
    }

    /** @return array<int,array<string,mixed>> */
    private function log(): array
    {
        return (array) (WpStubs::$options['homlity_example_synced_log'] ?? []);
    }

    /** @param array<string,mixed> $overrides */
    private function normalized(array $overrides = []): array
    {
        return array_replace_recursive([
            'external' => ['source' => 'wasi', 'id' => 'EXT-77'],
            'post'     => ['title' => 'Apartamento en El Poblado'],
        ], $overrides);
    }

    public function testLaExtensionDeEjemploSeRegistraYArranca(): void
    {
        $extension = $this->bootExample();

        self::assertSame('homlity-example', $extension->getSlug());
        self::assertTrue($this->registry->has('homlity-example'));
        self::assertSame([], $this->registry->failures());
    }

    public function testAnotaLosInmueblesNuevos(): void
    {
        $this->bootExample();

        (new PropertyUpsertService())->upsert($this->normalized([
            'metrics' => ['code' => 'VTAP132'],
            'pricing' => ['sale_price' => '450000000', 'sale_currency' => 'COP'],
        ]));

        $log = $this->log();

        self::assertCount(1, $log);
        self::assertSame('created', $log[0]['event']);
        self::assertSame('VTAP132', $log[0]['code']);
        self::assertSame(450000000.0, $log[0]['price']);
        self::assertSame('crm', $log[0]['origin']);
        self::assertSame('wasi', $log[0]['source']);
    }

    public function testAnotaUnCambioDePrecioPeroNoUnoDeDescripcion(): void
    {
        $this->bootExample();
        $upsert = new PropertyUpsertService();

        $upsert->upsert($this->normalized(['pricing' => ['sale_price' => '450000000']]));
        WpStubs::$options['homlity_example_synced_log'] = [];

        // Sólo cambia la descripción: no justifica republicar en un portal.
        $upsert->upsert($this->normalized([
            'pricing' => ['sale_price' => '450000000'],
            'post'    => ['description' => 'Otra descripción'],
        ]), PropertyContext::ORIGIN_ADMIN);
        self::assertSame([], $this->log());

        // Cambia el precio: eso sí.
        $upsert->upsert($this->normalized([
            'pricing' => ['sale_price' => '400000000'],
        ]), PropertyContext::ORIGIN_ADMIN);

        $log = $this->log();
        self::assertCount(1, $log);
        self::assertSame('updated', $log[0]['event']);
        self::assertContains('pricing.sale_price', $log[0]['changed']);
    }

    public function testNoLeDevuelveAlCrmLoQueElMismoAcabaDeMandar(): void
    {
        $this->bootExample();
        $upsert = new PropertyUpsertService();

        $base = ['external' => ['source' => 'mi-crm', 'id' => 'EXT-9'], 'post' => ['title' => 'Casa']];

        $upsert->upsert(array_replace_recursive($base, ['pricing' => ['sale_price' => '100']]));
        WpStubs::$options['homlity_example_synced_log'] = [];

        $upsert->upsert(array_replace_recursive($base, ['pricing' => ['sale_price' => '200']]));

        self::assertSame([], $this->log(), 'Reenviar al CRM su propio cambio sería un bucle.');
    }

    public function testElFiltroMarcaLosInmueblesImportados(): void
    {
        $this->bootExample();

        $result = (new PropertyUpsertService())->upsert($this->normalized());

        $payload = json_decode(
            (string) get_post_meta((int) $result['post_id'], '_property_sync_payload', true),
            true
        );

        self::assertIsArray($payload);
        self::assertSame('homlity-example', $payload['imported_by']);
        self::assertNotEmpty($payload['imported_at']);
    }

    public function testElRegistroNoCreceSinLimite(): void
    {
        $this->bootExample();

        WpStubs::$options['homlity_example_synced_log'] = array_fill(0, 60, ['event' => 'old']);

        (new PropertyUpsertService())->upsert($this->normalized());

        self::assertCount(50, $this->log(), 'Un log sin tope acaba llenando la tabla de opciones.');
    }
}
