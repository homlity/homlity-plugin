<?php
/**
 * Main Schema.org orchestrator.
 *
 * Hooks into wp_head, detects context, delegates to the right generator,
 * and prints a single <script type="application/ld+json"> block.
 *
 * Guards:
 *  - Runs only once (static $initialized flag).
 *  - Checks homlity_schema_enabled, homlity_schema_property_enabled, etc.
 *  - Never prints an empty @graph.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Schema_Manager
{
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // Priority 5 → runs before most SEO plugins (Rank Math / Yoast use 1–10).
        // Change to a higher number if you want Homlity schema to appear after them.
        add_action('wp_head', [__CLASS__, 'print_schema'], 5);
    }

    // ── Main entry point ──────────────────────────────────────────────────────

    public static function print_schema(): void
    {
        if (!self::is_enabled()) {
            return;
        }

        $post_type = Homlity_Schema_Helpers::post_type();

        if (is_singular($post_type)) {
            self::maybe_print_property_schema();
            return;
        }

        if (self::is_list_context()) {
            self::maybe_print_list_schema();
            return;
        }

        if (self::is_agency_context()) {
            self::maybe_print_agency_schema();
        }
    }

    // ── Enabled flags ─────────────────────────────────────────────────────────

    public static function is_enabled(): bool
    {
        return (bool) apply_filters(
            'homlity_schema_enabled',
            get_option('homlity_schema_enabled', '1') !== '0'
        );
    }

    public static function is_property_enabled(): bool
    {
        return (bool) apply_filters(
            'homlity_schema_property_enabled',
            get_option('homlity_schema_property_enabled', '1') !== '0'
        );
    }

    public static function is_list_enabled(): bool
    {
        return (bool) apply_filters(
            'homlity_schema_list_enabled',
            get_option('homlity_schema_list_enabled', '1') !== '0'
        );
    }

    public static function is_agency_enabled(): bool
    {
        return (bool) apply_filters(
            'homlity_schema_agency_enabled',
            get_option('homlity_schema_agency_enabled', '1') !== '0'
        );
    }

    // ── Context detection ─────────────────────────────────────────────────────

    private static function is_list_context(): bool
    {
        $post_type = Homlity_Schema_Helpers::post_type();

        // Post type archive (only when has_archive is true for the CPT)
        if (is_post_type_archive($post_type)) {
            return true;
        }

        // Related taxonomies
        $taxonomies = [
            Homlity_Schema_Helpers::type_taxonomy(),
            Homlity_Schema_Helpers::city_taxonomy(),
            Homlity_Schema_Helpers::zone_taxonomy(),
            Homlity_Schema_Helpers::operation_taxonomy(),
            'property_state',
            'property_country',
            'property_location',
        ];
        if (is_tax($taxonomies)) {
            return true;
        }

        // Configured catalog page (path match, ignores query string)
        $catalog = (string) get_option('homlity_schema_catalog_url', '');
        if ($catalog !== '' && self::path_matches($catalog)) {
            return true;
        }

        return false;
    }

    private static function is_agency_context(): bool
    {
        if (is_front_page() || is_home()) {
            return true;
        }

        $contact = (string) get_option('homlity_schema_contact_url', '');
        return $contact !== '' && self::path_matches($contact);
    }

    // ── Printers ──────────────────────────────────────────────────────────────

    private static function maybe_print_property_schema(): void
    {
        if (!self::is_property_enabled()) {
            return;
        }

        global $post;
        if (!$post || $post->post_status !== 'publish') {
            return;
        }

        $schema = new Homlity_Property_Schema();
        $graph  = $schema->get_graph((int) $post->ID);
        $graph  = apply_filters('homlity_schema_graph', $graph, 'property', (int) $post->ID);

        self::output($graph);
    }

    private static function maybe_print_list_schema(): void
    {
        if (!self::is_list_enabled()) {
            return;
        }

        $schema = new Homlity_Property_List_Schema();
        $graph  = $schema->get_graph();
        $graph  = apply_filters('homlity_schema_graph', $graph, 'list', 0);

        self::output($graph);
    }

    private static function maybe_print_agency_schema(): void
    {
        if (!self::is_agency_enabled()) {
            return;
        }

        $schema = new Homlity_Agency_Schema();
        $graph  = $schema->get_graph();
        $graph  = apply_filters('homlity_schema_graph', $graph, 'agency', 0);

        self::output($graph);
    }

    // ── Output ────────────────────────────────────────────────────────────────

    private static function output(array $graph): void
    {
        if (empty($graph)) {
            return;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@graph'   => array_values($graph),
        ];

        $html = '<script type="application/ld+json">' . "\n"
              . Homlity_Schema_Helpers::json($data) . "\n"
              . '</script>' . "\n";

        $html = (string) apply_filters('homlity_schema_output', $html, $data);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- deliberate JSON-LD, not HTML
        echo $html;
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    /** Compares only the URL path (ignores scheme, host, and query string). */
    private static function path_matches(string $target_url): bool
    {
        $current = (string) wp_parse_url(
            esc_url_raw(home_url(add_query_arg(null, null))),
            PHP_URL_PATH
        );
        $target  = (string) wp_parse_url($target_url, PHP_URL_PATH);

        return rtrim($current, '/') === rtrim($target, '/');
    }
}
