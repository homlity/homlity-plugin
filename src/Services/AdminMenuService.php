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
        add_action('admin_head', [$this, 'printMenuIconStyles']);

        $basename = plugin_basename(HOMLITY_RE_PLUGIN_FILE);
        add_filter('plugin_action_links_' . $basename, [$this, 'addActionLinks']);
        add_filter('plugin_row_meta', [$this, 'addRowMeta'], 10, 2);
    }

    public function printMenuIconStyles(): void
    {
        $slugs = [
            'homlity-real-estate',
            'homlity-real-estate-geo',
            'homlity-real-estate-management',
            'homlity-real-estate-types',
            'homlity-real-estate-settings',
        ];

        $selectors = implode(',', array_map(
            static fn(string $s): string => "#adminmenu #toplevel_page_{$s} .wp-menu-image img",
            $slugs
        ));

        echo '<style>' . $selectors . '{width:20px!important;height:20px!important;object-fit:contain;}</style>';
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
        $icon = HOMLITY_PLUGIN_URL . 'icono.png';
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

        add_submenu_page(
            'homlity-real-estate',
            __('Etiquetas', 'homlity-real-estate'),
            __('Etiquetas', 'homlity-real-estate'),
            'edit_posts',
            'edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_TAG . '&post_type=property'
        );
    }

    private function registerGeoMenu(): void
    {
        $icon = HOMLITY_PLUGIN_URL . 'icono.png';
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
        $icon = HOMLITY_PLUGIN_URL . 'icono.png';
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
        $icon = HOMLITY_PLUGIN_URL . 'icono.png';
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
        $icon = HOMLITY_PLUGIN_URL . 'icono.png';
        add_menu_page(
            __('Homlity', 'homlity-real-estate'),
            __('Homlity', 'homlity-real-estate'),
            'manage_options',
            'homlity-real-estate-settings',
            [new SettingsService(), 'renderSettingsPage'],
            $icon,
            30
        );

        // WordPress auto-generates a first submenu identical to the parent.
        // We rename it to "Configuración" so it has a meaningful label.
        global $submenu;
        if (isset($submenu['homlity-real-estate-settings'][0])) {
            $submenu['homlity-real-estate-settings'][0][0] = __('Configuración', 'homlity-real-estate');
        }

        // Plans catalog — first item visible after Configuración.
        add_submenu_page(
            'homlity-real-estate-settings',
            __('Integraciones', 'homlity-real-estate'),
            __('Integraciones', 'homlity-real-estate'),
            'manage_options',
            'homlity-inicio',
            [new HomlityPlansService(), 'renderPage']
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

        foreach ([
            'homlity-license',
            'simi-sync-license',
            'wasi-sync-license',
            'softinm-sync-license',
        ] as $hiddenSubmenuSlug) {
            remove_submenu_page('homlity-real-estate-settings', $hiddenSubmenuSlug);
        }
    }

    public function addActionLinks(array $links): array
    {
        $extra = [
            '<a href="' . esc_url(admin_url('admin.php?page=homlity-real-estate-settings')) . '">'
                . esc_html__('Ajustes', 'homlity-real-estate') . '</a>',
            '<a href="' . esc_url(wp_nonce_url(admin_url('update-core.php?force-check=1'), 'upgrade-core')) . '">'
                . esc_html__('Comprobar actualización', 'homlity-real-estate') . '</a>',
        ];

        return array_merge($extra, $links);
    }

    public function addRowMeta(array $links, string $pluginFile): array
    {
        if (plugin_basename(HOMLITY_RE_PLUGIN_FILE) !== $pluginFile) {
            return $links;
        }

        $links[] = '<a href="https://wordpress.org/support/plugin/homlity-real-estate/reviews/#new-post"'
            . ' target="_blank" rel="noopener noreferrer"'
            . ' title="' . esc_attr__('Califica el plugin', 'homlity-real-estate') . '">'
            . '<span>★</span><span>★</span><span>★</span><span>★</span><span>★</span></a>';

        return $links;
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
