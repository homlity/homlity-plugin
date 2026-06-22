<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
/**
 * Tracks unique-ish property visits for non-authenticated users and provides reports.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyVisitTrackingService implements ServiceInterface
{
    private const TABLE_SUFFIX = 'homlity_property_visits';
    private const SCHEMA_VERSION_OPTION = 'homlity_property_visits_schema_version';
    private const SCHEMA_VERSION = 1;
    private const VISITOR_COOKIE = 'homlity_visitor_id';
    private const VISIT_COOLDOWN_SECONDS = 86400; // 24h per property per visitor

    public function register(): void
    {
        add_action('init', [$this, 'maybeCreateTable']);
        add_action('template_redirect', [$this, 'trackPropertyVisit']);
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
            ip_hash CHAR(64) NOT NULL DEFAULT '',
            ua_hash CHAR(64) NOT NULL DEFAULT '',
            visited_at DATETIME NOT NULL,
            created_ts INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY property_id (property_id),
            KEY visitor_id (visitor_id),
            KEY visited_at (visited_at),
            KEY property_visitor_time (property_id, visitor_id, created_ts)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option(self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, false);
    }

    public function trackPropertyVisit(): void
    {
        if (!self::isTrackingAllowed()) {
            return;
        }
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        if (is_user_logged_in() || !is_singular(PropertyPostType::POST_TYPE)) {
            return;
        }
        if (BotDetector::isBot()) {
            return;
        }

        $propertyId = (int) get_queried_object_id();
        if ($propertyId <= 0) {
            return;
        }

        $visitorId = $this->resolveVisitorId();
        if ($visitorId === '') {
            return;
        }

        if ($this->hasRecentVisitByCookie($propertyId)) {
            return;
        }
        if ($this->hasRecentVisitInDb($propertyId, $visitorId)) {
            $this->markVisitCookie($propertyId);
            return;
        }

        global $wpdb;
        $ipHash = hash('sha256', sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? ''))));
        $uaHash = hash('sha256', sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))));
        $nowTs = time();
        $visitedAt = current_time('mysql');

        $wpdb->insert(
            $this->tableName(),
            [
                'property_id' => $propertyId,
                'visitor_id' => $visitorId,
                'ip_hash' => $ipHash,
                'ua_hash' => $uaHash,
                'visited_at' => $visitedAt,
                'created_ts' => $nowTs,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d']
        );

        $this->markVisitCookie($propertyId);
    }

    /**
     * Returns aggregated visit data for property edit reports.
     *
     * @return array<string,mixed>
     */
    public static function getReportForProperty(int $propertyId): array
    {
        if ($propertyId <= 0) {
            return [
                'totals' => ['all' => 0, 'today' => 0, 'last7' => 0, 'last30' => 0, 'unique_visitors' => 0],
                'daily' => [],
                'recent' => [],
            ];
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $nowTs = time();
        $todayStart = strtotime(gmdate('Y-m-d 00:00:00', $nowTs));
        $last7Start = $nowTs - (7 * 86400);
        $last30Start = $nowTs - (30 * 86400);

        $all = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE property_id = %d",
            $propertyId
        ));
        $today = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE property_id = %d AND created_ts >= %d",
            $propertyId,
            $todayStart
        ));
        $last7 = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE property_id = %d AND created_ts >= %d",
            $propertyId,
            $last7Start
        ));
        $last30 = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE property_id = %d AND created_ts >= %d",
            $propertyId,
            $last30Start
        ));
        $uniqueVisitors = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT visitor_id) FROM {$table} WHERE property_id = %d",
            $propertyId
        ));

        $dailyRows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(visited_at) as day, COUNT(*) as visits
             FROM {$table}
             WHERE property_id = %d AND created_ts >= %d
             GROUP BY DATE(visited_at)
             ORDER BY day ASC",
            $propertyId,
            $last30Start
        ), ARRAY_A);

        $daily = [];
        if (is_array($dailyRows)) {
            foreach ($dailyRows as $row) {
                $daily[] = [
                    'date' => (string) ($row['day'] ?? ''),
                    'visits' => (int) ($row['visits'] ?? 0),
                ];
            }
        }

        $recentRows = $wpdb->get_results($wpdb->prepare(
            "SELECT visitor_id, visited_at
             FROM {$table}
             WHERE property_id = %d
             ORDER BY id DESC
             LIMIT 50",
            $propertyId
        ), ARRAY_A);

        $recent = [];
        if (is_array($recentRows)) {
            foreach ($recentRows as $row) {
                $visitor = (string) ($row['visitor_id'] ?? '');
                $recent[] = [
                    'visitor' => substr($visitor, 0, 10) . '…',
                    'visited_at' => (string) ($row['visited_at'] ?? ''),
                ];
            }
        }

        return [
            'totals' => [
                'all' => $all,
                'today' => $today,
                'last7' => $last7,
                'last30' => $last30,
                'unique_visitors' => $uniqueVisitors,
            ],
            'daily' => $daily,
            'recent' => $recent,
        ];
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

    private function hasRecentVisitByCookie(int $propertyId): bool
    {
        $cookieKey = $this->propertyVisitCookieKey($propertyId);
        $lastTs = (int) ($_COOKIE[$cookieKey] ?? 0);
        return $lastTs > 0 && (time() - $lastTs) < self::VISIT_COOLDOWN_SECONDS;
    }

    private function hasRecentVisitInDb(int $propertyId, string $visitorId): bool
    {
        global $wpdb;
        $minTs = time() - self::VISIT_COOLDOWN_SECONDS;
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tableName()} WHERE property_id = %d AND visitor_id = %s AND created_ts >= %d",
            $propertyId,
            $visitorId,
            $minTs
        ));
        return $count > 0;
    }

    private function markVisitCookie(int $propertyId): void
    {
        $this->setCookie($this->propertyVisitCookieKey($propertyId), (string) time(), time() + self::VISIT_COOLDOWN_SECONDS);
    }

    private function propertyVisitCookieKey(int $propertyId): string
    {
        return 'homlity_property_visit_' . $propertyId;
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

