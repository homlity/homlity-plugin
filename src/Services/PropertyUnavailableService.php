<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shows a recovery or "unavailable" page when a visitor lands on a property
 * URL whose post is unpublished (draft, pending, private, future) OR whose
 * status/available meta marks it as inactive (sold, rented, retired).
 *
 * Recovery feature (default ON, option homlity_recovery_enabled = '1')
 * ─────────────────────────────────────────────────────────────────────
 * When enabled, this service delegates to RetiredPropertyRecoveryService to
 * determine the correct HTTP status (200/301/404/410), robots directives, and
 * a set of similar available properties, then renders property-recovery.php —
 * a rich landing page that preserves SEO authority and commercial intent.
 *
 * Legacy mode (homlity_recovery_enabled = '0')
 * ─────────────────────────────────────────────
 * Falls back to the original 410 + property-unavailable.php behavior, which
 * can also be replaced by an Elementor custom template via the plugin settings.
 *
 * SEO head integration
 * ─────────────────────
 * When the recovery feature is active, renderRecoverySeoHead() outputs:
 *   <meta name="robots">      (priority 1 in wp_head)
 *   <meta name="description"> (priority 1)
 *   <link rel="canonical">    (when status_code = 200)
 *
 * The standard SeoService schema (Residence + InStock Offer) is suppressed for
 * recovery pages via the $GLOBALS['homlity_property_recovery_context'] guard.
 *
 * Title/description filters are applied for WordPress native title as well as
 * Yoast SEO and Rank Math so any of the three outputs a contextual title.
 *
 * Logged-in users with edit_posts capability are always skipped so editors
 * can still preview drafts and inactive properties via the admin.
 *
 * Admin options
 * ─────────────
 * homlity_recovery_enabled           '1' (default) | '0'
 * homlity_recovery_min_results       int (default 3)
 * homlity_recovery_max_results       int (default 12)
 * homlity_recovery_price_tolerance   float (default 0.20)
 * homlity_recovery_area_tolerance    float (default 0.20)
 * homlity_recovery_no_results_action 'noindex' (default) | '404' | '410'
 * homlity_plugin_unavailable_template_id  Elementor template ID (legacy)
 * homlity_plugin_unavailable_page_layout  'elementor_canvas' | 'default' (legacy)
 */
class PropertyUnavailableService implements ServiceInterface
{
    private const REWRITE_SLUG              = 'inmueble';
    private const OPTION_UNAVAILABLE_TEMPLATE = 'homlity_plugin_unavailable_template_id';
    private const OPTION_UNAVAILABLE_LAYOUT   = 'homlity_plugin_unavailable_page_layout';

    public function register(): void
    {
        // Priority 5: after PropertyCodeRoutingService (priority 1) so code-based
        // redirects run first; before the default WP 404 handler (priority 10).
        add_action('template_redirect', [$this, 'maybeShowUnavailable'], 5);

        // SEO meta for recovery pages (fires before theme/plugin SEO outputs).
        add_action('wp_head', [$this, 'renderRecoverySeoHead'], 1);

        // Title override: WordPress native + Yoast SEO + Rank Math.
        add_filter('pre_get_document_title', [$this, 'filterRecoveryDocumentTitle'], 20);
        add_filter('wpseo_title',            [$this, 'filterRecoveryTitle'], 20);
        add_filter('wpseo_metadesc',         [$this, 'filterRecoveryDescription'], 20);
        add_filter('wpseo_robots',           [$this, 'filterRecoveryRobots'], 20);
        add_filter('rank_math/frontend/title',       [$this, 'filterRecoveryTitle'], 20);
        add_filter('rank_math/frontend/description', [$this, 'filterRecoveryDescription'], 20);
        add_filter('rank_math/robots',               [$this, 'filterRecoveryRobots'], 20);

        // Invalidate the cached recovery context whenever any property post is saved.
        add_action('save_post_' . PropertyPostType::POST_TYPE, static function (int $postId): void {
            RetiredPropertyRecoveryService::invalidateCache($postId);
        });
    }

    // ── template_redirect handler ─────────────────────────────────────────────

