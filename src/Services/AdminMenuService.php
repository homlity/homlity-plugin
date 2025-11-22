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
            __('Propiedades', 'inmopress-listings-inmobiliaria'),
            __('Propiedades', 'inmopress-listings-inmobiliaria'),
            'edit_posts',
            'inmopress-listings-inmobiliaria',
            '',
            $icon,
            26
        );

        add_submenu_page(
            'inmopress-listings-inmobiliaria',
            __('Todas las propiedades', 'inmopress-listings-inmobiliaria'),
            __('Todas las propiedades', 'inmopress-listings-inmobiliaria'),
            'edit_posts',
            'edit.php?post_type=property'
        );

        add_submenu_page(
            'inmopress-listings-inmobiliaria',
            __('Añadir nueva', 'inmopress-listings-inmobiliaria'),
            __('Añadir nueva', 'inmopress-listings-inmobiliaria'),
            'edit_posts',
            'post-new.php?post_type=property'
        );

        add_submenu_page(
            'inmopress-listings-inmobiliaria',
            __('Características', 'inmopress-listings-inmobiliaria'),
            __('Características', 'inmopress-listings-inmobiliaria'),
            'edit_posts',
            'edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_FEATURE . '&post_type=property'
        );

        add_submenu_page(
            'inmopress-listings-inmobiliaria',
            __('Lugares cercanos', 'inmopress-listings-inmobiliaria'),
            __('Lugares cercanos', 'inmopress-listings-inmobiliaria'),
            'edit_posts',
            'edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_NEARBY . '&post_type=property'
        );
    }

    private function registerGeoMenu(): void
    {
        $icon = PLUGIN_INMOBILIARIO_URL . 'icono.png';
        add_menu_page(
            __('Georeferenciación', 'inmopress-listings-inmobiliaria'),
            __('Georeferenciación', 'inmopress-listings-inmobiliaria'),
            'edit_posts',
            'plugin-inmobiliario-geo',
            '',
            $icon,
            27
        );

        $geoTax = [
            PropertyTaxonomies::TAXONOMY_COUNTRY => __('Países', 'inmopress-listings-inmobiliaria'),
            PropertyTaxonomies::TAXONOMY_STATE => __('Departamentos / Provincias', 'inmopress-listings-inmobiliaria'),
            PropertyTaxonomies::TAXONOMY_CITY => __('Ciudades / Municipios', 'inmopress-listings-inmobiliaria'),
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => __('Barrios', 'inmopress-listings-inmobiliaria'),
            PropertyTaxonomies::TAXONOMY_LOCATION => __('Zonas', 'inmopress-listings-inmobiliaria'),
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
            __('Tipos de gestión', 'inmopress-listings-inmobiliaria'),
            __('Tipos de gestión', 'inmopress-listings-inmobiliaria'),
            'edit_posts',
            'plugin-inmobiliario-management',
            '',
            $icon,
            28
        );

        add_submenu_page(
            'plugin-inmobiliario-management',
            __('Gestiones', 'inmopress-listings-inmobiliaria'),
            __('Gestiones', 'inmopress-listings-inmobiliaria'),
            'edit_posts',
            'edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_OPERATION . '&post_type=property'
        );
    }

    private function registerTypesMenu(): void
    {
        $icon = PLUGIN_INMOBILIARIO_URL . 'icono.png';
        add_menu_page(
            __('Tipos de inmuebles', 'inmopress-listings-inmobiliaria'),
            __('Tipos de inmuebles', 'inmopress-listings-inmobiliaria'),
            'edit_posts',
            'plugin-inmobiliario-types',
            '',
            $icon,
            29
        );

        $typeTax = [
            PropertyTaxonomies::TAXONOMY_TYPE => __('Tipos de inmueble', 'inmopress-listings-inmobiliaria'),
            PropertyTaxonomies::TAXONOMY_CATEGORY => __('Categorías', 'inmopress-listings-inmobiliaria'),
            PropertyTaxonomies::TAXONOMY_TAG => __('Etiquetas', 'inmopress-listings-inmobiliaria'),
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
            __('Configuración del plugin', 'inmopress-listings-inmobiliaria'),
            __('Configuración', 'inmopress-listings-inmobiliaria'),
            'manage_options',
            'plugin-inmobiliario-settings',
            [new SettingsService(), 'renderSettingsPage'],
            $icon,
            30
        );
    }
}
