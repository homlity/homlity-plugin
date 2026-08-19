<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\ErrorReporting;

use Homlity\PluginInmobiliario\ErrorReporting\ErrorEventQueue;
use Homlity\PluginInmobiliario\ErrorReporting\ErrorReporterService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Reproduce la incidencia de producción:
 *
 *   Exception: Scheduled action for hcap_update_maxmind_db will not be executed
 *   as no callbacks are registered.  →  reportada como homlity-simi@2.2.8
 *
 * Action Scheduler se distribuye dentro de vendor/ de los plugins de sincronización
 * Homlity y, cuando varias copias empatan en versión, la primera que carga es la
 * que ejecuta la cola de TODO el sitio. Como el queue runner re-lanza cualquier
 * Throwable dentro de una Exception creada en su propio archivo, la excepción que
 * llega a action_scheduler_failed_execution siempre apunta a nuestro vendor/,
 * aunque el hook pertenezca a un plugin de terceros.
 */
final class ActionSchedulerAttributionTest extends TestCase
{
    private ErrorEventQueue $queue;
    private ErrorReporterService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = dirname(__DIR__, 2) . '/Fixtures/plugins';
        require_once $fixtures . '/plugin-simi-sync/vendor/woocommerce/action-scheduler/classes/actions/ActionScheduler_Action.php';
        require_once $fixtures . '/plugin-simi-sync/vendor/woocommerce/action-scheduler/classes/abstracts/ActionScheduler_Abstract_QueueRunner.php';
        require_once $fixtures . '/plugin-simi-sync/src/Workers/PropertySyncWorker.php';
        require_once $fixtures . '/plugin-wasi-sync/src/Sync/PropertyWorker.php';

