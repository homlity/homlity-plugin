<?php
/**
 * Privacy boundary for every value persisted or sent by the reporter.
 */

namespace Homlity\PluginInmobiliario\ErrorReporting;

if (!defined('ABSPATH')) {
    exit;
}

final class ErrorSanitizer
{
    private const REDACTED = '[redacted]';
    private const MAX_STRING = 65535;

    /** @var string[] */
    private const SENSITIVE_KEYS = [
        'authorization', 'license', 'license_key', 'token', 'access_token', 'refresh_token',
        'jwt', 'password', 'passwd', 'pass', 'cookie', 'set_cookie', 'api_key', 'apikey',
        'secret', 'client_secret', 'card', 'credit_card', 'document', 'identification',
        'wasi_token', 'simi_token', 'softinm_token', 'id_company', 'username', 'user_email',
    ];

    /** @var string[] */
    private const SAFE_CONTEXT_KEYS = [
        'operation', 'provider', 'sync_type', 'run_id', 'job_id', 'property_id', 'property_code',
        'phase', 'status', 'http_status', 'attempt', 'max_attempts', 'processed', 'created',
        'updated', 'successful', 'failed', 'skipped', 'execution', 'reason', 'source', 'action_id',
        'aggregate_count', 'diagnostic', 'duration', 'duration_ms', 'sync_provider', 'sync_stage',
    ];

    public function text(string $value, int $limit = self::MAX_STRING): string
    {
        $value = wp_check_invalid_utf8($value, true);
        $value = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer ' . self::REDACTED, $value) ?? $value;
        $value = preg_replace('/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/', self::REDACTED, $value) ?? $value;
        $value = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', self::REDACTED, $value) ?? $value;
        $value = preg_replace('/(?<!\w)(?:\+?\d[\s().-]*){8,16}(?!\w)/', self::REDACTED, $value) ?? $value;
        $value = preg_replace_callback(
            '/\b(authorization|license(?:_key)?|access_token|refresh_token|token|jwt|password|passwd|api[_-]?key|secret|cookie|wasi_token|simi_token|softinm_token)\s*([:=])\s*([^\s&,;]+)/i',
            static fn (array $match): string => $match[1] . $match[2] . self::REDACTED,
            $value
        ) ?? $value;
        return $this->truncate($value, $limit);
    }

    public function path(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        foreach ([WP_PLUGIN_DIR, WP_CONTENT_DIR, ABSPATH] as $root) {
            $normalizedRoot = rtrim(str_replace('\\', '/', (string) $root), '/') . '/';
            if (strpos($path, $normalizedRoot) === 0) {
                if ($root === WP_PLUGIN_DIR) {
                    return 'wp-content/plugins/' . ltrim(substr($path, strlen($normalizedRoot)), '/');
                }
                if ($root === WP_CONTENT_DIR) {
                    return 'wp-content/' . ltrim(substr($path, strlen($normalizedRoot)), '/');
                }
                return 'wordpress/' . ltrim(substr($path, strlen($normalizedRoot)), '/');
            }
        }
        return basename($path);
    }

    public function requestPath(string $uri): string
    {
        $path = wp_parse_url($uri, PHP_URL_PATH);
        return $this->truncate(is_string($path) && $path !== '' ? $path : '/', 2048);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function value($value, string $key = '', int $depth = 0)
    {
        if ($this->isSensitiveKey($key)) {
            return self::REDACTED;
        }
        if ($depth > 5) {
            return '[depth-limited]';
        }
        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            return $this->text($value, 4096);
        }
        if (is_array($value)) {
            $clean = [];
            $count = 0;
            foreach ($value as $childKey => $childValue) {
                if (++$count > 50) {
                    $clean['_truncated'] = true;
                    break;
                }
                $safeKey = is_int($childKey) ? $childKey : sanitize_key((string) $childKey);
                $clean[$safeKey] = $this->value($childValue, (string) $childKey, $depth + 1);
            }
            return $clean;
        }
        if ($value instanceof \WP_Error) {
            return [
                'type' => 'WP_Error',
                'code' => sanitize_key((string) $value->get_error_code()),
                'message' => $this->text($value->get_error_message(), 4096),
            ];
        }
        if ($value instanceof \Throwable) {
            return [
                'type' => get_class($value),
                'message' => $this->text($value->getMessage(), 4096),
            ];
        }
        return is_object($value) ? '[object:' . sanitize_key(get_class($value)) . ']' : '[unsupported]';
    }

    /** @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    public function syncContext(array $context): array
    {
        $allowed = apply_filters('homlity_error_reporter_safe_context_keys', self::SAFE_CONTEXT_KEYS);
        $allowed = is_array($allowed) ? array_map('sanitize_key', $allowed) : self::SAFE_CONTEXT_KEYS;
        $clean = [];
        foreach ($context as $key => $value) {
            $safeKey = sanitize_key((string) $key);
            if (!in_array($safeKey, $allowed, true)) {
                continue;
            }
            $clean[$safeKey] = $this->value($value, $safeKey);
        }
        return $clean;
    }

    /** @param array<int, array<string, mixed>> $breadcrumbs
     *  @return array<int, array<string, mixed>>
     */
    public function breadcrumbs(array $breadcrumbs): array
    {
        $clean = [];
        foreach (array_slice($breadcrumbs, -50) as $breadcrumb) {
            if (!is_array($breadcrumb)) {
                continue;
            }
            $clean[] = [
                'timestamp' => sanitize_text_field((string) ($breadcrumb['timestamp'] ?? gmdate('c'))),
                'category' => sanitize_key((string) ($breadcrumb['category'] ?? 'application')),
                'message' => $this->text((string) ($breadcrumb['message'] ?? ''), 1024),
                'data' => $this->syncContext(is_array($breadcrumb['data'] ?? null) ? $breadcrumb['data'] : []),
            ];
        }
        return $clean;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($normalized === $sensitive || strpos($normalized, $sensitive . '_') !== false || substr($normalized, -strlen('_' . $sensitive)) === '_' . $sensitive) {
                return true;
            }
        }
        return false;
    }

    private function truncate(string $value, int $limit): string
    {
        if (strlen($value) <= $limit) {
            return $value;
        }
        return substr($value, 0, max(0, $limit - 14)) . '…[truncated]';
    }
}
