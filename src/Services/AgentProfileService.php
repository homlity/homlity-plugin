<?php
/**
 * Agent (asesor) profile pages.
 *
 * Every advisor has a public profile at their WordPress user URL,
 * /author/{user_nicename}/. The legacy /property-agent/{user_nicename}/ route
 * still resolves and 301s there, so links published before the move keep
 * working and only one URL is indexable.
 *
 * When a page is configured in `homlity_plugin_agent_profile_page_id` its
 * builder layout renders the profile: on the legacy route the rewrite resolves
 * straight to that page (Elementor, Divi and WPBakery render it as a regular
 * page), and on the author archive — which WordPress owns and cannot be
 * rewritten to a page without hijacking every author on the site — the same
 * layout is rendered inline by templates/property-agent.php.
 *
 * Only users who look like advisors are taken over: holding the advisor role
 * or at least one published property. A plain blog author keeps the theme's
 * own archive.
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

    /** Base path of the legacy profile URLs, kept as a 301 source. */
    public const ROUTE_BASE = 'property-agent';

    /**
     * Interruptor «Mostrar en la web» del perfil del asesor.
     *
     * Guarda '1' o '0'. Sin valor guardado el asesor se lista: el interruptor
     * llegó cuando los sitios ya tenían su plantilla montada, y estrenarlo
     * vaciando el listado de asesores sería peor que no tenerlo.
     */
    public const PUBLIC_META = '_homlity_agent_public';

    /** Resolved advisor for the current request, memoized. */
    private static ?WP_User $currentAgent = null;
    private static bool $currentAgentResolved = false;

    /** Per-request cache of qualifiesAsAgent(), keyed by user id. */
    private static array $qualifies = [];

    public function register(): void
    {
        add_action('template_redirect', [$this, 'redirectLegacyProfileUrl'], 0);
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
     * Whether the advisor profile lives at the native /author/{slug}/ URL.
     *
     * Filterable because some SEO plugins can be configured to disable author
     * archives site-wide; turning this off falls back to the /property-agent/
     * route for both the links and the 301.
     */
    public static function usesAuthorUrl(): bool
    {
        return (bool) apply_filters('homlity_agent_profile_use_author_url', true);
    }

    /**
     * True when the current request is an advisor profile URL, on either the
     * author archive or the legacy route.
     */
    public static function isAgentProfileRequest(): bool
    {
        if ((string) get_query_var(self::QUERY_VAR, '') !== '') {
            return true;
        }

        return self::isAuthorArchiveRequest();
    }

    /**
     * True when the request is the author archive of a user who qualifies as
     * an advisor. Non-advisor authors are deliberately left to the theme.
     */
    public static function isAuthorArchiveRequest(): bool
    {
        if (!self::usesAuthorUrl() || !function_exists('is_author') || !is_author()) {
            return false;
        }

        return self::authorArchiveAgent() instanceof WP_User;
    }

    /**
     * The advisor behind the current author archive, or null when the archive
     * belongs to someone who is not an advisor.
     */
    private static function authorArchiveAgent(): ?WP_User
    {
        $queried = get_queried_object();

        return $queried instanceof WP_User && self::qualifiesAsAgent($queried) ? $queried : null;
    }

    /**
     * Whether a user should get an advisor profile instead of the theme's
     * author archive.
     *
     * Holding the advisor role or at least one published property is what
     * separates an asesor from a plain blog author. The property check covers
     * CRM-synced advisors, who are often created without the role.
     */
    public static function qualifiesAsAgent(WP_User $user): bool
    {
        $userId = (int) $user->ID;
        if ($userId <= 0) {
            return false;
        }
        if (isset(self::$qualifies[$userId])) {
            return self::$qualifies[$userId];
        }

        $roles = array_map('strval', (array) $user->roles);
        $qualifies = in_array(CapabilityService::ROLE_ASSESSOR, $roles, true)
            || in_array(CapabilityService::LEGACY_ROLE_ASSESSOR, $roles, true)
            || self::propertyCount($userId) > 0;

        self::$qualifies[$userId] = (bool) apply_filters('homlity_user_is_agent', $qualifies, $user);

        return self::$qualifies[$userId];
    }

    /**
     * Si el asesor sale en los listados públicos del sitio.
     *
     * Un asesor que deja la inmobiliaria conserva sus inmuebles publicados
     * —siguen a la venta— y su rol, así que ni el recuento ni el rol sirven
     * para distinguirlo de quien sigue trabajando allí. Este interruptor sí.
     *
     * No afecta a la ficha del inmueble: quien atiende ese inmueble concreto
     * se sigue enseñando, porque la alternativa es una ficha sin nadie a quien
     * llamar. Lo que apaga es la aparición en los listados de asesores.
     */
    public static function isPubliclyListed(WP_User $agent): bool
    {
        $stored = get_user_meta($agent->ID, self::PUBLIC_META, true);
        $listed = ($stored === '' || $stored === null) ? true : ((string) $stored === '1');

        return (bool) apply_filters('homlity_agent_is_publicly_listed', $listed, $agent);
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
            self::$currentAgent = self::isAuthorArchiveRequest() ? self::authorArchiveAgent() : null;

            return self::$currentAgent;
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
        return self::normalizeUser($candidate) ?? self::currentAgent();
    }

    /**
     * Drop the per-request memoization.
     *
     * The resolved advisor and the advisor/not-advisor verdicts are cached for
     * the lifetime of the request; anything that serves more than one request
     * in a process (tests, WP-CLI loops) has to clear them between requests.
     */
    public static function resetRequestCache(): void
    {
        self::$currentAgent = null;
        self::$currentAgentResolved = false;
        self::$qualifies = [];
    }

    public static function currentAgentId(): int
    {
        $agent = self::currentAgent();

        return $agent instanceof WP_User ? (int) $agent->ID : 0;
    }

    // ── URLs ──────────────────────────────────────────────────────────────────

    /**
     * Public profile URL for an advisor (WP_User, user id or nicename).
     *
     * The advisor's own user URL, /author/{nicename}/, so the profile lives
     * where WordPress already publishes the person. Falls back to the legacy
     * route when author URLs are turned off, or when the argument is a bare
     * slug that matches no user (nothing to build an author URL from).
     */
    public static function profileUrl($agent): string
    {
        $user = self::normalizeUser($agent);

        if ($user instanceof WP_User && self::usesAuthorUrl()) {
            $url = self::authorUrl($user);
            if ($url !== '') {
                return $url;
            }
        }

        $slug = $user instanceof WP_User
            ? (string) $user->user_nicename
            : sanitize_title(is_string($agent) ? $agent : '');

        if ($slug === '') {
            return '';
        }

        return home_url('/' . self::ROUTE_BASE . '/' . $slug . '/');
    }

    /**
     * Legacy /property-agent/{slug}/ URL, kept for the 301 and as a fallback.
     */
    public static function legacyProfileUrl($agent): string
    {
        $user = self::normalizeUser($agent);
        $slug = $user instanceof WP_User
            ? (string) $user->user_nicename
            : sanitize_title(is_string($agent) ? $agent : '');

        return $slug === '' ? '' : home_url('/' . self::ROUTE_BASE . '/' . $slug . '/');
    }

    /** @param WP_User|int|string|null $agent */
    private static function normalizeUser($agent): ?WP_User
    {
        if ($agent instanceof WP_User) {
            return $agent;
        }
        if (is_numeric($agent) && (int) $agent > 0) {
            $user = get_user_by('id', (int) $agent);

            return $user instanceof WP_User ? $user : null;
        }
        if (is_string($agent) && $agent !== '') {
            $user = get_user_by('slug', sanitize_title($agent));

            return $user instanceof WP_User ? $user : null;
        }

        return null;
    }

    private static function authorUrl(WP_User $user): string
    {
        if (!function_exists('get_author_posts_url')) {
            return '';
        }

        $url = get_author_posts_url((int) $user->ID, (string) $user->user_nicename);

        return is_string($url) ? $url : '';
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
     * Origen de la foto del asesor: id de adjunto y URL, en ese orden de
     * preferencia —foto del CRM, plugins de avatar, gravatar y, como último
     * recurso, el logo de la empresa—.
     *
     * Existe aparte de avatarHtml() porque una etiqueta dinámica de Elementor
     * necesita el origen y no el marcado: el widget de imagen monta su propio
     * <img> con sus tamaños, su recorte y su carga diferida. La cadena de
     * preferencia se decide aquí una sola vez y avatarHtml() la reutiliza.
     *
     * @return array{id:int, url:string, source:string}
     */
    public static function avatarSource(WP_User $agent, int $size = 128): array
    {
        // La foto del CRM llega como id de adjunto o como URL suelta. Un id
        // cuyo adjunto ya no existe no cuenta como foto: se sigue bajando por
        // la lista en vez de dejar al asesor sin cara.
        $crmPhoto = (string) get_user_meta($agent->ID, '_homlity_advisor_photo', true);
        if ($crmPhoto !== '' && !is_numeric($crmPhoto)) {
            return ['id' => 0, 'url' => $crmPhoto, 'source' => 'crm'];
        }
        if (is_numeric($crmPhoto)) {
            $url = wp_get_attachment_image_url((int) $crmPhoto, 'medium');
            if (is_string($url) && $url !== '') {
                return ['id' => (int) $crmPhoto, 'url' => $url, 'source' => 'crm'];
            }
        }

        $wpUserAvatarId = (int) get_user_meta($agent->ID, 'wp_user_avatar', true);
        if ($wpUserAvatarId > 0) {
            $url = wp_get_attachment_image_url($wpUserAvatarId, 'medium');
            if (is_string($url) && $url !== '') {
                return ['id' => $wpUserAvatarId, 'url' => $url, 'source' => 'wp-user-avatar'];
            }
        }

        $simpleLocalAvatar = get_user_meta($agent->ID, 'simple_local_avatar', true);
        if (is_array($simpleLocalAvatar) && !empty($simpleLocalAvatar['full'])) {
            return [
                'id' => 0,
                'url' => (string) $simpleLocalAvatar['full'],
                'source' => 'simple-local-avatar',
            ];
        }

        if (function_exists('get_avatar_url')) {
            $url = get_avatar_url($agent->ID, ['size' => $size]);
            if (is_string($url) && $url !== '') {
                return ['id' => 0, 'url' => $url, 'source' => 'gravatar'];
            }
        }

        return [
            'id' => 0,
            'url' => (string) SeoGeoSettingsService::get('company_logo', ''),
            'source' => 'logo',
        ];
    }

    /**
     * Avatar markup resolving CRM photo → avatar plugins → WP avatar.
     */
    public static function avatarHtml(WP_User $agent, int $size = 128): string
    {
        $alt = esc_attr((string) $agent->display_name);
        $source = self::avatarSource($agent, $size);

        // El gravatar se pide con get_avatar() y no con su URL: es lo que
        // respeta el ajuste «Mostrar avatares», deja pasar los filtros de los
        // plugins de avatar y trae el srcset ya puesto.
        if ($source['source'] === 'gravatar') {
            $avatar = get_avatar($agent->ID, $size, '', (string) $agent->display_name);
            if (is_string($avatar) && $avatar !== '') {
                return $avatar;
            }
        }

        if ($source['id'] > 0) {
            $html = wp_get_attachment_image($source['id'], [$size, $size], false, ['alt' => $alt]);
            if (is_string($html) && $html !== '') {
                return $html;
            }
        }

        if ($source['url'] === '') {
            return '';
        }

        // El logo de la empresa no es cuadrado: darle el lado del avatar lo
        // deformaría, así que va sin medidas y lo dimensiona la hoja de estilos.
        $box = $source['source'] === 'logo'
            ? ''
            : ' width="' . (int) $size . '" height="' . (int) $size . '"';

        return '<img src="' . esc_url($source['url']) . '" alt="' . $alt . '"' . $box . '>';
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
     * Send the legacy /property-agent/{slug}/ URL to the advisor's user URL.
     *
     * Without this the same profile would answer on two paths and compete with
     * itself in the index. Runs before maybeSendNotFound() so a known advisor
     * is redirected rather than evaluated for a 404; an unknown slug falls
     * through and still 404s.
     */
    public function redirectLegacyProfileUrl(): void
    {
        if (is_admin() || !self::usesAuthorUrl()) {
            return;
        }
        if ((string) get_query_var(self::QUERY_VAR, '') === '') {
            return;
        }

        $agent = self::currentAgent();
        if (!$agent instanceof WP_User) {
            return;
        }

        $target = self::authorUrl($agent);
        if ($target === '') {
            return;
        }

        $paged = max(1, (int) get_query_var('paged'));
        if ($paged > 1) {
            $target = trailingslashit($target) . 'page/' . $paged . '/';
        }

        wp_safe_redirect($target, 301, 'Homlity Real Estate');
        exit;
    }

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
