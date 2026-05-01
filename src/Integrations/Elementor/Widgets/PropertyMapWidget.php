<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyMapWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_map';
    }

    public function get_title(): string
    {
        return __('Mapa y Street View', 'homlity-plugin');
    }

    public function get_icon(): string
    {
        return 'eicon-google-maps';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-plugin')]);
        $this->register_property_control();
        $this->end_controls_section();

        $this->start_controls_section('style_map', [
            'label' => __('Estilos', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'map_title_typography',
            'selector' => '{{WRAPPER}} .property-map h2',
        ]);
        $this->add_control('map_title_color', [
            'label' => __('Color título', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map h2' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('map_bg', [
            'label' => __('Color fondo', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'map_border',
            'selector' => '{{WRAPPER}} .property-map',
        ]);
        $this->add_responsive_control('map_padding', [
            'label' => __('Padding', 'homlity-plugin'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => ['{{WRAPPER}} .property-map' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('map_margin', [
            'label' => __('Margen', 'homlity-plugin'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => ['{{WRAPPER}} .property-map' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'map_shadow',
            'selector' => '{{WRAPPER}} .property-map',
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'map_frame_border',
            'selector' => '{{WRAPPER}} .property-map__frame',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'map_actions_typography',
            'selector' => '{{WRAPPER}} .property-map__actions a',
        ]);
        $this->add_control('map_actions_color', [
            'label' => __('Color enlaces acciones', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__actions a' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('map_actions_bg', [
            'label' => __('Fondo acciones', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__actions a' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('map_actions_hover_color', [
            'label' => __('Color enlaces acciones (hover)', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__actions a:hover' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('map_actions_hover_bg', [
            'label' => __('Fondo acciones (hover)', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__actions a:hover' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        TemplateService::includeComponent('property-map.php', [
            'post_id' => $this->current_property_id(),
        ]);
    }
}
