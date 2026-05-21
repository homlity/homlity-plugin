<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
/**
 * SEO & GEO settings: admin page, option management and front-end helpers.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class SeoGeoSettingsService implements ServiceInterface
{
    const OPTION_NAME  = 'homlity_seo_settings';
    const PAGE_SLUG    = 'homlity-seo-geo';
    const NONCE_ACTION = 'homlity_seo_geo_save';
    const NONCE_FIELD  = 'homlity_seo_geo_nonce';

    public function register(): void
    {
        add_action('homlity_plugin_register_integration_submenus', [$this, 'registerMenu']);
        add_action('admin_post_homlity_seo_geo_save', [$this, 'handleSave']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        require_once dirname(__DIR__, 2) . '/includes/seo-geo-helpers.php';
    }

    public function registerMenu(string $parentSlug): void
    {
        add_submenu_page(
            $parentSlug,
            __('SEO & GEO — Homlity', 'homlity-real-estate'),
            __('SEO & GEO', 'homlity-real-estate'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para acceder a esta página.', 'homlity-real-estate'));
        }
        $settings = self::getSettings();
        require dirname(__DIR__, 2) . '/includes/admin/views/seo-geo-settings.php';
    }

    public function handleSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos.', 'homlity-real-estate'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        // phpcs:ignore WordPress.Security.NonceVerification -- verified above
        $raw      = isset($_POST['homlity_seo']) && is_array($_POST['homlity_seo']) ? wp_unslash($_POST['homlity_seo']) : [];
        $settings = self::sanitize($raw);
        update_option(self::OPTION_NAME, $settings);

        // phpcs:ignore WordPress.Security.NonceVerification
        $tab = sanitize_key($_POST['_current_tab'] ?? 'general');
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=' . $tab . '&updated=1'));
        exit;
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_media();

        wp_enqueue_style(
            'homlity-seo-geo-admin',
            HOMLITY_PLUGIN_URL . 'assets/css/seo-geo-admin.css',
            ['wp-color-picker'],
            HOMLITY_PLUGIN_VERSION
        );
        wp_enqueue_script(
            'homlity-seo-geo-admin',
            HOMLITY_PLUGIN_URL . 'assets/js/seo-geo-admin.js',
            ['jquery', 'wp-color-picker'],
            HOMLITY_PLUGIN_VERSION,
            true
        );
        wp_localize_script('homlity-seo-geo-admin', 'homlitySeoGeo', [
            'uploadTitle'  => __('Seleccionar imagen', 'homlity-real-estate'),
            'uploadButton' => __('Usar esta imagen', 'homlity-real-estate'),
            'removeLabel'  => __('Eliminar', 'homlity-real-estate'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Static data accessors (used by helpers and schema service)
    // ──────────────────────────────────────────────────────────────────────────

    public static function getSettings(): array
    {
        $saved = get_option(self::OPTION_NAME, []);
        return array_merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    public static function get(string $key, $default = null)
    {
        $settings = self::getSettings();
        return $settings[$key] ?? $default;
    }

    public static function defaults(): array
    {
        return [
            // A. Datos generales
            'company_name'             => '',
            'company_legal_name'       => '',
            'company_nit'              => '',
            'company_type'             => 'inmobiliaria',
            'company_description'      => '',
            'company_description_long' => '',
            'company_years'            => '',
            'company_registration'     => '',
            'company_logo'             => '',
            'company_logo_secondary'   => '',
            'company_favicon'          => '',
            'company_og_image'         => '',
            'company_slogan'           => '',

            // B. Contacto
            'contact_email'            => '',
            'contact_email_sales'      => '',
            'contact_email_support'    => '',
            'contact_phone'            => '',
            'contact_mobile'           => '',
            'contact_whatsapp'         => '',
            'contact_whatsapp_props'   => '',
            'contact_whatsapp_url'     => '',
            'contact_whatsapp_text'    => '',
            'contact_website'          => '',
            'contact_hours'            => '',
            'contact_days'             => '',
            'contact_address'          => '',
            'contact_maps_link'        => '',
            'contact_place_id'         => '',

            // C. Ubicación
            'geo_country'              => '',
            'geo_state'                => '',
            'geo_city'                 => '',
            'geo_locality'             => '',
            'geo_neighborhood'         => '',
            'geo_address'              => '',
            'geo_postal_code'          => '',
            'geo_latitude'             => '',
            'geo_longitude'            => '',
            'geo_maps_embed'           => '',
            'geo_timezone'             => 'America/Bogota',

            // D. Zonas de cobertura
            'coverage_zones'           => [],

            // E. SEO global
            'seo_meta_title'           => '',
            'seo_meta_description'     => '',
            'seo_keywords'             => '',
            'seo_main_phrase'          => '',
            'seo_secondary_phrases'    => '',
            'seo_name'                 => '',
            'seo_separator'            => '|',
            'seo_og_image'             => '',
            'seo_twitter_image'        => '',
            'seo_enable_og'            => '1',
            'seo_enable_twitter'       => '1',
            'seo_enable_canonical'     => '1',
            'seo_enable_breadcrumbs'   => '1',
            'seo_enable_schema'        => '1',
            'seo_enable_sitemap'       => '',
            'seo_index_properties'     => '1',
            'seo_index_agents'         => '1',
            'seo_index_zones'          => '1',
            'seo_index_search'         => '',
            'seo_noindex_filtered'     => '1',
            'seo_noindex_empty'        => '1',

            // F. SEO para inmuebles
            'prop_title_tpl'           => '{tipo_inmueble} en {operacion} en {barrio}, {ciudad} | {inmobiliaria}',
            'prop_desc_tpl'            => 'Conoce este {tipo_inmueble} en {operacion} ubicado en {barrio}, {ciudad}. Área de {area} m², {habitaciones} habitaciones, {banos} baños. Publicado por {inmobiliaria}.',
            'prop_slug_tpl'            => '{operacion}-{tipo_inmueble}-{barrio}-{ciudad}-{codigo}',
            'prop_alt_tpl'             => '{tipo_inmueble} en {operacion} en {barrio}, {ciudad} - imagen {numero}',
            'prop_caption_tpl'         => 'Imagen de {tipo_inmueble} disponible en {barrio}, {ciudad}',

            // G. FAQs globales
            'global_faqs'              => [],

            // H. Schema
            'schema_type'              => 'RealEstateAgent',
            'schema_on_home'           => '1',
            'schema_on_properties'     => '1',
            'schema_on_agents'         => '',
            'schema_on_zones'          => '',
            'schema_faq_active'        => '1',

            // I. Redes sociales
            'social_facebook'          => '',
            'social_instagram'         => '',
            'social_linkedin'          => '',
            'social_youtube'           => '',
            'social_tiktok'            => '',
            'social_twitter'           => '',
            'social_pinterest'         => '',
            'social_whatsapp'          => '',
            'social_google_biz'        => '',
            'social_portals'           => '',
            'social_google_reviews'    => '',

            // J. Marca visual
            'brand_color_primary'      => '#ff6752',
            'brand_color_secondary'    => '',
            'brand_color_accent'       => '',
            'brand_color_button'       => '',
            'brand_color_button_text'  => '',
            'brand_logo'               => '',
            'brand_logo_dark'          => '',
            'brand_logo_light'         => '',
            'brand_default_image'      => '',
            'brand_og_image'           => '',

            // K. Herramientas externas
            'tools_gsc'                => '',
            'tools_ga'                 => '',
            'tools_gtm'                => '',
            'tools_meta_pixel'         => '',
            'tools_tiktok_pixel'       => '',
            'tools_linkedin_insight'   => '',
            'tools_hotjar'             => '',
            'tools_ms_clarity'         => '',
            'tools_gmaps_key'          => '',
            'tools_recaptcha_site'     => '',
            'tools_recaptcha_secret'   => '',

            // L. Avanzado
            'adv_slug_properties'      => 'inmuebles',
            'adv_slug_rent'            => 'arriendo',
            'adv_slug_sale'            => 'venta',
            'adv_slug_zones'           => 'zona',
            'adv_canonical'            => '1',
            'adv_redirect_slug'        => '',
            'adv_schema_per_property'  => '1',
            'adv_noindex_inactive'     => '1',
            'adv_noindex_no_photos'    => '',
            'adv_noindex_no_desc'      => '',
            'adv_min_desc_length'      => '100',
            'adv_alert_missing'        => '1',
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Sanitization
    // ──────────────────────────────────────────────────────────────────────────

    private static function sanitize(array $raw): array
    {
        $s = [];

        foreach (self::textFields() as $field) {
            $s[$field] = sanitize_text_field($raw[$field] ?? '');
        }

        foreach (self::textareaFields() as $field) {
            $s[$field] = sanitize_textarea_field($raw[$field] ?? '');
        }

        foreach (['contact_email', 'contact_email_sales', 'contact_email_support'] as $field) {
            $s[$field] = sanitize_email($raw[$field] ?? '');
        }

        foreach (self::urlFields() as $field) {
            $s[$field] = esc_url_raw($raw[$field] ?? '');
        }

        foreach (self::boolFields() as $field) {
            $s[$field] = !empty($raw[$field]) ? '1' : '';
        }

        // Secret keys — stored server-side only, never exposed to frontend
        $s['tools_recaptcha_secret'] = sanitize_text_field($raw['tools_recaptcha_secret'] ?? '');

        $s['coverage_zones'] = self::sanitizeCoverageZones($raw['coverage_zones'] ?? []);
        $s['global_faqs']    = self::sanitizeFaqs($raw['global_faqs'] ?? []);

        return $s;
    }

    private static function textFields(): array
    {
        return [
            'company_name', 'company_legal_name', 'company_nit', 'company_type',
            'company_years', 'company_registration', 'company_slogan',
            'contact_phone', 'contact_mobile', 'contact_whatsapp', 'contact_whatsapp_props',
            'contact_whatsapp_text', 'contact_hours', 'contact_days',
            'contact_address', 'contact_place_id',
            'geo_country', 'geo_state', 'geo_city', 'geo_locality', 'geo_neighborhood',
            'geo_address', 'geo_postal_code', 'geo_latitude', 'geo_longitude', 'geo_timezone',
            'seo_meta_title', 'seo_keywords', 'seo_main_phrase', 'seo_secondary_phrases',
            'seo_name', 'seo_separator',
            'prop_title_tpl', 'prop_slug_tpl', 'prop_alt_tpl', 'prop_caption_tpl',
            'schema_type',
            'social_facebook', 'social_instagram', 'social_linkedin', 'social_youtube',
            'social_tiktok', 'social_twitter', 'social_pinterest', 'social_whatsapp',
            'social_portals',
            'brand_color_primary', 'brand_color_secondary', 'brand_color_accent',
            'brand_color_button', 'brand_color_button_text',
            'tools_gsc', 'tools_ga', 'tools_gtm', 'tools_meta_pixel', 'tools_tiktok_pixel',
            'tools_linkedin_insight', 'tools_hotjar', 'tools_ms_clarity', 'tools_gmaps_key',
            'tools_recaptcha_site',
            'adv_slug_properties', 'adv_slug_rent', 'adv_slug_sale', 'adv_slug_zones',
            'adv_min_desc_length',
        ];
    }

    private static function textareaFields(): array
    {
        return [
            'company_description', 'company_description_long',
            'seo_meta_description', 'prop_desc_tpl',
        ];
    }

    private static function urlFields(): array
    {
        return [
            'company_logo', 'company_logo_secondary', 'company_favicon', 'company_og_image',
            'contact_whatsapp_url', 'contact_website', 'contact_maps_link',
            'geo_maps_embed',
            'seo_og_image', 'seo_twitter_image',
            'social_google_biz', 'social_google_reviews',
            'brand_logo', 'brand_logo_dark', 'brand_logo_light', 'brand_default_image',
            'brand_og_image',
        ];
    }

    private static function boolFields(): array
    {
        return [
            'seo_enable_og', 'seo_enable_twitter', 'seo_enable_canonical',
            'seo_enable_breadcrumbs', 'seo_enable_schema', 'seo_enable_sitemap',
            'seo_index_properties', 'seo_index_agents', 'seo_index_zones',
            'seo_index_search', 'seo_noindex_filtered', 'seo_noindex_empty',
            'schema_on_home', 'schema_on_properties', 'schema_on_agents',
            'schema_on_zones', 'schema_faq_active',
            'adv_canonical', 'adv_redirect_slug', 'adv_schema_per_property',
            'adv_noindex_inactive', 'adv_noindex_no_photos', 'adv_noindex_no_desc',
            'adv_alert_missing',
        ];
    }

    private static function sanitizeCoverageZones(array $zones): array
    {
        if (!is_array($zones)) {
            return [];
        }
        $clean = [];
        foreach ($zones as $zone) {
            if (!is_array($zone)) {
                continue;
            }
            $clean[] = [
                'country'       => sanitize_text_field($zone['country']       ?? ''),
                'state'         => sanitize_text_field($zone['state']         ?? ''),
                'city'          => sanitize_text_field($zone['city']          ?? ''),
                'neighborhood'  => sanitize_text_field($zone['neighborhood']  ?? ''),
                'coverage_type' => sanitize_text_field($zone['coverage_type'] ?? 'all'),
                'priority'      => sanitize_text_field($zone['priority']      ?? 'media'),
                'description'   => sanitize_textarea_field($zone['description'] ?? ''),
                'latitude'      => sanitize_text_field($zone['latitude']      ?? ''),
                'longitude'     => sanitize_text_field($zone['longitude']     ?? ''),
                'radius'        => absint($zone['radius'] ?? 0),
                'active'        => !empty($zone['active']) ? '1' : '',
                'seo_slug'      => sanitize_title($zone['seo_slug'] ?? ''),
            ];
        }
        return $clean;
    }

    private static function sanitizeFaqs(array $faqs): array
    {
        if (!is_array($faqs)) {
            return [];
        }
        $clean = [];
        foreach ($faqs as $faq) {
            if (!is_array($faq)) {
                continue;
            }
            $clean[] = [
                'question' => sanitize_text_field($faq['question'] ?? ''),
                'answer'   => wp_kses_post($faq['answer']          ?? ''),
                'category' => sanitize_text_field($faq['category'] ?? 'general'),
                'active'   => !empty($faq['active'])  ? '1' : '',
                'order'    => absint($faq['order']     ?? 0),
                'show_on'  => sanitize_text_field($faq['show_on']  ?? 'all'),
                'schema'   => !empty($faq['schema'])   ? '1' : '',
            ];
        }
        return $clean;
    }
}
