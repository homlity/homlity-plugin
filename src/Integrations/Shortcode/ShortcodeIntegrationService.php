<?php
/**
 * Registers the [homlity_listing] shortcode.
 *
 * This service is always active regardless of which page builder is installed.
 * WPBakery and Divi both route through this same shortcode; plain HTML/Bootstrap
 * pages can also use it directly.
 *
 * Usage:
 *   [homlity_listing]
 *   [homlity_listing view="map" columns="2" template="bootstrap" per_page="6"]
 *   [homlity_listing operation="12" filters="false"]
 *
 * All available attributes and their defaults are listed in ::defaults().
 */

namespace Homlity\PluginInmobiliario\Integrations\Shortcode;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;

if (!defined('ABSPATH')) {
    exit;
}

class ShortcodeIntegrationService implements ServiceInterface
{
    public function register(): void
    {
        add_shortcode('homlity_listing', [$this, 'render']);
    }

    public function render($atts): string
    {
        $atts   = shortcode_atts(self::defaults(), (array) $atts, 'homlity_listing');
        $config = ListingConfig::fromAtts($atts);

        ob_start();
        (new ListingRenderer())->render($config);
        return ob_get_clean() ?: '';
    }

    /**
     * Default attribute values – doubles as documentation for template authors.
     */
    public static function defaults(): array
    {
        return [
            // Presentation
            'template'         => 'default',  // 'default' | 'bootstrap'
            'view'             => 'grid',      // 'grid' | 'map'
            'cards'            => 'true',
            'map'              => 'true',
            'view_toggle'      => 'true',
            'columns'          => '3',

            // Query
            'per_page'         => '12',
            'orderby'          => 'date',      // date|price_asc|price_desc|title
            'featured'         => 'false',
            'operation'        => '0',         // preset operation term ID
            'type'             => '0',         // preset type term ID
            'locality'         => '0',         // preset locality post ID

            // Filters
            'filters'          => 'true',
            'filter_operation' => 'true',
            'filter_type'      => 'true',
            'filter_city'      => 'true',
            'filter_price'     => 'true',
            'filter_bedrooms'  => 'true',
            'sort'             => 'true',

            // Map
            'map_height'       => '500',
            'map_zoom'         => '12',
        ];
    }
}
