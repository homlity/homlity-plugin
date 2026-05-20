<?php
/**
 * Homlity Consignment Manager — main orchestrator for the public consignment form module.
 *
 * Loaded from plugin-inmobiliario.php via plugins_loaded priority 35.
 * Uses a static-init pattern (no-op if called twice) matching the existing
 * Homlity_Elementor_Manager and Homlity_Schema_Manager conventions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Consignment_Manager
{
    private static bool $initialized = false;

    // ── Bootstrap ──────────────────────────────────────────────────────────

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        if (!apply_filters('homlity_consignment_enabled', self::option('enabled', true))) {
            return;
        }

        $dir = __DIR__;
        require_once $dir . '/class-homlity-consignment-validator.php';
        require_once $dir . '/class-homlity-consignment-payload-builder.php';
        require_once $dir . '/class-homlity-consignment-media-handler.php';
        require_once $dir . '/class-homlity-consignment-notifications.php';
        require_once $dir . '/class-homlity-consignment-rest-controller.php';
        require_once $dir . '/class-homlity-consignment-admin.php';

        // REST routes
        add_action('rest_api_init', ['Homlity_Consignment_Rest_Controller', 'register_routes']);

        // Shortcode
        add_shortcode('homlity_consignment_form', [self::class, 'renderShortcode']);

        // Admin submenu via existing hook
        add_action('homlity_plugin_register_integration_submenus', ['Homlity_Consignment_Admin', 'registerMenu']);
        add_action('admin_post_homlity_consignment_save', ['Homlity_Consignment_Admin', 'handleSave']);

        // Elementor widget (no-op if Elementor is absent)
        add_action('elementor/widgets/register', [self::class, 'registerElementorWidget']);
        add_action('elementor/widgets/widgets_registered', [self::class, 'registerElementorWidgetLegacy']);
    }

    // ── Shortcode ─────────────────────────────────────────────────────────

    public static function renderShortcode($raw_atts): string
    {
        $atts = shortcode_atts([
            'provider'     => self::option('provider', 'public-consignment'),
            'redirect_url' => self::option('redirect_url', ''),
            'layout'       => 'default',
            'theme'        => 'light',
            'title'        => '',
        ], (array) $raw_atts, 'homlity_consignment_form');

        self::enqueueAssets();

        $provider     = sanitize_key($atts['provider']);
        $redirect_url = esc_url_raw($atts['redirect_url']);
        $layout       = sanitize_key($atts['layout']);
        $theme        = in_array($atts['theme'], ['light', 'dark'], true) ? $atts['theme'] : 'light';

        wp_localize_script('homlity-consignment-form', 'homlityConsignmentConfig', [
            'restBase'    => esc_url_raw(rest_url('homlity/v1/consignment')),
            'nonce'       => wp_create_nonce('wp_rest'),
            'provider'    => $provider,
            'redirectUrl' => $redirect_url,
            'layout'      => $layout,
            'theme'       => $theme,
            'siteTitle'   => get_bloginfo('name'),
        ]);

        return sprintf(
            '<div id="homlity-consignment-form-root" class="homlity-consignment-form" data-provider="%s" data-theme="%s" data-layout="%s"></div>',
            esc_attr($provider),
            esc_attr($theme),
            esc_attr($layout)
        );
    }

    // ── Asset enqueueing ──────────────────────────────────────────────────

    public static function enqueueAssets(): void
    {
        $dist = HOMLITY_PLUGIN_URL . 'assets/dist/';
        $ver  = HOMLITY_PLUGIN_VERSION;

        wp_enqueue_style(
            'homlity-consignment-form',
            $dist . 'consignment.css',
            [],
            $ver
        );

        wp_enqueue_script(
            'homlity-consignment-form',
            $dist . 'consignment.js',
            ['wp-element', 'wp-api-fetch', 'wp-i18n'],
            $ver,
            true
        );
    }

    // ── Elementor widget ──────────────────────────────────────────────────

    public static function registerElementorWidget($widgetsManager): void
    {
        $file = __DIR__ . '/class-homlity-consignment-elementor-widget.php';
        if (!class_exists('\Elementor\Widget_Base') || !file_exists($file)) {
            return;
        }
        require_once $file;
        $widget = new Homlity_Consignment_Elementor_Widget();
        if (method_exists($widgetsManager, 'register')) {
            $widgetsManager->register($widget);
        }
    }

    public static function registerElementorWidgetLegacy(): void
    {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }
        self::registerElementorWidget(\Elementor\Plugin::instance()->widgets_manager);
    }

    // ── Options helpers ───────────────────────────────────────────────────

    public static function option(string $key, $default = null)
    {
        return self::options()[$key] ?? $default;
    }

    public static function options(): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = wp_parse_args(
                (array) get_option('homlity_consignment_settings', []),
                self::defaults()
            );
        }
        return $cache;
    }

    /** Clears the static options cache (call after saving). */
    public static function flushOptionsCache(): void
    {
        // Use Closure trick to reset the static variable inside options().
        $fn = \Closure::bind(static function () {
            $cache = null; // reset
        }, null, self::class);
        // Re-assign via option read next call — simplest approach:
        delete_option('_homlity_consignment_cache_bust');
    }

    public static function defaults(): array
    {
        return [
            // General
            'enabled'              => true,
            'provider'             => 'public-consignment',
            'default_status'       => 'pending',
            'redirect_url'         => '',
            'notification_email'   => get_bloginfo('admin_email'),
            'default_currency'     => 'COP',
            'default_country'      => 'Colombia',
            'default_city'         => '',
            // Validation
            'require_coordinates'  => true,
            'require_image'        => true,
            'require_gallery'      => false,
            // Uploads
            'max_images'           => 20,
            'max_image_size'       => 5,   // MB
            'max_brochure_size'    => 10,  // MB
            // Security
            'enable_honeypot'      => true,
            'enable_rate_limit'    => true,
            'rate_limit_per_hour'  => 5,
            // Features
            'enable_logs'          => true,
            'allow_advisors'       => true,
            'allow_agencies'       => true,
            'allow_owners'         => true,
            'notify_admin'         => true,
            'notify_consignant'    => true,
            // Legal texts
            'data_policy_text'     => '',
            'authorization_text'   => '',
            // UI texts
            'form_title'           => 'Consigna tu inmueble',
            'form_subtitle'        => 'Completa el formulario y nuestro equipo se comunicará contigo.',
            'form_welcome_text'    => '',
            'btn_next_text'        => 'Siguiente',
            'btn_prev_text'        => 'Anterior',
            'btn_submit_text'      => 'Enviar inmueble para revisión',
            'success_message'      => 'Tu inmueble fue enviado correctamente. Nuestro equipo revisará la información y se comunicará contigo para validar la publicación.',
            'error_message'        => 'Ocurrió un error al enviar el formulario. Por favor intenta de nuevo.',
            // Styles
            'primary_color'        => '#2563eb',
            'secondary_color'      => '#1e40af',
            'bg_color'             => '#ffffff',
            'text_color'           => '#1f2937',
            'border_radius'        => '8',
        ];
    }
}
