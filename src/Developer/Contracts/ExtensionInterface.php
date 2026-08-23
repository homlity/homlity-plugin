<?php

declare(strict_types=1);

/**
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer\Contracts;

use Homlity\Developer\Extension\Requirements;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contract every Homlity extension must implement.
 *
 * An extension is a normal WordPress plugin that hands Homlity an object
 * describing itself. Homlity checks the declared requirements, registers the
 * extension under its slug, and calls {@see self::boot()} once — at that point
 * the plugin core is loaded and the Developer API is safe to use.
 *
 * Minimal implementation:
 *
 *     use Homlity\Developer\Contracts\ExtensionInterface;
 *     use Homlity\Developer\Extension\Requirements;
 *
 *     final class MyIntegration implements ExtensionInterface
 *     {
 *         public function getName(): string    { return 'Mi CRM'; }
 *         public function getSlug(): string    { return 'mi-crm'; }
 *         public function getVersion(): string { return '1.0.0'; }
 *
 *         public function getRequirements(): Requirements
 *         {
 *             return Requirements::create(['homlity' => '2.8.0']);
 *         }
 *
 *         public function boot(): void
 *         {
 *             add_action('homlity/property/updated', [$this, 'push']);
 *         }
 *     }
 *
 * @since 2.8.0
 */
interface ExtensionInterface
{
    /**
     * Human-readable name, shown in the Homlity admin screens.
     *
     * @since 2.8.0
     */
    public function getName(): string;

    /**
     * Unique machine-readable identifier.
     *
     * Must be a WordPress "key": lowercase letters, digits, dashes and
     * underscores. Two extensions cannot share a slug — the second one is
     * rejected. Namespace it with your vendor prefix, e.g. `acme-mi-crm`.
     *
     * @since 2.8.0
     */
    public function getSlug(): string;

    /**
     * Semantic version of the extension itself, e.g. '1.4.2'.
     *
     * @since 2.8.0
     */
    public function getVersion(): string;

    /**
     * Environment the extension needs in order to boot.
     *
     * Return `Requirements::none()` when the extension works on any install
     * that can run Homlity at all.
     *
     * @since 2.8.0
     */
    public function getRequirements(): Requirements;

    /**
     * Wire the extension into WordPress.
     *
     * Called once, on `plugins_loaded` priority 25, only when the declared
     * requirements are satisfied. The Homlity core services are registered at
     * this point, but post types and taxonomies are not — hook `init` (or
     * {@see \Homlity\Developer\Support\Hooks::INITIALIZED}) if you need those.
     *
     * Exceptions thrown here are caught by the registry, reported through
     * {@see \Homlity\Developer\Support\Hooks::EXTENSION_FAILED}, and never
     * allowed to take the site down.
     *
     * @since 2.8.0
     */
    public function boot(): void;
}
