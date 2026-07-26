<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
/**
 * Allows properties to be accessed by their code in the URL.
 *
 * When a request like /inmueble/AW001PR results in a 404, this service:
 *   1. Looks up _property_code in the local database (fast path).
 *   2. If not found, asks every registered SyncProvider to fetch and create
 *      the property on demand from its external CRM (slow path).
 *   3. Issues a 301 redirect to the canonical permalink on success.
 *
 * External CRM sync plugins register themselves via SyncRegistry during the
 * 'homlity_plugin_register_sync_providers' action.
 *
 * Examples:
 *   /inmueble/AW001PR  →  301 → /inmueble/apartamento-norte-bogota/
 *   /inmueble/aw001pr  →  301 → /inmueble/apartamento-norte-bogota/  (case-insensitive)
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyCodeRoutingService implements ServiceInterface
{
    private const REWRITE_SLUG    = 'inmueble';
    private const META_CODE       = '_property_code';
    private const NEGATIVE_PREFIX = 'homlity_code_miss_';
    private const NEGATIVE_TTL    = 300; // 5 minutes
    private const OPTION_UNAVAILABLE_TEMPLATE = 'homlity_plugin_unavailable_template_id';
    private const OPTION_UNAVAILABLE_LAYOUT   = 'homlity_plugin_unavailable_page_layout';

    public function register(): void
    {
        add_action('template_redirect', [$this, 'redirectByPropertyCode'], 1);
    }

    public function redirectByPropertyCode(): void
    {
        if (!is_404()) {
            return;
        }

        $requestedCode = $this->extractCodeFromRequest();
        if ($requestedCode === '') {
            return;
        }

        $codes   = $this->propertyCodeCandidates($requestedCode);
        $post_id = 0;

        // 1. Fast path: check every compatible representation locally.
        foreach ($codes as $code) {
            $post_id = $this->findPropertyByCode($code);
            if ($post_id) {
                break;
            }
        }

        $syncResult = null;

        // 2. Slow path: ask registered CRM sync providers for each candidate.
        if (!$post_id) {
            foreach ($codes as $code) {
                if (get_transient(self::NEGATIVE_PREFIX . md5($code))) {
                    continue;
                }

                $syncResult = SyncRegistry::syncByCodeDetailed($code);
                $post_id    = (int) ($syncResult['post_id'] ?? 0);

                if ($post_id) {
                    break;
                }

                if ($this->shouldNegativeCacheResult($syncResult)) {
                    // Nothing found anywhere — cache the miss to avoid hammering providers.
                    set_transient(self::NEGATIVE_PREFIX . md5($code), 1, self::NEGATIVE_TTL);
                }
            }
        }

        if (!$post_id) {
            $this->renderNotFoundMessage($syncResult);
            return;
        }

        $url = get_permalink($post_id);
        if (!$url) {
            return;
        }

        wp_safe_redirect($url, 301);
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the trailing URL segment when the path matches /inmueble/{segment}.
     * Returns empty string if the URL doesn't look like a property code URL.
     */
    private function extractCodeFromRequest(): string
    {
        $uri  = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = trim((string) wp_parse_url((string) $uri, PHP_URL_PATH), '/');

        // Path must be exactly:  inmueble/{code}
        // (no extra segments, no sub-paths)
        $parts = array_values(array_filter(explode('/', $path)));

        if (count($parts) !== 2 || $parts[0] !== self::REWRITE_SLUG) {
            return '';
        }

        return sanitize_text_field($parts[1]);
    }

    /**
     * Returns compatible code representations for legacy URLs.
     *
     * Some integrations publish numeric codes prefixed with the agency or
     * branch identifier (for example, 503-6708), while the local property is
     * stored under the final code (6708). The complete value always has
     * priority so legitimate codes containing a hyphen keep working.
     *
     * @return string[]
     */
    private function propertyCodeCandidates(string $code): array
    {
        $candidates = [$code];

        if (preg_match('/^\d+-(\d+)$/', $code, $matches) === 1) {
            $candidates[] = $matches[1];
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Queries the database for a property whose _property_code matches $code.
     * MySQL's default utf8_general_ci collation makes this case-insensitive.
     *
     * @return int Post ID or 0 if not found.
     */
    private function findPropertyByCode(string $code): int
    {
        $posts = get_posts([
            'post_type'      => PropertyPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => self::META_CODE,
                    'value'   => $code,
                    'compare' => '=',
                ],
            ],
        ]);

        return !empty($posts) ? (int) $posts[0] : 0;
    }

    /**
     * Renderiza mensaje explícito para URLs de código cuando no se pudo resolver el inmueble.
     *
     * @param array<string,mixed>|null $syncResult
     */
    private function renderNotFoundMessage(?array $syncResult): void
    {
        $status = sanitize_key((string) ($syncResult['status'] ?? 'not_found'));
        $message = in_array($status, ['not_found', 'unavailable'], true)
            ? __('Inmueble no existe o no está disponible.', 'homlity-real-estate')
            : __('Inmueble no se pudo sincronizar.', 'homlity-real-estate');

        status_header(404);
        nocache_headers();

        global $wp_query;
        $wp_query->is_404 = false;

        UnavailablePropertyContext::activate();
        $hml_unavailable_message = $message;
        $hml_unavailable_reason = $status;

        if ($this->renderElementorUnavailableTemplate()) {
            exit;
        }

        $template = $this->locateTemplate('property-unavailable.php');
        if (file_exists($template)) {
            include $template;
            exit;
        }

        wp_die(esc_html($message), esc_html__('Inmueble', 'homlity-real-estate'), ['response' => 404]);
        exit;
    }

    private function renderElementorUnavailableTemplate(): bool
    {
        $templateId = (int) get_option(self::OPTION_UNAVAILABLE_TEMPLATE, 0);
        $layout     = (string) get_option(self::OPTION_UNAVAILABLE_LAYOUT, 'default');
        $isCanvas   = ($layout === 'elementor_canvas');

        if (
            $templateId <= 0
            || !get_post_status($templateId)
            || !class_exists('\Elementor\Plugin')
        ) {
            return false;
        }

        if (!$isCanvas) {
            get_header();
        }

        echo wp_kses_post(
            \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($templateId)
        );

        if (!$isCanvas) {
            get_footer();
        }

        return true;
    }

    private function locateTemplate(string $name): string
    {
        $themeOverride = get_stylesheet_directory() . '/homlity-real-estate/' . $name;
        if (file_exists($themeOverride)) {
            return $themeOverride;
        }

        return HOMLITY_PLUGIN_PATH . 'templates/' . $name;
    }

    /**
     * @param array<string,mixed>|null $syncResult
     */
    private function shouldNegativeCacheResult(?array $syncResult): bool
    {
        $status = sanitize_key((string) ($syncResult['status'] ?? 'not_found'));

        return in_array($status, ['not_found', 'unavailable'], true);
    }
}
