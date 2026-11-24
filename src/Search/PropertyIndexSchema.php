<?php

namespace Homlity\PluginInmobiliario\Search;

if (!defined('ABSPATH')) {
    exit;
}

/** Owns the read-only search-index table and its schema readiness marker. */
final class PropertyIndexSchema
{
    public const TABLE_SUFFIX = 'homlity_property_index';
    public const SCHEMA_VERSION_OPTION = 'homlity_property_index_schema_version';
    public const READY_OPTION = 'homlity_property_index_ready';
    public const SCHEMA_VERSION = 1;
    private static ?bool $readyMemo = null;

    public static function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function ensure(): void
    {
        if ((int) get_option(self::SCHEMA_VERSION_OPTION, 0) === self::SCHEMA_VERSION) {
            return;
        }
        self::createTable();
    }

    public static function createTable(): void
    {
        global $wpdb;

        $table = esc_sql(self::tableName());
        $sql = "CREATE TABLE {$table} (
            post_id BIGINT UNSIGNED NOT NULL,
            is_visible TINYINT(1) NOT NULL DEFAULT 0,
            price_sale DECIMAL(20,2) NULL,
            price_rent DECIMAL(20,2) NULL,
            display_price DECIMAL(20,2) NULL,
            bedrooms SMALLINT UNSIGNED NULL,
            bathrooms SMALLINT UNSIGNED NULL,
            parking SMALLINT UNSIGNED NULL,
            area DECIMAL(12,2) NULL,
            latitude DECIMAL(10,6) NULL,
            longitude DECIMAL(10,6) NULL,
            featured TINYINT(1) NOT NULL DEFAULT 0,
            agent_id BIGINT UNSIGNED NULL,
            code VARCHAR(100) NULL,
            sync_id VARCHAR(100) NULL,
            search_text TEXT NOT NULL,
            post_date DATETIME NOT NULL,
            indexed_at DATETIME NOT NULL,
            PRIMARY KEY (post_id),
            KEY visible_date (is_visible, post_date, post_id),
            KEY visible_price (is_visible, display_price),
            KEY price_sale (price_sale),
            KEY price_rent (price_rent),
            KEY bedrooms (bedrooms),
            KEY area (area),
            KEY geo (latitude, longitude),
            KEY code (code),
            KEY sync_id (sync_id),
            KEY agent_id (agent_id),
            FULLTEXT KEY search_text (search_text)
        ) {$wpdb->get_charset_collate()};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        if (!self::tableExists()) {
            // Leave the version unset so the next admin request retries creation.
            self::$readyMemo = null;
            return;
        }
        update_option(self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, true);
        delete_option(self::READY_OPTION);
        self::$readyMemo = null;
    }

    public static function tableExists(): bool
    {
        global $wpdb;
        $table = self::tableName();
        return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function isReady(): bool
    {
        if (self::$readyMemo !== null) {
            return self::$readyMemo;
        }
        self::$readyMemo = (int) get_option(self::SCHEMA_VERSION_OPTION, 0) === self::SCHEMA_VERSION
            && (int) get_option(self::READY_OPTION, 0) === self::SCHEMA_VERSION;
        return self::$readyMemo;
    }

    public static function resetMemo(): void
    {
        self::$readyMemo = null;
    }
}
