<?php

declare(strict_types=1);

/**
 * Version and compatibility surface of the Homlity Developer API.
 *
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Entry point for version and environment checks.
 *
 * The Developer API is versioned independently from the plugin: the plugin
 * follows its own SemVer line (2.8.0, 2.9.0, …) while the API version only
 * moves when the *public contract* changes.
 *
 * @since 2.8.0
 */
final class Api
{
    /**
     * Semantic version of the public Developer API contract.
     *
     * MAJOR — a public hook, class or interface changed incompatibly.
     * MINOR — new public hooks, classes or methods, backward compatible.
     * PATCH — behaviour fixes that keep the documented contract intact.
     *
     * @since 2.8.0
     */
    public const VERSION = '1.0.0';

    /** Minimum PHP version required to run the plugin. @since 2.8.0 */
    public const MINIMUM_PHP = '8.0';

    /** Minimum WordPress version required to run the plugin. @since 2.8.0 */
    public const MINIMUM_WP = '5.8';

    /**
     * Version of the Homlity Real Estate plugin currently loaded.
     *
     * @since 2.8.0
     *
     * @return string Semantic version, or an empty string when the plugin is not loaded.
     */
    public static function pluginVersion(): string
    {
        return defined('HOMLITY_PLUGIN_VERSION') ? (string) HOMLITY_PLUGIN_VERSION : '';
    }

    /**
     * Whether the Homlity Real Estate plugin is loaded in this request.
     *
     * @since 2.8.0
     */
    public static function isAvailable(): bool
    {
        return defined('HOMLITY_PLUGIN_VERSION');
    }

    /**
     * Whether the loaded plugin is at least $minimum.
     *
     * @since 2.8.0
     *
     * @param string $minimum Minimum acceptable plugin version, e.g. '2.8.0'.
     */
    public static function isVersionSupported(string $minimum): bool
    {
        $current = self::pluginVersion();
        if ($current === '' || $minimum === '') {
            return false;
        }

        return version_compare($current, $minimum, '>=');
    }

    /**
     * Whether the loaded Developer API contract is at least $minimum.
     *
     * Prefer this over {@see self::isVersionSupported()} when what your
     * extension actually depends on is a hook or a class, not a plugin feature.
     *
     * @since 2.8.0
     *
     * @param string $minimum Minimum acceptable API version, e.g. '1.0.0'.
     */
    public static function isApiVersionSupported(string $minimum): bool
    {
        if ($minimum === '') {
            return false;
        }

        return version_compare(self::VERSION, $minimum, '>=');
    }

    /**
     * Current WordPress version, or an empty string outside WordPress.
     *
     * @since 2.8.0
     */
    public static function wordPressVersion(): string
    {
        global $wp_version;

        return isset($wp_version) ? (string) $wp_version : '';
    }

    /**
     * Current PHP version.
     *
     * @since 2.8.0
     */
    public static function phpVersion(): string
    {
        return PHP_VERSION;
    }
}
