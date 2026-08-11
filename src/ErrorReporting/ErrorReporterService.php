<?php
/**
 * Central coordinator for capture, queueing, delivery and diagnostics.
 */

namespace Homlity\PluginInmobiliario\ErrorReporting;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class ErrorReporterService implements ServiceInterface
{
    public const CRON_HOOK = 'homlity_error_reporter_deliver';

    private OfficialPluginRegistry $registry;
    private ErrorEventFactory $factory;
    private ErrorEventQueue $queue;
    private HomiErrorTransport $transport;
    private bool $capturing = false;
    /** @var array<int, bool> */
    private array $observedWorkerFailures = [];

    public function __construct(
        ?OfficialPluginRegistry $registry = null,
        ?ErrorEventFactory $factory = null,
        ?ErrorEventQueue $queue = null,
        ?HomiErrorTransport $transport = null
    ) {
        $this->registry = $registry ?: new OfficialPluginRegistry();
        $this->factory = $factory ?: new ErrorEventFactory($this->registry);
        $this->queue = $queue ?: new ErrorEventQueue();
        $this->transport = $transport ?: new HomiErrorTransport($this->registry);
    }

    public function register(): void
    {
        \Homlity_Error_Reporter::set_fatal_callback([$this, 'captureFatal']);
        add_action('homlity_report_sync_error', [$this, 'captureSyncError'], 10, 3);
        add_action('homlity_sync_job_failed', [$this, 'captureWorkerFailure'], 10, 2);
        add_action('homlity_sync_retry_scheduled', [$this, 'suppressRetryThrowable'], 10, 1);
        add_action('homlity_consignacion_publish_failed', [$this, 'captureConsignmentFailure'], 10, 2);
        add_action('action_scheduler_failed_execution', [$this, 'captureActionSchedulerFailure'], 10, 3);
        add_action(self::CRON_HOOK, [$this, 'deliverQueued']);
        add_action('admin_init', [$this, 'maybeDeliverFromAdmin']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_filter('cron_schedules', [$this, 'registerSchedule']);
        add_action('init', [$this, 'ensureSchedule']);
        $this->drainEarlyFatals();
    }

    /** @param array<string, mixed> $error */
    public function captureFatal(array $error): void
    {
        if ($this->capturing) {
            return;
        }
        $this->capturing = true;
        try {
            $event = $this->factory->fromFatal($error);
            if ($event === null || !$this->queue->enqueue($event)) {
                return;
            }
            // Shutdown has little time left: one short attempt, then the persistent queue remains.
            $this->deliverQueued(1, 2, (string) $event['event_id']);
        } catch (\Throwable $ignored) {
            // A reporter failure must never turn into another application failure.
        } finally {
            $this->capturing = false;
        }
    }

    /** @param array<string, mixed> $context */
    public function captureSyncError(string $plugin, $error, array $context = []): void
    {
        if ($this->capturing) {
            return;
        }
        $this->capturing = true;
        try {
            $event = $this->factory->fromSync($plugin, $error, $context);
            if ($event !== null) {
                $this->queue->enqueue($event);
            }
        } catch (\Throwable $ignored) {
        } finally {
            $this->capturing = false;
        }
    }

    /** @param mixed $payload */
    public function captureWorkerFailure($payload, \Throwable $error): void
    {
        $origin = $this->registry->originForThrowable($error);
        if ($origin === null) {
            return;
        }
        // SIMI and Softinm rethrow the same exception to Action Scheduler; WASI handles it locally.
        if ($origin !== 'homlity-wasi') {
            $this->observedWorkerFailures[spl_object_id($error)] = true;
        }
        $context = is_array($payload) ? $payload : [];
        if (!in_array(sanitize_key((string) ($context['status'] ?? '')), ['dead', 'permanent', 'final_failed'], true)) {
            return;
        }
        $context['operation'] = $context['operation'] ?? 'property_sync_job';
        $context['status'] = 'failed';
        $this->captureSyncError($origin, $error, $context);
    }

    public function suppressRetryThrowable(\Throwable $error): void
    {
        $this->observedWorkerFailures[spl_object_id($error)] = true;
    }

    public function captureConsignmentFailure($postId, $error): void
    {
        $this->captureSyncError('homlity-sync', $error, [
            'operation' => 'consignment_publish',
            'property_id' => absint($postId),
            'status' => 'failed',
        ]);
    }

    /** @param mixed $context */
    public function captureActionSchedulerFailure($actionId, \Throwable $error, $context = null): void
    {
        $objectId = spl_object_id($error);
        if (isset($this->observedWorkerFailures[$objectId])) {
            unset($this->observedWorkerFailures[$objectId]);
            return;
        }
        $origin = $this->registry->originForThrowable($error);
        if ($origin === null) {
            return;
        }
        $this->captureSyncError($origin, $error, [
            'operation' => 'action_scheduler',
            'action_id' => absint($actionId),
            'status' => 'failed',
            'execution' => is_scalar($context) ? (string) $context : '',
        ]);
    }

    /** @return array<string, array<string, int>> */
    public function registerSchedule(array $schedules): array
    {
        $schedules['homlity_error_reporter_five_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Cada cinco minutos (errores Homlity)', 'homlity-real-estate'),
        ];
        return $schedules;
    }

