<?php

declare(strict_types=1);

/**
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer;

use Homlity\Developer\Extension\ExtensionRegistry;
use Homlity\Developer\Services\PropertyRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The front door of the Homlity Developer API.
 *
 * Everything an extension needs is reachable from here:
 *
 *     Homlity::version();                       // '2.8.0'
 *     Homlity::apiVersion();                    // '1.0.0'
 *     Homlity::isVersionSupported('2.8.0');     // bool
 *     Homlity::extensions()->register($ext);    // ExtensionRegistry
 *     Homlity::properties()->find(123);         // ?Property
 *
 * The class holds two long-lived collaborators — the extension registry and
 * the property repository — behind static accessors. That is a deliberate
 * concession to WordPress: there is no container to inject from in a plugin
 * ecosystem, and a global accessor that returns a real object is far easier to
 * reason about (and to stub in tests, via {@see self::setExtensionRegistry()})
 * than static methods sprinkled across the codebase.
 *
 * @since 2.8.0
 */
final class Homlity
{
    private static ?ExtensionRegistry $extensions = null;
    private static ?PropertyRepository $properties = null;

    /**
     * Not instantiable: this is a namespace for the API, not an object.
     */
    private function __construct()
    {
    }

    /**
     * Version of the Homlity Real Estate plugin, or '' when it is not loaded.
     *
     * @since 2.8.0
     */
    public static function version(): string
    {
        return Api::pluginVersion();
    }

    /**
     * Version of the public Developer API contract.
     *
     * @since 2.8.0
     */
    public static function apiVersion(): string
    {
        return Api::VERSION;
    }

    /**
     * Whether the plugin is loaded in this request.
     *
     * @since 2.8.0
     */
    public static function isAvailable(): bool
    {
        return Api::isAvailable();
    }

    /**
     * Whether the loaded plugin is at least $minimum.
     *
     * @since 2.8.0
     *
     * @param string $minimum Minimum plugin version, e.g. '2.8.0'.
     */
    public static function isVersionSupported(string $minimum): bool
    {
        return Api::isVersionSupported($minimum);
    }

    /**
     * The registry of extensions active on this site.
     *
     * @since 2.8.0
     */
    public static function extensions(): ExtensionRegistry
    {
        if (self::$extensions === null) {
            self::$extensions = new ExtensionRegistry();
        }

        return self::$extensions;
    }

    /**
     * The read API for properties.
     *
     * @since 2.8.0
     */
    public static function properties(): PropertyRepository
    {
        if (self::$properties === null) {
            self::$properties = new PropertyRepository();
        }

        return self::$properties;
    }

    /**
     * Replace the extension registry.
     *
     * @internal Exists for the test suite. Calling this at runtime discards
     *           every extension already registered.
     */
    public static function setExtensionRegistry(?ExtensionRegistry $registry): void
    {
        self::$extensions = $registry;
    }

    /**
     * Replace the property repository.
     *
     * @internal Exists for the test suite.
     */
    public static function setPropertyRepository(?PropertyRepository $repository): void
    {
        self::$properties = $repository;
    }
}
