<?php

namespace Homlity\PluginInmobiliario\Search;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!defined('ABSPATH')) {
    exit;
}

/** Rebuilds the index in resumable batches for shared hosting without WP-CLI. */
final class PropertyIndexRebuilder implements ServiceInterface
{
    public const CRON_HOOK = 'homlity_property_index_rebuild_batch';
    public const RECONCILE_CRON_HOOK = 'homlity_property_index_reconcile';
    public const CURSOR_OPTION = 'homlity_property_index_rebuild_cursor';
    public const LAST_REBUILD_OPTION = 'homlity_property_index_last_rebuild';
    private const LOCK_OPTION = 'homlity_property_index_rebuild_lock';
    private const PENDING_IDS_OPTION = 'homlity_property_index_pending_ids';
    private const BATCH_SIZE = 200;

    public function register(): void
    {
        add_action('admin_init', [$this, 'maybeSchedule'], 20);
        add_action(self::CRON_HOOK, [$this, 'runBatch']);
        add_action(self::RECONCILE_CRON_HOOK, [$this, 'reconcile']);
        add_action('wp_ajax_homlity_property_index_rebuild', [$this, 'ajaxStart']);
        add_action('wp_ajax_homlity_property_index_rebuild_status', [$this, 'ajaxStatus']);

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command('homlity index', [$this, 'cli']);
        }
    }

    public function maybeSchedule(): void
    {
        if (!wp_next_scheduled(self::RECONCILE_CRON_HOOK)) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::RECONCILE_CRON_HOOK);
        }
        if (PropertyIndexSchema::isReady() || !PropertyIndexSchema::tableExists()) {
            return;
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            $this->scheduleNextBatch(30);
        }
    }

    /** Reindexes imports that wrote directly to the database without hooks. */
    public function reconcile(): int
    {
        if (!PropertyIndexSchema::tableExists()) {
            return 0;
        }
        global $wpdb;
        $ids = $wpdb->get_col(
            'SELECT p.ID FROM ' . $wpdb->posts . ' AS p
             LEFT JOIN ' . PropertyIndexSchema::tableName() . ' AS hpi ON hpi.post_id = p.ID
             WHERE p.post_type = \'' . esc_sql(PropertyPostType::POST_TYPE) . '\'
             AND (hpi.post_id IS NULL OR p.post_modified_gmt > hpi.indexed_at)
             ORDER BY p.ID ASC LIMIT 500'
        ) ?: [];
        $wpdb->query('DELETE hpi FROM ' . PropertyIndexSchema::tableName() . ' AS hpi LEFT JOIN ' . $wpdb->posts . ' AS p ON p.ID = hpi.post_id WHERE p.ID IS NULL OR p.post_type <> \'property\'');
        // Direct postmeta writes that do not update post_modified are intentionally
        // invisible here; use the admin rebuild button to refresh those rows.
        $count = (new PropertyIndexer())->indexMany(array_map('absint', $ids));
        if (count($ids) === 500) {
            $this->scheduleOnce(self::RECONCILE_CRON_HOOK, 1);
        }
        return $count;
    }

    public function start(): void
    {
        delete_option(PropertyIndexSchema::READY_OPTION);
        update_option(self::CURSOR_OPTION, 0, false);
        $this->scheduleNextBatch(1);
    }

    /** @return array{processed:int,remaining:bool,cursor:int} */
    public function runBatch(int $batchSize = self::BATCH_SIZE): array
    {
        $lockValue = (int) get_option(self::LOCK_OPTION, 0);
        if ($lockValue > 0 && time() - $lockValue < 60) {
            return ['processed' => 0, 'remaining' => true, 'cursor' => (int) get_option(self::CURSOR_OPTION, 0)];
        }
        delete_option(self::LOCK_OPTION);
        if (!add_option(self::LOCK_OPTION, time(), '', false)) {
            return ['processed' => 0, 'remaining' => true, 'cursor' => (int) get_option(self::CURSOR_OPTION, 0)];
        }
        try {
        global $wpdb;
        $pending = array_values(array_filter(array_map('absint', (array) get_option(self::PENDING_IDS_OPTION, []))));
        if ($pending !== []) {
            $ids = array_slice(array_values(array_unique($pending)), 0, $batchSize);
            $rest = array_slice($pending, count($ids));
            (new PropertyIndexer())->indexMany($ids);
            if ($rest === []) delete_option(self::PENDING_IDS_OPTION); else update_option(self::PENDING_IDS_OPTION, $rest, false);
            if ($rest !== []) $this->scheduleNextBatch(1);
            return ['processed' => count($ids), 'remaining' => $rest !== [], 'cursor' => (int) get_option(self::CURSOR_OPTION, 0)];
        }
        $cursor = max(0, (int) get_option(self::CURSOR_OPTION, 0));
        $ids = $wpdb->get_col($wpdb->prepare(
            'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d',
            PropertyPostType::POST_TYPE,
            $cursor,
            max(1, $batchSize)
        )) ?: [];
        $ids = array_map('absint', $ids);
        if ($ids === []) {
            update_option(PropertyIndexSchema::READY_OPTION, PropertyIndexSchema::SCHEMA_VERSION, true);
            update_option(self::LAST_REBUILD_OPTION, gmdate('Y-m-d H:i:s'), false);
            delete_option(self::CURSOR_OPTION);
            return ['processed' => 0, 'remaining' => false, 'cursor' => $cursor];
        }

        (new PropertyIndexer())->indexMany($ids);
        $newCursor = (int) end($ids);
        update_option(self::CURSOR_OPTION, $newCursor, false);
        $this->scheduleNextBatch(1);

        return ['processed' => count($ids), 'remaining' => true, 'cursor' => $newCursor];
        } finally {
            delete_option(self::LOCK_OPTION);
        }
    }

    public function ajaxStart(): void
    {
        check_ajax_referer('homlity_property_index', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No autorizado.', 'homlity-real-estate')], 403);
        }
        $this->start();
        wp_send_json_success($this->status());
    }

    public function ajaxStatus(): void
    {
        check_ajax_referer('homlity_property_index', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No autorizado.', 'homlity-real-estate')], 403);
        }
        if (!PropertyIndexSchema::isReady() && get_option(self::CURSOR_OPTION, null) !== null) {
            $this->runBatch(100);
        }
        wp_send_json_success($this->status());
    }

    /** @return array<string,mixed> */
    public function status(): array
    {
        global $wpdb;
        $indexed = PropertyIndexSchema::tableExists()
            ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . PropertyIndexSchema::tableName())
            : 0;
        $published = (int) wp_count_posts(PropertyPostType::POST_TYPE)->publish;
        return [
            'published' => $published,
            'indexed' => $indexed,
            'difference' => $published - $indexed,
            'ready' => PropertyIndexSchema::isReady(),
            'cursor' => (int) get_option(self::CURSOR_OPTION, 0),
            'last_rebuild' => (string) get_option(self::LAST_REBUILD_OPTION, ''),
        ];
    }

    /** @param array<int,string> $args */
    public function cli(array $args, array $assocArgs): void
    {
        $command = $args[0] ?? 'status';
        if ($command === 'rebuild') {
            $this->start();
            \WP_CLI::success('Reconstrucción programada.');
            return;
        }
        \WP_CLI::log(wp_json_encode($this->status()));
    }

    private function scheduleNextBatch(int $delay): void
    {
        $this->scheduleOnce(self::CRON_HOOK, $delay);
    }

    public function scheduleIds(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if ($ids === []) return;
        $pending = array_values(array_unique(array_merge((array) get_option(self::PENDING_IDS_OPTION, []), $ids)));
        update_option(self::PENDING_IDS_OPTION, $pending, false);
        $this->scheduleNextBatch(1);
    }

    private function scheduleOnce(string $hook, int $delay): void
    {
        if (!wp_next_scheduled($hook)) {
            wp_schedule_single_event(time() + max(1, $delay), $hook);
        }
    }
}
