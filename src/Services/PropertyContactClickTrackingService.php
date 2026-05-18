<?php
/**
 * Tracks unique contact-click events per property and visitor.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyContactClickTrackingService implements ServiceInterface
{
    private const TABLE_SUFFIX = 'homlity_property_contact_clicks';
    private const SCHEMA_VERSION_OPTION = 'homlity_property_contact_clicks_schema_version';
    private const SCHEMA_VERSION = 1;
    private const VISITOR_COOKIE = 'homlity_visitor_id';

    public function register(): void
    {
        add_action('init', [$this, 'maybeCreateTable']);
        add_action('wp_ajax_homlity_track_contact_click', [$this, 'trackContactClick']);
        add_action('wp_ajax_nopriv_homlity_track_contact_click', [$this, 'trackContactClick']);
    }

    public function maybeCreateTable(): void
    {
        $version = (int) get_option(self::SCHEMA_VERSION_OPTION, 0);
        if ($version >= self::SCHEMA_VERSION) {
            return;
        }

        global $wpdb;
        $table = $this->tableName();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            property_id BIGINT UNSIGNED NOT NULL,
            visitor_id VARCHAR(64) NOT NULL,
            event_type VARCHAR(16) NOT NULL,
            ip_hash CHAR(64) NOT NULL DEFAULT '',
            ua_hash CHAR(64) NOT NULL DEFAULT '',
            clicked_at DATETIME NOT NULL,
            created_ts INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_property_visitor_event (property_id, visitor_id, event_type),
            KEY property_id (property_id),
            KEY event_type (event_type),
            KEY clicked_at (clicked_at)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option(self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, false);
    }

    public function trackContactClick(): void
    {
        check_ajax_referer('homlity_contact_click_nonce', 'nonce');

        $propertyId = absint($_POST['property_id'] ?? 0);
        $eventType = sanitize_key((string) ($_POST['event_type'] ?? ''));
        if ($propertyId <= 0 || !in_array($eventType, ['whatsapp', 'phone', 'email'], true)) {
            wp_send_json_error(['message' => 'invalid_payload'], 400);
        }

        $visitorId = $this->resolveVisitorId();
        if ($visitorId === '') {
            wp_send_json_error(['message' => 'missing_visitor'], 400);
        }

        global $wpdb;
        $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $uaHash = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $nowTs = time();
        $clickedAt = current_time('mysql');

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$this->tableName()} (property_id, visitor_id, event_type, ip_hash, ua_hash, clicked_at, created_ts)
                 VALUES (%d, %s, %s, %s, %s, %s, %d)",
                $propertyId,
                $visitorId,
                $eventType,
                $ipHash,
                $uaHash,
                $clickedAt,
                $nowTs
            )
        );

        wp_send_json_success(['ok' => true]);
    }

    private function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    private function resolveVisitorId(): string
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
        $this->setCookie(self::VISITOR_COOKIE, $id, time() + (365 * 86400));
        return sanitize_text_field($id);
    }

    private function setCookie(string $name, string $value, int $expires): void
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
