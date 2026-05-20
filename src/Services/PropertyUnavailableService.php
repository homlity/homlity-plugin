<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
/**
 * Shows an "unavailable" page when a visitor lands on a property URL
 * whose post has been unpublished (draft, pending, private, future).
 *
 * WordPress normally returns 404 for those posts; this service intercepts
 * the 404 on /inmueble/{slug}, confirms the slug belonged to a known
 * property, and renders property-unavailable.php instead.
 *
 * Logged-in users with edit_posts capability are skipped so admins
 * can still preview drafts the normal way.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyUnavailableService implements ServiceInterface
{
    private const REWRITE_SLUG = 'inmueble';
    private const OPTION_UNAVAILABLE_TEMPLATE = 'homlity_plugin_unavailable_template_id';
    private const OPTION_UNAVAILABLE_LAYOUT = 'homlity_plugin_unavailable_page_layout';

    public function register(): void
    {
        // Priority 5: after PropertyCodeRoutingService (priority 1) so code-based
        // redirects run first; before the default WP 404 handler (priority 10).
        add_action('template_redirect', [$this, 'maybeShowUnavailable'], 5);
    }

    public function maybeShowUnavailable(): void
    {
        $isUnavailableSingle = is_singular(PropertyPostType::POST_TYPE) && $this->isCurrentPropertyUnavailable();
        if (!is_404() && !$isUnavailableSingle) {
            return;
        }

        // Let admins (and editors) continue to the normal 404 or draft preview.
        if (current_user_can('edit_posts')) {
            return;
        }

        if (!$isUnavailableSingle) {
            $slug = $this->extractSlugFromRequest();
            if ($slug === '') {
                return;
            }

            $post = $this->findUnpublishedProperty($slug);
            if (!$post) {
                return;
            }
        }

        // 410 Gone is semantically correct for content that existed but was removed.
        status_header(410);
        header('X-Robots-Tag: noindex, follow', true);
        nocache_headers();

        global $wp_query;
        $wp_query->is_404 = false; // prevent theme from rendering its own 404 header

        $this->renderUnavailableTemplate();
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns the URL slug when the path matches /inmueble/{slug}.
     * Returns empty string for any other URL pattern.
     */
    private function extractSlugFromRequest(): string
    {
        $uri  = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
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
     * {theme}/homlity-real-estate/property-unavailable.php.
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

    private function renderUnavailableTemplate(): void
    {
        $templateId = (int) get_option(self::OPTION_UNAVAILABLE_TEMPLATE, 0);
        $layout = (string) get_option(self::OPTION_UNAVAILABLE_LAYOUT, 'default');
        $isCanvasLayout = ($layout === 'elementor_canvas');

        if (
            $templateId > 0
            && get_post_status($templateId)
            && class_exists('\Elementor\Plugin')
        ) {
            if (!$isCanvasLayout) {
                get_header();
            }

            echo wp_kses_post( \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($templateId) );

            if (!$isCanvasLayout) {
                get_footer();
            }
            return;
        }

        include $this->locateTemplate('property-unavailable.php');
    }
}
