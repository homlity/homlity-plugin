<?php
/**
 * React-based admin settings page and option persistence.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsService implements ServiceInterface
{
    private string $optionName = HOMLITY_PLUGIN_SETTINGS_OPTION;

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueSettingsAssets']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerSettings(): void
    {
        register_setting($this->optionName, $this->optionName, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizeSettings'],
            'default' => $this->defaults(),
        ]);
    }

    public function registerMenu(): void
    {
        // Menu handled by AdminMenuService.
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('homlity-real-estate/v1', '/settings', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'getSettingsResponse'],
                'permission_callback' => [$this, 'canManageSettings'],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateSettingsResponse'],
                'permission_callback' => [$this, 'canManageSettings'],
            ],
        ]);
    }

    public function canManageSettings(): bool
    {
        return current_user_can('manage_options');
    }

    public function enqueueSettingsAssets(string $hook): void
    {
        if ($hook !== 'toplevel_page_homlity-real-estate-settings') {
            return;
        }

        wp_enqueue_style(
            'homlity-real-estate-settings-app',
            HOMLITY_PLUGIN_URL . 'assets/css/settings-app.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'homlity-real-estate-settings-app',
            HOMLITY_PLUGIN_URL . 'assets/js/settings-app.js',
            ['wp-element', 'wp-api-fetch', 'wp-i18n'],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        wp_localize_script('homlity-real-estate-settings-app', 'homlityPluginSettingsApp', [
            'settings' => $this->settingsPayload(),
            'defaults' => $this->defaults(),
            'currencies' => $this->supportedCurrencies(),
            'listingFieldOptions' => $this->listingFieldOptions(),
            'archiveOrderOptions' => $this->archiveOrderOptions(),
            'mapProviderOptions' => $this->mapProviderOptions(),
            'galleryModeOptions' => $this->galleryModeOptions(),
            'locationTaxonomies' => $this->locationTaxonomies(),
            'logoUrl' => HOMLITY_PLUGIN_URL . 'icono.png',
            'savePath' => '/homlity-real-estate/v1/settings',
            'locationTermsPath' => '/homlity-real-estate/v1/location-terms',
            'nonce' => wp_create_nonce('wp_rest'),
            'pageTitle' => __('Ajustes de la plataforma inmobiliaria', 'homlity-real-estate'),
            'saveLabel' => __('Guardar cambios', 'homlity-real-estate'),
            'resetLabel' => __('Restablecer', 'homlity-real-estate'),
        ]);
    }

    public function renderSettingsPage(): void
    {
        ?>
        <div class="wrap homlity-settings-admin">
            <div id="homlity-real-estate-settings-app"></div>
        </div>
        <?php
    }

    public function getSettingsResponse(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'settings' => $this->settingsPayload(),
            'defaults' => $this->defaults(),
            'currencies' => $this->supportedCurrencies(),
            'listingFieldOptions' => $this->listingFieldOptions(),
            'archiveOrderOptions' => $this->archiveOrderOptions(),
            'mapProviderOptions' => $this->mapProviderOptions(),
            'galleryModeOptions' => $this->galleryModeOptions(),
            'locationTaxonomies' => $this->locationTaxonomies(),
        ]);
    }

    public function updateSettingsResponse(\WP_REST_Request $request)
    {
        $incoming = $request->get_param('settings');
        if (!is_array($incoming)) {
            return new \WP_Error(
                'homlity_plugin_invalid_settings',
                __('La configuración enviada no es válida.', 'homlity-real-estate'),
                ['status' => 400]
            );
        }

        $merged = array_merge($this->settingsPayload(), $incoming);
        $sanitized = $this->sanitizeSettings($merged);
        update_option($this->optionName, $sanitized);

        return new \WP_REST_Response([
            'settings' => $this->settingsPayload(),
            'message' => __('Configuración guardada correctamente.', 'homlity-real-estate'),
        ]);
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

        $values['default_country'] = isset($values['default_country']) && $values['default_country'] !== ''
            ? absint($values['default_country'])
            : 0;

        $values['default_state'] = isset($values['default_state']) && $values['default_state'] !== ''
            ? absint($values['default_state'])
            : 0;

        $values['default_city'] = isset($values['default_city']) && $values['default_city'] !== ''
            ? absint($values['default_city'])
            : 0;

        $values['default_neighborhood'] = isset($values['default_neighborhood']) && $values['default_neighborhood'] !== ''
            ? absint($values['default_neighborhood'])
            : 0;

        $values['archive_per_page'] = isset($values['archive_per_page'])
            ? max(1, (int) $values['archive_per_page'])
            : $this->defaults()['archive_per_page'];

        $legacyArchiveOrderMap = [
            'date_desc' => 'created_desc',
            'date_asc' => 'created_asc',
        ];
        $allowedArchiveOrders = [
            'created_desc',
            'created_asc',
            'price_desc',
            'price_asc',
            'modified_desc',
            'modified_asc',
        ];
        $incomingArchiveOrder = isset($values['archive_order'])
            ? sanitize_key((string) $values['archive_order'])
            : '';
        if (isset($legacyArchiveOrderMap[$incomingArchiveOrder])) {
            $incomingArchiveOrder = $legacyArchiveOrderMap[$incomingArchiveOrder];
        }
        $values['archive_order'] = in_array($incomingArchiveOrder, $allowedArchiveOrders, true)
            ? $incomingArchiveOrder
            : $this->defaults()['archive_order'];

        $values['default_map_provider'] = isset($values['default_map_provider']) && in_array($values['default_map_provider'], ['leaflet_map', 'google_map'], true)
            ? $values['default_map_provider']
            : $this->defaults()['default_map_provider'];

        $values['detail_gallery_mode'] = isset($values['detail_gallery_mode']) && in_array($values['detail_gallery_mode'], ['light_gallery', 'owl_gallery'], true)
            ? $values['detail_gallery_mode']
            : $this->defaults()['detail_gallery_mode'];

        $values['enable_analytics'] = !empty($values['enable_analytics']);

        $values['primary_color'] = isset($values['primary_color'])
            ? sanitize_hex_color($values['primary_color'])
            : $this->defaults()['primary_color'];
        if (!$values['primary_color']) {
            $values['primary_color'] = $this->defaults()['primary_color'];
        }

        $values['base_currency'] = isset($values['base_currency'])
            ? strtoupper(sanitize_text_field($values['base_currency']))
            : $this->defaults()['base_currency'];

        $allowed = $this->supportedCurrencies();
        $looksLikeCurrency = (bool) preg_match('/^[A-Z]{3,5}$/', $values['base_currency']);
        if (!in_array($values['base_currency'], $allowed, true) && !$looksLikeCurrency) {
            $values['base_currency'] = $this->defaults()['base_currency'];
        }

        return $values;
    }

    private function settingsPayload(): array
    {
        $stored = get_option($this->optionName, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        if (($stored['primary_color'] ?? '') === '#2490ff') {
            $stored['primary_color'] = '#ff6752';
        }

        return $this->sanitizeSettings(array_merge($this->defaults(), $stored));
    }

    private function supportedCurrencies(): array
    {
        $currencies = \homlity_plugin_apply_filters(
            'homlity_plugin_supported_currencies',
            null,
            ['USD', 'EUR', 'GBP', 'COP', 'MXN', 'CLP', 'ARS', 'BRL', 'PEN', 'CAD', 'AUD', 'NZD', 'CHF', 'JPY', 'CNY']
        );

        if (!is_array($currencies)) {
            $currencies = ['USD'];
        }

        return array_values(array_unique(array_filter(array_map('strtoupper', $currencies))));
    }

    private function listingFieldOptions(): array
    {
        return [
            [
                'value' => 'price',
                'label' => __('Precio', 'homlity-real-estate'),
                'description' => __('Muestra el valor principal de la propiedad en cada tarjeta.', 'homlity-real-estate'),
            ],
            [
                'value' => 'excerpt',
                'label' => __('Descripción corta', 'homlity-real-estate'),
                'description' => __('Añade contexto editorial con un resumen breve del inmueble.', 'homlity-real-estate'),
            ],
            [
                'value' => 'features',
                'label' => __('Características', 'homlity-real-estate'),
                'description' => __('Expone área, habitaciones y baños en el listado.', 'homlity-real-estate'),
            ],
            [
                'value' => 'whatsapp',
                'label' => __('Botón de WhatsApp', 'homlity-real-estate'),
                'description' => __('Habilita contacto rápido desde la tarjeta del inmueble.', 'homlity-real-estate'),
            ],
        ];
    }

    private function archiveOrderOptions(): array
    {
        return [
            [
                'value' => 'created_desc',
                'label' => __('Fecha de creación (mayor a menor)', 'homlity-real-estate'),
            ],
            [
                'value' => 'created_asc',
                'label' => __('Fecha de creación (menor a mayor)', 'homlity-real-estate'),
            ],
            [
                'value' => 'price_desc',
                'label' => __('Precio (mayor a menor)', 'homlity-real-estate'),
            ],
            [
                'value' => 'price_asc',
                'label' => __('Precio (menor a mayor)', 'homlity-real-estate'),
            ],
            [
                'value' => 'modified_desc',
                'label' => __('Fecha de actualización (mayor a menor)', 'homlity-real-estate'),
            ],
            [
                'value' => 'modified_asc',
                'label' => __('Fecha de actualización (menor a mayor)', 'homlity-real-estate'),
            ],
        ];
    }

    private function mapProviderOptions(): array
    {
        return [
            [
                'value' => 'leaflet_map',
                'label' => __('Leaflet Map', 'homlity-real-estate'),
            ],
            [
                'value' => 'google_map',
                'label' => __('Google Map', 'homlity-real-estate'),
            ],
        ];
    }

    private function galleryModeOptions(): array
    {
        return [
            [
                'value' => 'light_gallery',
                'label' => __('Light Gallery', 'homlity-real-estate'),
            ],
            [
                'value' => 'owl_gallery',
                'label' => __('Owl Gallery', 'homlity-real-estate'),
            ],
        ];
    }

    private function locationTaxonomies(): array
    {
        return [
            'country' => PropertyTaxonomies::TAXONOMY_COUNTRY,
            'state' => PropertyTaxonomies::TAXONOMY_STATE,
            'city' => PropertyTaxonomies::TAXONOMY_CITY,
            'neighborhood' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
        ];
    }

    private function defaults(): array
    {
        return [
            'base_currency' => 'USD',
            'listing_fields' => ['price', 'excerpt', 'features', 'whatsapp'],
            'company_name' => 'Ecosistema Inmobiliario Homlity',
            'company_url' => 'https://github.com/homlity/homlity-real-estate',
            'support_email' => '',
            'default_country' => 0,
            'default_state' => 0,
            'default_city' => 0,
            'default_neighborhood' => 0,
            'archive_per_page' => 12,
            'archive_order' => 'created_desc',
            'primary_color' => '#ff6752',
            'default_map_provider' => 'leaflet_map',
            'detail_gallery_mode' => 'light_gallery',
            'enable_analytics' => false,
        ];
    }
}
