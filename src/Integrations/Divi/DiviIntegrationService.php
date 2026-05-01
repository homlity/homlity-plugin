<?php
/**
 * Divi Builder integration for the property listing.
 *
 * Registers a custom Divi module (PropertyListingModule) once the Divi builder
 * framework is ready. The whole service is a no-op when Divi is not active.
 */

namespace Homlity\PluginInmobiliario\Integrations\Divi;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class DiviIntegrationService implements ServiceInterface
{
    public function register(): void
    {
        // ET_Builder_Module is defined by the Divi theme / Divi Builder plugin.
        add_action('et_builder_ready', [$this, 'loadModule']);
    }

    public function loadModule(): void
    {
        if (!class_exists('ET_Builder_Module')) {
            return;
        }

        require_once HOMLITY_PLUGIN_PATH . 'src/Integrations/Divi/Modules/PropertyListingModule.php';
    }
}
