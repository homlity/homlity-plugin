<?php
/**
 * Handles versioning and upgrade routines.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class VersionService implements ServiceInterface
{
    private string $optionName = HOMLITY_PLUGIN_VERSION_OPTION;

    public function register(): void
    {
        add_action('init', [$this, 'maybeUpgrade'], 99);
    }

    public function maybeUpgrade(): void
    {
        $current = get_option($this->optionName, null);
        $target = HOMLITY_PLUGIN_VERSION;

        if ($current === $target) {
            return;
        }

        $this->runUpgrades($current, $target);
        update_option($this->optionName, $target);
    }

    private function runUpgrades(?string $fromVersion, string $toVersion): void
    {
        // Seed new terms or structures added in updates.
        (new DataSeederService())->seed();

        // Elementor's bundled Font Awesome 5 catalog uses "check-circle".
        $this->migrateElementorFontAwesomeIcons();

        // Refresh rewrite rules on structure changes.
        flush_rewrite_rules(false);
    }

    private function migrateElementorFontAwesomeIcons(): void
    {
        global $wpdb;

        $batchSize = 25;
        $totalUpdated = 0;

        do {
            $affected = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_value = REPLACE(meta_value, %s, %s)
                 WHERE meta_key = %s AND meta_value LIKE %s
                 LIMIT %d",
                'fa-circle-check',
                'fa-check-circle',
                '_elementor_data',
                '%' . $wpdb->esc_like('fa-circle-check') . '%',
                $batchSize
            ));

            if ($affected === false) {
                break;
            }

            $affected = (int) $affected;
            $totalUpdated += $affected;
        } while ($affected === $batchSize);

        if (
            $totalUpdated > 0
            && class_exists('\\Elementor\\Plugin')
            && isset(\Elementor\Plugin::$instance->files_manager)
        ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }
}
