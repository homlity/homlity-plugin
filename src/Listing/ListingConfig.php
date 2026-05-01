<?php
/**
 * Builder-agnostic value object that holds all configuration for a property listing.
 *
 * Each page builder integration (Elementor, WPBakery, Divi, shortcode) converts its own
 * settings format into a ListingConfig using the appropriate static factory method.
 * The renderer only deals with ListingConfig – it is completely unaware of which builder
 * produced the request.
 */

namespace Homlity\PluginInmobiliario\Listing;

if (!defined('ABSPATH')) {
    exit;
}

class ListingConfig
{
    // ── Defaults ─────────────────────────────────────────────────────────────

    private array $data = [
        'default_view'           => 'grid',   // 'grid' | 'map'
        'show_view_toggle'       => true,
        'columns'                => 3,
        'posts_per_page'         => 12,
        'orderby'                => 'date',   // 'date'|'price_asc'|'price_desc'|'title'
        'query_mode'             => 'custom', // 'custom' | 'current'
        'featured_only'          => false,
        'search_keyword'         => '',
        'preset_category'        => 0,
        'preset_operation'       => 0,
        'preset_type'            => 0,
        'preset_tag'             => 0,
        'preset_feature'         => 0,
        'preset_country'         => 0,
        'preset_state'           => 0,
        'preset_city'            => 0,
        'preset_neighborhood'    => 0,
        'preset_nearby'          => 0,
        'geo_latitude'           => '',
        'geo_longitude'          => '',
        'geo_radius_km'          => 0,
        'show_filters'           => true,
        'show_filter_operation'  => true,
        'show_filter_type'       => true,
        'show_filter_city'       => true,
        'show_filter_price'      => true,
        'show_filter_bedrooms'   => true,
        'show_sort'              => true,
        'map_height'             => 500,
        'map_zoom'               => 12,
        'template'               => 'default', // 'default' | 'bootstrap'
    ];

    // ── Private constructor – use static factories ────────────────────────────

    private function __construct() {}

    // ── Static factories ──────────────────────────────────────────────────────

    /**
     * Build from a plain key→value array (used internally and by AJAX).
     */
    public static function fromArray(array $raw): self
    {
        $config = new self();
        foreach ($raw as $key => $value) {
            if (array_key_exists($key, $config->data)) {
                $config->data[$key] = $value;
            }
        }
        return $config;
    }

    /**
     * Build from Elementor widget settings (get_settings_for_display result).
     */
    public static function fromElementor(array $settings): self
    {
        return self::fromArray([
            'default_view'          => sanitize_key($settings['default_view'] ?? 'grid'),
            'show_view_toggle'      => !empty($settings['show_view_toggle']),
            'columns'               => max(1, (int) ($settings['columns'] ?? 3)),
            'posts_per_page'        => max(1, (int) ($settings['posts_per_page'] ?? 12)),
            'orderby'               => sanitize_key($settings['default_orderby'] ?? 'date'),
            'query_mode'            => sanitize_key($settings['query_mode'] ?? 'custom'),
            'featured_only'         => !empty($settings['featured_only']),
            'search_keyword'        => sanitize_text_field($settings['search_keyword'] ?? ''),
            'preset_category'       => absint($settings['preset_category'] ?? 0),
            'preset_operation'      => absint($settings['preset_operation'] ?? 0),
            'preset_type'           => absint($settings['preset_type'] ?? 0),
            'preset_tag'            => absint($settings['preset_tag'] ?? 0),
            'preset_feature'        => absint($settings['preset_feature'] ?? 0),
            'preset_country'        => absint($settings['preset_country'] ?? 0),
            'preset_state'          => absint($settings['preset_state'] ?? 0),
            'preset_city'           => absint($settings['preset_city'] ?? 0),
            'preset_neighborhood'   => absint($settings['preset_neighborhood'] ?? 0),
            'preset_nearby'         => absint($settings['preset_nearby'] ?? 0),
            'geo_latitude'          => sanitize_text_field($settings['geo_latitude'] ?? ''),
            'geo_longitude'         => sanitize_text_field($settings['geo_longitude'] ?? ''),
            'geo_radius_km'         => max(0, (float) ($settings['geo_radius_km'] ?? 0)),
            'show_filters'          => !empty($settings['show_filters']),
            'show_filter_operation' => !empty($settings['show_filter_operation']),
            'show_filter_type'      => !empty($settings['show_filter_type']),
            'show_filter_city'      => !empty($settings['show_filter_city']),
            'show_filter_price'     => !empty($settings['show_filter_price']),
            'show_filter_bedrooms'  => !empty($settings['show_filter_bedrooms']),
            'show_sort'             => !empty($settings['show_sort']),
            'map_height'            => max(200, (int) ($settings['map_height']['size'] ?? 500)),
            'map_zoom'              => max(1, (int) ($settings['map_zoom'] ?? 12)),
            'template'              => self::sanitizeTemplate($settings['template'] ?? 'default'),
        ]);
    }

