<?php
/**
 * Handles versioning and upgrade routines.
 */

namespace Codwelt\PluginInmobiliario\Services;

use Codwelt\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class VersionService implements ServiceInterface
{
    private string $optionName = 'inmopress_version';

    public function register(): void
    {
        add_action('plugins_loaded', [$this, 'maybeUpgrade'], 1);
    }

    public function maybeUpgrade(): void
    {
        $current = get_option($this->optionName);
        $target = PLUGIN_INMOBILIARIO_VERSION;

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
