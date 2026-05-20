<?php
/**
 * Admin settings page for /llms-full.txt — Contexto para IA.
 *
 * Registers under the Homlity "Configuración" menu via the
 * 'homlity_plugin_register_integration_submenus' action.
 */

namespace Homlity\PluginInmobiliario\Services\Ai;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class LlmsFullAdminService implements ServiceInterface
{
    private const PAGE_SLUG       = 'homlity-llms-full';
    private const OPTION_GROUP    = 'homlity_llms_full';
    private const SECTION_GENERAL = 'homlity_llms_full_general';
    private const SECTION_CONTENT = 'homlity_llms_full_content';
    private const SECTION_URLS    = 'homlity_llms_full_urls';

    public function register(): void
    {
        add_action('homlity_plugin_register_integration_submenus', [$this, 'registerSubmenu']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    // ── Menu ──────────────────────────────────────────────────────────────────

    public function registerSubmenu(string $parentSlug): void
    {
        add_submenu_page(
            $parentSlug,
            __('Contexto para IA (llms-full.txt)', 'homlity-real-estate'),
            __('Contexto IA', 'homlity-real-estate'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    // ── Settings API ──────────────────────────────────────────────────────────

    public function registerSettings(): void
    {
        // Enabled flag — stored as '1' or '0' string to avoid PHP bool/DB ambiguity
        register_setting(self::OPTION_GROUP, 'homlity_llms_full_enabled', [
            'type'              => 'string',
            'sanitize_callback' => static fn($v): string => !empty($v) ? '1' : '0',
            'default'           => '1',
        ]);

        // Text / textarea options
        $textOpts = [
            'homlity_llms_full_business_description',
            'homlity_llms_full_city',
            'homlity_llms_full_whatsapp',
            'homlity_llms_full_email',
            'homlity_llms_full_services',
            'homlity_llms_full_integrations',
            'homlity_llms_full_extra_context',
        ];
        foreach ($textOpts as $opt) {
            register_setting(self::OPTION_GROUP, $opt, [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            ]);
        }

        // URL options
        $urlOpts = [
            'homlity_llms_full_catalog_url',
            'homlity_llms_full_contact_url',
            'homlity_llms_full_services_url',
            'homlity_llms_full_blog_url',
        ];
        foreach ($urlOpts as $opt) {
            register_setting(self::OPTION_GROUP, $opt, [
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            ]);
        }

        $page = self::PAGE_SLUG;

        // ── Section: General ──────────────────────────────────────────────────
        add_settings_section(
            self::SECTION_GENERAL,
            __('Configuración general', 'homlity-real-estate'),
            static function (): void {
                echo '<p>' . esc_html__('Información básica del negocio que aparecerá en el archivo llms-full.txt.', 'homlity-real-estate') . '</p>';
            },
            $page
        );

        $this->addField($page, self::SECTION_GENERAL, 'homlity_llms_full_enabled',
            __('Activar /llms-full.txt', 'homlity-real-estate'),
            [$this, 'fieldEnabled']
        );
        $this->addField($page, self::SECTION_GENERAL, 'homlity_llms_full_business_description',
            __('Descripción del negocio', 'homlity-real-estate'),
            [$this, 'fieldTextarea'],
            ['rows' => 4, 'desc' => __('Descripción pública del negocio inmobiliario. No incluir datos privados.', 'homlity-real-estate')]
        );
        $this->addField($page, self::SECTION_GENERAL, 'homlity_llms_full_city',
            __('Ciudad principal', 'homlity-real-estate'),
            [$this, 'fieldText'],
            ['placeholder' => 'Bogotá']
        );
        $this->addField($page, self::SECTION_GENERAL, 'homlity_llms_full_whatsapp',
            __('WhatsApp comercial', 'homlity-real-estate'),
            [$this, 'fieldText'],
            ['placeholder' => '+57 300 000 0000']
        );
        $this->addField($page, self::SECTION_GENERAL, 'homlity_llms_full_email',
            __('Correo comercial', 'homlity-real-estate'),
            [$this, 'fieldText'],
            ['placeholder' => 'contacto@ejemplo.com', 'type' => 'email']
        );

        // ── Section: Content ──────────────────────────────────────────────────
        add_settings_section(
            self::SECTION_CONTENT,
            __('Servicios, integraciones y contexto adicional', 'homlity-real-estate'),
            static function (): void {
                echo '<p>' . esc_html__('Un elemento por línea. El contenido debe ser información pública.', 'homlity-real-estate') . '</p>';
            },
            $page
        );

        $this->addField($page, self::SECTION_CONTENT, 'homlity_llms_full_services',
            __('Servicios inmobiliarios', 'homlity-real-estate'),
            [$this, 'fieldTextarea'],
            [
                'rows'        => 8,
                'placeholder' => "Venta de inmuebles\nArriendo de inmuebles\nAdministración inmobiliaria\nAvalúos",
            ]
        );
        $this->addField($page, self::SECTION_CONTENT, 'homlity_llms_full_integrations',
            __('Integraciones inmobiliarias', 'homlity-real-estate'),
            [$this, 'fieldTextarea'],
            [
                'rows'        => 5,
                'placeholder' => "Wasi\nSimi\nDomus\nSmart Inmobiliario",
            ]
        );
        $this->addField($page, self::SECTION_CONTENT, 'homlity_llms_full_extra_context',
            __('Contexto adicional público para IA', 'homlity-real-estate'),
            [$this, 'fieldTextarea'],
            [
                'rows' => 5,
                'desc' => __('Información adicional que los modelos de IA pueden usar como contexto. Solo información pública.', 'homlity-real-estate'),
            ]
        );

        // ── Section: URLs ─────────────────────────────────────────────────────
        add_settings_section(
            self::SECTION_URLS,
            __('URLs importantes', 'homlity-real-estate'),
            static function (): void {
                echo '<p>' . esc_html__('Deja en blanco para usar las URLs por defecto del sitio.', 'homlity-real-estate') . '</p>';
            },
            $page
        );

        $this->addField($page, self::SECTION_URLS, 'homlity_llms_full_catalog_url',
            __('Catálogo de inmuebles', 'homlity-real-estate'),
            [$this, 'fieldUrl'],
            ['placeholder' => home_url('/inmuebles/')]
        );
        $this->addField($page, self::SECTION_URLS, 'homlity_llms_full_contact_url',
            __('Contacto', 'homlity-real-estate'),
            [$this, 'fieldUrl'],
            ['placeholder' => home_url('/contacto/')]
        );
        $this->addField($page, self::SECTION_URLS, 'homlity_llms_full_services_url',
            __('Servicios', 'homlity-real-estate'),
            [$this, 'fieldUrl'],
            ['placeholder' => home_url('/servicios/')]
        );
        $this->addField($page, self::SECTION_URLS, 'homlity_llms_full_blog_url',
            __('Blog', 'homlity-real-estate'),
            [$this, 'fieldUrl'],
            ['placeholder' => home_url('/blog/')]
        );
    }

    // ── Page renderer ─────────────────────────────────────────────────────────

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $publicUrl = esc_url(home_url('/llms-full.txt'));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Contexto para IA — llms-full.txt', 'homlity-real-estate'); ?></h1>

            <div class="notice notice-info inline" style="margin: 12px 0;">
                <p>
                    <?php esc_html_e('URL pública del archivo:', 'homlity-real-estate'); ?>
                    <strong>
                        <a href="<?php echo esc_url($publicUrl); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($publicUrl); ?>
                        </a>
                    </strong>
                    &mdash;
                    <a href="<?php echo esc_url($publicUrl); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Abrir en nueva pestaña', 'homlity-real-estate'); ?>
                    </a>
                </p>
                <p style="color:#666;font-size:12px;">
                    <?php esc_html_e('Si la URL devuelve 404, ve a Ajustes → Enlaces permanentes y guarda sin cambios para refrescar las reglas de reescritura.', 'homlity-real-estate'); ?>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button(__('Guardar cambios', 'homlity-real-estate'));
                ?>
            </form>
        </div>
        <?php
    }

    // ── Field renderers ───────────────────────────────────────────────────────

    public function fieldEnabled(array $args): void
    {
        $name  = $args['name'];
        $value = get_option($name, '1');
        // Hidden input ensures the option is saved even when the checkbox is unchecked
        printf('<input type="hidden" name="%s" value="0">', esc_attr($name));
        printf(
            '<input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s> <label for="%1$s">%3$s</label>',
            esc_attr($name),
            checked($value, '1', false),
            esc_html__('Activado — el archivo /llms-full.txt estará disponible públicamente', 'homlity-real-estate')
        );
    }

    public function fieldText(array $args): void
    {
        $name        = $args['name'];
        $type        = $args['type'] ?? 'text';
        $value       = (string) get_option($name, '');
        $placeholder = $args['placeholder'] ?? '';
        printf(
            '<input type="%4$s" id="%1$s" name="%1$s" value="%2$s" placeholder="%3$s" class="regular-text">',
            esc_attr($name),
            esc_attr($value),
            esc_attr($placeholder),
            esc_attr($type)
        );
        if (!empty($args['desc'])) {
            echo '<p class="description">' . esc_html($args['desc']) . '</p>';
        }
    }

    public function fieldUrl(array $args): void
    {
        $name        = $args['name'];
        $value       = (string) get_option($name, '');
        $placeholder = $args['placeholder'] ?? '';
        printf(
            '<input type="url" id="%1$s" name="%1$s" value="%2$s" placeholder="%3$s" class="regular-text">',
            esc_attr($name),
            esc_attr(esc_url_raw($value)),
            esc_attr($placeholder)
        );
    }

    public function fieldTextarea(array $args): void
    {
        $name        = $args['name'];
        $value       = (string) get_option($name, '');
        $rows        = (int) ($args['rows'] ?? 4);
        $placeholder = $args['placeholder'] ?? '';
        printf(
            '<textarea id="%1$s" name="%1$s" rows="%2$s" placeholder="%3$s" class="large-text">%4$s</textarea>',
            esc_attr($name),
            esc_attr((string) $rows),
            esc_attr($placeholder),
            esc_textarea($value)
        );
        if (!empty($args['desc'])) {
            echo '<p class="description">' . esc_html($args['desc']) . '</p>';
        }
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    private function addField(string $page, string $section, string $name, string $label, callable $callback, array $args = []): void
    {
        $args['name'] = $name;
        add_settings_field($name, $label, $callback, $page, $section, $args);
    }
}
