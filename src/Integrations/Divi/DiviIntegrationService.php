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
    private bool $modulesLoaded = false;

    public function register(): void
    {
        // ET_Builder_Module is defined by the Divi theme / Divi Builder plugin.
        add_action('et_builder_ready', [$this, 'loadModule']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets'], 20);
    }

    public function loadModule(): void
    {
        if (!class_exists('ET_Builder_Module')) {
            return;
        }
        $this->modulesLoaded = true;

        require_once __DIR__ . '/Modules/PropertyListingModule.php';

        require_once __DIR__ . '/Compatibility/DiviWidgetApi.php';
        require_once __DIR__ . '/Modules/WidgetModule.php';

        foreach ($this->widgetClasses() as $widgetClass) {
            try {
                if (class_exists($widgetClass)) {
                    new \Homlity_Divi_Widget_Module($widgetClass);
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

    public function enqueueAssets(): void
    {
        if (!$this->modulesLoaded) {
            return;
        }

        // Divi modules can be placed on ordinary pages and in Theme Builder
        // templates, not only on property archives/details. Their structural
        // CSS therefore has to be available before the modules render.
        wp_enqueue_style(
            'homlity-real-estate-front-components',
            HOMLITY_PLUGIN_URL . 'assets/css/front-components.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );
        wp_enqueue_style(
            'homlity-real-estate-listing',
            HOMLITY_PLUGIN_URL . 'assets/css/property-listing.css',
            ['homlity-real-estate-front-components'],
            HOMLITY_PLUGIN_VERSION
        );
    }

    /** @return list<class-string> */
    private function widgetClasses(): array
    {
        $namespace = 'Homlity\\PluginInmobiliario\\Integrations\\Divi\\Widgets\\';
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
