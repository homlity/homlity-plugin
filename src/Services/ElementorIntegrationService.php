<?php
/**
 * Registers Elementor widgets, widget category and Theme Builder locations.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyAgentWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturesPrimaryWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturesSecondaryWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyGalleryWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyMapWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyRelatedWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyCardWidget;

if (!defined('ABSPATH')) {
    exit;
}

class ElementorIntegrationService implements ServiceInterface
{
    public function register(): void
    {
        add_action('elementor/loaded', [$this, 'init']);
    }

    public function init(): void
    {
        add_action('elementor/elements/categories_registered', [$this, 'registerCategory']);
        add_action('elementor/widgets/register', [$this, 'registerWidgets']);
        add_action('elementor/theme/register_locations', [$this, 'registerThemeLocations']);
        add_action('elementor/preview/enqueue_styles', [$this, 'enqueuePreviewAssets']);
    }

    public function registerCategory($elementsManager): void
    {
        $elementsManager->add_category('homlity-plugin', [
            'title' => __('Homlity Plugin', 'homlity-plugin'),
            'icon'  => 'fa fa-home',
        ]);
    }

    public function registerWidgets($widgetsManager): void
    {
        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }

        $widgets = [
            PropertyGalleryWidget::class,
            PropertyFeaturesPrimaryWidget::class,
            PropertyFeaturesSecondaryWidget::class,
            PropertyMapWidget::class,
            PropertyAgentWidget::class,
            PropertyRelatedWidget::class,
            PropertyCardWidget::class,
        ];

        foreach ($widgets as $widgetClass) {
            $widgetsManager->register(new $widgetClass());
        }
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
            'homlity-plugin-front-components',
            HOMLITY_PLUGIN_URL . 'assets/css/front-components.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );
    }
}
