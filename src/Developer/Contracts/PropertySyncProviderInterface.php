<?php

declare(strict_types=1);

/**
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer\Contracts;

use Homlity\PluginInmobiliario\Contracts\SyncProviderInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves a property that exists in an external CRM but not yet in WordPress.
 *
 * When a visitor opens `/inmueble/{CODE}` and no local property carries that
 * code, Homlity asks every registered provider, in priority order, to fetch and
 * create it. The first provider that returns a post ID wins and the visitor is
 * redirected to the canonical URL.
 *
 * This is the public face of the contract the plugin has shipped since 2.4.
 * Implementing this interface is the supported way to write a new one:
 *
 *     use Homlity\Developer\Contracts\PropertySyncProviderInterface;
 *
 *     final class MyCrmSyncProvider implements PropertySyncProviderInterface
 *     {
 *         public function getProviderId(): string { return 'mi-crm'; }
 *
 *         public function syncByCode(string $code): ?int
 *         {
 *             // fetch from the CRM, write the post, return its ID
 *             return null;
 *         }
 *     }
 *
 *     add_action('homlity_plugin_register_sync_providers', function () {
 *         \Homlity\PluginInmobiliario\Services\SyncRegistry::addProvider(
 *             new MyCrmSyncProvider()
 *         );
 *     });
 *
 * @since 2.8.0
 */
interface PropertySyncProviderInterface extends SyncProviderInterface
{
}
