<?php
/**
 * Divi widget: related properties for the current property.
 */

namespace Homlity\PluginInmobiliario\Integrations\Divi\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyRelatedWidget extends BasePropertyWidget
{
    use PropertyCardStylesTrait;

    public function get_name(): string
    {
        return 'property_related';
    }

    public function get_title(): string
    {
        return __('Inmuebles relacionados', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-posts-grid';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);

        $this->register_property_control();

        $this->add_control(
            'posts_per_page',
            [
                'label'   => __('Cantidad de inmuebles', 'homlity-real-estate'),
                'type'    => Controls_Manager::NUMBER,
                'min'     => 1,
                'max'     => 10,
                'default' => 10,
            ]
        );

        $this->add_control(
            'columns',
            [
                'label'   => __('Columnas', 'homlity-real-estate'),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                ],
                'default' => '3',
            ]
        );

        $this->end_controls_section();

        $this->registerCardContentControls();
        $this->registerCardStyleControls();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $config = ListingConfig::fromBuilderSettings($settings);

        TemplateService::includeComponent('property-related.php', [
            'post_id'       => $this->current_property_id(),
            'posts_per_page' => min(10, max(1, (int) ($settings['posts_per_page'] ?? 10))),
            'columns'       => (int) ($settings['columns'] ?? 3),
            'card_options'  => $config->cardOptions(),
        ]);
    }
}