    /**
     * Build from shortcode attributes (WPBakery, Divi, [homlity_listing] shortcode).
     *
     * Attribute names are intentionally short and CSS-agnostic so they work
     * as HTML attributes inside any builder's text field.
     *
     * Example: [homlity_listing view="map" columns="2" template="bootstrap"]
     */
    public static function fromAtts(array $atts): self
    {
        $bool = static function ($value, bool $default): bool {
            if ($value === null || $value === '') {
                return $default;
            }
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        };

        return self::fromArray([
            'default_view'          => sanitize_key($atts['view'] ?? 'grid'),
            'show_view_toggle'      => $bool($atts['view_toggle'] ?? null, true),
            'columns'               => max(1, (int) ($atts['columns'] ?? 3)),
            'posts_per_page'        => max(1, (int) ($atts['per_page'] ?? 12)),
            'orderby'               => sanitize_key($atts['orderby'] ?? 'date'),
            'query_mode'            => sanitize_key($atts['query_mode'] ?? 'custom'),
            'featured_only'         => $bool($atts['featured'] ?? null, false),
            'search_keyword'        => sanitize_text_field($atts['search'] ?? ''),
            'preset_category'       => absint($atts['category'] ?? 0),
            'preset_operation'      => absint($atts['operation'] ?? 0),
            'preset_type'           => absint($atts['type'] ?? 0),
            'preset_tag'            => absint($atts['tag'] ?? 0),
            'preset_feature'        => absint($atts['feature'] ?? 0),
            'preset_country'        => absint($atts['country'] ?? 0),
            'preset_state'          => absint($atts['state'] ?? 0),
            'preset_city'           => absint($atts['city'] ?? 0),
            'preset_neighborhood'   => absint($atts['neighborhood'] ?? 0),
            'preset_nearby'         => absint($atts['nearby'] ?? 0),
            'geo_latitude'          => sanitize_text_field($atts['lat'] ?? ''),
            'geo_longitude'         => sanitize_text_field($atts['lng'] ?? ''),
            'geo_radius_km'         => max(0, (float) ($atts['radius_km'] ?? 0)),
            'show_filters'          => $bool($atts['filters'] ?? null, true),
            'show_filter_operation' => $bool($atts['filter_operation'] ?? null, true),
            'show_filter_type'      => $bool($atts['filter_type'] ?? null, true),
            'show_filter_city'      => $bool($atts['filter_city'] ?? null, true),
            'show_filter_price'     => $bool($atts['filter_price'] ?? null, true),
            'show_filter_bedrooms'  => $bool($atts['filter_bedrooms'] ?? null, true),
            'show_sort'             => $bool($atts['sort'] ?? null, true),
            'map_height'            => max(200, (int) ($atts['map_height'] ?? 500)),
            'map_zoom'              => max(1, (int) ($atts['map_zoom'] ?? 12)),
            'template'              => self::sanitizeTemplate($atts['template'] ?? 'default'),
        ]);
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function defaultView(): string  { return (string) $this->data['default_view']; }
    public function showViewToggle(): bool { return (bool)   $this->data['show_view_toggle']; }
    public function columns(): int         { return (int)    $this->data['columns']; }
    public function postsPerPage(): int    { return (int)    $this->data['posts_per_page']; }
    public function orderby(): string      { return (string) $this->data['orderby']; }
    public function queryMode(): string    { return (string) $this->data['query_mode']; }
    public function featuredOnly(): bool   { return (bool)   $this->data['featured_only']; }
    public function searchKeyword(): string { return (string) $this->data['search_keyword']; }
    public function presetCategory(): int  { return (int)    $this->data['preset_category']; }
    public function presetOperation(): int { return (int)    $this->data['preset_operation']; }
    public function presetType(): int      { return (int)    $this->data['preset_type']; }
    public function presetTag(): int       { return (int)    $this->data['preset_tag']; }
    public function presetFeature(): int   { return (int)    $this->data['preset_feature']; }
    public function presetCountry(): int   { return (int)    $this->data['preset_country']; }
    public function presetState(): int     { return (int)    $this->data['preset_state']; }
    public function presetCity(): int      { return (int)    $this->data['preset_city']; }
    public function presetNeighborhood(): int { return (int) $this->data['preset_neighborhood']; }
    public function presetNearby(): int    { return (int)    $this->data['preset_nearby']; }
    public function geoLatitude(): string  { return (string) $this->data['geo_latitude']; }
    public function geoLongitude(): string { return (string) $this->data['geo_longitude']; }
    public function geoRadiusKm(): float   { return (float)  $this->data['geo_radius_km']; }
    public function showFilters(): bool    { return (bool)   $this->data['show_filters']; }
    public function showFilterOperation(): bool { return (bool) $this->data['show_filter_operation']; }
    public function showFilterType(): bool      { return (bool) $this->data['show_filter_type']; }
    public function showFilterCity(): bool      { return (bool) $this->data['show_filter_city']; }
    public function showFilterPrice(): bool     { return (bool) $this->data['show_filter_price']; }
    public function showFilterBedrooms(): bool  { return (bool) $this->data['show_filter_bedrooms']; }
    public function showSort(): bool       { return (bool)   $this->data['show_sort']; }
    public function mapHeight(): int       { return (int)    $this->data['map_height']; }
    public function mapZoom(): int         { return (int)    $this->data['map_zoom']; }
    public function template(): string     { return (string) $this->data['template']; }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns the params array expected by PropertySearchService::buildQueryArgs().
     */
    public function toQueryParams(): array
    {
        return [
            'per_page'         => $this->postsPerPage(),
            'orderby'          => $this->orderby(),
            'query_mode'       => $this->queryMode(),
            'featured'         => $this->featuredOnly(),
            'search'           => $this->searchKeyword(),
            'preset_category'  => $this->presetCategory(),
            'preset_operation' => $this->presetOperation(),
            'preset_type'      => $this->presetType(),
            'preset_tag'       => $this->presetTag(),
            'preset_feature'   => $this->presetFeature(),
            'preset_country'   => $this->presetCountry(),
            'preset_state'     => $this->presetState(),
            'preset_city'      => $this->presetCity(),
            'preset_neighborhood' => $this->presetNeighborhood(),
            'preset_nearby'    => $this->presetNearby(),
            'geo_latitude'     => $this->geoLatitude(),
            'geo_longitude'    => $this->geoLongitude(),
            'geo_radius_km'    => $this->geoRadiusKm(),
        ];
    }

    /**
     * The card template filename to use for the current template mode.
     * Used by the AJAX handler to render individual cards.
     */
    public function cardTemplate(): string
    {
        return $this->template() === 'bootstrap'
            ? 'property-card-bootstrap.php'
            : 'property-card.php';
    }

    /**
     * The listing template filename to use.
     */
    public function listingTemplate(): string
    {
        return $this->template() === 'bootstrap'
            ? 'property-listing-bootstrap.php'
            : 'property-listing.php';
    }

    private static function sanitizeTemplate(string $value): string
    {
        return in_array($value, ['default', 'bootstrap'], true) ? $value : 'default';
    }
}
