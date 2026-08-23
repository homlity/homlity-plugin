<?php

declare(strict_types=1);

/**
 * Wires the public Homlity Developer API into WordPress.
 *
 * Internal: extensions consume the hooks this service fires, they do not
 * instantiate it.
 */

namespace Homlity\PluginInmobiliario\Core;

use Homlity\Developer\Homlity;
use Homlity\Developer\Support\Hooks;
use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Owns the Developer API lifecycle.
 *
 * The whole boot order an extension author needs to know lives in this one
 * class, on purpose:
 *
 *   plugins_loaded : 20  core services registered → `homlity/loaded`
 *   plugins_loaded : 25  `homlity/extensions/register`
 *                        → every extension boots
 *                        → `homlity/extensions/registered`
 *   plugins_loaded : 30  `homlity_plugin_register_sync_providers` (pre-existing)
 *   init           : 100 post types, taxonomies and rewrites ready
 *                        → `homlity/initialized`
 *
 * @internal
 */
final class DeveloperApiService implements ServiceInterface
{
    public function register(): void
    {
        // Priority 25: after the core bootstrap at 20, before the sync-provider
        // registry at 30, so a sync provider may live inside an extension.
        add_action('plugins_loaded', [$this, 'registerExtensions'], 25);

        // Priority 100: after PropertyPostType/PropertyTaxonomies (10) and
        // VersionService::maybeUpgrade() (99) have all run.
        add_action('init', [$this, 'announceInitialized'], 100);

        // Property lifecycle events that WordPress itself owns.
        add_action('pre_post_update', [$this, 'capturePreUpdateSnapshot'], 10, 1);
        add_action('transition_post_status', [$this, 'announceStatusChange'], 10, 3);
        add_action('before_delete_post', [$this, 'announceDeletion'], 10, 1);
    }

    /**
     * Fire the registration window and boot everything that answered it.
     */
    public function registerExtensions(): void
    {
        $registry = Homlity::extensions();

        /**
         * Fires when Homlity extensions must register themselves.
         *
         * Runs on `plugins_loaded` priority 25. The plugin core is loaded;
         * post types and taxonomies are not yet registered.
         *
         * @since 2.8.0
         *
         * @param \Homlity\Developer\Extension\ExtensionRegistry $registry Registry to register into.
         */
        do_action(Hooks::EXTENSIONS_REGISTER, $registry);

        $registry->bootAll();

        /**
         * Fires once every extension has booted.
         *
         * @since 2.8.0
         *
         * @param \Homlity\Developer\Extension\ExtensionRegistry $registry Registry, now fully booted.
         */
        do_action(Hooks::EXTENSIONS_REGISTERED, $registry);
    }

    /**
     * Announce that post types, taxonomies and rewrite rules are in place.
     */
    public function announceInitialized(): void
    {
        /**
         * Fires when Homlity is fully initialized.
         *
         * Runs on `init` priority 100. Post types, taxonomies, rewrite rules
         * and shortcodes are registered; it is safe to query properties.
         *
         * @since 2.8.0
         */
        do_action(Hooks::INITIALIZED);
    }

    /**
     * Snapshot a property right before WordPress overwrites its post row.
     *
     * @param int $postId Post being updated.
     */
    public function capturePreUpdateSnapshot($postId): void
    {
        $postId = (int) $postId;
        if ($postId <= 0 || get_post_type($postId) !== PropertyPostType::POST_TYPE) {
            return;
        }

        PropertyEventDispatcher::rememberSnapshot($postId);
    }

    /**
     * @param string    $newStatus New post status.
     * @param string    $oldStatus Previous post status.
     * @param \WP_Post  $post      Post being transitioned.
     */
    public function announceStatusChange($newStatus, $oldStatus, $post): void
    {
        if (!$post instanceof \WP_Post || $post->post_type !== PropertyPostType::POST_TYPE) {
            return;
        }

        PropertyEventDispatcher::dispatchStatusChanged((string) $newStatus, (string) $oldStatus, (int) $post->ID);
    }

    /**
     * @param int $postId Post about to be permanently deleted.
     */
    public function announceDeletion($postId): void
    {
        $postId = (int) $postId;
        if ($postId <= 0 || get_post_type($postId) !== PropertyPostType::POST_TYPE) {
            return;
        }

        PropertyEventDispatcher::dispatchDeleted($postId);
    }
}
