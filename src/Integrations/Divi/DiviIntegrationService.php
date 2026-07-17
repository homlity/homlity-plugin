<?php
/**
 * Divi Builder integration for the property listing.
 *
 * Registers a custom Divi module (PropertyListingModule) once the Divi builder
 * framework is ready. The whole service is a no-op when Divi is not active.
 */

namespace Homlity\PluginInmobiliario\Integrations\Divi;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Services\DataSeederService;

if (!defined('ABSPATH')) {
    exit;
}

class DiviIntegrationService implements ServiceInterface
{
    public function register(): void
    {
        // ET_Builder_Module is defined by the Divi theme / Divi Builder plugin.
        add_action('et_builder_ready', [$this, 'loadModule']);
    }

    public function loadModule(): void
    {
        if (!class_exists('ET_Builder_Module')) {
            return;
        }

        require_once __DIR__ . '/Modules/PropertyListingModule.php';

        // Load the small Homlity-owned widget contract only when Elementor is
        // absent. It does not bootstrap or depend on the Elementor plugin.
        if (!class_exists('\\Elementor\\Widget_Base')) {
            require_once __DIR__ . '/Compatibility/ElementorShim.php';
        }
        require_once __DIR__ . '/Modules/ElementorWidgetModule.php';

        foreach ($this->widgetClasses() as $widgetClass) {
            try {
                if (class_exists($widgetClass)) {
                    new \Homlity_Divi_Elementor_Widget_Module($widgetClass);
                }
            } catch (\Throwable $exception) {
                /**
                 * Allow integrations to report a widget that could not be
                 * adapted without preventing Divi or the site from loading.
                 */
                do_action('homlity_divi_widget_registration_error', $widgetClass, $exception);

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        'Homlity Divi: no se pudo registrar %s: %s',
                        $widgetClass,
                        $exception->getMessage()
                    ));
                }
            }
        }

        (new DataSeederService())->seedBuilderTemplates();
    }

    /** @return list<class-string> */
    private function widgetClasses(): array
    {
        $namespace = 'Homlity\\PluginInmobiliario\\Integrations\\Elementor\\Widgets\\';
        return array_map(static fn(string $name): string => $namespace . $name, [
            'PropertyAgentWidget', 'PropertyAgentsAvailableWidget',
            'PropertyBreadcrumbWidget', 'PropertyCardWidget', 'PropertyContentWidget',
            'PropertyDynamicCodeButtonWidget', 'PropertyFeaturedCitiesWidget',
            'PropertyFeaturedNeighborhoodsWidget', 'PropertyFeaturedOperationsWidget',
            'PropertyFeaturedTermsWidget', 'PropertyFeaturedTypesWidget',
            'PropertyFeaturesPrimaryWidget', 'PropertyFeaturesSecondaryWidget',
            'PropertyFilterWidget', 'PropertyGalleryWidget', 'PropertyListingWidget',
            'PropertyMapWidget', 'PropertyMediaTabsWidget', 'PropertyOperationPriceWidget',
            'PropertyRelatedWidget', 'PropertyResultsTitleWidget', 'PropertyShareWidget',
            'PropertySummaryWidget', 'PropertyTechnicalSheetButtonWidget',
            'PropertyTitleWidget', 'PropertyVideoWidget', 'SimulatorWidget',
        ]);
    }
}
