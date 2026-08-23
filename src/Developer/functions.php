<?php

declare(strict_types=1);

/**
 * Global helpers of the Homlity Developer API.
 *
 * This file is loaded eagerly by the plugin bootstrap — before `plugins_loaded`
 * — so that any plugin loaded after Homlity can call these functions from its
 * own bootstrap. Because WordPress does not guarantee plugin load order, an
 * extension must still guard the first call:
 *
 *     add_action('plugins_loaded', function () {
 *         if (!function_exists('homlity_is_available') || !homlity_is_available()) {
 *             return; // Homlity is not installed or not active.
 *         }
 *         // …
 *     }, 21);
 *
 * @package Homlity\Developer
 * @since   2.8.0
 */

use Homlity\Developer\Api;
use Homlity\Developer\Contracts\ExtensionInterface;
use Homlity\Developer\Extension\ExtensionRegistry;
use Homlity\Developer\Homlity;
use Homlity\Developer\Models\Property;
use Homlity\Developer\Services\PropertyRepository;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('homlity_version')) {
    /**
     * Version of the Homlity Real Estate plugin.
     *
     * @since 2.8.0
     *
     * @return string Semantic version, or '' when the plugin is not loaded.
     */
    function homlity_version(): string
    {
        return Api::pluginVersion();
    }
}

if (!function_exists('homlity_api_version')) {
    /**
     * Version of the public Developer API contract.
     *
     * @since 2.8.0
     */
    function homlity_api_version(): string
    {
        return Api::VERSION;
    }
}

if (!function_exists('homlity_is_available')) {
    /**
     * Whether Homlity Real Estate is loaded in this request.
     *
     * @since 2.8.0
     */
    function homlity_is_available(): bool
    {
        return Api::isAvailable();
    }
}

if (!function_exists('homlity_is_version_supported')) {
    /**
     * Whether the loaded plugin is at least $minimum.
     *
     * @since 2.8.0
     *
     * @param string $minimum Minimum plugin version, e.g. '2.8.0'.
     */
    function homlity_is_version_supported(string $minimum): bool
    {
        return Api::isVersionSupported($minimum);
    }
}

if (!function_exists('homlity_extensions')) {
    /**
     * The registry of Homlity extensions active on this site.
     *
     * @since 2.8.0
     */
    function homlity_extensions(): ExtensionRegistry
    {
        return Homlity::extensions();
    }
}

if (!function_exists('homlity_register_extension')) {
    /**
     * Register a Homlity extension.
     *
     * Call it inside the `homlity/extensions/register` action. Registering
     * earlier is fine — the extension is queued and booted when the action
     * fires. Registering later boots it immediately.
     *
     * @since 2.8.0
     *
     * @param ExtensionInterface $extension Extension to register.
     * @return bool True when accepted; false when refused — see
     *              `Homlity::extensions()->failures()` for the reasons.
     */
    function homlity_register_extension(ExtensionInterface $extension): bool
    {
        return Homlity::extensions()->register($extension);
    }
}

if (!function_exists('homlity_properties')) {
    /**
     * The read API for properties.
     *
     * @since 2.8.0
     */
    function homlity_properties(): PropertyRepository
    {
        return Homlity::properties();
    }
}

if (!function_exists('homlity_get_property')) {
    /**
     * A property by post ID.
     *
     * @since 2.8.0
     *
     * @param int $propertyId Post ID.
     * @return Property|null Null when the post is missing or is not a property.
     */
    function homlity_get_property(int $propertyId): ?Property
    {
        return Homlity::properties()->find($propertyId);
    }
}
