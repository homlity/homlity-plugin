<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
/**
 * Schema.org helpers — static utility methods shared across all schema generators.
 *
 * Uses the real meta keys and taxonomy slugs from the Homlity plugin as defaults.
 * Every key is overridable via apply_filters.
 */

if (!defined('ABSPATH')) {
    exit;
}

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

class Homlity_Schema_Helpers
{
    // ── Post type & taxonomy resolution ──────────────────────────────────────

    public static function post_type(): string
    {
        return (string) apply_filters('homlity_schema_property_post_type', 'property');
    }

    public static function type_taxonomy(): string
    {
        return (string) apply_filters('homlity_schema_property_type_taxonomy', 'property_type');
    }

    public static function city_taxonomy(): string
    {
        return (string) apply_filters('homlity_schema_city_taxonomy', 'property_city');
    }

    public static function zone_taxonomy(): string
    {
        return (string) apply_filters('homlity_schema_zone_taxonomy', 'property_neighborhood');
    }

    public static function operation_taxonomy(): string
    {
        return (string) apply_filters('homlity_schema_operation_taxonomy', 'property_operation');
    }

    // ── Meta key mapping ──────────────────────────────────────────────────────

    /**
     * Returns the filtered meta key for a logical field name.
     * Defaults match the actual Homlity plugin meta keys exactly.
     */
    public static function meta_key(string $field): string
    {
        static $map = null;
        if ($map === null) {
            $map = apply_filters('homlity_schema_meta_keys', [
                'price_sale'     => '_property_price_sale',
                'currency_sale'  => '_property_currency_sale',
                'price_rent'     => '_property_price_rent',
                'currency_rent'  => '_property_currency_rent',
                'price_admin'    => '_property_price_admin',
                'currency_admin' => '_property_currency_admin',
                'price_valid_until' => '_property_price_valid_until',
                'area'           => '_property_area',
                'area_lot'       => '_property_area_lot',
                'area_private'   => '_property_area_private',
                'area_built'     => '_property_area_built',
                'bedrooms'       => '_property_bedrooms',
                'bathrooms'      => '_property_bathrooms',
                'parking'        => '_property_parking',
                'stratum'        => '_property_stratum',
                'floor'          => '_property_floor',
                'levels'         => '_property_levels',
                'elevators'      => '_property_elevators',
                'condition'      => '_property_condition',
                'year_built'     => '_property_age',   // plugin stores the year in _property_age
                'code'           => '_property_code',
                'address'        => '_property_address',
                'latitude'       => '_property_latitude',
                'longitude'      => '_property_longitude',
                'admin_included' => '_property_admin_included',
                'gallery'        => '_property_gallery',
                'featured'       => '_property_featured',
                'agent_id'       => '_property_agent_id',
                'agent_phone'    => '_property_agent_phone',
                'agent_email'    => '_property_agent_email',
            ]);
        }
        return isset($map[$field]) ? (string) $map[$field] : '';
    }

    public static function get_meta(int $post_id, string $field): string
    {
        $key = self::meta_key($field);
        return $key !== '' ? (string) get_post_meta($post_id, $key, true) : '';
    }

    // ── Property type → Schema.org type ──────────────────────────────────────

    /**
     * Maps a property_type taxonomy slug to the appropriate Schema.org @type.
     * Filterable via homlity_schema_property_type_map and homlity_schema_property_schema_type.
     */
    public static function schema_type(int $post_id): string
    {
        $map = apply_filters('homlity_schema_property_type_map', [
            'apartamento'           => 'Apartment',
            'apartaestudio'         => 'Apartment',
            'casa'                  => 'House',
            'casa-campestre'        => 'House',
            'casa-unifamiliar'      => 'SingleFamilyResidence',
            'habitacion'            => 'Room',
            'habitación'            => 'Room',
            'oficina'               => 'Place',
            'local'                 => 'Place',
            'local-comercial'       => 'Place',
            'bodega'                => 'Place',
            'lote'                  => 'Place',
            'terreno'               => 'Place',
            'finca'                 => 'Residence',
            'proyecto'              => 'ApartmentComplex',
            'proyecto-inmobiliario' => 'ApartmentComplex',
            'penthouse'             => 'Apartment',
        ]);

        $type = 'Residence'; // fallback

        $terms = get_the_terms($post_id, self::type_taxonomy());
        if (is_array($terms) && !empty($terms)) {
            $slug = sanitize_title(reset($terms)->slug);
            if (isset($map[$slug])) {
                $type = $map[$slug];
            }
        }

        return (string) apply_filters('homlity_schema_property_schema_type', $type, $post_id);
    }

