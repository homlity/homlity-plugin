<?php
/**
 * Custom admin menu organization for the plugin.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

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
        $icon = HOMLITY_PLUGIN_URL . 'FAVICON.ico';
        add_menu_page(
            __('Propiedades', 'homlity-real-estate'),
            __('Propiedades', 'homlity-real-estate'),
            'edit_posts',
            'homlity-real-estate',
            [$this, 'redirectToPropertiesPage'],
            $icon,
            26
        );

        add_submenu_page(
            'homlity-real-estate',
            __('Todas las propiedades', 'homlity-real-estate'),
            __('Todas las propiedades', 'homlity-real-estate'),
            'edit_posts',
            'edit.php?post_type=property'
        );

        add_submenu_page(
            'homlity-real-estate',
            __('Añadir nueva', 'homlity-real-estate'),
            __('Añadir nueva', 'homlity-real-estate'),
            'edit_posts',
            'post-new.php?post_type=property'
        );

        add_submenu_page(
            'homlity-real-estate',
            __('Características', 'homlity-real-estate'),
            __('Características', 'homlity-real-estate'),
            'edit_posts',
            'edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_FEATURE . '&post_type=property'
        );

        add_submenu_page(
            'homlity-real-estate',
            __('Lugares cercanos', 'homlity-real-estate'),
            __('Lugares cercanos', 'homlity-real-estate'),
            'edit_posts',
            'edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_NEARBY . '&post_type=property'
        );
    }

    private function registerGeoMenu(): void
    {
        $icon = HOMLITY_PLUGIN_URL . 'FAVICON.ico';
        add_menu_page(
            __('Georeferenciación', 'homlity-real-estate'),
            __('Georeferenciación', 'homlity-real-estate'),
            'edit_posts',
            'homlity-real-estate-geo',
            [$this, 'redirectToGeoPage'],
            $icon,
            27
        );

        $geoTax = [
            PropertyTaxonomies::TAXONOMY_COUNTRY => __('Países', 'homlity-real-estate'),
            PropertyTaxonomies::TAXONOMY_STATE => __('Departamentos / Provincias', 'homlity-real-estate'),
            PropertyTaxonomies::TAXONOMY_CITY => __('Ciudades / Municipios', 'homlity-real-estate'),
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => __('Barrios', 'homlity-real-estate'),
            PropertyTaxonomies::TAXONOMY_LOCATION => __('Zonas', 'homlity-real-estate'),
        ];

        foreach ($geoTax as $tax => $label) {
            add_submenu_page(
                'homlity-real-estate-geo',
                $label,
                $label,
                'edit_posts',
                'edit-tags.php?taxonomy=' . $tax . '&post_type=property'
            );
        }
    }

    private function registerManagementMenu(): void
    {
        $icon = HOMLITY_PLUGIN_URL . 'FAVICON.ico';
        add_menu_page(
            __('Tipos de gestión', 'homlity-real-estate'),
            __('Tipos de gestión', 'homlity-real-estate'),
            'edit_posts',
            'homlity-real-estate-management',
            [$this, 'redirectToManagementPage'],
            $icon,
            28
        );

        add_submenu_page(
            'homlity-real-estate-management',
            __('Gestiones', 'homlity-real-estate'),
            __('Gestiones', 'homlity-real-estate'),
            'edit_posts',
            'edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_OPERATION . '&post_type=property'
        );
    }

    private function registerTypesMenu(): void
    {
        $icon = HOMLITY_PLUGIN_URL . 'FAVICON.ico';
        add_menu_page(
            __('Tipos de inmuebles', 'homlity-real-estate'),
            __('Tipos de inmuebles', 'homlity-real-estate'),
            'edit_posts',
            'homlity-real-estate-types',
            [$this, 'redirectToTypesPage'],
            $icon,
            29
        );

        $typeTax = [
            PropertyTaxonomies::TAXONOMY_TYPE => __('Tipos de inmueble', 'homlity-real-estate'),
            PropertyTaxonomies::TAXONOMY_CATEGORY => __('Categorías', 'homlity-real-estate'),
            PropertyTaxonomies::TAXONOMY_TAG => __('Etiquetas', 'homlity-real-estate'),
        ];

        foreach ($typeTax as $tax => $label) {
            add_submenu_page(
                'homlity-real-estate-types',
                $label,
                $label,
                'edit_posts',
                'edit-tags.php?taxonomy=' . $tax . '&post_type=property'
            );
        }

        remove_submenu_page('homlity-real-estate-types', 'homlity-real-estate-types');
    }

    private function registerSettingsMenu(): void
    {
        $icon = HOMLITY_PLUGIN_URL . 'FAVICON.ico';
        add_menu_page(
            __('Configuración del plugin', 'homlity-real-estate'),
            __('Configuración', 'homlity-real-estate'),
            'manage_options',
            'homlity-real-estate-settings',
            [new SettingsService(), 'renderSettingsPage'],
            $icon,
            30
        );

        /**
         * Permite que plugins externos (integraciones) registren subpáginas
         * bajo el menú de configuración de homlity-real-estate.
         *
         * Uso en un plugin externo:
         *   add_action('homlity_plugin_register_integration_submenus', function(string $parentSlug) {
         *       add_submenu_page($parentSlug, 'Mi integración', 'Mi integración', 'manage_options', 'mi-slug', 'mi_callback');
         *   });
         */
        do_action('homlity_plugin_register_integration_submenus', 'homlity-real-estate-settings');
    }

    public function redirectToPropertiesPage(): void
    {
        $this->redirectToAdminPath('edit.php?post_type=property');
    }

    public function redirectToGeoPage(): void
    {
        $this->redirectToAdminPath('edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_COUNTRY . '&post_type=property');
    }

    public function redirectToManagementPage(): void
    {
        $this->redirectToAdminPath('edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_OPERATION . '&post_type=property');
    }

    public function redirectToTypesPage(): void
    {
        $this->redirectToAdminPath('edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_TYPE . '&post_type=property');
    }

    private function redirectToAdminPath(string $path): void
    {
        wp_safe_redirect(admin_url($path));
        exit;
    }
}
