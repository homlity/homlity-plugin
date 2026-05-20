<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyBreadcrumbWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_breadcrumb';
    }

    public function get_title(): string
    {
        return __('Breadcrumb inmueble', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-breadcrumbs';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();
        $this->add_control('show_home', [
            'label' => __('Mostrar Inicio', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->add_control('show_property_title', [
            'label' => __('Mostrar título del inmueble', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style', [
            'label' => __('Estilos', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'typography',
            'selector' => '{{WRAPPER}} .property-breadcrumb-widget',
        ]);
        $this->add_responsive_control('align', [
            'label' => __('Alineación', 'homlity-real-estate'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'left' => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'), 'icon' => 'eicon-text-align-center'],
                'right' => ['title' => __('Derecha', 'homlity-real-estate'), 'icon' => 'eicon-text-align-right'],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-breadcrumb-widget' => 'justify-content: {{VALUE}};',
            ],
        ]);
        $this->add_control('background_color', [
            'label' => __('Fondo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-breadcrumb-widget' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'border',
            'selector' => '{{WRAPPER}} .property-breadcrumb-widget',
        ]);
        $this->add_responsive_control('border_radius', [
            'label' => __('Radio borde', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .property-breadcrumb-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('padding', [
            'label' => __('Padding', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-breadcrumb-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('item_gap', [
            'label' => __('Espaciado entre migas', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 48]],
            'selectors' => [
                '{{WRAPPER}} .property-breadcrumb-widget' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'box_shadow',
            'selector' => '{{WRAPPER}} .property-breadcrumb-widget',
        ]);
        $this->add_control('text_color', [
            'label' => __('Color texto', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-breadcrumb-widget' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('current_color', [
            'label' => __('Color miga actual', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-breadcrumb-widget [aria-current="page"]' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('link_color', [
            'label' => __('Color enlaces', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-breadcrumb-widget a' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('link_hover_color', [
            'label' => __('Color enlaces (hover)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-breadcrumb-widget a:hover' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('link_decoration', [
            'label' => __('Decoración enlace', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'none',
            'options' => [
                'none' => __('Ninguna', 'homlity-real-estate'),
                'underline' => __('Subrayado', 'homlity-real-estate'),
            ],
            'selectors' => [
                '{{WRAPPER}} .property-breadcrumb-widget a' => 'text-decoration: {{VALUE}};',
            ],
        ]);
        $this->add_control('link_hover_decoration', [
            'label' => __('Decoración enlace hover', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'underline',
            'options' => [
                'none' => __('Ninguna', 'homlity-real-estate'),
                'underline' => __('Subrayado', 'homlity-real-estate'),
            ],
            'selectors' => [
                '{{WRAPPER}} .property-breadcrumb-widget a:hover' => 'text-decoration: {{VALUE}};',
            ],
        ]);
        $this->add_control('separator_color', [
            'label' => __('Color separador', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-breadcrumb-widget__sep' => 'color: {{VALUE}};',
            ],
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        TemplateService::includeComponent('property-breadcrumb.php', [
            'post_id' => $this->current_property_id(),
            'show_home' => (($settings['show_home'] ?? 'yes') === 'yes'),
            'show_property_title' => (($settings['show_property_title'] ?? 'yes') === 'yes'),
        ]);
    }
}