    // ── Operation detection ───────────────────────────────────────────────────

    /** Returns 'sale', 'rent', 'both', or ''. */
    public static function operation(int $post_id): string
    {
        $terms = get_the_terms($post_id, self::operation_taxonomy());
        if (!is_array($terms) || empty($terms)) {
            return '';
        }

        $haystack = implode(' ', array_map(
            fn($t) => strtolower($t->slug . ' ' . $t->name),
            $terms
        ));

        $is_sale = preg_match('/\b(venta|sale|sell|compra|compraventa)\b/', $haystack) === 1;
        $is_rent = preg_match('/\b(arriendo|alquiler|rent|lease|arrendamiento)\b/', $haystack) === 1;

        if ($is_sale && $is_rent) {
            return 'both';
        }
        return $is_sale ? 'sale' : ($is_rent ? 'rent' : '');
    }

    public static function business_function(string $operation): string
    {
        return $operation === 'rent'
            ? 'http://purl.org/goodrelations/v1#LeaseOut'
            : 'http://purl.org/goodrelations/v1#Sell';
    }

    // ── Price & currency ──────────────────────────────────────────────────────

    public static function price(int $post_id): string
    {
        $op = self::operation($post_id);
        $raw = $op === 'rent'
            ? self::get_meta($post_id, 'price_rent')
            : self::get_meta($post_id, 'price_sale');

        // Fallback to the other price type
        if ($raw === '') {
            $raw = $op === 'rent'
                ? self::get_meta($post_id, 'price_sale')
                : self::get_meta($post_id, 'price_rent');
        }

        return $raw !== '' ? (string) preg_replace('/[^0-9.]/', '', $raw) : '';
    }

    public static function currency(int $post_id): string
    {
        $op  = self::operation($post_id);
        $cur = $op === 'rent'
            ? self::get_meta($post_id, 'currency_rent')
            : self::get_meta($post_id, 'currency_sale');

        if ($cur === '') {
            $cur = $op === 'rent'
                ? self::get_meta($post_id, 'currency_sale')
                : self::get_meta($post_id, 'currency_rent');
        }
        if ($cur === '') {
            $cur = (string) get_option('homlity_schema_currency', 'COP');
        }

        return strtoupper($cur);
    }

    /** Returns a validated ISO date or an empty string when no validity is set. */
    public static function price_valid_until(int $post_id): string
    {
        $date = (string) apply_filters(
            'homlity_schema_price_valid_until',
            self::get_meta($post_id, 'price_valid_until'),
            $post_id
        );
        $date = trim($date);
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed instanceof \DateTimeImmutable && $parsed->format('Y-m-d') === $date
            ? $date
            : '';
    }

    // ── Availability ──────────────────────────────────────────────────────────

    public static function availability(int $post_id): string
    {
        $post = get_post($post_id);
        if ($post && $post->post_status === 'publish') {
            return 'https://schema.org/InStock';
        }
        return 'https://schema.org/SoldOut';
    }

    // ── Floor size ────────────────────────────────────────────────────────────

    public static function floor_size(int $post_id): float
    {
        foreach (['area_built', 'area_private', 'area'] as $field) {
            $v = self::get_meta($post_id, $field);
            if ($v !== '' && (float) $v > 0) {
                return (float) $v;
            }
        }
        return 0.0;
    }

    // ── Postal address ────────────────────────────────────────────────────────

