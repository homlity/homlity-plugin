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
        add_action('init', [$this, 'registerTranslatableStrings'], 12);
    }

    public function registerTranslatableStrings(): void
    {
        $strings = [
            'property' => __('Propiedad', 'homlity-real-estate'),
            'properties' => __('Propiedades', 'homlity-real-estate'),
            'base_currency' => __('Moneda base', 'homlity-real-estate'),
            'price_label' => __('Precio', 'homlity-real-estate'),
        ];

        foreach ($strings as $key => $string) {
            if (function_exists('pll_register_string')) {
                pll_register_string($key, $string, 'homlity-real-estate');
            }
            if (function_exists('icl_register_string')) {
                icl_register_string('homlity-real-estate', $key, $string);
            }
        }
    }
}
