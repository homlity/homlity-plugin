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
    private static ?array $optionsCache = null;

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
        require_once $dir . '/class-homlity-consignment-rest-controller.php';
        require_once $dir . '/class-homlity-consignment-media-handler.php';
        require_once $dir . '/class-homlity-consignment-notifications.php';
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
            'provider'     => (string) self::option('provider', 'public-consignment'),
            'redirect_url' => (string) self::option('redirect_url', ''),
        ], (array) $raw_atts, 'homlity_consignment_form');

        self::enqueueAssets();

        $config = [
            'provider'     => (string) $atts['provider'],
            'redirectUrl'  => (string) $atts['redirect_url'],
            'primaryColor' => (string) self::option('primary_color', '#2563eb'),
            'textColor'    => (string) self::option('text_color', '#1f2937'),
        ];

        return sprintf(
            '<div class="homlity-consignment-form-wrap"><div data-homlity-consignment-root data-config="%s"></div></div>',
            esc_attr(wp_json_encode($config))
        );
    }

    // ── Asset enqueueing ──────────────────────────────────────────────────

    public static function enqueueAssets(): void
    {
        $asset_file = HOMLITY_PLUGIN_PATH . 'assets/dist/index.asset.php';
        $asset_meta = file_exists($asset_file) ? require $asset_file : [
            // Keep this fallback aligned with the bundle metadata. If a
            // damaged/incomplete installation loses index.asset.php, the
            // compiled JSX must still have its runtime before index.js runs.
            'dependencies' => ['react-jsx-runtime', 'wp-element'],
            'version'      => HOMLITY_PLUGIN_VERSION,
        ];

        $style_handle  = 'homlity-consignment-form';
        $script_handle = 'homlity-consignment-form';
        $style_url     = HOMLITY_PLUGIN_URL . 'assets/dist/index.css';
        $script_url    = HOMLITY_PLUGIN_URL . 'assets/dist/index.js';
        $version       = $asset_meta['version'] ?? HOMLITY_PLUGIN_VERSION;

        wp_enqueue_style(
            'homlity-real-estate-leaflet-front',
            HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.min.css',
            [],
            '1.9.4'
        );

        wp_enqueue_script(
            'homlity-real-estate-leaflet-front',
            HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.min.js',
            [],
            '1.9.4',
            true
        );

        wp_localize_script('homlity-real-estate-leaflet-front', 'homlityLeafletAssets', [
            'iconUrl' => file_exists(HOMLITY_PLUGIN_PATH . 'assets/vendor/leaflet/images/marker-icon.png')
                ? HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/images/marker-icon.png'
                : '',
            'iconRetinaUrl' => file_exists(HOMLITY_PLUGIN_PATH . 'assets/vendor/leaflet/images/marker-icon-2x.png')
                ? HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/images/marker-icon-2x.png'
                : '',
            'shadowUrl' => file_exists(HOMLITY_PLUGIN_PATH . 'assets/vendor/leaflet/images/marker-shadow.png')
                ? HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/images/marker-shadow.png'
                : '',
        ]);

        wp_register_style($style_handle, $style_url, [], $version);
        wp_enqueue_style($style_handle);

        wp_register_script(
            $script_handle,
            $script_url,
            array_merge(
                (array) ($asset_meta['dependencies'] ?? ['wp-element']),
                ['homlity-real-estate-leaflet-front']
            ),
            $version,
            true
        );

        wp_add_inline_script(
            $script_handle,
            'window.homlityConsignmentConfig = Object.assign({}, window.homlityConsignmentConfig || {}, ' . wp_json_encode(self::frontendConfig($style_url)) . ');',
            'before'
        );

        wp_enqueue_script($script_handle);
    }

    private static function frontendConfig(string $styleUrl): array
    {
        return [
            'restBase' => rest_url('homlity/v1/consignment'),
            'nonce'    => wp_create_nonce('wp_rest'),
            'cssUrl'   => $styleUrl,
        ];
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
        if (self::$optionsCache === null) {
            self::$optionsCache = wp_parse_args(
                (array) get_option('homlity_consignment_settings', []),
                self::defaults()
            );
        }
        return self::$optionsCache;
    }

    /** Clears the static options cache (call after saving). */
    public static function flushOptionsCache(): void
    {
        self::$optionsCache = null;
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
                self::configureConsignmentBuilder($existing_id);
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
        self::configureConsignmentBuilder((int) $page_id);
        return $page_id;
    }

    private static function configureConsignmentBuilder(int $page_id): void
    {
        $existing = trim((string) get_post_field('post_content', $page_id));
        $seeded = (string) get_post_meta($page_id, '_homlity_consignment_builder', true);
        if ($seeded === '' && $existing !== '' && $existing !== '[homlity_consignment_form]') {
            return;
        }

        $builder = self::preferredPageBuilder();
        $shortcode = '[homlity_consignment_form]';
        if ($builder === 'divi') {
            $content = '[et_pb_section][et_pb_row][et_pb_column type="4_4"][et_pb_text]'
                . $shortcode . '[/et_pb_text][/et_pb_column][/et_pb_row][/et_pb_section]';
            update_post_meta($page_id, '_et_pb_use_builder', 'on');
            update_post_meta($page_id, '_et_pb_page_layout', 'et_full_width_page');
            update_post_meta($page_id, '_et_pb_built_for_post_type', 'page');
        } elseif ($builder === 'wpbakery') {
            $content = '[vc_row][vc_column][vc_column_text]' . $shortcode . '[/vc_column_text][/vc_column][/vc_row]';
            update_post_meta($page_id, '_wpb_vc_js_status', 'true');
        } elseif ($builder === 'elementor') {
            $content = '';
            $id = static fn(): string => substr(md5(uniqid('', true)), 0, 7);
            $data = [[
                'id' => $id(), 'elType' => 'section', 'settings' => [], 'isInner' => false,
                'elements' => [[
                    'id' => $id(), 'elType' => 'column', 'settings' => ['_column_size' => 100], 'isInner' => false,
                    'elements' => [[
                        'id' => $id(), 'elType' => 'widget', 'widgetType' => 'shortcode',
                        'settings' => ['shortcode' => $shortcode], 'elements' => [],
                    ]],
                ]],
            ]];
            update_post_meta($page_id, '_elementor_edit_mode', 'builder');
            update_post_meta($page_id, '_elementor_template_type', 'wp-page');
            update_post_meta($page_id, '_elementor_data', wp_slash(wp_json_encode($data)));
        } else {
            $content = $shortcode;
        }

        wp_update_post(['ID' => $page_id, 'post_content' => wp_slash($content)]);
        update_post_meta($page_id, '_homlity_consignment_builder', $builder);
    }

    private static function preferredPageBuilder(): string
    {
        $template = strtolower((string) get_template());
        $stylesheet = strtolower((string) get_stylesheet());
        if (in_array($template, ['divi', 'extra'], true) || in_array($stylesheet, ['divi', 'extra'], true) || self::pluginIsActive('divi-builder/divi-builder.php')) {
            return 'divi';
        }
        if (defined('ELEMENTOR_VERSION') || class_exists('Elementor\\Plugin') || self::pluginIsActive('elementor/elementor.php')) {
            return 'elementor';
        }
        if (defined('WPB_VC_VERSION') || class_exists('Vc_Manager') || self::pluginIsActive('js_composer/js_composer.php')) {
            return 'wpbakery';
        }
        return 'native';
    }

    private static function pluginIsActive(string $plugin): bool
    {
        if (!function_exists('is_plugin_active')) {
            $file = ABSPATH . 'wp-admin/includes/plugin.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        return function_exists('is_plugin_active') && is_plugin_active($plugin);
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
