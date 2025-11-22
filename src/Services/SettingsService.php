<?php
/**
 * Admin settings for currency and global options.
 */

namespace Codwelt\PluginInmobiliario\Services;

use Codwelt\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsService implements ServiceInterface
{
    private string $optionName = 'inmopress_settings';

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerSettings(): void
    {
        register_setting($this->optionName, $this->optionName, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizeSettings'],
            'default' => $this->defaults(),
        ]);

        add_settings_section(
            'inmopress_currency',
            __('Moneda y formato', 'inmopress-listings-inmobiliaria'),
            '__return_empty_string',
            $this->optionName
        );

        add_settings_field(
            'base_currency',
            __('Moneda base', 'inmopress-listings-inmobiliaria'),
            [$this, 'renderBaseCurrencyField'],
            $this->optionName,
            'inmopress_currency'
        );

        add_settings_section(
            'inmopress_listing',
            __('Listado de propiedades', 'inmopress-listings-inmobiliaria'),
            '__return_empty_string',
            $this->optionName
        );

        add_settings_field(
            'listing_fields',
            __('Campos visibles en listado', 'inmopress-listings-inmobiliaria'),
            [$this, 'renderListingFields'],
            $this->optionName,
            'inmopress_listing'
        );

        add_settings_section(
            'inmopress_info',
            __('Información del plugin', 'inmopress-listings-inmobiliaria'),
            '__return_empty_string',
            $this->optionName
        );

        add_settings_field(
            'company_name',
            __('Empresa creadora', 'inmopress-listings-inmobiliaria'),
            [$this, 'renderCompanyName'],
            $this->optionName,
            'inmopress_info'
        );

        add_settings_field(
            'company_url',
            __('Sitio web', 'inmopress-listings-inmobiliaria'),
            [$this, 'renderCompanyUrl'],
            $this->optionName,
            'inmopress_info'
        );

        add_settings_field(
            'support_email',
            __('Correo de soporte', 'inmopress-listings-inmobiliaria'),
            [$this, 'renderSupportEmail'],
            $this->optionName,
            'inmopress_info'
        );

        add_settings_field(
            'default_country',
            __('País por defecto', 'inmopress-listings-inmobiliaria'),
            [$this, 'renderDefaultCountry'],
            $this->optionName,
            'inmopress_info'
        );

        add_settings_field(
            'default_state',
            __('Departamento/Provincia por defecto', 'inmopress-listings-inmobiliaria'),
            [$this, 'renderDefaultState'],
            $this->optionName,
            'inmopress_info'
        );

        add_settings_field(
            'default_city',
            __('Ciudad/Municipio por defecto', 'inmopress-listings-inmobiliaria'),
            [$this, 'renderDefaultCity'],
            $this->optionName,
            'inmopress_info'
        );

        add_settings_field(
            'archive_per_page',
            __('Inmuebles por página (archivo/búsqueda)', 'inmopress-listings-inmobiliaria'),
            [$this, 'renderArchivePerPage'],
            $this->optionName,
            'inmopress_listing'
        );

        add_settings_field(
            'archive_order',
            __('Orden por defecto', 'inmopress-listings-inmobiliaria'),
            [$this, 'renderArchiveOrder'],
            $this->optionName,
            'inmopress_listing'
        );

    }

    public function registerMenu(): void
    {
        // Menú principal manejado por AdminMenuService::registerSettingsMenu.
    }

    public function renderSettingsPage(): void
    {
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:10px;">
                <img src="<?php echo esc_url(PLUGIN_INMOBILIARIO_URL . 'icono.png'); ?>" alt="inmopress" style="width:32px;height:32px;">
                <span><?php esc_html_e('Ajustes de la plataforma inmobiliaria', 'inmopress-listings-inmobiliaria'); ?></span>
            </h1>
            <form method="post" action="options.php">
                <?php
                settings_fields($this->optionName);
                do_settings_sections($this->optionName);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function renderBaseCurrencyField(): void
    {
        $settings = get_option($this->optionName, $this->defaults());
        $current = $settings['base_currency'] ?? 'USD';
        $currencies = apply_filters('inmopress_supported_currencies', ['USD', 'EUR', 'GBP', 'COP', 'MXN', 'CLP']);
        ?>
        <select name="<?php echo esc_attr($this->optionName); ?>[base_currency]">
            <?php foreach ($currencies as $code): ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($current, $code); ?>>
                    <?php echo esc_html($code); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function sanitizeSettings(array $values): array
    {
        $values['listing_fields'] = isset($values['listing_fields']) && is_array($values['listing_fields'])
            ? array_values(array_intersect($values['listing_fields'], ['price', 'excerpt', 'features', 'whatsapp']))
            : $this->defaults()['listing_fields'];

        $values['company_name'] = isset($values['company_name'])
            ? sanitize_text_field($values['company_name'])
            : $this->defaults()['company_name'];

        $values['company_url'] = isset($values['company_url'])
            ? esc_url_raw($values['company_url'])
            : $this->defaults()['company_url'];

        $values['support_email'] = isset($values['support_email'])
            ? sanitize_email($values['support_email'])
            : $this->defaults()['support_email'];

        $values['default_country'] = isset($values['default_country'])
            ? absint($values['default_country'])
            : $this->defaults()['default_country'];

        $values['default_state'] = isset($values['default_state'])
            ? absint($values['default_state'])
            : $this->defaults()['default_state'];

        $values['default_city'] = isset($values['default_city'])
            ? absint($values['default_city'])
            : $this->defaults()['default_city'];

        $values['archive_per_page'] = isset($values['archive_per_page'])
            ? max(1, (int) $values['archive_per_page'])
            : $this->defaults()['archive_per_page'];

        $values['archive_order'] = isset($values['archive_order']) && in_array($values['archive_order'], ['price_desc', 'date_desc'], true)
            ? $values['archive_order']
            : $this->defaults()['archive_order'];

        $values['base_currency'] = isset($values['base_currency'])
            ? strtoupper(sanitize_text_field($values['base_currency']))
            : $this->defaults()['base_currency'];

        $allowed = apply_filters('inmopress_supported_currencies', ['USD']);
        $looksLikeCurrency = (bool) preg_match('/^[A-Z]{3,5}$/', $values['base_currency']);
        if (!in_array($values['base_currency'], $allowed, true) && !$looksLikeCurrency) {
            $values['base_currency'] = $this->defaults()['base_currency'];
        }

        return $values;
    }

    private function defaults(): array
    {
        return [
            'base_currency' => 'USD',
            'listing_fields' => ['price', 'excerpt', 'features', 'whatsapp'],
            'company_name' => 'Codwelt',
            'company_url' => 'https://codwelt.com',
            'support_email' => 'soporte@codwelt.com',
            'default_country' => '',
            'default_state' => '',
            'default_city' => '',
            'archive_per_page' => 12,
            'archive_order' => 'date_desc',
        ];
    }

    public function renderListingFields(): void
    {
        $settings = get_option($this->optionName, $this->defaults());
        $selected = $settings['listing_fields'] ?? $this->defaults()['listing_fields'];
        $fields = [
            'price' => __('Precio', 'inmopress-listings-inmobiliaria'),
            'excerpt' => __('Descripción corta', 'inmopress-listings-inmobiliaria'),
            'features' => __('Características (área, hab, baños)', 'inmopress-listings-inmobiliaria'),
            'whatsapp' => __('Botón de WhatsApp', 'inmopress-listings-inmobiliaria'),
        ];
        foreach ($fields as $key => $label) {
            ?>
            <label style="display:block;margin-bottom:4px;">
                <input type="checkbox" name="<?php echo esc_attr($this->optionName); ?>[listing_fields][]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected, true)); ?>>
                <?php echo esc_html($label); ?>
            </label>
            <?php
        }
    }

    public function renderCompanyName(): void
    {
        $settings = get_option($this->optionName, $this->defaults());
        ?>
        <input type="text" name="<?php echo esc_attr($this->optionName); ?>[company_name]" value="<?php echo esc_attr($settings['company_name'] ?? ''); ?>" class="regular-text">
        <?php
    }

    public function renderCompanyUrl(): void
    {
        $settings = get_option($this->optionName, $this->defaults());
        ?>
        <input type="url" name="<?php echo esc_attr($this->optionName); ?>[company_url]" value="<?php echo esc_attr($settings['company_url'] ?? ''); ?>" class="regular-text">
        <?php
    }

    public function renderSupportEmail(): void
    {
        $settings = get_option($this->optionName, $this->defaults());
        ?>
        <input type="email" name="<?php echo esc_attr($this->optionName); ?>[support_email]" value="<?php echo esc_attr($settings['support_email'] ?? ''); ?>" class="regular-text">
        <?php
    }

    public function renderDefaultCountry(): void
    {
        $settings = get_option($this->optionName, $this->defaults());
        ?>
        <input type="text" name="<?php echo esc_attr($this->optionName); ?>[default_country]" value="<?php echo esc_attr($settings['default_country'] ?? ''); ?>" class="regular-text">
        <?php
    }

    public function renderDefaultState(): void
    {
        $settings = get_option($this->optionName, $this->defaults());
        ?>
        <input type="text" name="<?php echo esc_attr($this->optionName); ?>[default_state]" value="<?php echo esc_attr($settings['default_state'] ?? ''); ?>" class="regular-text">
        <?php
    }

    public function renderDefaultCity(): void
    {
        $settings = get_option($this->optionName, $this->defaults());
        ?>
        <input type="text" name="<?php echo esc_attr($this->optionName); ?>[default_city]" value="<?php echo esc_attr($settings['default_city'] ?? ''); ?>" class="regular-text">
        <?php
    }

    public function renderArchivePerPage(): void
    {
        $settings = get_option($this->optionName, $this->defaults());
        ?>
        <input type="number" min="1" name="<?php echo esc_attr($this->optionName); ?>[archive_per_page]" value="<?php echo esc_attr($settings['archive_per_page'] ?? 12); ?>" class="small-text">
        <?php
    }

    public function renderArchiveOrder(): void
    {
        $settings = get_option($this->optionName, $this->defaults());
        $current = $settings['archive_order'] ?? 'date_desc';
        $options = [
            'date_desc' => __('Fecha de creación (más reciente primero)', 'inmopress-listings-inmobiliaria'),
            'price_desc' => __('Precio (mayor a menor)', 'inmopress-listings-inmobiliaria'),
        ];
        ?>
        <select name="<?php echo esc_attr($this->optionName); ?>[archive_order]">
            <?php foreach ($options as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}
