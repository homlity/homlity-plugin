<?php
/**
 * Base contract for services bootstrapped by the plugin.
 */

namespace Homlity\PluginInmobiliario\Core\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

interface ServiceInterface
{
    /**
     * Wire the service into WordPress hooks.
     */
    public function register(): void;
}
