<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;

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
        return __('Mapa y Street View', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-google-maps';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();
        $this->add_control('map_tab_icon_map', [
            'label' => __('Icono tab Mapa', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => [
                'value' => 'fas fa-map-marked-alt',
                'library' => 'fa-solid',
            ],
        ]);
        $this->add_control('map_tab_icon_street', [
            'label' => __('Icono tab Street View', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => [
                'value' => 'fas fa-street-view',
                'library' => 'fa-solid',
            ],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_map', [
            'label' => __('Estilos', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'map_title_typography',
            'selector' => '{{WRAPPER}} .property-map h2',
        ]);
        $this->add_control('map_title_color', [
            'label' => __('Color título', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map h2' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('map_bg', [
            'label' => __('Color fondo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'map_border',
            'selector' => '{{WRAPPER}} .property-map',
        ]);
        $this->add_responsive_control('map_padding', [
            'label' => __('Padding', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => ['{{WRAPPER}} .property-map' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('map_margin', [
            'label' => __('Margen', 'homlity-real-estate'),
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
        $this->add_responsive_control('map_panel_height', [
            'label' => __('Altura panel Mapa', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range' => [
                'px' => ['min' => 200, 'max' => 1000],
                'vh' => ['min' => 20, 'max' => 100],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-map__panel[data-map-panel="map"] iframe' => 'height: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .property-map__panel[data-map-panel="map"] .property-map__leaflet' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('street_panel_height', [
            'label' => __('Altura panel Street View', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range' => [
                'px' => ['min' => 200, 'max' => 1000],
                'vh' => ['min' => 20, 'max' => 100],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-map__panel[data-map-panel="street"] iframe' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'map_actions_typography',
            'selector' => '{{WRAPPER}} .property-map__actions a',
        ]);
        $this->add_control('map_actions_color', [
            'label' => __('Color enlaces acciones', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__actions a' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('map_actions_bg', [
            'label' => __('Fondo acciones', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__actions a' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('map_actions_hover_color', [
            'label' => __('Color enlaces acciones (hover)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__actions a:hover' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('map_actions_hover_bg', [
            'label' => __('Fondo acciones (hover)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__actions a:hover' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_tabs', [
            'label' => __('Estilos tabs mapa', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_control('map_tabs_wrap_heading', [
            'label' => __('Contenedor tabs', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
        ]);
        $this->add_control('map_tabs_wrap_bg_color', [
            'label' => __('Fondo contenedor', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__tabs' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'map_tabs_wrap_border',
            'selector' => '{{WRAPPER}} .property-map__tabs',
        ]);
        $this->add_responsive_control('map_tabs_wrap_radius', [
            'label' => __('Radio contenedor', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .property-map__tabs' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('map_tabs_wrap_padding', [
            'label' => __('Padding contenedor', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-map__tabs' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'map_tabs_wrap_shadow',
            'selector' => '{{WRAPPER}} .property-map__tabs',
        ]);
        $this->add_responsive_control('map_tabs_justify', [
            'label' => __('Alineación tabs', 'homlity-real-estate'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'flex-start' => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-h-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'), 'icon' => 'eicon-h-align-center'],
                'flex-end' => ['title' => __('Derecha', 'homlity-real-estate'), 'icon' => 'eicon-h-align-right'],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-map__tabs' => 'justify-content: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('map_tab_flex_grow', [
            'label' => __('Tabs ancho completo', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'selectors' => [
                '{{WRAPPER}} .property-map__tab' => 'flex: {{VALUE}};',
            ],
            'selectors_dictionary' => [
                '' => '0 0 auto',
                'yes' => '1 1 0',
            ],
        ]);
        $this->add_control('map_tabs_item_heading', [
            'label' => __('Items tab', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'map_tabs_typography',
            'selector' => '{{WRAPPER}} .property-map__tab',
        ]);
        $this->add_control('map_tabs_text_color', [
            'label' => __('Color texto', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__tab' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('map_tabs_bg_color', [
            'label' => __('Fondo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__tab' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_control('map_tab_icon_heading', [
            'label' => __('Icono', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_control('map_tab_icon_color', [
            'label' => __('Color icono', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-map__tab .property-map__tab-icon i'    => 'color: {{VALUE}};',
                '{{WRAPPER}} .property-map__tab .property-map__tab-icon svg'   => 'fill: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('map_tab_icon_size', [
            'label' => __('Tamaño icono', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range' => ['px' => ['min' => 8, 'max' => 60]],
            'selectors' => [
                '{{WRAPPER}} .property-map__tab .property-map__tab-icon i'   => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .property-map__tab .property-map__tab-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('map_tab_icon_padding', [
            'label' => __('Padding icono', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-map__tab .property-map__tab-icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_control('map_tab_icon_bg', [
            'label' => __('Fondo icono', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-map__tab .property-map__tab-icon' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('map_tab_icon_radius', [
            'label' => __('Radio icono', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .property-map__tab .property-map__tab-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'map_tabs_border',
            'selector' => '{{WRAPPER}} .property-map__tab',
        ]);
        $this->add_responsive_control('map_tabs_radius', [
            'label' => __('Radio', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .property-map__tab' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('map_tabs_padding', [
            'label' => __('Padding', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-map__tab' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('map_tabs_gap', [
            'label' => __('Separación entre tabs', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'selectors' => ['{{WRAPPER}} .property-map__tabs' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'map_tabs_shadow',
            'selector' => '{{WRAPPER}} .property-map__tab',
        ]);

        $this->add_control('map_tabs_hover_heading', [
            'label' => __('Hover', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_control('map_tabs_hover_text_color', [
            'label' => __('Color texto (hover)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__tab:hover, {{WRAPPER}} .property-map__tab:focus-visible' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('map_tabs_hover_bg_color', [
            'label' => __('Fondo (hover)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__tab:hover, {{WRAPPER}} .property-map__tab:focus-visible' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_control('map_tab_icon_color_hover', [
            'label' => __('Color icono (hover)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-map__tab:hover .property-map__tab-icon i, {{WRAPPER}} .property-map__tab:focus-visible .property-map__tab-icon i'   => 'color: {{VALUE}};',
                '{{WRAPPER}} .property-map__tab:hover .property-map__tab-icon svg, {{WRAPPER}} .property-map__tab:focus-visible .property-map__tab-icon svg' => 'fill: {{VALUE}};',
            ],
        ]);
        $this->add_control('map_tabs_hover_border_color', [
            'label' => __('Color borde (hover)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__tab:hover, {{WRAPPER}} .property-map__tab:focus-visible' => 'border-color: {{VALUE}};'],
        ]);

        $this->add_control('map_tabs_active_heading', [
            'label' => __('Activo', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_control('map_tabs_active_text_color', [
            'label' => __('Color texto (activo)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__tab.is-active' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('map_tabs_active_bg_color', [
            'label' => __('Fondo (activo)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__tab.is-active' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_control('map_tab_icon_color_active', [
            'label' => __('Color icono (activo)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-map__tab.is-active .property-map__tab-icon i'   => 'color: {{VALUE}};',
                '{{WRAPPER}} .property-map__tab.is-active .property-map__tab-icon svg' => 'fill: {{VALUE}};',
            ],
        ]);
        $this->add_control('map_tabs_active_border_color', [
            'label' => __('Color borde (activo)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-map__tab.is-active' => 'border-color: {{VALUE}};'],
        ]);

        $this->add_control('map_tabs_position_heading', [
            'label' => __('Posición tabs', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_responsive_control('map_tabs_offset_top', [
            'label' => __('Distancia superior', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => [
                'px' => ['min' => 0, 'max' => 200],
                '%' => ['min' => 0, 'max' => 50],
            ],
            'selectors' => ['{{WRAPPER}} .property-map__tabs' => 'top: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('map_tabs_offset_left', [
            'label' => __('Distancia izquierda', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => [
                'px' => ['min' => 0, 'max' => 200],
                '%' => ['min' => 0, 'max' => 50],
            ],
            'selectors' => ['{{WRAPPER}} .property-map__tabs' => 'left: {{SIZE}}{{UNIT}};'],
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        if (class_exists('\Elementor\Icons_Manager')) {
            Icons_Manager::enqueue_shim();
        }

        wp_enqueue_script(
            'homlity-real-estate-map-tabs',
            HOMLITY_PLUGIN_URL . 'assets/js/property-map-tabs.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        $settings = $this->get_settings_for_display();
        TemplateService::includeComponent('property-map.php', [
            'post_id' => $this->current_property_id(),
            'map_tab_icon_map' => $settings['map_tab_icon_map'] ?? [],
            'map_tab_icon_street' => $settings['map_tab_icon_street'] ?? [],
        ]);
    }
}
