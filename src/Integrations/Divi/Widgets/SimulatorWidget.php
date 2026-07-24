<?php

namespace Homlity\PluginInmobiliario\Integrations\Divi\Widgets;

use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Controls_Manager;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Background;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Border;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Box_Shadow;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Typography;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Widget_Base;
use Homlity\PluginInmobiliario\Services\SimulatorService;

if (!defined('ABSPATH')) {
    exit;
}

class SimulatorWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'codwelt_simulator';
    }

    public function get_title(): string
    {
        return __('Simulador', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-counter';
    }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }

    public function get_keywords(): array
    {
        return ['simulador', 'venta', 'arriendo', 'inmobiliaria'];
    }

    public function get_script_depends(): array
    {
        SimulatorService::registerAssets();
        return [SimulatorService::SCRIPT_HANDLE];
    }

    public function get_style_depends(): array
    {
        SimulatorService::registerAssets();
        return [SimulatorService::STYLE_HANDLE];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('Simulador', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('modo', [
            'label' => __('Tipo de simulador', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'venta',
            'options' => [
                'venta' => __('Simulador de venta', 'homlity-real-estate'),
                'arriendo' => __('Simulador de arriendo', 'homlity-real-estate'),
            ],
        ]);

        $this->add_control('show_title', [
            'label' => __('Mostrar título', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __('Sí', 'homlity-real-estate'),
            'label_off' => __('No', 'homlity-real-estate'),
            'return_value' => 'yes',
            'default' => 'no',
        ]);

        $this->add_control('title_text', [
            'label' => __('Texto del título', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Simulador', 'homlity-real-estate'),
            'condition' => ['show_title' => 'yes'],
        ]);

        $this->add_control('title_tag', [
            'label' => __('Etiqueta HTML', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'h2',
            'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'P'],
            'condition' => ['show_title' => 'yes'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style_container', [
            'label' => __('Contenedor', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'container_background',
            'types' => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .homlity-simulator-widget',
        ]);

        $this->add_responsive_control('container_padding', [
            'label' => __('Relleno', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors' => [
                '{{WRAPPER}} .homlity-simulator-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('container_border_radius', [
            'label' => __('Radio de borde', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .homlity-simulator-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'container_border',
            'selector' => '{{WRAPPER}} .homlity-simulator-widget',
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'container_box_shadow',
            'selector' => '{{WRAPPER}} .homlity-simulator-widget',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style_title', [
            'label' => __('Título', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_title' => 'yes'],
        ]);

        $this->add_control('title_color', [
            'label' => __('Color', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .homlity-simulator-widget__title' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typography',
            'selector' => '{{WRAPPER}} .homlity-simulator-widget__title',
        ]);

        $this->add_responsive_control('title_align', [
            'label' => __('Alineación', 'homlity-real-estate'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'left' => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'), 'icon' => 'eicon-text-align-center'],
                'right' => ['title' => __('Derecha', 'homlity-real-estate'), 'icon' => 'eicon-text-align-right'],
            ],
            'selectors' => [
                '{{WRAPPER}} .homlity-simulator-widget__title' => 'text-align: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('title_margin', [
            'label' => __('Margen', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors' => [
                '{{WRAPPER}} .homlity-simulator-widget__title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $mode = isset($settings['modo']) ? (string) $settings['modo'] : 'venta';
        $tag = isset($settings['title_tag']) ? (string) $settings['title_tag'] : 'h2';
        ?>
        <div class="homlity-simulator-widget">
            <?php if (($settings['show_title'] ?? '') === 'yes' && !empty($settings['title_text'])) : ?>
                <<?php echo esc_attr($tag); ?> class="homlity-simulator-widget__title">
                    <?php echo esc_html((string) $settings['title_text']); ?>
                </<?php echo esc_attr($tag); ?>>
            <?php endif; ?>
            <?php echo SimulatorService::renderSimulator($mode); ?>
        </div>
        <?php
    }
}
