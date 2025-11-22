<?php
/**
 * Custom admin menu organization for the plugin.
 */

namespace Codwelt\PluginInmobiliario\Services;

use Codwelt\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class AdminMenuService implements ServiceInterface
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenus']);
    }

    public function registerMenus(): void
    {
        $this->registerPropertiesMenu();
        $this->registerGeoMenu();
        $this->registerManagementMenu();
        $this->registerTypesMenu();
        $this->registerSettingsMenu();
    }

    private function registerPropertiesMenu(): void
    {
        $icon = PLUGIN_INMOBILIARIO_URL . 'icono.png';
        add_menu_page(
            __('Propiedades', 'plugin-inmobiliario'),
            __('Propiedades', 'plugin-inmobiliario'),
            'edit_posts',
            'plugin-inmobiliario',
            '',
            $icon,
            26
        );

        add_submenu_page(
            'plugin-inmobiliario',
            __('Todas las propiedades', 'plugin-inmobiliario'),
            __('Todas las propiedades', 'plugin-inmobiliario'),
            'edit_posts',
            'edit.php?post_type=property'
        );

        add_submenu_page(
            'plugin-inmobiliario',
            __('Añadir nueva', 'plugin-inmobiliario'),
            __('Añadir nueva', 'plugin-inmobiliario'),
            'edit_posts',
            'post-new.php?post_type=property'
        );

        add_submenu_page(
            'plugin-inmobiliario',
            __('Características', 'plugin-inmobiliario'),
            __('Características', 'plugin-inmobiliario'),
            'edit_posts',
            'edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_FEATURE . '&post_type=property'
        );

        add_submenu_page(
            'plugin-inmobiliario',
            __('Lugares cercanos', 'plugin-inmobiliario'),
            __('Lugares cercanos', 'plugin-inmobiliario'),
            'edit_posts',
            'edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_NEARBY . '&post_type=property'
        );
    }

    private function registerGeoMenu(): void
    {
        $icon = PLUGIN_INMOBILIARIO_URL . 'icono.png';
        add_menu_page(
            __('Georeferenciación', 'plugin-inmobiliario'),
            __('Georeferenciación', 'plugin-inmobiliario'),
            'edit_posts',
            'plugin-inmobiliario-geo',
            '',
            $icon,
            27
        );

        $geoTax = [
            PropertyTaxonomies::TAXONOMY_COUNTRY => __('Países', 'plugin-inmobiliario'),
            PropertyTaxonomies::TAXONOMY_STATE => __('Departamentos / Provincias', 'plugin-inmobiliario'),
            PropertyTaxonomies::TAXONOMY_CITY => __('Ciudades / Municipios', 'plugin-inmobiliario'),
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => __('Barrios', 'plugin-inmobiliario'),
            PropertyTaxonomies::TAXONOMY_LOCATION => __('Zonas', 'plugin-inmobiliario'),
        ];

        foreach ($geoTax as $tax => $label) {
            add_submenu_page(
                'plugin-inmobiliario-geo',
                $label,
                $label,
                'edit_posts',
                'edit-tags.php?taxonomy=' . $tax . '&post_type=property'
            );
        }
    }

    private function registerManagementMenu(): void
    {
        $icon = PLUGIN_INMOBILIARIO_URL . 'icono.png';
        add_menu_page(
            __('Tipos de gestión', 'plugin-inmobiliario'),
            __('Tipos de gestión', 'plugin-inmobiliario'),
            'edit_posts',
            'plugin-inmobiliario-management',
            '',
            $icon,
            28
        );

        add_submenu_page(
            'plugin-inmobiliario-management',
            __('Gestiones', 'plugin-inmobiliario'),
            __('Gestiones', 'plugin-inmobiliario'),
            'edit_posts',
            'edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_OPERATION . '&post_type=property'
        );
    }

    private function registerTypesMenu(): void
    {
        $icon = PLUGIN_INMOBILIARIO_URL . 'icono.png';
        add_menu_page(
            __('Tipos de inmuebles', 'plugin-inmobiliario'),
            __('Tipos de inmuebles', 'plugin-inmobiliario'),
            'edit_posts',
            'plugin-inmobiliario-types',
            '',
            $icon,
            29
        );

        $typeTax = [
            PropertyTaxonomies::TAXONOMY_TYPE => __('Tipos de inmueble', 'plugin-inmobiliario'),
            PropertyTaxonomies::TAXONOMY_CATEGORY => __('Categorías', 'plugin-inmobiliario'),
            PropertyTaxonomies::TAXONOMY_TAG => __('Etiquetas', 'plugin-inmobiliario'),
        ];

        foreach ($typeTax as $tax => $label) {
            add_submenu_page(
                'plugin-inmobiliario-types',
                $label,
                $label,
                'edit_posts',
                'edit-tags.php?taxonomy=' . $tax . '&post_type=property'
            );
        }
    }

    private function registerSettingsMenu(): void
    {
        $icon = PLUGIN_INMOBILIARIO_URL . 'icono.png';
        add_menu_page(
            __('Configuración del plugin', 'plugin-inmobiliario'),
            __('Configuración', 'plugin-inmobiliario'),
            'manage_options',
            'plugin-inmobiliario-settings',
            [new SettingsService(), 'renderSettingsPage'],
            $icon,
            30
        );
    }
}
