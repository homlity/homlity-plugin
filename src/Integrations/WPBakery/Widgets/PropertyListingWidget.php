<?php
/**
 * WPBakery widget: property listing with grid/map view, filters and sort.
 *
 * This widget is a thin adapter: it translates WPBakery controls into a
 * ListingConfig value object and delegates all rendering to ListingRenderer.
 */

namespace Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets;

use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Widget_Base;
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
        $settings = $this->get_settings_for_display();
        $configuredPerPage = max(1, (int) ($settings['posts_per_page'] ?? 12));

        // Rendering dozens of complete cards in the visual editor creates a
        // very large preview response and can exhaust PHP memory. The public
        // listing keeps the configured page size; only WPBakery is capped.
        if ($this->homlityIsEditor()) {
            $settings['posts_per_page'] = min(12, $configuredPerPage);
        }

        $this->markMemoryDiagnostic('homlity.wpbakery.listing.render', [
            'configured_per_page' => $configuredPerPage,
            'effective_per_page'  => (int) ($settings['posts_per_page'] ?? 12),
            'memory'              => memory_get_usage(true),
        ]);

        $config = ListingConfig::fromBuilderSettings($settings);
        (new ListingRenderer())->render($config);

        $this->markMemoryDiagnostic('homlity.wpbakery.listing.rendered', [
            'memory' => memory_get_usage(true),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────


    private function markMemoryDiagnostic(string $phase, array $context = []): void
    {
        $diagnostic = '\\SimiSync\\Support\\MemoryFatalDiagnostics';
        if (class_exists($diagnostic) && method_exists($diagnostic, 'mark')) {
            $diagnostic::mark($phase, $context);
        }
    }

}
