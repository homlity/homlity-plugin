<?php
/**
 * Contract that any CRM synchronizer must implement to participate in
 * homlity-real-estate's on-demand property sync.
 *
 * Usage — from an external sync plugin:
 *
 *   add_action('homlity_plugin_register_sync_providers', function () {
 *       \Homlity\PluginInmobiliario\Services\SyncRegistry::addProvider(
 *           new MyPlugin\MySyncProvider()
 *       );
 *   });
 */

namespace Homlity\PluginInmobiliario\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

interface SyncProviderInterface
{
    /**
     * Unique machine-readable identifier for this provider.
     * Examples: "homlity-sync", "wasi-sync", "sedi-sync".
     */
    public function getProviderId(): string;

    /**
     * Fetch the property identified by $code from an external CRM, create it
     * as a WordPress post (with meta, taxonomies, and images), and return the
     * new post ID.
     *
     * Return null if:
     * - the property does not exist in this CRM, or
     * - the sync attempt failed for any reason.
     *
     * The caller (PropertyCodeRoutingService) owns the 301 redirect logic;
     * this method must only create the post and return its ID.
     *
     * @param string $code Property code as it appears in the URL (e.g. "VTAP1320041").
     * @return int|null    WordPress post ID on success, null otherwise.
     */
    public function syncByCode(string $code): ?int;
}
