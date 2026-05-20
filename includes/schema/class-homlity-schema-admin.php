<?php
/**
 * Admin settings page for the Schema.org module.
 *
 * Registers under the Homlity "Configuración" menu via the
 * 'homlity_plugin_register_integration_submenus' action (same pattern as LlmsFullAdminService).
 * Falls back to Settings > Homlity Schema SEO when that action never fires.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Schema_Admin
{
    private const PAGE_SLUG    = 'homlity-schema-seo';
    private const OPTION_GROUP = 'homlity_schema_settings';

    private static bool $submenu_added = false;

    public static function init(): void
    {
        add_action('homlity_plugin_register_integration_submenus', [__CLASS__, 'register_submenu']);
        add_action('admin_menu',  [__CLASS__, 'maybe_register_standalone_menu'], 99);
        add_action('admin_init',  [__CLASS__, 'register_settings']);
    }

    // ── Menu registration ─────────────────────────────────────────────────────

    /** Called by the Homlity integration action with the parent slug. */
    public static function register_submenu(string $parent_slug): void
    {
        add_submenu_page(
            $parent_slug,
            __('Schema SEO', 'homlity-real-estate'),
            __('Schema SEO', 'homlity-real-estate'),
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
        self::$submenu_added = true;
    }

    /**
     * Fallback: add under Settings when the Homlity parent menu doesn't exist
     * (e.g., plugin disabled or standalone schema usage).
     */
    public static function maybe_register_standalone_menu(): void
    {
        if (self::$submenu_added) {
            return;
        }
        add_options_page(
            __('Homlity Schema SEO', 'homlity-real-estate'),
            __('Homlity Schema SEO', 'homlity-real-estate'),
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    // ── Settings API ──────────────────────────────────────────────────────────

    public static function register_settings(): void
    {
        $page = self::PAGE_SLUG;

        // ── Toggle options (stored as '1' / '0') ──────────────────────────────
        foreach (['homlity_schema_enabled', 'homlity_schema_property_enabled', 'homlity_schema_list_enabled', 'homlity_schema_agency_enabled'] as $opt) {
            register_setting(self::OPTION_GROUP, $opt, [
                'type'              => 'string',
                'sanitize_callback' => static fn($v): string => !empty($v) ? '1' : '0',
                'default'           => '1',
            ]);
        }

        // ── Text / textarea options ───────────────────────────────────────────
        $text_opts = [
            'homlity_schema_agency_name',
            'homlity_schema_agency_description',
            'homlity_schema_agency_phone',
            'homlity_schema_agency_address',
            'homlity_schema_agency_city',
            'homlity_schema_agency_region',
            'homlity_schema_agency_country',
            'homlity_schema_agency_same_as',
            'homlity_schema_currency',
        ];
        foreach ($text_opts as $opt) {
            register_setting(self::OPTION_GROUP, $opt, [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            ]);
        }

        // ── Email ─────────────────────────────────────────────────────────────
        register_setting(self::OPTION_GROUP, 'homlity_schema_agency_email', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default'           => '',
        ]);

        // ── URL options ───────────────────────────────────────────────────────
        foreach (['homlity_schema_agency_logo', 'homlity_schema_catalog_url', 'homlity_schema_contact_url', 'homlity_schema_search_url'] as $opt) {
            register_setting(self::OPTION_GROUP, $opt, [
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            ]);
        }

        // ── Integer options ───────────────────────────────────────────────────
        register_setting(self::OPTION_GROUP, 'homlity_schema_itemlist_limit', [
            'type'              => 'integer',
            'sanitize_callback' => static fn($v): int => max(1, min(100, absint($v))),
            'default'           => 20,
        ]);

        // ── Sections ──────────────────────────────────────────────────────────

        add_settings_section('homlity_schema_sec_toggle', __('Activación', 'homlity-real-estate'),
            static fn() => print '<p>' . esc_html__('Activa o desactiva los schemas por tipo de página.', 'homlity-real-estate') . '</p>',
            $page
        );

        add_settings_section('homlity_schema_sec_agency', __('Información de la agencia', 'homlity-real-estate'),
            static fn() => print '<p>' . esc_html__('Datos públicos de la inmobiliaria que aparecen en RealEstateAgent y LocalBusiness.', 'homlity-real-estate') . '</p>',
            $page
        );

        add_settings_section('homlity_schema_sec_urls', __('URLs y configuración', 'homlity-real-estate'),
            static fn() => print '<p>' . esc_html__('URLs clave del sitio y parámetros globales del módulo Schema.', 'homlity-real-estate') . '</p>',
            $page
        );

        // ── Fields: activation toggles ────────────────────────────────────────

        self::add_toggle($page, 'homlity_schema_sec_toggle', 'homlity_schema_enabled',
            __('Activar Schema SEO', 'homlity-real-estate'),
            __('Habilita el módulo completo de Schema.org JSON-LD en el sitio.', 'homlity-real-estate')
        );
        self::add_toggle($page, 'homlity_schema_sec_toggle', 'homlity_schema_property_enabled',
            __('Schema en fichas de inmuebles', 'homlity-real-estate'),
            __('Genera RealEstateListing + Apartment/House/… + Offer + BreadcrumbList en páginas individuales.', 'homlity-real-estate')
        );
        self::add_toggle($page, 'homlity_schema_sec_toggle', 'homlity_schema_list_enabled',
            __('Schema en listados y taxonomías', 'homlity-real-estate'),
            __('Genera CollectionPage + ItemList en archivos, catálogos y páginas de taxonomía.', 'homlity-real-estate')
        );
        self::add_toggle($page, 'homlity_schema_sec_toggle', 'homlity_schema_agency_enabled',
            __('Schema de agencia', 'homlity-real-estate'),
            __('Genera RealEstateAgent + WebSite en la portada y páginas institucionales configuradas.', 'homlity-real-estate')
        );

        // ── Fields: agency ────────────────────────────────────────────────────

        self::add_text($page, 'homlity_schema_sec_agency', 'homlity_schema_agency_name',
            __('Nombre de la agencia', 'homlity-real-estate'), get_bloginfo('name'));
        self::add_textarea($page, 'homlity_schema_sec_agency', 'homlity_schema_agency_description',
            __('Descripción', 'homlity-real-estate'), '', 3);
        self::add_text($page, 'homlity_schema_sec_agency', 'homlity_schema_agency_phone',
            __('Teléfono', 'homlity-real-estate'), '+57 300 000 0000', 'tel');
        self::add_text($page, 'homlity_schema_sec_agency', 'homlity_schema_agency_email',
            __('Correo público', 'homlity-real-estate'), 'contacto@ejemplo.com', 'email');
        self::add_url($page, 'homlity_schema_sec_agency', 'homlity_schema_agency_logo',
            __('URL del logo', 'homlity-real-estate'));
        self::add_text($page, 'homlity_schema_sec_agency', 'homlity_schema_agency_address',
            __('Dirección', 'homlity-real-estate'), 'Calle 123 # 45-67');
        self::add_text($page, 'homlity_schema_sec_agency', 'homlity_schema_agency_city',
            __('Ciudad', 'homlity-real-estate'), 'Bogotá');
        self::add_text($page, 'homlity_schema_sec_agency', 'homlity_schema_agency_region',
            __('Departamento / Región', 'homlity-real-estate'), 'Cundinamarca');
        self::add_text($page, 'homlity_schema_sec_agency', 'homlity_schema_agency_country',
            __('País (código ISO 3166-1 alpha-2)', 'homlity-real-estate'), 'CO');
        self::add_textarea($page, 'homlity_schema_sec_agency', 'homlity_schema_agency_same_as',
            __('Redes sociales (una URL por línea)', 'homlity-real-estate'),
            "https://www.facebook.com/agencia\nhttps://www.instagram.com/agencia", 4);

        // ── Fields: URLs & config ─────────────────────────────────────────────

        self::add_url($page, 'homlity_schema_sec_urls', 'homlity_schema_catalog_url',
            __('URL del catálogo de inmuebles', 'homlity-real-estate'));
        self::add_url($page, 'homlity_schema_sec_urls', 'homlity_schema_contact_url',
            __('URL de la página de contacto', 'homlity-real-estate'));
        self::add_url($page, 'homlity_schema_sec_urls', 'homlity_schema_search_url',
            __('URL del buscador de inmuebles (usa {search_term_string} como placeholder)', 'homlity-real-estate'));
        self::add_text($page, 'homlity_schema_sec_urls', 'homlity_schema_currency',
            __('Moneda por defecto (código ISO 4217)', 'homlity-real-estate'), 'COP');

        add_settings_field(
            'homlity_schema_itemlist_limit',
            __('Límite de inmuebles en ItemList', 'homlity-real-estate'),
            [__CLASS__, 'field_number'],
            $page,
            'homlity_schema_sec_urls',
            [
                'name' => 'homlity_schema_itemlist_limit',
                'min'  => 1,
                'max'  => 100,
                'desc' => __('Cantidad máxima de inmuebles incluidos en el schema de listado. Recomendado: 20.', 'homlity-real-estate'),
            ]
        );
    }

    // ── Page renderer ─────────────────────────────────────────────────────────

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Homlity — Schema SEO (JSON-LD)', 'homlity-real-estate'); ?></h1>
            <p><?php esc_html_e('Configura los datos estructurados Schema.org que se generan automáticamente en cada tipo de página para mejorar el posicionamiento inmobiliario en buscadores e IA.', 'homlity-real-estate'); ?></p>

            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button(__('Guardar cambios', 'homlity-real-estate'));
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e('Validación', 'homlity-real-estate'); ?></h2>
            <ul>
                <li>
                    <strong>Rich Results Test (Google):</strong>
                    <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener noreferrer">
                        search.google.com/test/rich-results
                    </a>
                </li>
                <li>
                    <strong>Schema Markup Validator:</strong>
                    <a href="https://validator.schema.org/" target="_blank" rel="noopener noreferrer">
                        validator.schema.org
                    </a>
                </li>
            </ul>
            <p class="description">
                <?php esc_html_e('Pega la URL de una ficha de inmueble en cada herramienta y verifica que el schema RealEstateListing aparezca sin errores.', 'homlity-real-estate'); ?>
            </p>
        </div>
        <?php
    }

    // ── Field renderers ───────────────────────────────────────────────────────

    public static function field_toggle(array $args): void
    {
        $name  = $args['name'];
        $desc  = $args['desc'] ?? '';
        $value = get_option($name, '1');
        printf('<input type="hidden" name="%s" value="0">', esc_attr($name));
        printf(
            '<label><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s> %3$s</label>',
            esc_attr($name),
            checked($value, '1', false),
            esc_html($desc)
        );
    }

    public static function field_text(array $args): void
    {
        $name  = $args['name'];
        $type  = $args['type'] ?? 'text';
        $ph    = $args['placeholder'] ?? '';
        $value = (string) get_option($name, '');
        printf(
            '<input type="%4$s" id="%1$s" name="%1$s" value="%2$s" placeholder="%3$s" class="regular-text">',
            esc_attr($name),
            esc_attr($value),
            esc_attr($ph),
            esc_attr($type)
        );
    }

    public static function field_url(array $args): void
    {
        $name  = $args['name'];
        $value = esc_url_raw((string) get_option($name, ''));
        printf(
            '<input type="url" id="%1$s" name="%1$s" value="%2$s" class="regular-text">',
            esc_attr($name),
            esc_attr($value)
        );
    }

    public static function field_textarea(array $args): void
    {
        $name  = $args['name'];
        $rows  = (int) ($args['rows'] ?? 4);
        $ph    = $args['placeholder'] ?? '';
        $value = (string) get_option($name, '');
        printf(
            '<textarea id="%1$s" name="%1$s" rows="%2$s" placeholder="%3$s" class="large-text">%4$s</textarea>',
            esc_attr($name),
            esc_attr((string) $rows),
            esc_attr($ph),
            esc_textarea($value)
        );
    }

    public static function field_number(array $args): void
    {
        $name  = $args['name'];
        $min   = (int) ($args['min'] ?? 1);
        $max   = (int) ($args['max'] ?? 100);
        $desc  = $args['desc'] ?? '';
        $value = absint(get_option($name, 20));
        printf(
            '<input type="number" id="%1$s" name="%1$s" value="%2$s" min="%3$s" max="%4$s" class="small-text">',
            esc_attr($name),
            esc_attr((string) $value),
            esc_attr((string) $min),
            esc_attr((string) $max)
        );
        if ($desc !== '') {
            echo '<p class="description">' . esc_html($desc) . '</p>';
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function add_toggle(string $page, string $section, string $name, string $label, string $desc = ''): void
    {
        add_settings_field($name, $label, [__CLASS__, 'field_toggle'], $page, $section, ['name' => $name, 'desc' => $desc]);
    }

    private static function add_text(string $page, string $section, string $name, string $label, string $ph = '', string $type = 'text'): void
    {
        add_settings_field($name, $label, [__CLASS__, 'field_text'], $page, $section, ['name' => $name, 'placeholder' => $ph, 'type' => $type]);
    }

    private static function add_url(string $page, string $section, string $name, string $label): void
    {
        add_settings_field($name, $label, [__CLASS__, 'field_url'], $page, $section, ['name' => $name]);
    }

    private static function add_textarea(string $page, string $section, string $name, string $label, string $ph = '', int $rows = 4): void
    {
        add_settings_field($name, $label, [__CLASS__, 'field_textarea'], $page, $section, ['name' => $name, 'placeholder' => $ph, 'rows' => $rows]);
    }
}
