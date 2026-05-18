<?php
/**
 * Admin bar shortcuts for Homlity plugin pages.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class AdminBarService implements ServiceInterface
{
    public function register(): void
    {
        add_action('admin_bar_menu', [$this, 'registerNodes'], 90);
    }

    public function registerNodes(\WP_Admin_Bar $adminBar): void
    {
        if (!is_admin_bar_showing() || !current_user_can('edit_posts')) {
            return;
        }

        $adminBar->add_node([
            'id' => 'homlity-plugin-links',
            'title' => __('Homlity', 'homlity-plugin'),
            'href' => admin_url('edit.php?post_type=property'),
        ]);

        $adminBar->add_node([
            'id' => 'homlity-plugin-settings',
            'parent' => 'homlity-plugin-links',
            'title' => __('Configuración del plugin', 'homlity-plugin'),
            'href' => admin_url('admin.php?page=homlity-plugin-settings'),
        ]);

        $adminBar->add_node([
            'id' => 'homlity-plugin-properties',
            'parent' => 'homlity-plugin-links',
            'title' => __('Ver todas las propiedades', 'homlity-plugin'),
            'href' => admin_url('edit.php?post_type=property'),
        ]);

        if ($this->syncPageIsAvailable() && current_user_can('manage_options')) {
            $adminBar->add_node([
                'id' => 'homlity-plugin-sync-logs',
                'parent' => 'homlity-plugin-links',
                'title' => __('Sincronización de inmuebles', 'homlity-plugin'),
                'href' => admin_url('admin.php?page=homlity-sync&tab=logs'),
            ]);
        }
    }

    private function syncPageIsAvailable(): bool
    {
        global $admin_page_hooks;

        if (!is_array($admin_page_hooks)) {
            return false;
        }

        return isset($admin_page_hooks['homlity-sync']);
    }
}
