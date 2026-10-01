<?php
// Los superglobales que se leen en este archivo sirven sólo para saber en qué
// contexto del maquetador se está pintando (vista previa, pestaña activa,
// petición AJAX del editor). No procesan formularios: van saneados con
// absint()/sanitize_key() y toda rama que cambia estado exige current_user_can(),
// así que un nonce no aplica.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
/**
 * Elementor widget: property listing with grid/map view, filters and sort.
 *
 * This widget is a thin adapter: it translates Elementor controls into a
 * ListingConfig value object and delegates all rendering to ListingRenderer.
 */

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Widget_Base;
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
        $rawSettings = (array) $this->get_data('settings');
        $settings['_hpl_responsive_columns'] = !empty($rawSettings['columns_tablet']) || !empty($rawSettings['columns_mobile']);
        $configuredPerPage = max(1, (int) ($settings['posts_per_page'] ?? 12));

        // Rendering dozens of complete cards in Elementor's editor creates a
        // very large htmlCache and can exhaust PHP memory. The public listing
        // keeps the configured page size; only the editor preview is capped.
        if ($this->isElementorEditorRequest()) {
            $settings['posts_per_page'] = min(12, $configuredPerPage);
        }

        $this->markMemoryDiagnostic('homlity.elementor.listing.render', [
            'configured_per_page' => $configuredPerPage,
            'effective_per_page'  => (int) ($settings['posts_per_page'] ?? 12),
            'memory'              => memory_get_usage(true),
        ]);

        $config = ListingConfig::fromElementor($settings);
        (new ListingRenderer())->render($config);

        $this->markMemoryDiagnostic('homlity.elementor.listing.rendered', [
            'memory' => memory_get_usage(true),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────


    private function isElementorEditorRequest(): bool
    {
        $action = isset($_REQUEST['action'])
            ? sanitize_key(wp_unslash((string) $_REQUEST['action']))
            : '';

        if ($action === 'elementor_ajax') {
            return true;
        }

        if (!class_exists('\\Elementor\\Plugin')) {
            return false;
        }

        $elementor = \Elementor\Plugin::instance();

        return (
            isset($elementor->editor)
            && method_exists($elementor->editor, 'is_edit_mode')
            && $elementor->editor->is_edit_mode()
        ) || (
            isset($elementor->preview)
            && method_exists($elementor->preview, 'is_preview_mode')
            && $elementor->preview->is_preview_mode()
        );
    }

    private function markMemoryDiagnostic(string $phase, array $context = []): void
    {
        $diagnostic = '\\SimiSync\\Support\\MemoryFatalDiagnostics';
        if (class_exists($diagnostic) && method_exists($diagnostic, 'mark')) {
            $diagnostic::mark($phase, $context);
        }
    }

}