    public function maybeShowUnavailable(): void
    {
        $isUnavailableSingle = is_singular(PropertyPostType::POST_TYPE)
            && $this->isCurrentPropertyUnavailable();

        if (!is_404() && !$isUnavailableSingle) {
            return;
        }

        // Let admins/editors preview drafts and inactive properties.
        if (current_user_can('edit_posts')) {
            return;
        }

        // Resolve the property ID.
        if ($isUnavailableSingle) {
            $propertyId = (int) get_queried_object_id();
        } else {
            // is_404 path: try to match the URL to an unpublished property post.
            $slug = $this->extractSlugFromRequest();
            if ($slug === '') {
                return;
            }
            $post = $this->findUnpublishedProperty($slug);
            if (!$post) {
                return;
            }
            $propertyId = (int) $post->ID;
        }

        // ── Populate context registry (used by shortcodes in any template) ───
        UnavailablePropertyContext::setPropertyId($propertyId);

        // Enqueue listing assets now (before wp_head fires) so CSS/JS are
        // available whether the template is Elementor, PHP fallback or a page
        // built with the new [homlity_unavailable_similar_properties] shortcode.
        add_action('wp_enqueue_scripts', [ListingRenderer::class, 'enqueueAssets'], 1);

        // ── Recovery mode (default) ───────────────────────────────────────────
        $recoveryEnabled = (string) get_option('homlity_recovery_enabled', '1');
        if ($recoveryEnabled === '1') {
            $context = (new RetiredPropertyRecoveryService())->buildRecoveryContext($propertyId);

            // CASE D: explicit replacement → 301 redirect.
            if ($context['status_code'] === 301 && !empty($context['redirect_url'])) {
                wp_safe_redirect(esc_url_raw((string) $context['redirect_url']), 301);
                exit;
            }

            // Publish context in both the global (for property-recovery.php) and
            // the static registry (for shortcodes in Elementor pages).
            $GLOBALS['homlity_property_recovery_context'] = $context;
            UnavailablePropertyContext::setRecoveryContext($context);

            status_header((int) $context['status_code']);
            $robotsHeader = str_replace(',', ', ', (string) $context['robots']);
            header('X-Robots-Tag: ' . $robotsHeader, true);
            nocache_headers();

            global $wp_query;
            $wp_query->is_404 = false;

            $this->renderRecoveryTemplate($context);
            exit;
        }

        // ── Legacy mode ───────────────────────────────────────────────────────
        status_header(410);
        header('X-Robots-Tag: noindex, follow', true);
        nocache_headers();

        global $wp_query;
        $wp_query->is_404 = false;

        $this->renderUnavailableTemplate();
        exit;
    }

    // ── wp_head hooks ─────────────────────────────────────────────────────────

    /**
     * Output robots, description and canonical <meta> / <link> tags for
     * recovery pages. Fires at wp_head priority 1 (before all SEO plugins).
     */
    public function renderRecoverySeoHead(): void
    {
        $context = $GLOBALS['homlity_property_recovery_context'] ?? null;
        if (!is_array($context) || empty($context['is_retired'])) {
            return;
        }

        $robots = (string) ($context['robots'] ?? 'noindex,follow');
        $robots = str_replace(',', ', ', $robots);

        echo '<meta name="robots" content="' . esc_attr($robots) . '">' . "\n";

        $desc = trim((string) ($context['meta_description'] ?? ''));
        if ($desc !== '') {
            echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
        }

        // Self-canonical only when the page has enough results to be indexable.
        if (($context['canonical'] ?? '') === 'self' && (int) ($context['property_id'] ?? 0) > 0) {
            $canonicalUrl = get_permalink((int) $context['property_id']);
            if ($canonicalUrl) {
                echo '<link rel="canonical" href="' . esc_url($canonicalUrl) . '">' . "\n";
            }
        }
    }

    // ── Title / description / robots filters ──────────────────────────────────

    /** WordPress native title (pre_get_document_title). */
    public function filterRecoveryDocumentTitle(string $title): string
    {
        $context = $GLOBALS['homlity_property_recovery_context'] ?? null;
        if (!is_array($context) || empty($context['is_retired'])) {
            return $title;
        }
        $customTitle = trim((string) ($context['meta_title'] ?? ''));
        if ($customTitle === '') {
            return $title;
        }
        $siteName = get_bloginfo('name');
        return $siteName !== '' ? ($customTitle . ' | ' . $siteName) : $customTitle;
    }

    /** Yoast SEO title + Rank Math title. */
    public function filterRecoveryTitle(string $title): string
    {
        $context = $GLOBALS['homlity_property_recovery_context'] ?? null;
        if (!is_array($context) || empty($context['is_retired'])) {
            return $title;
        }
        $customTitle = trim((string) ($context['meta_title'] ?? ''));
        return $customTitle !== '' ? $customTitle : $title;
    }

    /** Yoast SEO description + Rank Math description. */
    public function filterRecoveryDescription(string $desc): string
    {
        $context = $GLOBALS['homlity_property_recovery_context'] ?? null;
        if (!is_array($context) || empty($context['is_retired'])) {
            return $desc;
        }
        $customDesc = trim((string) ($context['meta_description'] ?? ''));
        return $customDesc !== '' ? $customDesc : $desc;
    }

