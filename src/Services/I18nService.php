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
        add_action('init', [$this, 'registerTranslatableStrings'], 12);
    }

    public function registerTranslatableStrings(): void
    {
        $strings = [
            'property' => __('Propiedad', 'inmopress-listings-inmobiliaria'),
            'properties' => __('Propiedades', 'inmopress-listings-inmobiliaria'),
            'base_currency' => __('Moneda base', 'inmopress-listings-inmobiliaria'),
            'price_label' => __('Precio', 'inmopress-listings-inmobiliaria'),
        ];

        foreach ($strings as $key => $string) {
            if (function_exists('pll_register_string')) {
                pll_register_string($key, $string, 'inmopress-listings-inmobiliaria');
            }
            if (function_exists('icl_register_string')) {
                icl_register_string('inmopress-listings-inmobiliaria', $key, $string);
            }
        }
    }
}
