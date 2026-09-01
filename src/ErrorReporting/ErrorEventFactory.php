<?php
/**
 * Constructs versioned, sanitized error event envelopes.
 */

namespace Homlity\PluginInmobiliario\ErrorReporting;

if (!defined('ABSPATH')) {
    exit;
}

final class ErrorEventFactory
{
    public const SCHEMA_VERSION = '1.0';
    private const MAX_PAYLOAD_BYTES = 250000;

    /**
     * Estados que describen una instalación sin terminar de configurar, no un
     * fallo del código.
     */
    private const CONFIGURATION_STATUSES = [
        'not_configured', 'unconfigured', 'config_missing', 'configuration_missing',
        'missing_configuration', 'credentials_missing', 'missing_credentials',
        'invalid_credentials', 'invalid_token', 'token_missing', 'unauthorized',
        'forbidden', 'license_invalid', 'license_expired', 'license_missing',
    ];

    /**
     * Fragmentos (en minúsculas y sin acentos) que identifican un mensaje de
     * configuración pendiente o credenciales rechazadas por el CRM remoto.
     */
    private const CONFIGURATION_MESSAGE_PATTERNS = [
        'no configurado', 'no configurada', 'no configurados', 'no configuradas',
        'sin configurar', 'falta configurar', 'debes configurar', 'not configured',
        'is not set', 'no esta configurado', 'no esta configurada',
        'token invalido', 'token es invalido', 'invalid token', 'token expirado',
        'credencial', 'credential', 'api key', 'apikey', 'api_key', 'clave api',
        'unauthorized', 'forbidden', 'no autorizado', 'acceso denegado', 'access denied',
        'authentication failed', 'autenticacion fallida',
        'licencia invalida', 'licencia expirada', 'licencia inactiva', 'sin licencia',
        'requiere una licencia', 'license expired', 'license invalid', 'invalid license',
    ];

    /**
     * Contabilidad interna de Action Scheduler. Estos mensajes se lanzan cuando
     * la librería no consigue actualizar la fila de una acción, algo que ocurre
     * DESPUÉS de que el trabajo ya se ejecutó: otro runner concurrente completó
     * la misma acción o la limpieza periódica borró la fila. No hay defecto que
     * corregir en el plugin y el trabajo no se perdió.
     */
    private const SCHEDULER_NOISE_MESSAGE_PATTERNS = [
        'unidentified action',
        'deleted by another process',
        'invalid action id. no status found',
    ];

    private OfficialPluginRegistry $registry;
    private ErrorSanitizer $sanitizer;

    public function __construct(?OfficialPluginRegistry $registry = null, ?ErrorSanitizer $sanitizer = null)
    {
        $this->registry = $registry ?: new OfficialPluginRegistry();
        $this->sanitizer = $sanitizer ?: new ErrorSanitizer();
    }

    /** @param array<string, mixed> $error
     *  @return array<string, mixed>|null
     */
    public function fromFatal(array $error): ?array
    {
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
            return null;
        }
        if ($this->isClearlyInvalidAutomatedRequest()) {
            return null;
        }
        $file = (string) ($error['file'] ?? '');
        $origin = $this->registry->originForFile($file);
        if ($origin === null) {
            return null;
        }

