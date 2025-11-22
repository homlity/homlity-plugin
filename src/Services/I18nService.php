<?php
/**
 * Handles translations and multi-language helpers.
 */

namespace Codwelt\PluginInmobiliario\Services;

use Codwelt\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class I18nService implements ServiceInterface
{
    public function register(): void
    {
        add_action('init', [$this, 'loadTextDomain']);
        add_action('init', [$this, 'registerTranslatableStrings'], 12);
    }

    public function loadTextDomain(): void
    {
        $pluginBasename = plugin_basename(PLUGIN_INMOBILIARIO_PATH . 'plugin-inmobiliario.php');
        load_plugin_textdomain('plugin-inmobiliario', false, dirname($pluginBasename) . '/languages');
    }

    public function registerTranslatableStrings(): void
    {
        $strings = [
            'property' => __('Propiedad', 'plugin-inmobiliario'),
            'properties' => __('Propiedades', 'plugin-inmobiliario'),
            'base_currency' => __('Moneda base', 'plugin-inmobiliario'),
            'price_label' => __('Precio', 'plugin-inmobiliario'),
        ];

        foreach ($strings as $key => $string) {
            if (function_exists('pll_register_string')) {
                pll_register_string($key, $string, 'plugin-inmobiliario');
            }
            if (function_exists('icl_register_string')) {
                icl_register_string('plugin-inmobiliario', $key, $string);
            }
        }
    }
}
