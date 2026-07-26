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
        'show_grid_view'         => true,
        'show_map_view'          => true,
        'show_view_toggle'       => true,
        'columns'                => 3,
        'posts_per_page'         => 12,
        'orderby'                => 'date',   // 'date'|'price_asc'|'price_desc'|'title'
        'query_mode'             => 'custom', // 'custom' | 'current' | 'related_current'
        'featured_only'          => false,
        'search_keyword'         => '',
        'preset_category'        => 0,
        'preset_operation'       => 0,
        'preset_type'            => 0,
        'preset_tag'             => 0,
        'preset_tag_ids'         => [],
        'use_current_property_tags' => false,
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
        'card_hover_effect'      => 'lift', // 'none' | 'lift' | 'zoom' | 'glow'
        'card_show_title'        => true,
        'card_show_excerpt'      => true,
        'card_show_operation'    => true,
        'card_show_price'        => true,
        'card_show_features'     => true,
        'card_show_whatsapp'     => true,
        'card_whatsapp_label'    => '',
        'card_whatsapp_show_icon' => true,
        'card_whatsapp_icon_position' => 'left',
        'card_whatsapp_icon' => ['value' => 'fab fa-whatsapp', 'library' => 'fa-brands'],
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
        'card_feature_icon_area' => ['value' => 'fas fa-ruler-combined', 'library' => 'fa-solid'],
        'card_feature_icon_bedrooms' => ['value' => 'fas fa-bed', 'library' => 'fa-solid'],
        'card_feature_icon_bathrooms' => ['value' => 'fas fa-bath', 'library' => 'fa-solid'],
        'card_feature_icon_parking' => ['value' => 'fas fa-car', 'library' => 'fa-solid'],
        'card_feature_icon_area_lot' => ['value' => 'fas fa-draw-polygon', 'library' => 'fa-solid'],
        'card_feature_icon_area_private' => ['value' => 'fas fa-house', 'library' => 'fa-solid'],
        'card_feature_icon_area_built' => ['value' => 'fas fa-ruler', 'library' => 'fa-solid'],
        'card_feature_icon_age' => ['value' => 'fas fa-clock', 'library' => 'fa-solid'],
        'card_feature_icon_condition' => ['value' => 'fas fa-circle-check', 'library' => 'fa-solid'],
        'card_feature_icon_code' => ['value' => 'fas fa-hashtag', 'library' => 'fa-solid'],
        'card_link_new_tab'      => false,

        // ── Related-properties mode ───────────────────────────────────────────
        'related_property_id'    => 0,       // 0 = auto-detect from current post
        'related_taxonomies'     => [],      // empty = all allowed taxonomies
        'related_strategy'       => 'any',   // 'any' | 'all' | 'tags_first'
        'related_fallback'       => 'recent', // 'recent' | 'same_city' | 'hide' | 'empty'
        'related_empty_message'  => '',
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
        return self::fromBuilderSettings($settings);
    }

    /**
     * Build from normalized visual-builder settings without coupling the
     * domain configuration to Elementor or Divi.
     */
    public static function fromBuilderSettings(array $settings): self
    {
        return self::fromArray([
            'default_view'          => sanitize_key($settings['default_view'] ?? 'grid'),
            'show_grid_view'        => !array_key_exists('show_grid_view', $settings) || !empty($settings['show_grid_view']),
            'show_map_view'         => !array_key_exists('show_map_view', $settings) || !empty($settings['show_map_view']),
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
            'preset_tag_ids'        => array_values(array_filter(array_map('absint', (array) ($settings['preset_tag_ids'] ?? [])))),
            'use_current_property_tags' => !empty($settings['use_current_property_tags']),
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
            'card_hover_effect'     => self::sanitizeHoverEffect($settings['card_hover_effect'] ?? 'lift'),
            'card_show_title'       => !empty($settings['card_show_title']),
            'card_show_excerpt'     => !empty($settings['card_show_excerpt']),
            'card_show_operation'   => !empty($settings['card_show_operation']),
            'card_show_price'       => !empty($settings['card_show_price']),
            'card_show_features'    => !empty($settings['card_show_features']),
            'card_show_whatsapp'    => !empty($settings['card_show_whatsapp']),
            'card_whatsapp_label'   => sanitize_text_field($settings['card_whatsapp_label'] ?? ''),
            'card_whatsapp_show_icon' => !empty($settings['card_whatsapp_show_icon']),
            'card_whatsapp_icon_position' => in_array(($settings['card_whatsapp_icon_position'] ?? 'left'), ['left', 'right'], true)
                ? ($settings['card_whatsapp_icon_position'] ?? 'left')
                : 'left',
            'card_whatsapp_icon' => self::normalizeElementorIcon($settings['card_whatsapp_icon'] ?? []),
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
            'card_feature_icon_area' => self::normalizeElementorIcon($settings['card_feature_icon_area'] ?? []),
            'card_feature_icon_bedrooms' => self::normalizeElementorIcon($settings['card_feature_icon_bedrooms'] ?? []),
            'card_feature_icon_bathrooms' => self::normalizeElementorIcon($settings['card_feature_icon_bathrooms'] ?? []),
            'card_feature_icon_parking' => self::normalizeElementorIcon($settings['card_feature_icon_parking'] ?? []),
            'card_feature_icon_area_lot' => self::normalizeElementorIcon($settings['card_feature_icon_area_lot'] ?? []),
            'card_feature_icon_area_private' => self::normalizeElementorIcon($settings['card_feature_icon_area_private'] ?? []),
            'card_feature_icon_area_built' => self::normalizeElementorIcon($settings['card_feature_icon_area_built'] ?? []),
            'card_feature_icon_age' => self::normalizeElementorIcon($settings['card_feature_icon_age'] ?? []),
            'card_feature_icon_condition' => self::normalizeElementorIcon($settings['card_feature_icon_condition'] ?? []),
            'card_feature_icon_code' => self::normalizeElementorIcon($settings['card_feature_icon_code'] ?? []),
            'card_link_new_tab'      => !empty($settings['card_link_new_tab']),
            // Related-properties mode
            'related_property_id'   => absint($settings['related_property_id'] ?? 0),
            'related_taxonomies'    => array_values(array_filter(array_map('sanitize_key', (array) ($settings['related_taxonomies'] ?? [])))),
            'related_strategy'      => self::sanitizeRelatedStrategy($settings['related_strategy'] ?? 'any'),
            'related_fallback'      => self::sanitizeRelatedFallback($settings['related_fallback'] ?? 'recent'),
            'related_empty_message' => sanitize_text_field($settings['related_empty_message'] ?? ''),
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
        $icon = static function ($value): array {
            if (is_array($value)) {
                return self::normalizeElementorIcon($value);
            }

            $value = sanitize_text_field((string) $value);
            return self::normalizeElementorIcon([
                'value'   => $value,
                'library' => $value !== '' ? 'divi' : '',
            ]);
        };

        return self::fromArray([
            'default_view'          => sanitize_key($atts['view'] ?? 'grid'),
            'show_grid_view'        => $bool($atts['show_grid_view'] ?? $atts['cards'] ?? null, true),
            'show_map_view'         => $bool($atts['show_map_view'] ?? $atts['map'] ?? null, true),
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
            'preset_tag_ids'        => [],
            'use_current_property_tags' => $bool($atts['current_property_tags'] ?? null, false),
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
            'card_hover_effect'     => self::sanitizeHoverEffect($atts['card_hover_effect'] ?? 'lift'),
            'card_show_title'       => $bool($atts['card_title'] ?? null, true),
            'card_show_excerpt'     => $bool($atts['card_excerpt'] ?? null, true),
            'card_show_operation'   => $bool($atts['card_operation'] ?? null, true),
            'card_show_price'       => $bool($atts['card_price'] ?? null, true),
            'card_show_features'    => $bool($atts['card_features'] ?? null, true),
            'card_show_whatsapp'    => $bool($atts['card_whatsapp'] ?? null, true),
            'card_whatsapp_label'   => sanitize_text_field($atts['card_whatsapp_label'] ?? ''),
            'card_whatsapp_show_icon' => $bool(
                $atts['card_whatsapp_show_icon'] ?? $atts['card_whatsapp_icon'] ?? null,
                true
            ),
            'card_whatsapp_icon_position' => in_array(($atts['card_whatsapp_icon_position'] ?? 'left'), ['left', 'right'], true)
                ? ($atts['card_whatsapp_icon_position'] ?? 'left')
                : 'left',
            'card_whatsapp_icon' => $icon($atts['card_whatsapp_icon_value'] ?? []),
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
            'card_feature_icon_area' => $icon($atts['card_feature_icon_area_value'] ?? $atts['card_feature_icon_area'] ?? []),
            'card_feature_icon_bedrooms' => $icon($atts['card_feature_icon_bedrooms_value'] ?? $atts['card_feature_icon_bedrooms'] ?? []),
            'card_feature_icon_bathrooms' => $icon($atts['card_feature_icon_bathrooms_value'] ?? $atts['card_feature_icon_bathrooms'] ?? []),
            'card_feature_icon_parking' => $icon($atts['card_feature_icon_parking_value'] ?? $atts['card_feature_icon_parking'] ?? []),
            'card_feature_icon_area_lot' => $icon($atts['card_feature_icon_area_lot_value'] ?? $atts['card_feature_icon_area_lot'] ?? []),
            'card_feature_icon_area_private' => $icon($atts['card_feature_icon_area_private_value'] ?? $atts['card_feature_icon_area_private'] ?? []),
            'card_feature_icon_area_built' => $icon($atts['card_feature_icon_area_built_value'] ?? $atts['card_feature_icon_area_built'] ?? []),
            'card_feature_icon_age' => $icon($atts['card_feature_icon_age_value'] ?? $atts['card_feature_icon_age'] ?? []),
            'card_feature_icon_condition' => $icon($atts['card_feature_icon_condition_value'] ?? $atts['card_feature_icon_condition'] ?? []),
            'card_feature_icon_code' => $icon($atts['card_feature_icon_code_value'] ?? $atts['card_feature_icon_code'] ?? []),
            'card_link_new_tab'      => $bool($atts['card_link_new_tab'] ?? null, false),
        ]);
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function defaultView(): string
    {
        $requested = (string) $this->data['default_view'];
        if ($requested === 'map' && $this->showMapView()) {
            return 'map';
        }
        if ($requested === 'grid' && $this->showGridView()) {
            return 'grid';
        }
        if ($this->showGridView()) {
            return 'grid';
        }
        if ($this->showMapView()) {
            return 'map';
        }
        return 'grid';
    }
    public function showGridView(): bool   { return (bool) $this->data['show_grid_view']; }
    public function showMapView(): bool    { return (bool) $this->data['show_map_view']; }
    public function showViewToggle(): bool
    {
        return (bool) $this->data['show_view_toggle']
            && $this->showGridView()
            && $this->showMapView();
    }
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
    public function presetTagIds(): array  { return array_values(array_filter(array_map('absint', (array) $this->data['preset_tag_ids']))); }
    public function useCurrentPropertyTags(): bool { return (bool) $this->data['use_current_property_tags']; }
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

    // Related-properties getters
    public function relatedPropertyId(): int      { return (int)    $this->data['related_property_id']; }
    public function relatedTaxonomies(): array    { return (array)  $this->data['related_taxonomies']; }
    public function relatedStrategy(): string     { return (string) $this->data['related_strategy']; }
    public function relatedFallback(): string     { return (string) $this->data['related_fallback']; }
    public function relatedEmptyMessage(): string { return (string) $this->data['related_empty_message']; }
    public function cardOptions(): array   { return [
        'media_mode' => (string) $this->data['card_media_mode'],
        'visual_preset' => (string) $this->data['card_visual_preset'],
        'hover_effect' => (string) $this->data['card_hover_effect'],
        'show_title' => (bool) $this->data['card_show_title'],
        'show_excerpt' => (bool) $this->data['card_show_excerpt'],
        'show_operation' => (bool) $this->data['card_show_operation'],
        'show_price' => (bool) $this->data['card_show_price'],
        'show_features' => (bool) $this->data['card_show_features'],
        'show_whatsapp' => (bool) $this->data['card_show_whatsapp'],
        'whatsapp_label' => (string) $this->data['card_whatsapp_label'],
        'whatsapp_show_icon' => (bool) $this->data['card_whatsapp_show_icon'],
        'whatsapp_icon_position' => (string) $this->data['card_whatsapp_icon_position'],
        'whatsapp_icon' => is_array($this->data['card_whatsapp_icon']) ? $this->data['card_whatsapp_icon'] : [],
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
        'feature_icon_area' => is_array($this->data['card_feature_icon_area']) ? $this->data['card_feature_icon_area'] : [],
        'feature_icon_bedrooms' => is_array($this->data['card_feature_icon_bedrooms']) ? $this->data['card_feature_icon_bedrooms'] : [],
        'feature_icon_bathrooms' => is_array($this->data['card_feature_icon_bathrooms']) ? $this->data['card_feature_icon_bathrooms'] : [],
        'feature_icon_parking' => is_array($this->data['card_feature_icon_parking']) ? $this->data['card_feature_icon_parking'] : [],
        'feature_icon_area_lot' => is_array($this->data['card_feature_icon_area_lot']) ? $this->data['card_feature_icon_area_lot'] : [],
        'feature_icon_area_private' => is_array($this->data['card_feature_icon_area_private']) ? $this->data['card_feature_icon_area_private'] : [],
        'feature_icon_area_built' => is_array($this->data['card_feature_icon_area_built']) ? $this->data['card_feature_icon_area_built'] : [],
        'feature_icon_age' => is_array($this->data['card_feature_icon_age']) ? $this->data['card_feature_icon_age'] : [],
        'feature_icon_condition' => is_array($this->data['card_feature_icon_condition']) ? $this->data['card_feature_icon_condition'] : [],
        'feature_icon_code' => is_array($this->data['card_feature_icon_code']) ? $this->data['card_feature_icon_code'] : [],
        'link_new_tab' => (bool) $this->data['card_link_new_tab'],
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
            'preset_tag_ids'   => $this->presetTagIds(),
            'use_current_property_tags' => $this->useCurrentPropertyTags(),
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

    private static function sanitizeHoverEffect(string $value): string
    {
        return in_array($value, ['none', 'lift', 'zoom', 'glow'], true) ? $value : 'lift';
    }

    private static function sanitizeCardPreset(string $value): string
    {
        return in_array($value, ['default', 'cover_overlay', 'minimal_light'], true) ? $value : 'default';
    }

    private static function sanitizeRelatedStrategy(string $value): string
    {
        return \in_array($value, ['any', 'all', 'tags_first'], true) ? $value : 'any';
    }

    private static function sanitizeRelatedFallback(string $value): string
    {
        return \in_array($value, ['recent', 'same_city', 'hide', 'empty'], true) ? $value : 'recent';
    }

    private static function normalizeElementorIcon($raw): array
    {
        $value = '';
        $library = '';
        if (is_array($raw)) {
            $value = isset($raw['value']) ? sanitize_text_field((string) $raw['value']) : '';
            $library = isset($raw['library']) ? sanitize_key((string) $raw['library']) : '';
        }

        return [
            'value' => $value,
            'library' => $library,
        ];
    }
}