        return $this->baseEvent($origin, 'fatal', [
            'type' => 'PHPFatalError',
            'message' => $this->sanitizer->text((string) ($error['message'] ?? 'PHP fatal error')),
            'file' => $this->sanitizer->path($file),
            'line' => max(0, (int) ($error['line'] ?? 0)),
            'stacktrace' => '',
        ], ['operation' => 'php_shutdown']);
    }

    /** @param array<string, mixed> $context
     *  @return array<string, mixed>|null
     */
    public function fromSync(string $origin, $error, array $context = []): ?array
    {
        $origin = $error instanceof \Throwable
            ? $this->registry->originForThrowable($error)
            : $this->registry->normalizeOrigin($origin);
        if ($origin === null || !$this->isReportableSyncFailure($error, $context)) {
            return null;
        }

        if ($error instanceof \Throwable) {
            $exception = $this->exceptionFromThrowable($error);
        } elseif ($error instanceof \WP_Error) {
            $exception = [
                'type' => 'WP_Error:' . sanitize_key((string) $error->get_error_code()),
                'message' => $this->sanitizer->text($error->get_error_message()),
                'file' => '',
                'line' => 0,
                'stacktrace' => '',
            ];
        } else {
            $exception = [
                'type' => sanitize_text_field((string) ($context['error_type'] ?? 'SynchronizationError')),
                'message' => $this->sanitizer->text(is_scalar($error) ? (string) $error : 'Synchronization failed'),
                'file' => '',
                'line' => 0,
                'stacktrace' => '',
            ];
        }

        return $this->baseEvent($origin, 'error', $exception, $this->sanitizer->syncContext($context));
    }

    /**
     * Fallo de una acción programada cuyo propietario ya fue resuelto por el
     * hook/grupo de la acción. A diferencia de fromSync(), NO vuelve a deducir
     * el origen desde el archivo de la excepción: el queue runner de Action
     * Scheduler crea la excepción dentro del vendor/ del plugin que hospeda la
     * copia activa de la librería, que no es necesariamente el culpable.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    public function fromScheduledAction(string $origin, \Throwable $error, array $context = []): ?array
    {
        $origin = $this->registry->normalizeOrigin($origin);
        if ($origin === null || !$this->isReportableSyncFailure($error, $context)) {
            return null;
        }

        return $this->baseEvent(
            $origin,
            'error',
            $this->exceptionFromThrowable($this->registry->rootCause($error)),
            $this->sanitizer->syncContext($context)
        );
    }

    /** @param array<string, mixed> $context */
    public function isReportableSyncFailure($error, array $context): bool
    {
        $status = sanitize_key((string) ($context['status'] ?? ''));
        $reason = sanitize_key((string) ($context['reason'] ?? ''));
        $excluded = array_merge([
            'success', 'completed', 'empty', 'no_changes', 'not_found', 'validation_error',
            'invalid_request', 'invalid_payload', 'provider_disabled', 'license_blocked',
            'locked', 'skipped', 'unavailable', 'incomplete_or_unstable_snapshot',
        ], self::CONFIGURATION_STATUSES);
        if (in_array($status, $excluded, true) || in_array($reason, $excluded, true)) {
            return false;
        }
        $httpStatus = (int) ($context['http_status'] ?? 0);
        if ($httpStatus >= 400 && $httpStatus < 500 && !in_array($httpStatus, [408, 429], true)) {
            return false;
        }
        if ($this->isConfigurationFailure($error, $context)) {
            return false;
        }
        if ($this->isSchedulerNoise($error, $context)) {
            return false;
        }
        if ($error instanceof \WP_Error) {
            $expectedCodes = array_merge(
                ['not_found', 'invalid_request', 'validation_error', 'provider_disabled', 'license_invalid', 'sync_locked'],
                self::CONFIGURATION_STATUSES
            );
            return !in_array(sanitize_key((string) $error->get_error_code()), $expectedCodes, true);
        }
        return $error instanceof \Throwable || (is_string($error) && trim($error) !== '');
    }

    /**
     * Un fallo por configuración ausente o credenciales rechazadas no es un
     * defecto del plugin: sólo el dueño del sitio puede resolverlo, y se repite
     * en cada ejecución programada hasta que lo hace. Reportarlo convertiría el
     * panel de incidencias en un registro de instalaciones a medio configurar,
     * así que se descarta aquí. Los fatales de PHP no pasan por este filtro.
     *
     * @param mixed $error
     * @param array<string, mixed> $context
     */
    public function isConfigurationFailure($error, array $context = []): bool
    {
        $normalized = $this->normalizeForMatching($this->collectMessages($error, $context));
        $isConfiguration = false;
        if (trim($normalized) !== '') {
            foreach (self::CONFIGURATION_MESSAGE_PATTERNS as $pattern) {
                if (strpos($normalized, $pattern) !== false) {
                    $isConfiguration = true;
                    break;
                }
            }
        }

        return (bool) apply_filters(
            'homlity_error_reporter_is_configuration_failure',
            $isConfiguration,
            $normalized,
            $context
        );
    }

    /**
     * Fallo de la contabilidad de la cola, no del trabajo encolado. Action
     * Scheduler marca la acción como fallida cuando no logra escribir su propio
     * cambio de estado, aunque el callback ya se haya ejecutado con éxito, así
     * que reportarlo describiría una carrera de la librería y no un defecto
     * nuestro. Los fatales de PHP no pasan por este filtro.
     *
     * @param mixed $error
     * @param array<string, mixed> $context
     */
    public function isSchedulerNoise($error, array $context = []): bool
    {
        $normalized = $this->normalizeForMatching($this->collectMessages($error, $context));
        $isNoise = false;
        if (trim($normalized) !== '') {
            foreach (self::SCHEDULER_NOISE_MESSAGE_PATTERNS as $pattern) {
                if (strpos($normalized, $pattern) !== false) {
                    $isNoise = true;
                    break;
                }
            }
        }

        return (bool) apply_filters(
            'homlity_error_reporter_is_scheduler_noise',
            $isNoise,
            $normalized,
            $context
        );
    }

    /**
     * Concatena el mensaje del error con toda su cadena de causas: Action
     * Scheduler envuelve la excepción original, así que el motivo real sólo
     * aparece recorriendo getPrevious().
     *
     * @param mixed $error
     * @param array<string, mixed> $context
     */
    private function collectMessages($error, array $context): string
    {
        $message = '';
        if ($error instanceof \Throwable) {
            for ($current = $error; $current !== null; $current = $current->getPrevious()) {
                $message .= ' ' . $current->getMessage();
            }
        } elseif ($error instanceof \WP_Error) {
            $message = $error->get_error_code() . ' ' . $error->get_error_message();
        } elseif (is_scalar($error)) {
            $message = (string) $error;
        }

        return $message . ' ' . (string) ($context['message'] ?? '');
    }

    /**
     * Minúsculas y sin acentos: los plugins escriben los mismos mensajes con y
     * sin tilde ("token inválido" / "token invalido") y ambos deben coincidir.
     */
    private function normalizeForMatching(string $value): string
    {
        $value = strtolower($value);
        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    /** @return array<string, mixed> */
    private function exceptionFromThrowable(\Throwable $throwable): array
    {
        return [
            'type' => get_class($throwable),
            'message' => $this->sanitizer->text($throwable->getMessage()),
            'file' => $this->sanitizer->path($throwable->getFile()),
            'line' => max(0, $throwable->getLine()),
            'stacktrace' => $this->sanitizer->text($throwable->getTraceAsString()),
        ];
    }

    /** @param array<string, mixed> $exception
     *  @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    private function baseEvent(string $origin, string $severity, array $exception, array $context): array
    {
        if (isset($context['provider']) && !isset($context['sync_provider'])) {
            $context['sync_provider'] = $context['provider'];
        }
        if (isset($context['phase']) && !isset($context['sync_stage'])) {
            $context['sync_stage'] = $context['phase'];
        }
        $culprit = trim((string) ($exception['file'] ?? ''));
        if ($culprit === '') {
            $culprit = $origin;
        }
        if (!empty($exception['line'])) {
            $culprit .= ':' . (int) $exception['line'];
        }
        $event = [
            'schema_version' => self::SCHEMA_VERSION,
            'event_id' => wp_generate_uuid4(),
            'occurred_at' => gmdate('c'),
            'application' => 'plugin',
            'environment' => $this->environment(),
            'severity' => $severity,
            'release' => $origin . '@' . ($this->registry->version($origin) ?: 'unknown'),
            'message' => (string) ($exception['message'] ?? 'Homlity error'),
            'exception' => $exception,
            'culprit' => $culprit,
            'platform' => 'wordpress',
            'runtime' => [
                'wordpress' => (string) get_bloginfo('version'),
                'php' => PHP_VERSION,
                'plugin_collector' => 'homlity-real-estate',
                'plugin_collector_version' => defined('HOMLITY_PLUGIN_VERSION') ? HOMLITY_PLUGIN_VERSION : 'unknown',
                'multisite' => is_multisite(),
            ],
            'request' => [
                'method' => strtoupper(sanitize_key(wp_unslash((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI')))),
                'path' => $this->sanitizer->requestPath((string) ($_SERVER['REQUEST_URI'] ?? '/')),
                'request_id' => $this->requestId(),
            ],
            'tags' => [
                'origin_plugin' => $origin,
                'error_kind' => $severity === 'fatal' ? 'fatal' : 'sync',
                'operation' => sanitize_key((string) ($context['operation'] ?? ($severity === 'fatal' ? 'php_shutdown' : 'synchronization'))),
                'execution' => $this->execution(),
            ],
            'breadcrumbs' => $this->sanitizer->breadcrumbs((array) apply_filters('homlity_error_reporter_breadcrumbs', [])),
            'context' => $context,
        ];

        return $this->fitPayload($event);
    }

    /** @param array<string, mixed> $event
     *  @return array<string, mixed>
     */
    private function fitPayload(array $event): array
    {
        $json = wp_json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($json) && strlen($json) <= self::MAX_PAYLOAD_BYTES) {
            return $event;
        }
        $event['breadcrumbs'] = [];
        $event['context'] = ['truncated' => true];
        $event['exception']['stacktrace'] = substr((string) ($event['exception']['stacktrace'] ?? ''), 0, 32768);
        return $event;
    }

    private function environment(): string
    {
        $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
        $map = ['local' => 'development', 'development' => 'development', 'staging' => 'staging', 'testing' => 'testing'];
        return $map[$environment] ?? 'production';
    }

    private function execution(): string
    {
        return self::executionFromFlags([
            'cli' => defined('WP_CLI') && WP_CLI,
            'cron' => defined('DOING_CRON') && DOING_CRON,
            'rest' => defined('REST_REQUEST') && REST_REQUEST,
            'ajax' => defined('DOING_AJAX') && DOING_AJAX,
            'admin' => is_admin(),
        ]);
    }

    /** @param array<string, bool> $flags */
    public static function executionFromFlags(array $flags): string
    {
        if (!empty($flags['cli'])) {
            return 'cli';
        }
        if (!empty($flags['cron'])) {
            return 'cron';
        }
        if (!empty($flags['rest'])) {
            return 'rest';
        }
        if (!empty($flags['ajax'])) {
            return 'ajax';
        }
        return !empty($flags['admin']) ? 'admin' : 'web';
    }

    private function requestId(): string
    {
        $candidate = sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '')));
        return preg_match('/^[A-Za-z0-9._-]{8,128}$/', $candidate) ? $candidate : '';
    }

    private function isClearlyInvalidAutomatedRequest(): bool
    {
        // La URI va sin sanitizar a propósito: este método busca justamente los
        // bytes que un sanitizador borraría (%2e%2e, ../, NUL). Limpiarla antes
        // de comparar dejaría pasar el escaneo que se quiere detectar.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $uri = strtolower(wp_unslash((string) ($_SERVER['REQUEST_URI'] ?? '')));
        $agent = strtolower(sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))));
        $invalidPaths = ['/.env', '/.git/', '/wp-config.php', '/vendor/phpunit/', '../', '%2e%2e', "\0"];
        $scannerAgents = ['sqlmap', 'nikto', 'masscan', 'zgrab', 'nuclei'];
        foreach ($invalidPaths as $needle) {
            if ($needle !== '' && strpos($uri, $needle) !== false) {
                return true;
            }
        }
        foreach ($scannerAgents as $needle) {
            if (strpos($agent, $needle) !== false) {
                return true;
            }
        }
        return (bool) apply_filters('homlity_error_reporter_ignore_request', false, $uri, $agent);
    }
}
