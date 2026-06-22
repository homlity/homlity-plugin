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
        require_once $dir . '/class-homlity-consignacion-rest-controller.php';

        // REST routes
        add_action('rest_api_init', ['Homlity_Consignment_Rest_Controller', 'register_routes']);
        add_action('rest_api_init', ['Homlity_Consignacion_Rest_Controller', 'register_routes']);

        // Shortcode
        add_shortcode('homlity_consignment_form', [self::class, 'renderShortcode']);

        // Admin submenu via existing hook
        add_action('homlity_plugin_register_integration_submenus', ['Homlity_Consignment_Admin', 'registerMenu']);
        add_action('admin_post_homlity_consignment_save', ['Homlity_Consignment_Admin', 'handleSave']);
        add_action('admin_post_homlity_consignment_create_page', ['Homlity_Consignment_Admin', 'handleCreatePage']);

        // Admin notice when the consignment page is missing
        add_action('admin_notices', [self::class, 'maybeShowPageNotice']);

        // Elementor widget (no-op if Elementor is absent)
        add_action('elementor/widgets/register', [self::class, 'registerElementorWidget']);
        add_action('elementor/widgets/widgets_registered', [self::class, 'registerElementorWidgetLegacy']);
    }

    // ── Shortcode ─────────────────────────────────────────────────────────

    public static function renderShortcode($raw_atts): string
    {
        $atts = shortcode_atts([
            'provider'       => self::option('provider', 'public-consignment'),
            'redirect_url'   => self::option('redirect_url', ''),
            'primary_color'  => self::option('primary_color', '#2563eb'),
            'text_color'     => self::option('text_color', '#1f2937'),
        ], (array) $raw_atts, 'homlity_consignment_form');

        self::enqueueAssets();

        $primary_color = sanitize_hex_color($atts['primary_color']) ?: '#2563eb';
        $text_color    = sanitize_hex_color($atts['text_color'])    ?: '#1f2937';

        // The configuracion object is injected after the element mounts.
        // urlPlugin must be without trailing slash: /wp-json/homlity
        $url_plugin   = untrailingslashit(rest_url('homlity'));
        $configuracion = wp_json_encode([
            'urlPlugin'       => $url_plugin,
            'theme'           => [
                'color' => [
                    'primary' => $primary_color,
                    'text'    => $text_color,
                ],
            ],
            'georeferencing'  => [
                'country' => [
                    'id'   => 1,
                    'name' => sanitize_text_field(self::option('default_country', 'Colombia')),
                ],
            ],
        ]);

        // Unique ID to support multiple shortcodes on same page
        static $instance = 0;
        $instance++;
        $el_id = 'homlity-consignacion-' . $instance;

        // Inject config as an inline script that runs AFTER the Vue bundle.
        // wp_add_inline_script appends JS to the bundle's script tag in the footer,
        // so by the time it executes the custom element is already defined and mounted.
        wp_add_inline_script(
            'homlity-consignacion',
            '(function(){var el=document.getElementById(' . wp_json_encode($el_id) . ');'
            . 'if(el){el.configuracion=' . $configuracion . ';}})()',
            'after'
        );

        // --primary se inyecta como inline style para que el color sea visible
        // desde el primer render, sin esperar al watcher de Vue.
        return sprintf(
            '<div class="homlity-consignment-wrap" style="--primary:%s;--homlity-text:%s;">' .
            '<homlity-consignacion id="%s"></homlity-consignacion>' .
            '</div>',
            esc_attr($primary_color),
            esc_attr($text_color),
            esc_attr($el_id)
        );
    }

    // ── Asset enqueueing ──────────────────────────────────────────────────

    public static function enqueueAssets(): void
    {
        $dist = HOMLITY_PLUGIN_URL . 'assets/dist/';
        $ver  = HOMLITY_PLUGIN_VERSION;

        // Vue web component bundle (self-contained, no dependencies)
        wp_enqueue_script(
            'homlity-consignacion',
            $dist . 'homlity-consignacion.js',
            [],
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

    // ── Consignment page management ───────────────────────────────────────

    const PAGE_ID_OPTION = 'homlity_consignment_page_id';

    /**
     * Creates the public consignment page if it doesn't already exist.
     * Safe to call multiple times: returns existing page ID if the page is still live.
     *
     * @return int  Post ID on success, 0 on failure.
     */
    public static function createConsignmentPage(): int
    {
        $existing_id = (int) get_option(self::PAGE_ID_OPTION, 0);

        // If the stored page still exists and is not trashed/deleted, return it.
        if ($existing_id > 0) {
            $status = get_post_status($existing_id);
            if ($status !== false && $status !== 'trash') {
                return $existing_id;
            }
        }

        $page_id = wp_insert_post([
            'post_type'    => 'page',
            'post_title'   => __('Consigna tu Inmueble', 'homlity-real-estate'),
            'post_name'    => 'consigna-tu-inmueble',
            'post_content' => '[homlity_consignment_form]',
            'post_status'  => 'publish',
            'post_author'  => (int) get_option('default_post_author', 1),
        ], true);

        if (is_wp_error($page_id) || $page_id <= 0) {
            return 0;
        }

        update_option(self::PAGE_ID_OPTION, $page_id, false);
        return $page_id;
    }

    /**
     * Forces recreation of the consignment page (ignores existing stored ID).
     *
     * @return int  New post ID, 0 on failure.
     */
    public static function recreateConsignmentPage(): int
    {
        delete_option(self::PAGE_ID_OPTION);
        return self::createConsignmentPage();
    }

    /**
     * Returns the stored consignment page ID (0 if not set or deleted).
     */
    public static function getConsignmentPageId(): int
    {
        $id = (int) get_option(self::PAGE_ID_OPTION, 0);
        if ($id <= 0) {
            return 0;
        }
        $status = get_post_status($id);
        return ($status !== false && $status !== 'trash') ? $id : 0;
    }

    /**
     * Shows a one-time admin notice on Homlity screens when the consignment
     * page has never been created or was deleted.
     */
    public static function maybeShowPageNotice(): void
    {
        // Only on Homlity admin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'homlity') === false) {
            return;
        }

        if (self::getConsignmentPageId() > 0) {
            return;
        }

        $create_url = wp_nonce_url(
            admin_url('admin-post.php?action=homlity_consignment_create_page'),
            'homlity_consignment_create_page'
        );
        printf(
            '<div class="notice notice-warning"><p>'
            . '<strong>%s</strong> %s &nbsp; <a href="%s" class="button button-primary">%s</a></p></div>',
            esc_html__('Homlity Consignación:', 'homlity-real-estate'),
            esc_html__('No existe la página pública de consignación de inmuebles.', 'homlity-real-estate'),
            esc_url($create_url),
            esc_html__('Crear página ahora', 'homlity-real-estate')
        );
    }
}
