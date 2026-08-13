<?php

namespace Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets;

use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Controls_Manager;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Group_Control_Border;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Group_Control_Box_Shadow;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Group_Control_Typography;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyTechnicalSheetButtonWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_technical_sheet_button';
    }

    public function get_title(): string
    {
        return __('Botón ficha técnica', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-document-file';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();
        $this->add_control('button_text', [
            'label' => __('Texto botón', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Ver ficha técnica', 'homlity-real-estate'),
        ]);
        $this->add_control('open_in_new_tab', [
            'label' => __('Abrir en nueva pestaña', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style', [
            'label' => __('Estilos', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'button_typography',
            'selector' => '{{WRAPPER}} .property-tech-sheet-btn',
        ]);
        $this->add_control('button_color', [
            'label' => __('Color texto', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-tech-sheet-btn' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('button_bg', [
            'label' => __('Color fondo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-tech-sheet-btn' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'button_border',
            'selector' => '{{WRAPPER}} .property-tech-sheet-btn',
        ]);
        $this->add_responsive_control('button_radius', [
            'label' => __('Radio', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 50]],
            'selectors' => ['{{WRAPPER}} .property-tech-sheet-btn' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('button_padding', [
            'label' => __('Padding', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => ['{{WRAPPER}} .property-tech-sheet-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'button_shadow',
            'selector' => '{{WRAPPER}} .property-tech-sheet-btn',
        ]);
        $this->add_control('button_hover_color', [
            'label' => __('Color texto hover', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-tech-sheet-btn:hover' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('button_hover_bg', [
            'label' => __('Color fondo hover', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-tech-sheet-btn:hover' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        TemplateService::includeComponent('property-technical-sheet-button.php', [
            'post_id' => $this->current_property_id(),
            'settings' => $settings,
        ]);
    }
}
