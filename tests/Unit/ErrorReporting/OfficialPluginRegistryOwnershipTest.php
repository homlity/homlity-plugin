<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\ErrorReporting;

use Homlity\PluginInmobiliario\ErrorReporting\OfficialPluginRegistry;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * La propiedad de un error se decide por quién declara el hook / grupo, no por
 * qué archivo lo lanzó: las librerías que empaquetamos (Action Scheduler) se
 * ejecutan en nombre de terceros.
 */
final class OfficialPluginRegistryOwnershipTest extends TestCase
{
    private OfficialPluginRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new OfficialPluginRegistry();
    }

    /** @dataProvider hooksPropios */
    public function testReconoceLosHooksDeCadaPluginOficial(string $hook, string $origin): void
    {
        self::assertSame($origin, $this->registry->originForHook($hook));
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function hooksPropios(): array
    {
        return [
            'simi'        => ['simi_sync/run_provider', 'homlity-simi'],
            'wasi'        => ['wasi_sync/license_heartbeat', 'homlity-wasi'],
            'softinm'     => ['softinm_sync/cleanup', 'homlity-softinm'],
            'sync'        => ['homlity_sync_run_incremental', 'homlity-sync'],
            'real estate' => ['homlity_plugin_crm_process_queue', 'homlity-real-estate'],
            'reporter'    => ['homlity_error_reporter_deliver', 'homlity-real-estate'],
        ];
    }

    /** @dataProvider hooksAjenos */
    public function testRechazaLosHooksDeTerceros(string $hook): void
    {
        self::assertNull($this->registry->originForHook($hook));
    }

    /** @return array<string,array{0:string}> */
    public static function hooksAjenos(): array
    {
        return [
            'hcaptcha'      => ['hcap_update_maxmind_db'],
            'woocommerce'   => ['woocommerce_cleanup_sessions'],
            'action sched.' => ['action_scheduler_run_queue'],
            'core'          => ['wp_version_check'],
            'vacío'         => [''],
        ];
    }

    public function testElPrefijoMasEspecificoDecideEntrePluginsPropios(): void
    {
        // 'homlity_sync_' es más específico que los prefijos de real-estate.
        self::assertSame('homlity-sync', $this->registry->originForHook('homlity_sync_validate_license'));
    }

    public function testResuelveTambienPorGrupoDeActionScheduler(): void
    {
        self::assertSame('homlity-simi', $this->registry->originForActionGroup('simi-sync'));
        self::assertSame('homlity-wasi', $this->registry->originForActionGroup('wasi-sync'));
        self::assertNull($this->registry->originForActionGroup('hcaptcha'));
        self::assertNull($this->registry->originForActionGroup(''));
    }

    public function testLaPropiedadEsExtensibleMedianteFiltro(): void
    {
        WpStubs::addFilter('homlity_error_reporter_official_plugins', static function (array $plugins): array {
            $plugins['homlity-simi']['hook_prefixes'][] = 'simi_legacy_';

            return $plugins;
        });

        self::assertSame('homlity-simi', $this->registry->originForHook('simi_legacy_import'));
    }

    public function testUnaLibreriaEmpaquetadaNoIdentificaAlCulpableCuandoSeExigeCodigoPropio(): void
    {
        $vendorFile = WP_PLUGIN_DIR . '/plugin-simi-sync/vendor/woocommerce/action-scheduler/classes/actions/ActionScheduler_Action.php';

        // Por compatibilidad, la atribución por archivo sigue viendo el plugin…
        self::assertSame('homlity-simi', $this->registry->originForFile($vendorFile));
        // …pero no cuando se exige código propio (fallos de acciones programadas).
        self::assertNull($this->registry->originForFile($vendorFile, true));
    }

    public function testElCodigoPropioFueraDeVendorSiIdentificaAlPlugin(): void
    {
        $ownFile = WP_PLUGIN_DIR . '/plugin-simi-sync/src/Workers/PropertySyncWorker.php';

        self::assertSame('homlity-simi', $this->registry->originForFile($ownFile, true));
    }

    public function testRootCauseDevuelveLaExcepcionOriginalDeLaCadena(): void
    {
        $root = new \RuntimeException('causa real');
        $wrapped = new \Exception('mensaje copiado', 0, new \LogicException('intermedia', 0, $root));

        self::assertSame($root, $this->registry->rootCause($wrapped));
        self::assertSame($root, $this->registry->rootCause($root));
    }

    public function testCadaPluginOficialDeclaraSuPropiedadDeHooks(): void
    {
        foreach ($this->registry->definitions() as $canonical => $definition) {
            self::assertNotEmpty(
                $definition['hook_prefixes'] ?? [],
                "El plugin {$canonical} no declara hook_prefixes: sus crons no serían atribuibles"
            );
        }
    }
}