    /** Yoast SEO robots + Rank Math robots. */
    public function filterRecoveryRobots(string $robots): string
    {
        $context = $GLOBALS['homlity_property_recovery_context'] ?? null;
        if (!is_array($context) || empty($context['is_retired'])) {
            return $robots;
        }
        $customRobots = trim((string) ($context['robots'] ?? ''));
        return $customRobots !== '' ? $customRobots : $robots;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns the URL slug when the path matches /inmueble/{slug}.
     * Returns empty string for any other URL pattern.
     */
    private function extractSlugFromRequest(): string
    {
        $uri  = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash((string) $_SERVER['REQUEST_URI'])) : '';
        $path = trim((string) wp_parse_url((string) $uri, PHP_URL_PATH), '/');
        $parts = array_values(array_filter(explode('/', $path)));

        if (count($parts) !== 2 || $parts[0] !== self::REWRITE_SLUG) {
            return '';
        }

        return sanitize_title($parts[1]);
    }

    /**
     * Queries for a property whose post_name matches $slug in a non-published status.
     * Returns null if no matching property is found.
     */
    private function findUnpublishedProperty(string $slug): ?WP_Post
    {
        $posts = get_posts([
            'post_type'      => PropertyPostType::POST_TYPE,
            'name'           => $slug,
            'post_status'    => ['draft', 'pending', 'private', 'future'],
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ]);

        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Returns the template path, allowing theme overrides at
     * {theme}/homlity-real-estate/{name}.
     */
    private function locateTemplate(string $name): string
    {
        $themeOverride = get_stylesheet_directory() . '/homlity-real-estate/' . $name;
        if (file_exists($themeOverride)) {
            return $themeOverride;
        }

        return HOMLITY_PLUGIN_PATH . 'templates/' . $name;
    }

    private function isCurrentPropertyUnavailable(): bool
    {
        $postId = (int) get_queried_object_id();
        if ($postId <= 0) {
            return false;
        }

        // If status exists and it's not active, treat as unavailable.
        $status = strtolower(trim((string) get_post_meta($postId, '_property_status', true)));
        if ($status !== '' && $status !== 'active') {
            return true;
        }

        // If availability exists and is not an active truthy value, unavailable.
        $available = strtolower(trim((string) get_post_meta($postId, '_property_available', true)));
        if ($available !== '') {
            return !in_array($available, ['1', 'true', 'yes', 'active'], true);
        }

        return false;
    }

    /**
     * Render the rich recovery template (new default) or the Elementor custom
     * template if one is configured in the plugin settings.
     *
     * @param array<string,mixed> $context
     */
    private function renderRecoveryTemplate(array $context): void
    {
        $templateId = (int) get_option(self::OPTION_UNAVAILABLE_TEMPLATE, 0);
        $layout     = (string) get_option(self::OPTION_UNAVAILABLE_LAYOUT, 'default');

        // Elementor custom template (backward compat): the designer has full control.
        if (
            $templateId > 0
            && get_post_status($templateId)
            && class_exists('\Elementor\Plugin')
        ) {
            $isCanvas = ($layout === 'elementor_canvas');
            if (!$isCanvas) {
                get_header();
            }
            echo wp_kses_post(
                \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($templateId)
            );
            if (!$isCanvas) {
                get_footer();
            }
            return;
        }

        if ($templateId > 0 && get_post_status($templateId) && get_post_meta($templateId, '_homlity_seeded_builder', true) !== '') {
            $this->renderBuilderPage($templateId);
            return;
        }

        // New rich recovery template.
        include $this->locateTemplate('property-recovery.php');
    }

    /**
     * Legacy unavailable template rendering (used when recovery is disabled).
     */
    private function renderUnavailableTemplate(): void
    {
        $templateId = (int) get_option(self::OPTION_UNAVAILABLE_TEMPLATE, 0);
        $layout     = (string) get_option(self::OPTION_UNAVAILABLE_LAYOUT, 'default');
        $isCanvas   = ($layout === 'elementor_canvas');

        if (
            $templateId > 0
            && get_post_status($templateId)
            && class_exists('\Elementor\Plugin')
        ) {
            if (!$isCanvas) {
                get_header();
            }
            echo wp_kses_post(
                \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($templateId)
            );
            if (!$isCanvas) {
                get_footer();
            }
            return;
        }

        if ($templateId > 0 && get_post_status($templateId) && get_post_meta($templateId, '_homlity_seeded_builder', true) !== '') {
            $this->renderBuilderPage($templateId);
            return;
        }

        include $this->locateTemplate('property-unavailable.php');
    }

    private function renderBuilderPage(int $pageId): void
    {
        global $post;
        $previousPost = $post ?? null;
        $post = get_post($pageId);
        if (!$post instanceof \WP_Post) {
            return;
        }
        setup_postdata($post);
        get_header();
        echo apply_filters('the_content', $post->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        get_footer();
        wp_reset_postdata();
        $post = $previousPost;
    }
}
