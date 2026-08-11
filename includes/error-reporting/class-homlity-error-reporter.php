<?php
/**
 * Public bridge used by official plugins without coupling them to collector internals.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Homlity_Error_Reporter', false)) {
    final class Homlity_Error_Reporter
    {
        /** @var callable|null */
        private static $fatalCallback;
        private static string $fatalFingerprint = '';

        public static function set_fatal_callback(callable $callback): void
        {
            self::$fatalCallback = $callback;
        }

        /** @param array<string, mixed> $error */
        public static function capture_fatal(array $error): void
        {
            $fingerprint = hash('sha256', implode('|', [
                (string) ($error['type'] ?? ''),
                (string) ($error['file'] ?? ''),
                (string) ($error['line'] ?? ''),
                (string) ($error['message'] ?? ''),
            ]));
            if (self::$fatalFingerprint !== '' && hash_equals(self::$fatalFingerprint, $fingerprint)) {
                return;
            }
            self::$fatalFingerprint = $fingerprint;
            if (is_callable(self::$fatalCallback)) {
                call_user_func(self::$fatalCallback, $error);
                return;
            }
            self::store_early_fatal($error);
        }

        /** @param array<string, mixed> $context */
        public static function report_sync_error(string $plugin, $error, array $context = []): void
        {
            do_action('homlity_report_sync_error', $plugin, $error, $context);
        }

        /** @param array<string, mixed> $error */
        private static function store_early_fatal(array $error): void
        {
            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
            if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
                return;
            }
            $file = str_replace('\\', '/', (string) ($error['file'] ?? ''));
            $root = rtrim(str_replace('\\', '/', WP_PLUGIN_DIR), '/') . '/';
            if (strpos($file, $root) !== 0) {
                return;
            }
            $directory = strtok(substr($file, strlen($root)), '/');
            $official = ['homlity-real-estate', 'homlity-sync', 'plugin-wasi-sync', 'homlity-wasi', 'plugin-simi-sync', 'homlity-simi', 'plugin-softinm-sync', 'homlity-softinm'];
            if (!in_array($directory, $official, true)) {
                return;
            }
            $message = (string) ($error['message'] ?? 'PHP fatal error');
            $message = preg_replace('/\b(authorization|license(?:_key)?|token|password|api[_-]?key|secret)\s*([:=])\s*([^\s&,;]+)/i', '$1$2[redacted]', $message) ?? $message;
            $message = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[redacted]', $message) ?? $message;
            $message = preg_replace('/(?<!\w)(?:\+?\d[\s().-]*){8,16}(?!\w)/', '[redacted]', $message) ?? $message;
            $relativeFile = 'wp-content/plugins/' . ltrim(substr($file, strlen($root)), '/');
            $items = get_option('homlity_error_reporter_early_fatals', []);
            $items = is_array($items) ? $items : [];
            $items[] = [
                'type' => (int) $error['type'],
                'message' => substr($message, 0, 65535),
                'file' => $relativeFile,
                'line' => (int) ($error['line'] ?? 0),
                'captured_at' => gmdate('c'),
            ];
            update_option('homlity_error_reporter_early_fatals', array_slice($items, -10), false);
        }
    }
}
