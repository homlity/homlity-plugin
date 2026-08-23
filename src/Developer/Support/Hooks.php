<?php

declare(strict_types=1);

/**
 * Canonical names of every hook that belongs to the Homlity Developer API.
 *
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook name registry for the public Developer API.
 *
 * Every constant on this class is part of the public contract: the *value*
 * of the constant will not change within a major version of the plugin.
 * Extensions may hard-code the string or reference the constant; both are
 * supported, but referencing the constant makes typos impossible.
 *
 * Naming convention — public hooks use slashes:
 *
 *     homlity/<domain>/<event>
 *
 * Hooks written with underscores (`homlity_*`) predate the Developer API.
 * They still work and are not deprecated, but they are *internal*: they may
 * change or disappear in a minor release. See docs/developers/api/actions.md.
 *
 * @since 2.8.0
 */
final class Hooks
{
    // ─── Lifecycle (actions) ─────────────────────────────────────────────

    /**
     * Fires once the plugin core has finished registering its services.
     * Runs on `plugins_loaded` priority 20.
     */
    public const LOADED = 'homlity/loaded';

    /**
     * Fires when extensions must register themselves.
     * Runs on `plugins_loaded` priority 25.
     */
    public const EXTENSIONS_REGISTER = 'homlity/extensions/register';

    /**
     * Fires after every registered extension has been booted.
     * Runs on `plugins_loaded` priority 25, right after {@see self::EXTENSIONS_REGISTER}.
     */
    public const EXTENSIONS_REGISTERED = 'homlity/extensions/registered';

    /**
     * Fires for each individual extension right after it boots.
     * Runs on `plugins_loaded` priority 25.
     */
    public const EXTENSION_REGISTERED = 'homlity/extension/registered';

    /**
     * Fires when an extension could not be registered or booted.
     * Runs on `plugins_loaded` priority 25.
     */
    public const EXTENSION_FAILED = 'homlity/extension/failed';

    /**
     * Fires when post types, taxonomies and rewrite rules are in place.
     * Runs on `init` priority 100.
     */
    public const INITIALIZED = 'homlity/initialized';

    // ─── Property lifecycle (actions) ────────────────────────────────────

    /** A property post was created. */
    public const PROPERTY_CREATED = 'homlity/property/created';

    /** An existing property post was written to. */
    public const PROPERTY_UPDATED = 'homlity/property/updated';

    /** A property post is about to be permanently deleted. */
    public const PROPERTY_DELETED = 'homlity/property/deleted';

    /** A property was written by an external source (CRM, consignment, sync). */
    public const PROPERTY_SYNCHRONIZED = 'homlity/property/synchronized';

    /** The WordPress post status of a property changed. */
    public const PROPERTY_STATUS_CHANGED = 'homlity/property/status_changed';

    /** The image gallery of a property changed. */
    public const PROPERTY_IMAGES_CHANGED = 'homlity/property/images_changed';

    // ─── Filters ─────────────────────────────────────────────────────────

    /** Filters the normalized payload just before it is written to the database. */
    public const FILTER_PROPERTY_NORMALIZED = 'homlity/property/normalized';

    /** Filters the raw field array used to hydrate a {@see \Homlity\Developer\Models\Property}. */
    public const FILTER_PROPERTY_DATA = 'homlity/property/data';

    /** Filters the `WP_Query` arguments used by the property search. */
    public const FILTER_PROPERTY_QUERY_ARGS = 'homlity/property/query_args';

    /** Filters whether an extension is considered compatible with this install. */
    public const FILTER_EXTENSION_IS_COMPATIBLE = 'homlity/extension/is_compatible';

    /**
     * Every public action, in declaration order.
     *
     * @since 2.8.0
     *
     * @return string[]
     */
    public static function actions(): array
    {
        return [
            self::LOADED,
            self::EXTENSIONS_REGISTER,
            self::EXTENSIONS_REGISTERED,
            self::EXTENSION_REGISTERED,
            self::EXTENSION_FAILED,
            self::INITIALIZED,
            self::PROPERTY_CREATED,
            self::PROPERTY_UPDATED,
            self::PROPERTY_DELETED,
            self::PROPERTY_SYNCHRONIZED,
            self::PROPERTY_STATUS_CHANGED,
            self::PROPERTY_IMAGES_CHANGED,
        ];
    }

    /**
     * Every public filter, in declaration order.
     *
     * @since 2.8.0
     *
     * @return string[]
     */
    public static function filters(): array
    {
        return [
            self::FILTER_PROPERTY_NORMALIZED,
            self::FILTER_PROPERTY_DATA,
            self::FILTER_PROPERTY_QUERY_ARGS,
            self::FILTER_EXTENSION_IS_COMPATIBLE,
        ];
    }
}
