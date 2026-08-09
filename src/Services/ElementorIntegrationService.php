<?php
/**
 * Registers Elementor widgets, widget category and Theme Builder locations.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyAgentWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturesPrimaryWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturesSecondaryWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFilterWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyGalleryWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyListingWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyMapWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyRelatedWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyResultsTitleWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyCardWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\SimulatorWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertySummaryWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyContentWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyOperationPriceWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyShareWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyTitleWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyMediaTabsWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyBreadcrumbWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyVideoWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyTechnicalSheetButtonWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyDynamicCodeButtonWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturedCitiesWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturedNeighborhoodsWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturedOperationsWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturedTypesWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyAgentsAvailableWidget;
use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!defined('ABSPATH')) {
    exit;
}

class ElementorIntegrationService implements ServiceInterface
{
    private bool $initialized = false;
    private bool $widgetsRegistered = false;

    public function register(): void
    {
        if (did_action('elementor/loaded')) {
            $this->init();
            return;
        }

        add_action('elementor/loaded', [$this, 'init']);
    }

    public function init(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        if (did_action('init')) {
            (new DataSeederService())->seedElementorTemplates();
        } else {
            add_action('init', static function () {
                (new DataSeederService())->seedElementorTemplates();
            }, 11);
        }

        add_action('elementor/elements/categories_registered', [$this, 'registerCategory']);
        add_action('elementor/widgets/register', [$this, 'registerWidgets']);
        add_action('elementor/widgets/widgets_registered', [$this, 'registerLegacyWidgets']);
        add_action('elementor/theme/register_locations', [$this, 'registerThemeLocations']);
        add_action('elementor/preview/enqueue_styles', [$this, 'enqueuePreviewAssets']);

        if (did_action('init')) {
            $this->registerPropertyElementorSupport();
        } else {
            add_action('init', [$this, 'registerPropertyElementorSupport'], 20);
        }
    }

    public function registerPropertyElementorSupport(): void
    {
        add_post_type_support(PropertyPostType::POST_TYPE, 'elementor');

        $supportedPostTypes = get_option('elementor_cpt_support', ['page', 'post']);
        if (!is_array($supportedPostTypes)) {
            $supportedPostTypes = ['page', 'post'];
        }

        if (!in_array(PropertyPostType::POST_TYPE, $supportedPostTypes, true)) {
            $supportedPostTypes[] = PropertyPostType::POST_TYPE;
            update_option('elementor_cpt_support', array_values($supportedPostTypes));
        }
    }

    public function registerCategory($elementsManager): void
    {
        $elementsManager->add_category('homlity-real-estate', [
            'title' => __('Homlity Plugin', 'homlity-real-estate'),
            'icon'  => 'fa fa-home',
        ]);
    }

    public function registerWidgets($widgetsManager): void
    {
        if ($this->widgetsRegistered) {
            return;
        }

        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }

        if (!method_exists($widgetsManager, 'register') && !method_exists($widgetsManager, 'register_widget_type')) {
            return;
        }

        $widgets = [
            PropertyFilterWidget::class,
            PropertyListingWidget::class,
            PropertyResultsTitleWidget::class,
            PropertyTitleWidget::class,
            PropertyOperationPriceWidget::class,
            PropertyContentWidget::class,
            PropertySummaryWidget::class,
            PropertyGalleryWidget::class,
            PropertyBreadcrumbWidget::class,
            PropertyMediaTabsWidget::class,
            PropertyVideoWidget::class,
            PropertyTechnicalSheetButtonWidget::class,
            PropertyDynamicCodeButtonWidget::class,
            PropertyFeaturedCitiesWidget::class,
            PropertyFeaturedNeighborhoodsWidget::class,
            PropertyFeaturedOperationsWidget::class,
            PropertyFeaturedTypesWidget::class,
            PropertyAgentsAvailableWidget::class,
            PropertyFeaturesPrimaryWidget::class,
            PropertyFeaturesSecondaryWidget::class,
            PropertyMapWidget::class,
            PropertyAgentWidget::class,
            PropertyShareWidget::class,
            PropertyRelatedWidget::class,
            PropertyCardWidget::class,
            SimulatorWidget::class,
        ];

        $this->markMemoryDiagnostic('homlity.elementor.widgets.start', [
            'widgets' => count($widgets),
            'memory'  => memory_get_usage(true),
        ]);

        foreach ($widgets as $widgetClass) {
            $this->markMemoryDiagnostic('homlity.elementor.widget.register', [
                'widget' => $widgetClass,
                'memory' => memory_get_usage(true),
            ]);
            $widget = new $widgetClass();
            if (method_exists($widgetsManager, 'register')) {
                $widgetsManager->register($widget);
            } else {
                $widgetsManager->register_widget_type($widget);
            }
        }

        $this->widgetsRegistered = true;
        $this->markMemoryDiagnostic('homlity.elementor.widgets.done', [
            'memory' => memory_get_usage(true),
        ]);
    }

    private function markMemoryDiagnostic(string $phase, array $context = []): void
    {
        $diagnostic = '\\SimiSync\\Support\\MemoryFatalDiagnostics';
        if (class_exists($diagnostic) && method_exists($diagnostic, 'mark')) {
            $diagnostic::mark($phase, $context);
        }
    }

    public function registerLegacyWidgets(): void
    {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }

        $this->registerWidgets(\Elementor\Plugin::instance()->widgets_manager);
    }

    /**
     * Registers single and archive locations for Elementor Theme Builder (Pro).
     * Users can then assign custom templates to Property posts / archive in Theme Builder.
     */
    public function registerThemeLocations($elementorThemeManager): void
    {
        $elementorThemeManager->register_all_core_location();
    }

    /**
     * Ensures plugin CSS is available inside the Elementor editor preview iframe.
     */
    public function enqueuePreviewAssets(): void
    {
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

        wp_enqueue_style(
            'homlity-real-estate-leaflet',
            HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.min.css',
            [],
            '1.9.4'
        );

        wp_enqueue_script(
            'homlity-real-estate-leaflet',
            HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.min.js',
            [],
            '1.9.4',
            true
        );

        wp_enqueue_script(
            'homlity-real-estate-listing',
            HOMLITY_PLUGIN_URL . 'assets/js/property-listing.js',
            ['homlity-real-estate-leaflet'],
            HOMLITY_PLUGIN_VERSION,
            true
        );
    }
}