    public function ensureSchedule(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'homlity_error_reporter_five_minutes', self::CRON_HOOK);
        }
    }

    public function maybeDeliverFromAdmin(): void
    {
        if (!current_user_can('manage_options') || get_transient('homlity_error_reporter_admin_delivery')) {
            return;
        }
        set_transient('homlity_error_reporter_admin_delivery', 1, MINUTE_IN_SECONDS);
        $this->deliverQueued(3, 5);
    }

    public function deliverQueued(int $limit = 10, int $timeout = 10, string $preferredEventId = ''): void
    {
        $token = $this->queue->acquireLock();
        if ($token === null) {
            return;
        }
        try {
            foreach ($this->queue->due($limit, $preferredEventId) as $entry) {
                $eventId = (string) ($entry['event_id'] ?? '');
                $result = $this->transport->send((string) ($entry['origin'] ?? ''), (array) ($entry['payload'] ?? []), $timeout);
                $status = (int) $result['status'];
                if ($result['success'] && in_array($status, [200, 202], true)) {
                    $this->queue->remove($eventId);
                    $this->queue->recordState([
                        'last_success_at' => gmdate('c'),
                        'last_http_status' => $status,
                        'last_local_error' => '',
                        'license_revalidation_required' => false,
                    ]);
                } elseif (in_array($status, [401, 403], true)) {
                    $this->queue->block($eventId, $status);
                } elseif ($status === 422) {
                    $this->queue->remove($eventId);
                    $this->queue->recordState([
                        'last_error_at' => gmdate('c'),
                        'last_http_status' => 422,
                        'last_local_error' => 'invalid_event_discarded',
                    ]);
                } elseif ($status === 0 || $status === 429 || $status >= 500) {
                    $this->queue->retry($eventId, $status);
                } else {
                    $this->queue->remove($eventId);
                    $this->queue->recordState([
                        'last_error_at' => gmdate('c'),
                        'last_http_status' => $status,
                        'last_local_error' => 'non_retryable_response',
                    ]);
                }
            }
        } finally {
            $this->queue->releaseLock($token);
        }
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('homlity-real-estate/v1', '/error-reporter/diagnostics', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'diagnosticsResponse'],
            'permission_callback' => [$this, 'canManage'],
        ]);
        register_rest_route('homlity-real-estate/v1', '/error-reporter/connection-test', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [$this, 'connectionTestResponse'],
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function diagnosticsResponse(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'reporter' => [
                'status' => 'enabled',
                'collector' => 'homlity-real-estate',
                'version' => defined('HOMLITY_PLUGIN_VERSION') ? HOMLITY_PLUGIN_VERSION : 'unknown',
            ],
            'queue' => $this->queue->diagnostics(),
            'plugins' => $this->registry->detectedPlugins(),
            'schedule' => ($next = wp_next_scheduled(self::CRON_HOOK)) ? gmdate('c', $next) : '',
        ]);
    }

    public function connectionTestResponse(): \WP_REST_Response
    {
        $plugins = $this->registry->detectedPlugins();
        $ready = array_values(array_filter($plugins, static fn (array $plugin): bool => !empty($plugin['license_valid'])));
        if ($ready !== []) {
            $this->queue->unblockOrigins(array_column($ready, 'plugin'));
        }
        return new \WP_REST_Response([
            'success' => $ready !== [],
            'mode' => 'local_validation',
            'message' => $ready !== []
                ? __('Configuración local válida. No se generó ni envió un error de prueba.', 'homlity-real-estate')
                : __('No hay una instalación Homlity con licencia y site_id válidos para reportar.', 'homlity-real-estate'),
            'ready_plugins' => array_column($ready, 'plugin'),
        ], $ready !== [] ? 200 : 409);
    }

    private function drainEarlyFatals(): void
    {
        $items = get_option('homlity_error_reporter_early_fatals', []);
        if (!is_array($items) || $items === []) {
            return;
        }
        delete_option('homlity_error_reporter_early_fatals');
        foreach (array_slice($items, -10) as $error) {
            if (is_array($error)) {
                $storedFile = str_replace('\\', '/', (string) ($error['file'] ?? ''));
                if (strpos($storedFile, 'wp-content/plugins/') === 0) {
                    $error['file'] = WP_PLUGIN_DIR . '/' . substr($storedFile, strlen('wp-content/plugins/'));
                }
                $event = $this->factory->fromFatal($error);
                if ($event !== null) {
                    $this->queue->enqueue($event);
                }
            }
        }
    }
}
