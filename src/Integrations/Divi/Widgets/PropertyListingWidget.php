<?php
/**
 * Divi widget: property listing with grid/map view, filters and sort.
 *
 * This widget is a thin adapter: it translates Divi controls into a
 * ListingConfig value object and delegates all rendering to ListingRenderer.
 */

namespace Homlity\PluginInmobiliario\Integrations\Divi\Widgets;

use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Widget_Base;
use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyListingWidget extends Widget_Base
{
    use PropertyCardStylesTrait;
    use \Homlity\PluginInmobiliario\Integrations\Shared\PropertyListingControlsTrait;

    public function get_name(): string  { return 'property_listing'; }
    public function get_title(): string { return __('Listado de inmuebles', 'homlity-real-estate'); }
    public function get_icon(): string  { return 'eicon-posts-grid'; }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }

    protected function register_controls(): void
    {
        $this->registerListingControls();
    }

    protected function render(): void
    {
        $config = ListingConfig::fromBuilderSettings($this->get_settings_for_display());
        (new ListingRenderer())->render($config);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────


}
