<?php

namespace Homlity\PluginInmobiliario\Integrations\Divi\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Controls_Manager;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Border;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Box_Shadow;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

class PropertySummaryWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_summary';
    }

    public function get_title(): string
    {
        return __('Resumen del inmueble', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-info-circle-o';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();
        $this->end_controls_section();

        $this->start_controls_section('style_summary', [
            'label' => __('Resumen', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'summary_typography',
            'selector' => '{{WRAPPER}} .property-summary',
        ]);
        $this->add_control('summary_color', [
            'label' => __('Color de texto', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-summary' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('summary_link_color', [
            'label' => __('Color de enlaces', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-summary a' => 'color: {{VALUE}};'],
        ]);
        $this->add_responsive_control('summary_align', [
            'label' => __('Alineación', 'homlity-real-estate'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'left' => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'), 'icon' => 'eicon-text-align-center'],
                'right' => ['title' => __('Derecha', 'homlity-real-estate'), 'icon' => 'eicon-text-align-right'],
                'justify' => ['title' => __('Justificado', 'homlity-real-estate'), 'icon' => 'eicon-text-align-justify'],
            ],
            'selectors' => ['{{WRAPPER}} .property-summary' => 'text-align: {{VALUE}};'],
        ]);
        $this->add_control('summary_background', [
            'label' => __('Color de fondo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-summary' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_responsive_control('summary_padding', [
            'label' => __('Padding', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => ['{{WRAPPER}} .property-summary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('summary_margin', [
            'label' => __('Margen', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => ['{{WRAPPER}} .property-summary' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('summary_radius', [
            'label' => __('Radio de borde', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => ['{{WRAPPER}} .property-summary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'summary_border',
            'selector' => '{{WRAPPER}} .property-summary',
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'summary_shadow',
            'selector' => '{{WRAPPER}} .property-summary',
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        TemplateService::includeComponent('property-summary.php', [
            'post_id' => $this->current_property_id(),
        ]);
    }
}
