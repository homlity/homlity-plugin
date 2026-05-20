<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
/**
 * Cleans analytics rows when a property is removed.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyAnalyticsCleanupService implements ServiceInterface
{
    private const CRON_HOOK = 'homlity_purge_orphan_analytics';
    private const BOOTSTRAP_OPTION = 'homlity_orphan_analytics_cleanup_bootstrapped';

    public function register(): void
    {
        // User moved post to trash.
        add_action('trashed_post', [$this, 'cleanupByPostId']);
        // Permanent deletion.
        add_action('before_delete_post', [$this, 'cleanupByPostId']);

        add_action(self::CRON_HOOK, [$this, 'purgeOrphanAnalytics']);
        add_action('init', [$this, 'maybeScheduleOrphanCleanup']);
    }

    public function cleanupByPostId(int $postId): void
    {
        $postId = absint($postId);
        if ($postId <= 0) {
            return;
        }

        if (get_post_type($postId) !== PropertyPostType::POST_TYPE) {
            return;
        }

        global $wpdb;
        $tables = [
            $wpdb->prefix . 'homlity_property_visits',
            $wpdb->prefix . 'homlity_property_contact_clicks',
            $wpdb->prefix . 'homlity_property_sheet_downloads',
        ];

        foreach ($tables as $table) {
            $exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                continue;
            }
            $wpdb->delete($table, ['property_id' => $postId], ['%d']);
        }
    }

    public function maybeScheduleOrphanCleanup(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'daily', self::CRON_HOOK);
        }

        // One-time bootstrap cleanup for existing orphan rows.
        if ((int) get_option(self::BOOTSTRAP_OPTION, 0) === 1) {
            return;
        }
        $this->purgeOrphanAnalytics();
        update_option(self::BOOTSTRAP_OPTION, 1, false);
    }

    public function purgeOrphanAnalytics(): void
    {
        global $wpdb;

        $postType = PropertyPostType::POST_TYPE;
        $postsTable = $wpdb->posts;
        $tables = [
            $wpdb->prefix . 'homlity_property_visits',
            $wpdb->prefix . 'homlity_property_contact_clicks',
            $wpdb->prefix . 'homlity_property_sheet_downloads',
        ];

        foreach ($tables as $table) {
            $exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                continue;
            }

            $wpdb->query(
                "DELETE t FROM {$table} t
                 LEFT JOIN {$postsTable} p ON p.ID = t.property_id
                 WHERE p.ID IS NULL OR p.post_type <> '{$postType}'"
            );
        }
    }
}
