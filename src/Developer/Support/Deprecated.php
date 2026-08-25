<?php
// Los nombres de hook de este archivo salen de constantes de Homlity\Developer\Support\Hooks
// (o los recibe el método como argumento ya prefijado). Todas valen 'homlity/...',
// pero el sniff sólo sabe leer literales y las marca como dinámicas.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

declare(strict_types=1);

/**
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * How Homlity retires a piece of public API.
 *
 * Nothing is removed without first passing through here. A deprecated hook,
 * function or argument keeps working for at least one full minor cycle and is
 * only dropped in a major release, and while it is deprecated it emits a
 * notice on `WP_DEBUG` installs so integrators find out during development
 * rather than after an update.
 *
 * Extensions may use these helpers for their own deprecations too.
 *
 * @since 2.8.0
 */
final class Deprecated
{
    /**
     * Fire a deprecated action alongside its replacement.
     *
     * Call the replacement yourself first, then this, so listeners of the old
     * hook see the same state as listeners of the new one.
     *
     * @since 2.8.0
     *
     * @param string  $hook        Deprecated action name.
     * @param mixed[] $args        Arguments to pass to listeners.
     * @param string  $version     Plugin version that deprecated it, e.g. '2.9.0'.
     * @param string  $replacement Hook to use instead, or '' when there is none.
     */
    public static function action(string $hook, array $args, string $version, string $replacement = ''): void
    {
        if (function_exists('do_action_deprecated')) {
            do_action_deprecated($hook, $args, $version, $replacement, self::message($replacement));

            return;
        }

        do_action($hook, ...$args);
    }

    /**
     * Apply a deprecated filter alongside its replacement.
     *
     * @since 2.8.0
     *
     * @param string  $hook        Deprecated filter name.
     * @param mixed[] $args        Arguments; the first one is the filtered value.
     * @param string  $version     Plugin version that deprecated it.
     * @param string  $replacement Filter to use instead, or ''.
     * @return mixed The filtered value.
     */
    public static function filter(string $hook, array $args, string $version, string $replacement = '')
    {
        if (function_exists('apply_filters_deprecated')) {
            return apply_filters_deprecated($hook, $args, $version, $replacement, self::message($replacement));
        }

        return apply_filters_ref_array($hook, $args);
    }

    /**
     * Report that a deprecated function was called.
     *
     * @since 2.8.0
     *
     * @param string $function    Fully-qualified name of the deprecated function.
     * @param string $version     Plugin version that deprecated it.
     * @param string $replacement What to call instead, or ''.
     */
    public static function fn(string $function, string $version, string $replacement = ''): void
    {
        if (function_exists('_deprecated_function')) {
            _deprecated_function(esc_html($function), esc_html($version), esc_html($replacement));
        }
    }

    /**
     * Report that a deprecated argument was passed.
     *
     * @since 2.8.0
     *
     * @param string $function Function that received it.
     * @param string $version  Plugin version that deprecated it.
     * @param string $message  What the caller should do instead.
     */
    public static function argument(string $function, string $version, string $message = ''): void
    {
        if (function_exists('_deprecated_argument')) {
            _deprecated_argument(esc_html($function), esc_html($version), esc_html($message));
        }
    }

    private static function message(string $replacement): string
    {
        if ($replacement === '') {
            return __('Este punto de extensión de Homlity se eliminará en la próxima versión mayor.', 'homlity-real-estate');
        }

        return sprintf(
            /* translators: %s: replacement hook name. */
            __('Este punto de extensión de Homlity se eliminará en la próxima versión mayor. Usa «%s».', 'homlity-real-estate'),
            $replacement
        );
    }
}
