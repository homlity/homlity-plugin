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
        load_plugin_textdomain(HOMLITY_PLUGIN_TEXT_DOMAIN, false, dirname(plugin_basename(HOMLITY_PLUGIN_FILE)) . '/languages');
    }

    public function registerTranslatableStrings(): void
    {
        $strings = [
            'property' => __('Propiedad', HOMLITY_PLUGIN_TEXT_DOMAIN),
            'properties' => __('Propiedades', HOMLITY_PLUGIN_TEXT_DOMAIN),
            'base_currency' => __('Moneda base', HOMLITY_PLUGIN_TEXT_DOMAIN),
            'price_label' => __('Precio', HOMLITY_PLUGIN_TEXT_DOMAIN),
        ];

        foreach ($strings as $key => $string) {
            if (function_exists('pll_register_string')) {
                pll_register_string($key, $string, HOMLITY_PLUGIN_TEXT_DOMAIN);
            }
            if (function_exists('icl_register_string')) {
                icl_register_string(HOMLITY_PLUGIN_TEXT_DOMAIN, $key, $string);
            }
        }
    }
}
