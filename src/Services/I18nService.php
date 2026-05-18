<?php
/**
 * Handles translations and multi-language helpers.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class I18nService implements ServiceInterface
{
    public function register(): void
    {
        add_action('init', [$this, 'loadTextDomain'], 5);
        add_action('init', [$this, 'registerTranslatableStrings'], 12);
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain('homlity-plugin', false, dirname(plugin_basename(HOMLITY_PLUGIN_FILE)) . '/languages');
    }

    public function registerTranslatableStrings(): void
    {
        $strings = [
            'property' => __('Propiedad', 'homlity-plugin'),
            'properties' => __('Propiedades', 'homlity-plugin'),
            'base_currency' => __('Moneda base', 'homlity-plugin'),
            'price_label' => __('Precio', 'homlity-plugin'),
        ];

        foreach ($strings as $key => $string) {
            if (function_exists('pll_register_string')) {
                pll_register_string($key, $string, 'homlity-plugin');
            }
            if (function_exists('icl_register_string')) {
                icl_register_string('homlity-plugin', $key, $string);
            }
        }
    }
}
