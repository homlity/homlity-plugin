<?php
/**
 * Tiny early bootstrap: only observes the final PHP error at shutdown.
 */

if (!defined('ABSPATH') || defined('HOMLITY_ERROR_FATAL_HANDLER_REGISTERED')) {
    return;
}

define('HOMLITY_ERROR_FATAL_HANDLER_REGISTERED', true);
require_once __DIR__ . '/class-homlity-error-reporter.php';

register_shutdown_function(static function (): void {
    static $handled = false;
    if ($handled) {
        return;
    }
    $handled = true;
    $error = error_get_last();
    if (is_array($error)) {
        Homlity_Error_Reporter::capture_fatal($error);
    }
});
