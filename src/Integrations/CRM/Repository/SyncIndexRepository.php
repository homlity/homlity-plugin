<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Repository;

if (!defined('ABSPATH')) {
    exit;
}

class SyncIndexRepository
{
    public const TABLE = 'homlity_sync_index';

    public function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    public function ensureTable(): void
    {
        global $wpdb;

        $table = esc_sql($this->tableName());
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_key VARCHAR(64) NOT NULL,
            external_id VARCHAR(191) NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            external_hash VARCHAR(191) NOT NULL DEFAULT '',
            last_synced_at DATETIME NULL,
            last_source_updated_at DATETIME NULL,
            sync_status VARCHAR(32) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY source_external_unique (source_key, external_id),
            KEY post_id_idx (post_id),
            KEY sync_status_idx (sync_status)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function upsert(string $sourceKey, string $externalId, int $postId, string $externalHash = '', string $status = 'synced', ?string $sourceUpdatedAt = null): void
    {
        global $wpdb;

        $sourceKey = sanitize_key($sourceKey);
        $externalId = sanitize_text_field($externalId);
        if ($sourceKey === '' || $externalId === '') {
            return;
        }

        $table = esc_sql($this->tableName());
        $now = gmdate('Y-m-d H:i:s');
        $sourceUpdatedAtSql = $this->normalizeDateForSql($sourceUpdatedAt);

        $existingId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE source_key = %s AND external_id = %s LIMIT 1",
            $sourceKey,
            $externalId
        ));

        $payload = [
            'source_key' => $sourceKey,
            'external_id' => $externalId,
            'post_id' => max(0, $postId),
            'external_hash' => sanitize_text_field($externalHash),
            'last_synced_at' => $now,
            'last_source_updated_at' => $sourceUpdatedAtSql,
            'sync_status' => sanitize_key($status) ?: 'synced',
            'updated_at' => $now,
        ];

        if ($existingId > 0) {
            $wpdb->update($table, $payload, ['id' => $existingId]);
            return;
        }

        $payload['created_at'] = $now;
        $wpdb->insert($table, $payload);
    }

    public function findPostId(string $sourceKey, string $externalId): int
    {
        global $wpdb;

        $table = esc_sql($this->tableName());
        $sourceKey = sanitize_key($sourceKey);
        $externalId = sanitize_text_field($externalId);

        if ($sourceKey === '' || $externalId === '') {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$table} WHERE source_key = %s AND external_id = %s LIMIT 1",
            $sourceKey,
            $externalId
        ));
    }

    private function normalizeDateForSql(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $ts);
    }
}