        $this->queue = new ErrorEventQueue();
        $this->service = new ErrorReporterService(null, null, $this->queue);
    }

    public function testAccionDeTerceroSinCallbackNoSeReportaComoFalloDeHomlity(): void
    {
        WpStubs::setScheduledAction(4211, 'hcap_update_maxmind_db', 'hcaptcha');

        $this->service->captureActionSchedulerFailure(4211, $this->orphanHookFailure('hcap_update_maxmind_db'), 'WP Cron');

        self::assertSame(
            [],
            $this->queued(),
            'Un hook de terceros huérfano no puede generar un evento atribuido a un plugin Homlity'
        );
    }

    public function testElFalloSeAtribuyeAlPropietarioDelHookNoAlPluginQueHospedaActionScheduler(): void
    {
        WpStubs::setScheduledAction(908, 'wasi_sync/run_provider', 'wasi-sync');

        $this->service->captureActionSchedulerFailure(908, $this->workerFailure('wasi'), 'Async Request');

        $event = $this->firstEvent();
        self::assertSame('homlity-wasi', $event['tags']['origin_plugin']);
        self::assertSame('homlity-wasi@1.9.0', $event['release']);
    }

    public function testElFalloRealDeUnaAccionPropiaSeSigueReportando(): void
    {
        WpStubs::setScheduledAction(4212, 'simi_sync/run_provider', 'simi-sync');

        $this->service->captureActionSchedulerFailure(4212, $this->workerFailure('simi'), 'WP Cron');

        $event = $this->firstEvent();
        self::assertSame('homlity-simi', $event['tags']['origin_plugin']);
        self::assertSame('homlity-simi@2.2.8', $event['release']);
        self::assertSame('action_scheduler', $event['tags']['operation']);
        self::assertSame(4212, $event['context']['action_id']);
    }

    public function testElEventoConservaLaExcepcionOriginalYNoLaDelQueueRunner(): void
    {
        WpStubs::setScheduledAction(4213, 'simi_sync/run_provider', 'simi-sync');

        $this->service->captureActionSchedulerFailure(4213, $this->workerFailure('simi'), 'WP Cron');

        $exception = $this->firstEvent()['exception'];
        self::assertSame('RuntimeException', $exception['type'], 'El tipo debe ser el de la causa raíz, no la Exception envolvente');
        self::assertStringContainsString('plugin-simi-sync/src/Workers/PropertySyncWorker.php', $exception['file']);
        self::assertStringContainsString('homlity_fixture_simi_worker_failure', $exception['stacktrace']);
    }

    public function testElFalloDeWorkerYaReportadoNoSeDuplicaAlLlegarEnvueltoPorActionScheduler(): void
    {
        WpStubs::setScheduledAction(4214, 'simi_sync/run_provider', 'simi-sync');
        $original = $this->workerException('simi');

        $this->service->captureWorkerFailure(['status' => 'dead', 'run_id' => 'r-1'], $original);
        $this->service->captureActionSchedulerFailure(4214, $this->wrap($original), 'WP Cron');

        self::assertCount(1, $this->queued(), 'El mismo fallo no debe reportarse dos veces');
    }

    public function testSinInformacionDeLaAccionSeMantieneLaAtribucionPorTraza(): void
    {
        // El store de Action Scheduler no resuelve la acción (id desconocido).
        $this->service->captureActionSchedulerFailure(99999, $this->workerFailure('simi'), 'WP Cron');

        self::assertSame('homlity-simi', $this->firstEvent()['tags']['origin_plugin']);
    }

    public function testSinInformacionDeLaAccionUnFalloDeTerceroSigueSinAtribuirse(): void
    {
        // Excepción de tercero: nace y muere fuera de código Homlity.
        $this->service->captureActionSchedulerFailure(99999, new \Exception('third party failure'), 'WP Cron');

        self::assertSame([], $this->queued());
    }

    public function testUnHookPropioAunNoDeclaradoNoPierdeObservabilidadSiLaCausaEsCodigoNuestro(): void
    {
        // Escenario de regresión: renombramos un hook y olvidamos declarar su
        // prefijo. El fallo debe seguir reportándose gracias a la causa raíz.
        WpStubs::setScheduledAction(5001, 'simi_sync_v3/run_provider', 'simi-sync-v3');

        $this->service->captureActionSchedulerFailure(5001, $this->workerFailure('simi'), 'WP Cron');

        self::assertSame('homlity-simi', $this->firstEvent()['tags']['origin_plugin']);
    }

    public function testElHookYElGrupoViajanEnElContextoDelEvento(): void
    {
        WpStubs::setScheduledAction(5002, 'simi_sync/cleanup', 'simi-sync');

        $this->service->captureActionSchedulerFailure(5002, $this->workerFailure('simi'), 'WP Cron');

        $context = $this->firstEvent()['context'];
        self::assertSame('simi_sync/cleanup', $context['hook']);
        self::assertSame('simi-sync', $context['action_group']);
    }

    /** Excepción tal y como la entrega Action Scheduler para un hook sin callbacks. */
    private function orphanHookFailure(string $hook): \Exception
    {
        return \homlity_fixture_action_scheduler_process_action(
            static fn () => \homlity_fixture_action_scheduler_execute($hook)
        );
    }

    /** Fallo real de un worker propio, envuelto por el queue runner. */
    private function workerFailure(string $plugin): \Exception
    {
        return $this->wrap($this->workerException($plugin));
    }

    private function workerException(string $plugin): \RuntimeException
    {
        try {
            $plugin === 'simi'
                ? \homlity_fixture_simi_worker_failure()
                : \homlity_fixture_wasi_worker_failure();
        } catch (\RuntimeException $error) {
            return $error;
        }

        throw new \LogicException('El fixture no lanzó la excepción esperada.');
    }

    private function wrap(\Throwable $error): \Exception
    {
        return \homlity_fixture_action_scheduler_process_action(static function () use ($error): void {
            throw $error;
        });
    }

    /** @return array<int,array<string,mixed>> */
    private function queued(): array
    {
        return (array) get_option(ErrorEventQueue::OPTION, []);
    }

    /** @return array<string,mixed> */
    private function firstEvent(): array
    {
        $queued = $this->queued();
        self::assertNotSame([], $queued, 'Se esperaba un evento encolado');

        return (array) $queued[0]['payload'];
    }
}
