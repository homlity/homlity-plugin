<?php
/**
 * Registers Elementor widgets to expose plugin components.
 */

namespace Codwelt\PluginInmobiliario\Services;

use Codwelt\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Codwelt\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyAgentWidget;
use Codwelt\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturesPrimaryWidget;
use Codwelt\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturesSecondaryWidget;
use Codwelt\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyGalleryWidget;
use Codwelt\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyMapWidget;
use Codwelt\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyRelatedWidget;
use Codwelt\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyCardWidget;

if (!defined('ABSPATH')) {
    exit;
}

class ElementorIntegrationService implements ServiceInterface
{
    public function register(): void
    {
        add_action('elementor/widgets/register', [$this, 'registerWidgets']);
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
}