    /**
     * Returns the public, coarse location of a property.
     *
     * Deliberately excludes the value stored in _property_address. Property
     * listings must never expose their exact street address in JSON-LD; city,
     * region and country provide enough geographic context for search engines.
     */
    public static function postal_address(int $post_id): array
    {
        $city_terms    = get_the_terms($post_id, self::city_taxonomy());
        $state_terms   = get_the_terms($post_id, 'property_state');
        $country_terms = get_the_terms($post_id, 'property_country');

        $city    = is_array($city_terms) && !empty($city_terms)    ? self::clean(reset($city_terms)->name)    : '';
        $state   = is_array($state_terms) && !empty($state_terms)  ? self::clean(reset($state_terms)->name)   : '';
        $country = is_array($country_terms) && !empty($country_terms) ? self::clean(reset($country_terms)->name) : '';

        if ($city === '' && $state === '' && $country === '') {
            return [];
        }

        return self::drop_empty([
            '@type'           => 'PostalAddress',
            'addressLocality' => $city,
            'addressRegion'   => $state,
            'addressCountry'  => $country,
        ]);
    }

    // ── Neighborhood ─────────────────────────────────────────────────────────

    /** Returns the public neighborhood as the Place containing the property. */
    public static function neighborhood(int $post_id): array
    {
        $terms = get_the_terms($post_id, self::zone_taxonomy());
        if (!is_array($terms) || empty($terms)) {
            return [];
        }

        $name = self::clean(reset($terms)->name);
        if ($name === '') {
            return [];
        }

        $place = [
            '@type' => 'Place',
            'name'  => $name,
        ];

        return (array) apply_filters('homlity_schema_property_neighborhood', $place, $post_id);
    }

    // ── Geo coordinates ───────────────────────────────────────────────────────

    public static function geo(int $post_id): array
    {
        $raw_lat = self::get_meta($post_id, 'latitude');
        $raw_lng = self::get_meta($post_id, 'longitude');

        if (!is_numeric($raw_lat) || !is_numeric($raw_lng)) {
            return [];
        }

        $lat = (float) $raw_lat;
        $lng = (float) $raw_lng;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return [];
        }

        // Two decimals describe an approximate area of about 1 km instead of
        // publishing the exact location. Sites can opt into another precision.
        $precision = (int) apply_filters('homlity_schema_geo_precision', 2, $post_id);
        $precision = max(0, min(6, $precision));

