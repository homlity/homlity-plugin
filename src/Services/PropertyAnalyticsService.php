<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
/**
 * Admin analytics dashboard for property visits.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyAnalyticsService implements ServiceInterface
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'homlity-real-estate',
            __('Analítica de visitas', 'homlity-real-estate'),
            __('Analítica de visitas', 'homlity-real-estate'),
            'edit_posts',
            'homlity-real-estate-analytics',
            [$this, 'renderPage']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        $isAnalyticsPage = isset($_GET['page']) && sanitize_key((string) $_GET['page']) === 'homlity-real-estate-analytics';
        if (!$isAnalyticsPage && strpos($hook, 'homlity-real-estate-analytics') === false) {
            return;
        }

        $cssFile = HOMLITY_PLUGIN_PATH . 'assets/css/analytics-app.css';
        $jsFile = HOMLITY_PLUGIN_PATH . 'assets/js/analytics-app.js';
        $cssVersion = file_exists($cssFile) ? (string) filemtime($cssFile) : HOMLITY_PLUGIN_VERSION;
        $jsVersion = file_exists($jsFile) ? (string) filemtime($jsFile) : HOMLITY_PLUGIN_VERSION;

        wp_enqueue_style(
            'homlity-real-estate-analytics-app',
            HOMLITY_PLUGIN_URL . 'assets/css/analytics-app.css',
            [],
            $cssVersion
        );

        wp_enqueue_script(
            'homlity-real-estate-analytics-app',
            HOMLITY_PLUGIN_URL . 'assets/js/analytics-app.js',
            ['wp-element', 'wp-api-fetch', 'wp-i18n'],
            $jsVersion,
            true
        );

        wp_localize_script('homlity-real-estate-analytics-app', 'homlityPluginAnalyticsApp', [
            'nonce' => wp_create_nonce('wp_rest'),
            'analyticsPath' => '/homlity-real-estate/v1/analytics/visits',
            'defaultRange' => 30,
            'ranges' => [1, 15, 30, 60, 90],
        ]);
    }

    public function renderPage(): void
    {
        ?>
        <div class="wrap">
            <?php $this->renderBanners(); ?>
            <div id="homlity-real-estate-analytics-app"></div>
        </div>
        <?php
    }

    private function renderBanners(): void
    {
        ?>
        <div class="homlity-admin-banners">
            <a href="https://homi.homlity.com/registro-inmobiliaria-gratis" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Registrarse gratis en Homlity', 'homlity-real-estate'); ?>">
                <img src="<?php echo esc_url(HOMLITY_PLUGIN_URL . 'assets/img/homlity.png'); ?>" alt="<?php esc_attr_e('Impulsa tu inmobiliaria con Homlity', 'homlity-real-estate'); ?>">
            </a>
            <a href="https://homi.homlity.com/register" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Registrarse gratis en el Directorio Inmobiliario de Homlity', 'homlity-real-estate'); ?>">
                <img src="<?php echo esc_url(HOMLITY_PLUGIN_URL . 'assets/img/directorio-inmobiliario.png'); ?>" alt="<?php esc_attr_e('Únete al Directorio Inmobiliario de Homlity', 'homlity-real-estate'); ?>">
            </a>
        </div>
        <?php
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('homlity-real-estate/v1', '/analytics/visits', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getAnalytics'],
            'permission_callback' => static function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function getAnalytics(\WP_REST_Request $request): \WP_REST_Response
    {
        $range = absint($request->get_param('range'));
        if (!in_array($range, [1, 15, 30, 60, 90], true)) {
            $range = 30;
        }
        $cacheKey = 'homlity_analytics_range_' . $range;
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return new \WP_REST_Response($cached);
        }

        global $wpdb;
        $sheetTableName = $wpdb->prefix . 'homlity_property_sheet_downloads';
        $daysAgoTs = strtotime('-' . $range . ' days');

        $totalVisits = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}homlity_property_visits WHERE created_ts >= %d",
            $daysAgoTs
        ));

        $totalUniqueVisitors = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT visitor_id) FROM {$wpdb->prefix}homlity_property_visits WHERE created_ts >= %d",
            $daysAgoTs
        ));

        $dailyRows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(visited_at) as day, COUNT(*) as visits
             FROM {$wpdb->prefix}homlity_property_visits
             WHERE created_ts >= %d
             GROUP BY DATE(visited_at)
             ORDER BY day ASC",
            $daysAgoTs
        ), ARRAY_A);

        $topPropertiesRows = $wpdb->get_results($wpdb->prepare(
            "SELECT v.property_id, COUNT(*) as visits
             FROM {$wpdb->prefix}homlity_property_visits v
             WHERE v.created_ts >= %d
             GROUP BY v.property_id
             ORDER BY visits DESC
             LIMIT 10",
            $daysAgoTs
        ), ARRAY_A);

        $propertyIds = array_values(array_filter(array_map(static function ($row) {
            return (int) ($row['property_id'] ?? 0);
        }, (array) $topPropertiesRows)));

        $propertyTitles = [];
        if ($propertyIds) {
            $titlePosts = get_posts([
                'post_type' => PropertyPostType::POST_TYPE,
                'post_status' => 'any',
                'post__in' => $propertyIds,
                'posts_per_page' => count($propertyIds),
                'orderby' => 'post__in',
                'fields' => 'ids',
                'no_found_rows' => true,
            ]);

            foreach ((array) $titlePosts as $titlePostId) {
                $titlePostId = (int) $titlePostId;
                $propertyTitles[$titlePostId] = (string) get_the_title($titlePostId);
            }
        }

        $topProperties = [];
        foreach ((array) $topPropertiesRows as $row) {
            $pid = (int) ($row['property_id'] ?? 0);
            $topProperties[] = [
                'property_id' => $pid,
                'title' => $propertyTitles[$pid] ?? ('#' . $pid),
                'visits' => (int) ($row['visits'] ?? 0),
            ];
        }

        $byTax = static function (string $taxonomy) use ($wpdb, $daysAgoTs): array {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT t.name as label, COUNT(*) as visits
                 FROM {$wpdb->prefix}homlity_property_visits v
                 INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = v.property_id
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
                 WHERE v.created_ts >= %d AND tt.taxonomy = %s
                 GROUP BY t.term_id, t.name
                 ORDER BY visits DESC
                 LIMIT 10",
                $daysAgoTs,
                $taxonomy
            ), ARRAY_A);

            $out = [];
            foreach ((array) $rows as $row) {
                $out[] = [
                    'label' => (string) ($row['label'] ?? ''),
                    'visits' => (int) ($row['visits'] ?? 0),
                ];
            }
            return $out;
        };

        $byType = $byTax(PropertyTaxonomies::TAXONOMY_TYPE);
        $byManagement = $byTax(PropertyTaxonomies::TAXONOMY_OPERATION);
        $byNeighborhood = $byTax(PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD);

        $daily = [];
        foreach ((array) $dailyRows as $row) {
            $daily[] = [
                'date' => (string) ($row['day'] ?? ''),
                'visits' => (int) ($row['visits'] ?? 0),
            ];
        }

        $contactTotalsRows = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) as clicks
             FROM {$wpdb->prefix}homlity_property_contact_clicks
             WHERE created_ts >= %d
             GROUP BY event_type",
            $daysAgoTs
        ), ARRAY_A);
        $contactTotals = ['whatsapp' => 0, 'phone' => 0, 'email' => 0];
        foreach ((array) $contactTotalsRows as $row) {
            $type = sanitize_key((string) ($row['event_type'] ?? ''));
            if (isset($contactTotals[$type])) {
                $contactTotals[$type] = (int) ($row['clicks'] ?? 0);
            }
        }
        $contactTotals['all'] = $contactTotals['whatsapp'] + $contactTotals['phone'] + $contactTotals['email'];

        $contactDailyRows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(clicked_at) as day, event_type, COUNT(*) as clicks
             FROM {$wpdb->prefix}homlity_property_contact_clicks
             WHERE created_ts >= %d
             GROUP BY DATE(clicked_at), event_type
             ORDER BY day ASC",
            $daysAgoTs
        ), ARRAY_A);

        $contactDailyMap = [];
        foreach ((array) $contactDailyRows as $row) {
            $day = (string) ($row['day'] ?? '');
            if ($day === '') {
                continue;
            }
            if (!isset($contactDailyMap[$day])) {
                $contactDailyMap[$day] = ['date' => $day, 'whatsapp' => 0, 'phone' => 0, 'email' => 0, 'total' => 0];
            }
            $type = sanitize_key((string) ($row['event_type'] ?? ''));
            $count = (int) ($row['clicks'] ?? 0);
            if (isset($contactDailyMap[$day][$type])) {
                $contactDailyMap[$day][$type] += $count;
            }
            $contactDailyMap[$day]['total'] += $count;
        }
        $contactDaily = array_values($contactDailyMap);

        $contactTopPropertiesRows = $wpdb->get_results($wpdb->prepare(
            "SELECT c.property_id, COUNT(*) as clicks
             FROM {$wpdb->prefix}homlity_property_contact_clicks c
             WHERE c.created_ts >= %d
             GROUP BY c.property_id
             ORDER BY clicks DESC
             LIMIT 10",
            $daysAgoTs
        ), ARRAY_A);

        $contactTopProperties = [];
        foreach ((array) $contactTopPropertiesRows as $row) {
            $pid = (int) ($row['property_id'] ?? 0);
            $contactTopProperties[] = [
                'property_id' => $pid,
                'title' => $propertyTitles[$pid] ?? ('#' . $pid),
                'visits' => (int) ($row['clicks'] ?? 0),
            ];
        }

        $advisorRows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value as advisor_id, c.property_id, COUNT(*) as clicks
             FROM {$wpdb->prefix}homlity_property_contact_clicks c
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = c.property_id AND pm.meta_key = %s
             WHERE c.created_ts >= %d
             GROUP BY pm.meta_value, c.property_id
             ORDER BY clicks DESC",
            '_property_agent_id',
            $daysAgoTs
        ), ARRAY_A);

        $advisorMap = [];
        foreach ((array) $advisorRows as $row) {
            $advisorId = (int) ($row['advisor_id'] ?? 0);
            $propertyId = (int) ($row['property_id'] ?? 0);
            $clicks = (int) ($row['clicks'] ?? 0);
            if ($advisorId <= 0 || $propertyId <= 0) {
                continue;
            }
            if (!isset($advisorMap[$advisorId])) {
                $user = get_user_by('id', $advisorId);
                $advisorMap[$advisorId] = [
                    'advisor_id' => $advisorId,
                    'advisor_name' => $user ? (string) $user->display_name : ('Asesor #' . $advisorId),
                    'total_clicks' => 0,
                    'properties' => [],
                ];
            }
            $advisorMap[$advisorId]['total_clicks'] += $clicks;
            $advisorMap[$advisorId]['properties'][] = [
                'property_id' => $propertyId,
                'title' => $propertyTitles[$propertyId] ?? ('#' . $propertyId),
                'clicks' => $clicks,
            ];
        }

        $advisorContacts = array_values($advisorMap);
        usort($advisorContacts, static function (array $a, array $b): int {
            return $b['total_clicks'] <=> $a['total_clicks'];
        });
        foreach ($advisorContacts as &$advisorContact) {
            usort($advisorContact['properties'], static function (array $a, array $b): int {
                return $b['clicks'] <=> $a['clicks'];
            });
            $advisorContact['properties'] = array_slice($advisorContact['properties'], 0, 10);
        }
        unset($advisorContact);
        $advisorContacts = array_slice($advisorContacts, 0, 10);

        $sheetTotals = [
            'total' => 0,
            'unique_visitors' => 0,
        ];
        $sheetDaily = [];
        $sheetExists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $sheetTableName)) === $sheetTableName;
        if ($sheetExists) {
            $sheetTotals['total'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}homlity_property_sheet_downloads WHERE created_ts >= %d",
                $daysAgoTs
            ));
            $sheetTotals['unique_visitors'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT visitor_id) FROM {$wpdb->prefix}homlity_property_sheet_downloads WHERE created_ts >= %d",
                $daysAgoTs
            ));
            $sheetDailyRows = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(downloaded_at) as day, COUNT(*) as downloads
                 FROM {$wpdb->prefix}homlity_property_sheet_downloads
                 WHERE created_ts >= %d
                 GROUP BY DATE(downloaded_at)
                 ORDER BY day ASC",
                $daysAgoTs
            ), ARRAY_A);
            foreach ((array) $sheetDailyRows as $row) {
                $sheetDaily[] = [
                    'date' => (string) ($row['day'] ?? ''),
                    'downloads' => (int) ($row['downloads'] ?? 0),
                ];
            }
        }

        $payload = [
            'range' => $range,
            'totals' => [
                'visits' => $totalVisits,
                'unique_visitors' => $totalUniqueVisitors,
            ],
            'daily' => $daily,
            'top_properties' => $topProperties,
            'by_type' => $byType,
            'by_management' => $byManagement,
            'by_neighborhood' => $byNeighborhood,
            'most_viewed_property' => $topProperties[0] ?? null,
            'most_viewed_management' => $byManagement[0] ?? null,
            'most_viewed_neighborhood' => $byNeighborhood[0] ?? null,
            'contact_clicks' => [
                'totals' => $contactTotals,
                'daily' => $contactDaily,
                'top_properties' => $contactTopProperties,
                'advisors' => $advisorContacts,
                'most_contacted_advisor' => $advisorContacts[0] ?? null,
            ],
            'technical_sheet_downloads' => [
                'totals' => $sheetTotals,
                'daily' => $sheetDaily,
            ],
        ];
        set_transient($cacheKey, $payload, 5 * MINUTE_IN_SECONDS);
        return new \WP_REST_Response($payload);
    }
}
