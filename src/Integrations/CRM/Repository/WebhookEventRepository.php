<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Repository;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookEventRepository
{
    public const TABLE = 'homlity_webhook_events';

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
            source_key VARCHAR(64) NOT NULL,
            event_id VARCHAR(191) NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            external_id VARCHAR(191) NOT NULL DEFAULT '',
            payload_hash VARCHAR(191) NOT NULL DEFAULT '',
            status VARCHAR(32) NOT NULL DEFAULT 'received',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY source_event_unique (source_key, event_id),
            KEY source_external_idx (source_key, external_id),
            KEY status_idx (status)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function exists(string $sourceKey, string $eventId): bool
    {
        global $wpdb;

        $sourceKey = sanitize_key($sourceKey);
        $eventId = sanitize_text_field($eventId);
        if ($sourceKey === '' || $eventId === '') {
            return false;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$this->tableName()} WHERE source_key = %s AND event_id = %s",
            $sourceKey,
            $eventId
        )) > 0;
    }

    public function record(string $sourceKey, string $eventId, string $eventType, string $externalId, string $payloadHash, string $status = 'queued'): void
    {
        global $wpdb;

        $sourceKey = sanitize_key($sourceKey);
        $eventId = sanitize_text_field($eventId);
        if ($sourceKey === '' || $eventId === '') {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $wpdb->insert($this->tableName(), [
            'source_key' => $sourceKey,
            'event_id' => $eventId,
            'event_type' => sanitize_key($eventType),
            'external_id' => sanitize_text_field($externalId),
            'payload_hash' => sanitize_text_field($payloadHash),
            'status' => sanitize_key($status),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function markStatus(string $sourceKey, string $eventId, string $status): void
    {
        global $wpdb;

        $sourceKey = sanitize_key($sourceKey);
        $eventId = sanitize_text_field($eventId);
        if ($sourceKey === '' || $eventId === '') {
            return;
        }

        $wpdb->update(
            $this->tableName(),
            [
                'status' => sanitize_key($status),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
            [
                'source_key' => $sourceKey,
                'event_id' => $eventId,
            ]
        );
    }
}