        return [
            '@type'     => 'GeoCoordinates',
            'latitude'  => round($lat, $precision),
            'longitude' => round($lng, $precision),
        ];
    }

    // ── Images ────────────────────────────────────────────────────────────────

    /** Returns an array of public image URLs (thumbnail first, then gallery). */
    public static function images(int $post_id): array
    {
        $urls    = [];
        $thumb   = (int) get_post_thumbnail_id($post_id);
        if ($thumb > 0) {
            $src = wp_get_attachment_image_url($thumb, 'large');
            if ($src) {
                $urls[$thumb] = $src;
            }
        }

        $gallery_raw = get_post_meta($post_id, self::meta_key('gallery'), true);
        if (is_array($gallery_raw)) {
            $ids = array_filter(array_map('absint', $gallery_raw));
        } else {
            $ids = array_filter(array_map('absint', explode(',', (string) $gallery_raw)));
        }
        foreach ($ids as $att_id) {
            if ($att_id === $thumb || isset($urls[$att_id])) {
                continue;
            }
            $src = wp_get_attachment_image_url($att_id, 'large');
            if ($src) {
                $urls[$att_id] = $src;
            }
        }

        return array_values($urls);
    }

    // ── Amenity features (LocationFeatureSpecification) ───────────────────────

    public static function amenity_features(int $post_id): array
    {
        $features = [];

        $add_feature = static function (string $name, $value = true) use (&$features): void {
            $name = Homlity_Schema_Helpers::clean($name);
            if ($name === '') {
                return;
            }

            $key = sanitize_title($name);
            $features[$key] = [
                '@type' => 'LocationFeatureSpecification',
                'name'  => $name,
                'value' => $value,
            ];
        };

        $parking = absint(self::get_meta($post_id, 'parking'));
        if ($parking > 0) {
            $add_feature('Parqueaderos', $parking);
        }

        $elevators = absint(self::get_meta($post_id, 'elevators'));
        if ($elevators > 0) {
            $add_feature('Ascensores', $elevators);
        }

        $terms = PropertyTaxonomies::getVisibleFeatureTermsForPost($post_id);
        if ($terms !== []) {
            foreach ($terms as $term) {
                $add_feature((string) $term->name);
            }
        }

        return array_values($features);
    }

    // ── Additional properties (PropertyValue) ─────────────────────────────────

    public static function additional_properties(int $post_id): array
    {
        $props = [];

        $lot_size = (float) self::get_meta($post_id, 'area_lot');
        if ($lot_size > 0) {
            $props[] = [
                '@type'     => 'PropertyValue',
                'name'      => 'Área del lote',
                'propertyID' => 'lotSize',
                'value'     => $lot_size,
                'unitCode'  => 'MTK',
                'unitText'  => 'm²',
            ];
        }

        $admin = (float) self::get_meta($post_id, 'price_admin');
        if ($admin > 0) {
            $cur   = strtoupper(self::get_meta($post_id, 'currency_admin'))
                  ?: (string) get_option('homlity_schema_currency', 'COP');
            $incl  = self::get_meta($post_id, 'admin_included');
            $label = ($incl === '1' || $incl === 'yes' || (bool) $incl)
                   ? 'Administración (incluida)'
                   : 'Administración';
            $props[] = [
                '@type' => 'PropertyValue',
                'name'  => $label,
                'value' => number_format($admin, 0, ',', '.') . ' ' . $cur,
            ];
        }

        $condition = self::clean(self::get_meta($post_id, 'condition'));
        if ($condition !== '') {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Estado', 'value' => $condition];
        }

        $stratum = self::clean(self::get_meta($post_id, 'stratum'));
        if ($stratum !== '') {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Estrato', 'propertyID' => 'stratum', 'value' => $stratum];
        }

        $floor = self::clean(self::get_meta($post_id, 'floor'));
        if ($floor !== '') {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Piso', 'propertyID' => 'floor', 'value' => $floor];
        }

        $levels = absint(self::get_meta($post_id, 'levels'));
        if ($levels > 0) {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Niveles', 'propertyID' => 'levels', 'value' => $levels];
        }

        return $props;
    }

    // ── Description ───────────────────────────────────────────────────────────

    public static function description(int $post_id, int $max = 300): string
    {
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }

        // Prefer excerpt
        $text = !empty($post->post_excerpt)
            ? $post->post_excerpt
            : wp_strip_all_tags(apply_filters('the_content', $post->post_content));

        $text = (string) preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max - 1) . '…';
        }

        return $text;
    }

    // ── Agency helpers ────────────────────────────────────────────────────────

    public static function agency(): array
    {
        return [
            'name'        => (string) get_option('homlity_schema_agency_name', get_bloginfo('name')),
            'description' => (string) get_option('homlity_schema_agency_description', get_bloginfo('description')),
            'telephone'   => (string) get_option('homlity_schema_agency_phone', ''),
            'email'       => (string) get_option('homlity_schema_agency_email', ''),
            'logo'        => (string) get_option('homlity_schema_agency_logo', ''),
            'address'     => (string) get_option('homlity_schema_agency_address', ''),
            'city'        => (string) get_option('homlity_schema_agency_city', ''),
            'region'      => (string) get_option('homlity_schema_agency_region', ''),
            'country'     => (string) get_option('homlity_schema_agency_country', 'CO'),
            'same_as'     => (string) get_option('homlity_schema_agency_same_as', ''),
        ];
    }

    /** Parses the same_as textarea into a validated URL array. */
    public static function same_as_urls(string $raw): array
    {
        $lines = array_map('trim', explode("\n", $raw));
        return array_values(array_filter($lines, fn($u) => self::valid_url($u)));
    }

    // ── Text & array utilities ────────────────────────────────────────────────

    public static function clean(string $text): string
    {
        return sanitize_text_field(wp_strip_all_tags($text));
    }

    public static function valid_url(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Recursively removes null, '', false, and empty-array values.
     * Keys are preserved; the result is re-indexed only for sequential arrays.
     */
    public static function drop_empty(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::drop_empty($v);
                if (empty($data[$k])) {
                    unset($data[$k]);
                }
            } elseif ($v === null || $v === '' || $v === false) {
                unset($data[$k]);
            }
        }
        return $data;
    }

    public static function json(array $data): string
    {
        $out = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return $out !== false ? $out : '{}';
    }
}
