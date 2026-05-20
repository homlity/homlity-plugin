<?php
/**
 * Global helper functions for Homlity SEO & GEO settings.
 * These are the public API for themes and other plugins to consume.
 */

if (!defined('ABSPATH')) {
    exit;
}

use Homlity\PluginInmobiliario\Services\SeoGeoSettingsService;

if (!function_exists('homlity_get_seo_setting')) {
    function homlity_get_seo_setting(string $key, $default = null)
    {
        return SeoGeoSettingsService::get($key, $default);
    }
}

if (!function_exists('homlity_get_business_info')) {
    function homlity_get_business_info(): array
    {
        $s = SeoGeoSettingsService::getSettings();
        return [
            'name'             => $s['company_name']         ?? '',
            'legal_name'       => $s['company_legal_name']   ?? '',
            'nit'              => $s['company_nit']          ?? '',
            'type'             => $s['company_type']         ?? '',
            'description'      => $s['company_description']  ?? '',
            'slogan'           => $s['company_slogan']        ?? '',
            'years'            => $s['company_years']         ?? '',
            'logo'             => $s['company_logo']          ?? '',
            'logo_secondary'   => $s['company_logo_secondary'] ?? '',
            'favicon'          => $s['company_favicon']       ?? '',
            'og_image'         => $s['company_og_image']      ?? '',
            'email'            => $s['contact_email']         ?? '',
            'phone'            => $s['contact_phone']         ?? '',
            'mobile'           => $s['contact_mobile']        ?? '',
            'whatsapp'         => $s['contact_whatsapp']      ?? '',
            'website'          => $s['contact_website']       ?? '',
            'address'          => $s['contact_address']       ?? '',
            'hours'            => $s['contact_hours']         ?? '',
        ];
    }
}

if (!function_exists('homlity_get_geo_settings')) {
    function homlity_get_geo_settings(): array
    {
        $s = SeoGeoSettingsService::getSettings();
        return [
            'country'      => $s['geo_country']     ?? '',
            'state'        => $s['geo_state']        ?? '',
            'city'         => $s['geo_city']         ?? '',
            'locality'     => $s['geo_locality']     ?? '',
            'neighborhood' => $s['geo_neighborhood'] ?? '',
            'address'      => $s['geo_address']      ?? '',
            'postal_code'  => $s['geo_postal_code']  ?? '',
            'latitude'     => $s['geo_latitude']     ?? '',
            'longitude'    => $s['geo_longitude']    ?? '',
            'maps_embed'   => $s['geo_maps_embed']   ?? '',
            'timezone'     => $s['geo_timezone']     ?? 'America/Bogota',
        ];
    }
}

if (!function_exists('homlity_get_coverage_areas')) {
    /**
     * Returns active coverage zones.
     *
     * @param string|null $type Filter by coverage_type (e.g. 'venta', 'arriendo') or null for all active.
     */
    function homlity_get_coverage_areas(?string $type = null): array
    {
        $zones = SeoGeoSettingsService::get('coverage_zones', []);
        if (!is_array($zones)) {
            return [];
        }
        return array_values(array_filter($zones, static function (array $zone) use ($type): bool {
            if (empty($zone['active'])) {
                return false;
            }
            if ($type !== null && $zone['coverage_type'] !== $type && $zone['coverage_type'] !== 'all') {
                return false;
            }
            return true;
        }));
    }
}

if (!function_exists('homlity_get_global_faqs')) {
    /**
     * Returns active FAQs, optionally filtered by display context.
     *
     * @param string|null $context  'home', 'property', 'zone', 'contact', or null for all.
     * @param bool        $schemaOnly  Return only FAQs marked for Schema.
     */
    function homlity_get_global_faqs(?string $context = null, bool $schemaOnly = false): array
    {
        $faqs = SeoGeoSettingsService::get('global_faqs', []);
        if (!is_array($faqs)) {
            return [];
        }

        $filtered = array_filter($faqs, static function (array $faq) use ($context, $schemaOnly): bool {
            if (empty($faq['active'])) {
                return false;
            }
            if ($schemaOnly && empty($faq['schema'])) {
                return false;
            }
            if ($context !== null && $faq['show_on'] !== 'all' && $faq['show_on'] !== $context) {
                return false;
            }
            return true;
        });

        usort($filtered, static fn($a, $b) => (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0));

        return array_values($filtered);
    }
}

if (!function_exists('homlity_render_business_json_ld')) {
    /**
     * Echoes the organization JSON-LD block if schema is enabled.
     * Thin wrapper — the full output is handled by SeoGeoSchemaService in wp_head.
     */
    function homlity_render_business_json_ld(): void
    {
        // Intentionally a no-op when called manually: the service already
        // handles wp_head output. This function exists for developer reference.
        do_action('homlity_render_business_json_ld');
    }
}

if (!function_exists('homlity_get_social_links')) {
    function homlity_get_social_links(): array
    {
        $s = SeoGeoSettingsService::getSettings();
        return array_filter([
            'facebook'       => $s['social_facebook']       ?? '',
            'instagram'      => $s['social_instagram']      ?? '',
            'linkedin'       => $s['social_linkedin']       ?? '',
            'youtube'        => $s['social_youtube']        ?? '',
            'tiktok'         => $s['social_tiktok']         ?? '',
            'twitter'        => $s['social_twitter']        ?? '',
            'pinterest'      => $s['social_pinterest']      ?? '',
            'whatsapp'       => $s['social_whatsapp']       ?? '',
            'google_biz'     => $s['social_google_biz']     ?? '',
            'google_reviews' => $s['social_google_reviews'] ?? '',
        ]);
    }
}
