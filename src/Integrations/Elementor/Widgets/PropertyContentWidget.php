<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyContentWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_content';
    }

    public function get_title(): string
    {
        return __('Descripción completa', 'homlity-plugin');
    }

    public function get_icon(): string
    {
        return 'eicon-text';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-plugin')]);
        $this->register_property_control();
        $this->add_control('content_tag', [
            'label' => __('Etiqueta HTML contenedor', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'default' => 'div',
            'options' => [
                'div' => 'DIV', 'section' => 'SECTION', 'article' => 'ARTICLE', 'p' => 'P',
            ],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_content', [
            'label' => __('Estilos', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_responsive_control('content_align', [
            'label' => __('Alineación', 'homlity-plugin'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'left' => ['title' => __('Izquierda', 'homlity-plugin'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-plugin'), 'icon' => 'eicon-text-align-center'],
                'right' => ['title' => __('Derecha', 'homlity-plugin'), 'icon' => 'eicon-text-align-right'],
                'justify' => ['title' => __('Justificado', 'homlity-plugin'), 'icon' => 'eicon-text-align-justify'],
            ],
            'selectors' => ['{{WRAPPER}} .property-content-widget' => 'text-align: {{VALUE}};'],
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'content_typography',
            'selector' => '{{WRAPPER}} .property-content-widget',
        ]);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name' => 'content_text_shadow',
            'selector' => '{{WRAPPER}} .property-content-widget',
        ]);
        $this->add_control('content_stroke_width', [
            'label' => __('Trazo ancho (px)', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 4]],
            'selectors' => ['{{WRAPPER}} .property-content-widget' => '-webkit-text-stroke-width: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_control('content_stroke_color', [
            'label' => __('Trazo color', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-content-widget' => '-webkit-text-stroke-color: {{VALUE}};'],
        ]);
        $this->start_controls_tabs('content_states');
        $this->start_controls_tab('content_normal', ['label' => __('Normal', 'homlity-plugin')]);
        $this->add_control('content_color', [
            'label' => __('Color texto', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-content-widget' => 'color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();
        $this->start_controls_tab('content_hover', ['label' => __('Hover', 'homlity-plugin')]);
        $this->add_control('content_color_hover', [
            'label' => __('Color texto (hover)', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-content-widget:hover' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('content_stroke_color_hover', [
            'label' => __('Trazo color (hover)', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-content-widget:hover' => '-webkit-text-stroke-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        TemplateService::includeComponent('property-content.php', [
            'post_id' => $this->current_property_id(),
            'content_tag' => $settings['content_tag'] ?? 'div',
        ]);
    }
}
