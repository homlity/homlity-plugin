<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
namespace Homlity\PluginInmobiliario\Integrations\CRM\Repository;

if (!defined('ABSPATH')) {
    exit;
}

class PullSyncJobRepository
{
    public const TABLE = 'homlity_sync_jobs';

    public function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    public function ensureTable(): void
    {
        global $wpdb;

        $table = $this->tableName();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id VARCHAR(64) NOT NULL,
            source_key VARCHAR(64) NOT NULL,
            mode VARCHAR(32) NOT NULL DEFAULT 'pull_api_batch',
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            cursor_json LONGTEXT NULL,
            updated_since DATETIME NULL,
            batch_size INT UNSIGNED NOT NULL DEFAULT 50,
            lock_token VARCHAR(191) NOT NULL DEFAULT '',
            locked_until DATETIME NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_unique (job_id),
            KEY source_status_idx (source_key, status),
            KEY locked_until_idx (locked_until)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * @param array<string,mixed> $cursor
     */
    public function createOrUpdate(string $sourceKey, string $jobId, array $cursor = [], ?string $updatedSince = null, int $batchSize = 50, string $status = 'pending'): void
    {
        global $wpdb;

        $sourceKey = sanitize_key($sourceKey);
        $jobId = sanitize_key($jobId);
        if ($sourceKey === '' || $jobId === '') {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $payload = [
            'job_id' => $jobId,
            'source_key' => $sourceKey,
            'mode' => 'pull_api_batch',
            'status' => sanitize_key($status) ?: 'pending',
            'cursor_json' => wp_json_encode($cursor),
            'updated_since' => $this->normalizeDateForSql($updatedSince),
            'batch_size' => max(1, min(500, $batchSize)),
            'updated_at' => $now,
        ];

        $existingId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->tableName()} WHERE job_id = %s LIMIT 1",
            $jobId
        ));

        if ($existingId > 0) {
            $wpdb->update($this->tableName(), $payload, ['id' => $existingId]);
            return;
        }

        $payload['created_at'] = $now;
        $wpdb->insert($this->tableName(), $payload);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findRunnable(string $sourceKey): ?array
    {
        global $wpdb;

        $sourceKey = sanitize_key($sourceKey);
        if ($sourceKey === '') {
            return null;
        }

        $now = gmdate('Y-m-d H:i:s');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tableName()} WHERE source_key = %s AND status IN ('pending','retrying','processing') AND (locked_until IS NULL OR locked_until < %s) ORDER BY id ASC LIMIT 1",
            $sourceKey,
            $now
        ), ARRAY_A);

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    public function acquireLock(string $jobId, string $lockToken, int $ttlSeconds = 300): bool
    {
        global $wpdb;

        $jobId = sanitize_key($jobId);
        $lockToken = sanitize_text_field($lockToken);
        if ($jobId === '' || $lockToken === '') {
            return false;
        }

        $now = gmdate('Y-m-d H:i:s');
        $lockedUntil = gmdate('Y-m-d H:i:s', time() + max(30, $ttlSeconds));

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->tableName()} SET lock_token = %s, locked_until = %s, status = 'processing', updated_at = %s WHERE job_id = %s AND (locked_until IS NULL OR locked_until < %s)",
            $lockToken,
            $lockedUntil,
            $now,
            $jobId,
            $now
        ));

        return (int) $updated > 0;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    public function updateCheckpoint(string $jobId, array $cursor, bool $hasMore, ?string $updatedSince = null): void
    {
        global $wpdb;

        $status = $hasMore ? 'pending' : 'synced';
        $wpdb->update($this->tableName(), [
            'cursor_json' => wp_json_encode($cursor),
            'updated_since' => $this->normalizeDateForSql($updatedSince),
            'status' => $status,
            'lock_token' => '',
            'locked_until' => null,
            'last_error' => '',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['job_id' => sanitize_key($jobId)]);
    }

    public function markFailed(string $jobId, string $error): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->tableName()} SET status = 'retrying', attempts = attempts + 1, lock_token = '', locked_until = NULL, last_error = %s, updated_at = %s WHERE job_id = %s",
            sanitize_textarea_field($error),
            gmdate('Y-m-d H:i:s'),
            sanitize_key($jobId)
        ));
    }

    /** @param array<string,mixed> $row */
    private function normalizeRow(array $row): array
    {
        $cursor = json_decode((string) ($row['cursor_json'] ?? ''), true);
        $row['cursor'] = is_array($cursor) ? $cursor : [];
        unset($row['cursor_json']);

        return $row;
    }

    private function normalizeDateForSql(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $ts = strtotime($value);
        return $ts === false ? null : gmdate('Y-m-d H:i:s', $ts);
    }
}
