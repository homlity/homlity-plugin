<?php
/**
 * Elementor widget: related properties for the current property.
 */

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyRelatedWidget extends BasePropertyWidget
{
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
                'max'     => 24,
                'default' => 3,
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
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        TemplateService::includeComponent('property-related.php', [
            'post_id'       => $this->current_property_id(),
            'posts_per_page' => (int) ($settings['posts_per_page'] ?? 3),
            'columns'       => (int) ($settings['columns'] ?? 3),
        ]);
    }
}
