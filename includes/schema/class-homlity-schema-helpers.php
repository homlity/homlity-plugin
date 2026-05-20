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
                'area'           => '_property_area',
                'area_lot'       => '_property_area_lot',
                'area_private'   => '_property_area_private',
                'area_built'     => '_property_area_built',
                'bedrooms'       => '_property_bedrooms',
                'bathrooms'      => '_property_bathrooms',
                'parking'        => '_property_parking',
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
        foreach (['area_built', 'area_private', 'area', 'area_lot'] as $field) {
            $v = self::get_meta($post_id, $field);
            if ($v !== '' && (float) $v > 0) {
                return (float) $v;
            }
        }
        return 0.0;
    }

    // ── Postal address ────────────────────────────────────────────────────────

    /** Returns a PostalAddress array or [] when there's nothing to show. */
    public static function postal_address(int $post_id): array
    {
        $street = self::clean(self::get_meta($post_id, 'address'));

        $city_terms    = get_the_terms($post_id, self::city_taxonomy());
        $state_terms   = get_the_terms($post_id, 'property_state');
        $country_terms = get_the_terms($post_id, 'property_country');

        $city    = is_array($city_terms) && !empty($city_terms)    ? self::clean(reset($city_terms)->name)    : '';
        $state   = is_array($state_terms) && !empty($state_terms)  ? self::clean(reset($state_terms)->name)   : '';
        $country = is_array($country_terms) && !empty($country_terms) ? self::clean(reset($country_terms)->name) : '';

        if ($street === '' && $city === '' && $state === '' && $country === '') {
            return [];
        }

        return self::drop_empty([
            '@type'           => 'PostalAddress',
            'streetAddress'   => $street,
            'addressLocality' => $city,
            'addressRegion'   => $state,
            'addressCountry'  => $country,
        ]);
    }

    // ── Geo coordinates ───────────────────────────────────────────────────────

    public static function geo(int $post_id): array
    {
        $lat = (float) self::get_meta($post_id, 'latitude');
        $lng = (float) self::get_meta($post_id, 'longitude');
        if ($lat === 0.0 || $lng === 0.0) {
            return [];
        }
        return [
            '@type'     => 'GeoCoordinates',
            'latitude'  => $lat,
            'longitude' => $lng,
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
        $ids = array_filter(array_map('absint', explode(',', (string) $gallery_raw)));
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

        if (absint(self::get_meta($post_id, 'parking')) > 0) {
            $features[] = ['@type' => 'LocationFeatureSpecification', 'name' => 'Parqueadero', 'value' => true];
        }

        $terms = get_the_terms($post_id, 'property_feature');
        if (is_array($terms)) {
            foreach ($terms as $term) {
                $features[] = [
                    '@type' => 'LocationFeatureSpecification',
                    'name'  => self::clean($term->name),
                    'value' => true,
                ];
            }
        }

        return $features;
    }

    // ── Additional properties (PropertyValue) ─────────────────────────────────

    public static function additional_properties(int $post_id): array
    {
        $props = [];

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

        $code = self::clean(self::get_meta($post_id, 'code'));
        if ($code !== '') {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Referencia', 'value' => $code];
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
