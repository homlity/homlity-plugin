<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
/**
 * Tracks technical-sheet PDF downloads.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyTechnicalSheetDownloadTrackingService implements ServiceInterface
{
    private const TABLE_SUFFIX = 'homlity_property_sheet_downloads';
    private const SCHEMA_VERSION_OPTION = 'homlity_property_sheet_downloads_schema_version';
    private const SCHEMA_VERSION = 1;
    private const VISITOR_COOKIE = 'homlity_visitor_id';

    public function register(): void
    {
        add_action('init', [$this, 'maybeCreateTable']);
    }

    public function maybeCreateTable(): void
    {
        $version = (int) get_option(self::SCHEMA_VERSION_OPTION, 0);
        if ($version >= self::SCHEMA_VERSION) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            property_id BIGINT UNSIGNED NOT NULL,
            visitor_id VARCHAR(64) NOT NULL,
            ip_hash CHAR(64) NOT NULL DEFAULT '',
            ua_hash CHAR(64) NOT NULL DEFAULT '',
            downloaded_at DATETIME NOT NULL,
            created_ts INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY property_id (property_id),
            KEY visitor_id (visitor_id),
            KEY downloaded_at (downloaded_at),
            KEY property_time (property_id, created_ts)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option(self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, false);
    }

    public static function trackDownload(int $propertyId): void
    {
        if ($propertyId <= 0) {
            return;
        }
        if (!self::isTrackingAllowed()) {
            return;
        }
        if (BotDetector::isBot()) {
            return;
        }

        $visitorId = self::resolveVisitorId();
        if ($visitorId === '') {
            return;
        }

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . self::TABLE_SUFFIX,
            [
                'property_id' => $propertyId,
                'visitor_id' => $visitorId,
                'ip_hash' => hash('sha256', sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')))),
                'ua_hash' => hash('sha256', sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')))),
                'downloaded_at' => current_time('mysql'),
                'created_ts' => time(),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d']
        );
    }

    private static function isTrackingAllowed(): bool
    {
        $settings = (array) get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
        if (empty($settings['enable_analytics'])) {
            return false;
        }
        if (function_exists('wp_has_consent') && !wp_has_consent('statistics')) {
            return false;
        }
        return true;
    }

    private static function resolveVisitorId(): string
    {
        $existing = sanitize_text_field((string) ($_COOKIE[self::VISITOR_COOKIE] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        try {
            $id = bin2hex(random_bytes(16));
        } catch (\Throwable) {
            $id = wp_generate_password(32, false, false);
        }
        self::setCookie(self::VISITOR_COOKIE, $id, time() + (365 * 86400));

        return sanitize_text_field($id);
    }

    private static function setCookie(string $name, string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie($name, $value, [
            'expires' => $expires,
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

