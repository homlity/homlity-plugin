<?php
/**
 * Technical sheet view of a property.
 *
 * The sheet lives at /ficha-tecnica/{property-slug}/ whenever a page is
 * configured in `homlity_plugin_sheet_page_id`: the rewrite resolves the
 * request to that page, so Elementor, Divi and WPBakery render it as a regular
 * page — builder assets, theme layout and editor preview included — while the
 * property of the request stays available to the widget through this service.
 *
 * Without a configured page the legacy `?homlity_sheet=1` URL keeps working
 * against the plugin template.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

class TechnicalSheetService implements ServiceInterface
{
    /** Slug of the property on the /ficha-tecnica/ route. */
    public const QUERY_VAR = 'homlity_sheet_property';

    /** Legacy flag: /inmueble/{slug}/?homlity_sheet=1 */
    public const LEGACY_VAR = 'homlity_sheet';

    public const PAGE_OPTION = 'homlity_plugin_sheet_page_id';

    /** Base path of the sheet URLs. */
    public const ROUTE_BASE = 'ficha-tecnica';

    /** Resolved property for the current request, memoized. */
    private static int $currentPropertyId = 0;
    private static bool $currentPropertyResolved = false;

    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybeSendNotFound'], 1);
        add_filter('document_title_parts', [$this, 'filterDocumentTitle']);
        add_filter('body_class', [$this, 'filterBodyClass']);
        add_action('wp_head', [$this, 'renderHead'], 2);
    }

    // ── Request context ───────────────────────────────────────────────────────

    /** True on /ficha-tecnica/{slug}/. */
    public static function isRouteRequest(): bool
    {
        return self::routeSlug() !== '';
    }

    /** True on the legacy ?homlity_sheet=1 URL. */
    public static function isLegacyRequest(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $flag = isset($_GET[self::LEGACY_VAR])
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ? sanitize_key(wp_unslash((string) $_GET[self::LEGACY_VAR]))
            : sanitize_key((string) get_query_var(self::LEGACY_VAR, ''));

        return $flag === '1';
    }

    public static function isSheetRequest(): bool
    {
        return self::isRouteRequest() || self::isLegacyRequest();
    }

    private static function routeSlug(): string
    {
        return sanitize_title((string) get_query_var(self::QUERY_VAR, ''));
    }

    /**
     * The property of the current request, 0 outside a sheet view.
     */
    public static function currentPropertyId(): int
    {
        if (self::$currentPropertyResolved) {
            return self::$currentPropertyId;
        }

        self::$currentPropertyResolved = true;
        self::$currentPropertyId = 0;

        $slug = self::routeSlug();
        if ($slug !== '') {
            $property = get_page_by_path($slug, OBJECT, PropertyPostType::POST_TYPE);
            if ($property instanceof WP_Post) {
                self::$currentPropertyId = (int) $property->ID;
            }

            return self::$currentPropertyId;
        }

        if (self::isLegacyRequest()) {
            $queriedId = (int) get_queried_object_id();
            if ($queriedId > 0 && get_post_type($queriedId) === PropertyPostType::POST_TYPE) {
                self::$currentPropertyId = $queriedId;
            }
        }

        return self::$currentPropertyId;
    }

    /**
     * Property for a widget: an explicit id wins, then the request, then the
     * post being rendered. Returns 0 when none of them is a property.
     */
    public static function resolvePropertyId(int $candidate = 0): int
    {
        if ($candidate > 0 && get_post_type($candidate) === PropertyPostType::POST_TYPE) {
            return $candidate;
        }

        $current = self::currentPropertyId();
        if ($current > 0) {
            return $current;
        }

        $queriedId = (int) get_queried_object_id();
        if ($queriedId > 0 && get_post_type($queriedId) === PropertyPostType::POST_TYPE) {
            return $queriedId;
        }

        $postId = (int) get_the_ID();

        return $postId > 0 && get_post_type($postId) === PropertyPostType::POST_TYPE ? $postId : 0;
    }

    /**
     * Newest published property, so the builder editors show a real sheet
     * instead of an empty widget while the page is being designed.
     */
    public static function previewPropertyId(): int
    {
        $ids = get_posts([
            'post_type' => PropertyPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return !empty($ids) ? (int) $ids[0] : 0;
    }

    // ── URLs ──────────────────────────────────────────────────────────────────

    /**
     * Public sheet URL of a property. Uses the builder route when a page is
     * configured, otherwise the legacy query-arg URL.
     */
    public static function sheetUrl(int $postId): string
    {
        $property = get_post($postId);
        if (!$property instanceof WP_Post || $property->post_type !== PropertyPostType::POST_TYPE) {
            return '';
        }

        if (self::pageId() > 0 && $property->post_name !== '') {
            return home_url('/' . self::ROUTE_BASE . '/' . $property->post_name . '/');
        }

        return add_query_arg(self::LEGACY_VAR, '1', (string) get_permalink($postId));
    }

    /**
     * URL that streams the sheet as a downloadable PDF (requires Dompdf).
     */
    public static function pdfUrl(int $postId): string
    {
        $url = self::sheetUrl($postId);

        return $url !== '' ? add_query_arg('download', '1', $url) : '';
    }

    /**
     * Whether a request to pdfUrl() actually returns a PDF.
     *
     * Without Dompdf the download URL falls through to the HTML sheet, so a
     * caller that promises a file has to check first: a link that saves an
     * .html to the visitor's downloads folder is worse than one that opens it.
     */
    public static function pdfAvailable(): bool
    {
        return (bool) apply_filters('homlity_technical_sheet_pdf_available', class_exists('\\Dompdf\\Dompdf'));
    }

    /**
     * Where the sheet button should point, and whether the browser will get a
     * file instead of a page.
     *
     * @return array{url:string,is_download:bool}
     */
    public static function buttonTarget(int $postId, bool $preferPdf = true): array
    {
        if ($preferPdf && self::pdfAvailable()) {
            $pdf = self::pdfUrl($postId);
            if ($pdf !== '') {
                return ['url' => $pdf, 'is_download' => true];
            }
        }

        return ['url' => self::sheetUrl($postId), 'is_download' => false];
    }

    /**
     * Page whose builder layout renders the sheet, 0 when not configured.
     *
     * Only a published page counts: routing a public URL to a draft would 404
     * for every visitor.
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

    // ── Front hooks ───────────────────────────────────────────────────────────

    /**
     * An unknown property slug must be a 404, never an empty sheet page.
     */
    public function maybeSendNotFound(): void
    {
        if (is_admin() || !self::isRouteRequest()) {
            return;
        }

        if (self::currentPropertyId() > 0) {
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
        $postId = self::isRouteRequest() ? self::currentPropertyId() : 0;
        if ($postId <= 0) {
            return $parts;
        }

        $parts['title'] = sprintf(
            /* translators: %s: property title */
            __('Ficha técnica · %s', 'homlity-real-estate'),
            (string) get_the_title($postId)
        );
        unset($parts['tagline']);

        return $parts;
    }

    /**
     * @param string[] $classes
     * @return string[]
     */
    public function filterBodyClass(array $classes): array
    {
        if (self::isSheetRequest()) {
            $classes[] = 'homlity-technical-sheet';
        }

        return $classes;
    }

    /**
     * The sheet duplicates the property detail, so it stays out of the index
     * while still passing link equity to the property itself.
     */
    public function renderHead(): void
    {
        $postId = self::isRouteRequest() ? self::currentPropertyId() : 0;
        if ($postId <= 0) {
            return;
        }

        echo '<meta name="robots" content="noindex,follow">' . "\n";
    }
}
