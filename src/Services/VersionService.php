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

        // Refresh rewrite rules on structure changes.
        flush_rewrite_rules(false);
    }
}
