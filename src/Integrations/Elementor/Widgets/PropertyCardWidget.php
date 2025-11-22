<?php

namespace Codwelt\PluginInmobiliario\Integrations\Elementor\Widgets;

use Codwelt\PluginInmobiliario\Services\TemplateService;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyCardWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_card';
    }

    public function get_title(): string
    {
        return __('Tarjeta de inmueble', 'inmopress-listings-inmobiliaria');
    }

    public function get_icon(): string
    {
        return 'eicon-post';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'inmopress-listings-inmobiliaria')]);
        $this->register_property_control();
        $this->add_control(
            'show_excerpt',
            [
                'label' => __('Mostrar extracto', 'inmopress-listings-inmobiliaria'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
        $this->end_controls_section();
    }

    protected function render(): void
    {
        TemplateService::includeComponent('property-card.php', [
            'post_id' => $this->current_property_id(),
        ]);
    }
}
