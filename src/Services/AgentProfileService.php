<?php
/**
 * Agent (asesor) profile pages.
 *
 * Every advisor linked to a property gets its own front page at
 * /property-agent/{user_nicename}/. When a page is configured in
 * `homlity_plugin_agent_profile_page_id`, the rewrite resolves to that page so
 * Elementor, Divi and WPBakery render it as a regular page — builder assets,
 * theme layout and editor preview included — while the advisor of the request
 * stays available to the widgets through this service.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

class AgentProfileService implements ServiceInterface
{
    public const QUERY_VAR  = 'property_agent';
    public const PAGE_OPTION = 'homlity_plugin_agent_profile_page_id';

    /** Base path of the profile URLs. */
    public const ROUTE_BASE = 'property-agent';

    /** Resolved advisor for the current request, memoized. */
    private static ?WP_User $currentAgent = null;
    private static bool $currentAgentResolved = false;

    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybeSendNotFound'], 1);
        add_filter('document_title_parts', [$this, 'filterDocumentTitle']);
        add_filter('body_class', [$this, 'filterBodyClass']);
        add_action('wp_head', [$this, 'renderHead'], 2);

        // SEO plugins own the title/canonical when active; without these the
        // profile of every advisor would share the builder page's metadata.
        add_filter('wpseo_title', [$this, 'filterSeoPluginTitle'], 20);
        add_filter('wpseo_canonical', [$this, 'filterSeoPluginCanonical'], 20);
        add_filter('wpseo_opengraph_url', [$this, 'filterSeoPluginCanonical'], 20);
        add_filter('rank_math/frontend/title', [$this, 'filterSeoPluginTitle'], 20);
        add_filter('rank_math/frontend/canonical', [$this, 'filterSeoPluginCanonical'], 20);
    }

    // ── Request context ───────────────────────────────────────────────────────

    /**
     * True when the current request is an advisor profile URL.
     */
    public static function isAgentProfileRequest(): bool
    {
        return (string) get_query_var(self::QUERY_VAR, '') !== '';
    }

    /**
     * The advisor of the current request, or null outside a profile page.
     */
    public static function currentAgent(): ?WP_User
    {
        if (self::$currentAgentResolved) {
            return self::$currentAgent;
        }

        self::$currentAgentResolved = true;
        self::$currentAgent = null;

        $slug = sanitize_title((string) get_query_var(self::QUERY_VAR, ''));
        if ($slug === '') {
            return null;
        }

        $user = get_user_by('slug', $slug);
        if ($user instanceof WP_User) {
            self::$currentAgent = $user;
        }

        return self::$currentAgent;
    }

    /**
     * Resolve an advisor from an explicit id/slug, falling back to the advisor
     * of the current request. Used by the builder widgets so a fixed advisor can
     * be previewed inside the editor.
     */
    public static function resolveAgent($candidate = null): ?WP_User
    {
        if (is_numeric($candidate) && (int) $candidate > 0) {
            $user = get_user_by('id', (int) $candidate);
            if ($user instanceof WP_User) {
                return $user;
            }
        }

        if (is_string($candidate) && $candidate !== '') {
            $user = get_user_by('slug', sanitize_title($candidate));
            if ($user instanceof WP_User) {
                return $user;
            }
        }

        return self::currentAgent();
    }

    public static function currentAgentId(): int
    {
        $agent = self::currentAgent();

        return $agent instanceof WP_User ? (int) $agent->ID : 0;
    }

    // ── URLs ──────────────────────────────────────────────────────────────────

    /**
     * Public profile URL for an advisor (WP_User, user id or nicename).
     */
    public static function profileUrl($agent): string
    {
        if ($agent instanceof WP_User) {
            $slug = (string) $agent->user_nicename;
        } elseif (is_numeric($agent)) {
            $user = get_user_by('id', (int) $agent);
            $slug = $user instanceof WP_User ? (string) $user->user_nicename : '';
        } else {
            $slug = sanitize_title((string) $agent);
        }

        if ($slug === '') {
            return '';
        }

        return home_url('/' . self::ROUTE_BASE . '/' . $slug . '/');
    }

    /**
     * Profile URL of the current request, including its pagination segment.
     */
    public static function canonicalUrl(?WP_User $agent = null): string
    {
        $agent = $agent ?? self::currentAgent();
        if (!$agent instanceof WP_User) {
            return '';
        }

        $url = self::profileUrl($agent);
        if ($url === '') {
            return '';
        }

        $paged = max(1, (int) get_query_var('paged'));

        return $paged > 1 ? trailingslashit($url) . 'page/' . $paged . '/' : $url;
    }

    /**
     * Page whose builder layout renders the profile, 0 when not configured.
     *
     * Only a published page counts: routing a public URL to a draft would 404
     * for every visitor. An unpublished selection falls back to the plugin
     * template until it goes live.
     */
    public static function pageId(): int
    {
        $pageId = (int) get_option(self::PAGE_OPTION, 0);
        if ($pageId <= 0) {
            return 0;
        }

        return get_post_status($pageId) === 'publish' ? $pageId : 0;
    }

    /**
     * True when the configured page is driven by a page builder, meaning the
     * builder itself must render the request instead of the plugin template.
     */
    public static function pageUsesBuilder(): bool
    {
        $pageId = self::pageId();
        if ($pageId <= 0) {
            return false;
        }

        if (get_post_meta($pageId, '_elementor_edit_mode', true) === 'builder' && defined('ELEMENTOR_VERSION')) {
            return true;
        }
        if (get_post_meta($pageId, '_et_pb_use_builder', true) === 'on') {
            return true;
        }
        if (get_post_meta($pageId, '_wpb_vc_js_status', true) === 'true') {
            return true;
        }

        return (string) get_post_meta($pageId, '_homlity_seeded_builder', true) !== '';
    }

    // ── Advisor data ──────────────────────────────────────────────────────────

    /**
     * Normalized advisor data used by the profile widgets and templates.
     *
     * @return array{
     *   id:int, name:string, role:string, phone:string, email:string,
     *   bio:string, website:string, profile_url:string, avatar_html:string,
     *   photo_url:string, property_count:int
     * }
     */
    public static function agentData(?WP_User $agent): array
    {
        if (!$agent instanceof WP_User) {
            return [
                'id' => 0,
                'name' => '',
                'role' => '',
                'phone' => '',
                'email' => '',
                'bio' => '',
                'website' => '',
                'profile_url' => '',
                'avatar_html' => '',
                'photo_url' => '',
                'property_count' => 0,
            ];
        }

        return [
            'id' => (int) $agent->ID,
            'name' => (string) $agent->display_name,
            'role' => self::agentRole($agent),
            'phone' => self::agentPhone($agent),
            'email' => (string) $agent->user_email,
            'bio' => (string) get_user_meta($agent->ID, 'description', true),
            'website' => (string) $agent->user_url,
            'profile_url' => self::profileUrl($agent),
            'avatar_html' => self::avatarHtml($agent),
            'photo_url' => self::photoUrl($agent),
            'property_count' => self::propertyCount((int) $agent->ID),
        ];
    }

    public static function agentRole(WP_User $agent): string
    {
        $role = (string) get_user_meta($agent->ID, '_homlity_advisor_role', true);

        return $role !== '' ? $role : (string) get_user_meta($agent->ID, 'homlity_advisor_role', true);
    }

    public static function agentPhone(WP_User $agent): string
    {
        foreach (['_homlity_advisor_phone', 'phone', 'billing_phone'] as $metaKey) {
            $phone = (string) get_user_meta($agent->ID, $metaKey, true);
            if (trim($phone) !== '') {
                return trim($phone);
            }
        }

        return '';
    }

    /**
     * Raw photo URL/attachment id stored by the CRM sync, when present.
     */
    public static function photoUrl(WP_User $agent): string
    {
        $photo = (string) get_user_meta($agent->ID, '_homlity_advisor_photo', true);
        if ($photo === '') {
            return '';
        }

        if (is_numeric($photo)) {
            $url = wp_get_attachment_image_url((int) $photo, 'medium');
            return is_string($url) ? $url : '';
        }

        return $photo;
    }

    /**
     * Avatar markup resolving CRM photo → avatar plugins → WP avatar.
     */
    public static function avatarHtml(WP_User $agent, int $size = 128): string
    {
        $alt = esc_attr((string) $agent->display_name);

        $crmPhoto = (string) get_user_meta($agent->ID, '_homlity_advisor_photo', true);
        if ($crmPhoto !== '') {
            if (is_numeric($crmPhoto)) {
                $html = wp_get_attachment_image((int) $crmPhoto, [$size, $size], false, ['alt' => $alt]);
                if (is_string($html) && $html !== '') {
                    return $html;
                }
            } else {
                return '<img src="' . esc_url($crmPhoto) . '" alt="' . $alt . '" width="' . (int) $size . '" height="' . (int) $size . '">';
            }
        }

        $wpUserAvatarId = (int) get_user_meta($agent->ID, 'wp_user_avatar', true);
        if ($wpUserAvatarId > 0) {
            $html = wp_get_attachment_image($wpUserAvatarId, [$size, $size], false, ['alt' => $alt]);
            if (is_string($html) && $html !== '') {
                return $html;
            }
        }

        $simpleLocalAvatar = get_user_meta($agent->ID, 'simple_local_avatar', true);
        if (is_array($simpleLocalAvatar) && !empty($simpleLocalAvatar['full'])) {
            return '<img src="' . esc_url((string) $simpleLocalAvatar['full']) . '" alt="' . $alt . '" width="' . (int) $size . '" height="' . (int) $size . '">';
        }

        $avatar = get_avatar($agent->ID, $size, '', (string) $agent->display_name);
        if (is_string($avatar) && $avatar !== '') {
            return $avatar;
        }

        $logoUrl = SeoGeoSettingsService::get('company_logo', '');

        return $logoUrl ? '<img src="' . esc_url((string) $logoUrl) . '" alt="' . $alt . '">' : '';
    }

    /**
     * Published & available properties assigned to the advisor.
     */
    public static function propertyCount(int $agentId): int
    {
        if ($agentId <= 0) {
            return 0;
        }

        $query = new \WP_Query([
            'post_type' => PropertyPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query' => [
                [
                    'key' => '_property_agent_id',
                    'value' => $agentId,
                ],
            ],
        ]);

        return (int) $query->found_posts;
    }

    /**
     * Advisors selectable in the builder widgets: every user holding at least
     * one property, plus every user with the advisor role.
     *
     * @return array<string,string> user id => label
     */
    public static function agentChoices(bool $includeEmptyOption = true): array
    {
        static $cache = null;

        if ($cache === null) {
            global $wpdb;

            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED)
                     FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE pm.meta_key = '_property_agent_id'
                       AND pm.meta_value REGEXP '^[0-9]+$'
                       AND CAST(pm.meta_value AS UNSIGNED) > 0
                       AND p.post_type = %s
                       AND p.post_status = 'publish'
                     LIMIT 500",
                    PropertyPostType::POST_TYPE
                )
            );
            $ids = array_values(array_filter(array_map('absint', (array) $ids)));

            foreach ([CapabilityService::ROLE_ASSESSOR, CapabilityService::LEGACY_ROLE_ASSESSOR] as $role) {
                $roleUsers = get_users([
                    'role' => $role,
                    'fields' => 'ID',
                    'number' => 500,
                ]);
                foreach ((array) $roleUsers as $roleUserId) {
                    $ids[] = (int) $roleUserId;
                }
            }

            $ids = array_values(array_unique(array_filter($ids)));

            $choices = [];
            foreach ($ids as $id) {
                $user = get_user_by('id', $id);
                if ($user instanceof WP_User) {
                    $choices[(string) $id] = (string) $user->display_name;
                }
            }
            natcasesort($choices);

            $cache = $choices;
        }

        return $includeEmptyOption
            ? ['' => __('— Sin filtrar por asesor —', 'homlity-real-estate')] + $cache
            : $cache;
    }

    // ── Front hooks ───────────────────────────────────────────────────────────

    /**
     * An unknown advisor slug must be a 404, never an empty profile page.
     */
    public function maybeSendNotFound(): void
    {
        if (is_admin() || !self::isAgentProfileRequest()) {
            return;
        }

        if (self::currentAgent() instanceof WP_User) {
            return;
        }

        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }

    /**
     * @param array<string,string> $parts
     * @return array<string,string>
     */
    public function filterDocumentTitle(array $parts): array
    {
        $agent = self::isAgentProfileRequest() ? self::currentAgent() : null;
        if (!$agent instanceof WP_User) {
            return $parts;
        }

        $parts['title'] = (string) $agent->display_name;
        unset($parts['tagline']);

        return $parts;
    }

    /**
     * @param string[] $classes
     * @return string[]
     */
    public function filterBodyClass(array $classes): array
    {
        if (self::isAgentProfileRequest()) {
            $classes[] = 'homlity-agent-profile';
        }

        return $classes;
    }

    /**
     * @param string $title
     */
    public function filterSeoPluginTitle($title)
    {
        $agent = self::isAgentProfileRequest() ? self::currentAgent() : null;

        return $agent instanceof WP_User ? (string) $agent->display_name : $title;
    }

    /**
     * @param string $canonical
     */
    public function filterSeoPluginCanonical($canonical)
    {
        $agent = self::isAgentProfileRequest() ? self::currentAgent() : null;
        if (!$agent instanceof WP_User) {
            return $canonical;
        }

        $url = self::canonicalUrl($agent);

        return $url !== '' ? $url : $canonical;
    }

    /**
     * Canonical URL of the advisor instead of the shared builder page URL.
     */
    public function renderHead(): void
    {
        $agent = self::isAgentProfileRequest() ? self::currentAgent() : null;
        if (!$agent instanceof WP_User) {
            return;
        }

        remove_action('wp_head', 'rel_canonical');

        $url = self::canonicalUrl($agent);
        if ($url === '') {
            return;
        }

        echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr((string) $agent->display_name) . '">' . "\n";
        echo '<meta property="og:type" content="profile">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";

        $photo = self::photoUrl($agent);
        if ($photo !== '') {
            echo '<meta property="og:image" content="' . esc_url($photo) . '">' . "\n";
        }
    }
}
