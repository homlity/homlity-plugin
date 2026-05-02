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
        'show_sort'              => true,
        'show_results_count'     => true,
        'show_pagination'        => true,
        'map_height'             => 500,
        'map_zoom'               => 12,
        'template'               => 'default', // 'default' | 'bootstrap'
        'card_media_mode'        => 'single', // 'single' | 'slider'
        'card_visual_preset'     => 'default', // 'default' | 'cover_overlay' | 'minimal_light'
        'card_show_title'        => true,
        'card_show_excerpt'      => true,
        'card_show_operation'    => true,
        'card_show_price'        => true,
        'card_show_features'     => true,
        'card_show_whatsapp'     => true,
        'card_whatsapp_label'    => '',
        'card_feature_area'      => true,
        'card_feature_bedrooms'  => true,
        'card_feature_bathrooms' => true,
        'card_feature_parking'   => true,
        'card_feature_area_lot'  => true,
        'card_feature_area_private' => true,
        'card_feature_area_built' => true,
        'card_feature_age'       => true,
        'card_feature_condition' => true,
        'card_feature_code'      => true,
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
            'show_sort'             => !empty($settings['show_sort']),
            'show_results_count'    => !empty($settings['show_results_count']),
            'show_pagination'       => !empty($settings['show_pagination']),
            'map_height'            => max(200, (int) ($settings['map_height']['size'] ?? 500)),
            'map_zoom'              => max(1, (int) ($settings['map_zoom'] ?? 12)),
            'template'              => self::sanitizeTemplate($settings['template'] ?? 'default'),
            'card_media_mode'       => self::sanitizeMediaMode($settings['card_media_mode'] ?? 'single'),
            'card_visual_preset'    => self::sanitizeCardPreset($settings['card_visual_preset'] ?? 'default'),
            'card_show_title'       => !empty($settings['card_show_title']),
            'card_show_excerpt'     => !empty($settings['card_show_excerpt']),
            'card_show_operation'   => !empty($settings['card_show_operation']),
            'card_show_price'       => !empty($settings['card_show_price']),
            'card_show_features'    => !empty($settings['card_show_features']),
            'card_show_whatsapp'    => !empty($settings['card_show_whatsapp']),
            'card_whatsapp_label'   => sanitize_text_field($settings['card_whatsapp_label'] ?? ''),
            'card_feature_area'      => !empty($settings['card_feature_area']),
            'card_feature_bedrooms'  => !empty($settings['card_feature_bedrooms']),
            'card_feature_bathrooms' => !empty($settings['card_feature_bathrooms']),
            'card_feature_parking'   => !empty($settings['card_feature_parking']),
            'card_feature_area_lot'  => !empty($settings['card_feature_area_lot']),
            'card_feature_area_private' => !empty($settings['card_feature_area_private']),
            'card_feature_area_built' => !empty($settings['card_feature_area_built']),
            'card_feature_age'       => !empty($settings['card_feature_age']),
            'card_feature_condition' => !empty($settings['card_feature_condition']),
            'card_feature_code'      => !empty($settings['card_feature_code']),
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
            'show_sort'             => $bool($atts['sort'] ?? null, true),
            'show_results_count'    => $bool($atts['results_count'] ?? null, true),
            'show_pagination'       => $bool($atts['pagination'] ?? null, true),
            'map_height'            => max(200, (int) ($atts['map_height'] ?? 500)),
            'map_zoom'              => max(1, (int) ($atts['map_zoom'] ?? 12)),
            'template'              => self::sanitizeTemplate($atts['template'] ?? 'default'),
            'card_media_mode'       => self::sanitizeMediaMode($atts['card_media'] ?? 'single'),
            'card_visual_preset'    => self::sanitizeCardPreset($atts['card_preset'] ?? 'default'),
            'card_show_title'       => $bool($atts['card_title'] ?? null, true),
            'card_show_excerpt'     => $bool($atts['card_excerpt'] ?? null, true),
            'card_show_operation'   => $bool($atts['card_operation'] ?? null, true),
            'card_show_price'       => $bool($atts['card_price'] ?? null, true),
            'card_show_features'    => $bool($atts['card_features'] ?? null, true),
            'card_show_whatsapp'    => $bool($atts['card_whatsapp'] ?? null, true),
            'card_whatsapp_label'   => sanitize_text_field($atts['card_whatsapp_label'] ?? ''),
            'card_feature_area'      => $bool($atts['card_area'] ?? null, true),
            'card_feature_bedrooms'  => $bool($atts['card_bedrooms'] ?? null, true),
            'card_feature_bathrooms' => $bool($atts['card_bathrooms'] ?? null, true),
            'card_feature_parking'   => $bool($atts['card_parking'] ?? null, true),
            'card_feature_area_lot'  => $bool($atts['card_area_lot'] ?? null, true),
            'card_feature_area_private' => $bool($atts['card_area_private'] ?? null, true),
            'card_feature_area_built' => $bool($atts['card_area_built'] ?? null, true),
            'card_feature_age'       => $bool($atts['card_age'] ?? null, true),
            'card_feature_condition' => $bool($atts['card_condition'] ?? null, true),
            'card_feature_code'      => $bool($atts['card_code'] ?? null, true),
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
    public function showSort(): bool       { return (bool)   $this->data['show_sort']; }
    public function showResultsCount(): bool { return (bool) $this->data['show_results_count']; }
    public function showPagination(): bool { return (bool)   $this->data['show_pagination']; }
    public function mapHeight(): int       { return (int)    $this->data['map_height']; }
    public function mapZoom(): int         { return (int)    $this->data['map_zoom']; }
    public function template(): string     { return (string) $this->data['template']; }
    public function cardOptions(): array   { return [
        'media_mode' => (string) $this->data['card_media_mode'],
        'visual_preset' => (string) $this->data['card_visual_preset'],
        'show_title' => (bool) $this->data['card_show_title'],
        'show_excerpt' => (bool) $this->data['card_show_excerpt'],
        'show_operation' => (bool) $this->data['card_show_operation'],
        'show_price' => (bool) $this->data['card_show_price'],
        'show_features' => (bool) $this->data['card_show_features'],
        'show_whatsapp' => (bool) $this->data['card_show_whatsapp'],
        'whatsapp_label' => (string) $this->data['card_whatsapp_label'],
        'feature_area' => (bool) $this->data['card_feature_area'],
        'feature_bedrooms' => (bool) $this->data['card_feature_bedrooms'],
        'feature_bathrooms' => (bool) $this->data['card_feature_bathrooms'],
        'feature_parking' => (bool) $this->data['card_feature_parking'],
        'feature_area_lot' => (bool) $this->data['card_feature_area_lot'],
        'feature_area_private' => (bool) $this->data['card_feature_area_private'],
        'feature_area_built' => (bool) $this->data['card_feature_area_built'],
        'feature_age' => (bool) $this->data['card_feature_age'],
        'feature_condition' => (bool) $this->data['card_feature_condition'],
        'feature_code' => (bool) $this->data['card_feature_code'],
    ]; }

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

    private static function sanitizeMediaMode(string $value): string
    {
        return in_array($value, ['single', 'slider'], true) ? $value : 'single';
    }

    private static function sanitizeCardPreset(string $value): string
    {
        return in_array($value, ['default', 'cover_overlay', 'minimal_light'], true) ? $value : 'default';
    }
}
