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

    /** @param array<string, mixed> $context */
    public function isReportableSyncFailure($error, array $context): bool
    {
        $status = sanitize_key((string) ($context['status'] ?? ''));
        $reason = sanitize_key((string) ($context['reason'] ?? ''));
        $excluded = [
            'success', 'completed', 'empty', 'no_changes', 'not_found', 'validation_error',
            'invalid_request', 'invalid_payload', 'provider_disabled', 'license_blocked',
            'locked', 'skipped', 'unavailable', 'incomplete_or_unstable_snapshot',
        ];
        if (in_array($status, $excluded, true) || in_array($reason, $excluded, true)) {
            return false;
        }
        $httpStatus = (int) ($context['http_status'] ?? 0);
        if ($httpStatus >= 400 && $httpStatus < 500 && !in_array($httpStatus, [408, 429], true)) {
            return false;
        }
        if ($error instanceof \WP_Error) {
            $expectedCodes = ['not_found', 'invalid_request', 'validation_error', 'provider_disabled', 'license_invalid', 'sync_locked'];
            return !in_array(sanitize_key((string) $error->get_error_code()), $expectedCodes, true);
        }
        return $error instanceof \Throwable || (is_string($error) && trim($error) !== '');
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
                'method' => strtoupper(sanitize_key((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'))),
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
        $candidate = (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
        return preg_match('/^[A-Za-z0-9._-]{8,128}$/', $candidate) ? $candidate : '';
    }

    private function isClearlyInvalidAutomatedRequest(): bool
    {
        $uri = strtolower((string) ($_SERVER['REQUEST_URI'] ?? ''));
        $agent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
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
