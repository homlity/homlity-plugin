<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyTitleWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_title';
    }

    public function get_title(): string
    {
        return __('Nombre del inmueble', 'homlity-plugin');
    }

    public function get_icon(): string
    {
        return 'eicon-post-title';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-plugin')]);
        $this->register_property_control();
        $this->add_control('title_tag', [
            'label' => __('Etiqueta HTML', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'default' => 'h1',
            'options' => [
                'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6',
                'p' => 'P', 'span' => 'SPAN', 'div' => 'DIV',
            ],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_title', [
            'label' => __('Estilos', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_responsive_control('title_align', [
            'label' => __('Alineación', 'homlity-plugin'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'left' => ['title' => __('Izquierda', 'homlity-plugin'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-plugin'), 'icon' => 'eicon-text-align-center'],
                'right' => ['title' => __('Derecha', 'homlity-plugin'), 'icon' => 'eicon-text-align-right'],
                'justify' => ['title' => __('Justificado', 'homlity-plugin'), 'icon' => 'eicon-text-align-justify'],
            ],
            'selectors' => ['{{WRAPPER}} .property-title-widget' => 'text-align: {{VALUE}};'],
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typography',
            'selector' => '{{WRAPPER}} .property-title-widget',
        ]);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name' => 'title_text_shadow',
            'selector' => '{{WRAPPER}} .property-title-widget',
        ]);
        $this->add_control('title_stroke_width', [
            'label' => __('Trazo ancho (px)', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 6]],
            'selectors' => ['{{WRAPPER}} .property-title-widget' => '-webkit-text-stroke-width: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_control('title_stroke_color', [
            'label' => __('Trazo color', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-title-widget' => '-webkit-text-stroke-color: {{VALUE}};'],
        ]);
        $this->start_controls_tabs('title_states');
        $this->start_controls_tab('title_normal', ['label' => __('Normal', 'homlity-plugin')]);
        $this->add_control('title_color', [
            'label' => __('Color texto', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-title-widget' => 'color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();
        $this->start_controls_tab('title_hover', ['label' => __('Hover', 'homlity-plugin')]);
        $this->add_control('title_color_hover', [
            'label' => __('Color texto (hover)', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-title-widget:hover' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('title_stroke_color_hover', [
            'label' => __('Trazo color (hover)', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-title-widget:hover' => '-webkit-text-stroke-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        TemplateService::includeComponent('property-title.php', [
            'post_id' => $this->current_property_id(),
            'title_tag' => $settings['title_tag'] ?? 'h1',
        ]);
    }
}
