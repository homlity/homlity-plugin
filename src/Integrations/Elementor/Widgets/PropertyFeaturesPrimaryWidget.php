<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyFeaturesPrimaryWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_features_primary';
    }

    public function get_title(): string
    {
        return __('Características principales', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-list';
    }

    private function featuresConfig(): array
    {
        return [
            'area'         => ['label' => __('Área total',      'homlity-real-estate'), 'icon' => 'fas fa-ruler-combined', 'icon_library' => 'fa-solid'],
            'area_lot'     => ['label' => __('Área de lote',    'homlity-real-estate'), 'icon' => 'eicon-square',         'icon_library' => 'eicons'],
            'area_private' => ['label' => __('Área privada',    'homlity-real-estate'), 'icon' => 'eicon-lock',           'icon_library' => 'eicons'],
            'area_built'   => ['label' => __('Área construida', 'homlity-real-estate'), 'icon' => 'eicon-columns',        'icon_library' => 'eicons'],
            'bedrooms'     => ['label' => __('Habitaciones',    'homlity-real-estate'), 'icon' => 'fas fa-bed',           'icon_library' => 'fa-solid'],
            'bathrooms'    => ['label' => __('Baños',           'homlity-real-estate'), 'icon' => 'fas fa-bath',          'icon_library' => 'fa-solid'],
            'parking'      => ['label' => __('Parqueaderos',    'homlity-real-estate'), 'icon' => 'fas fa-car',           'icon_library' => 'fa-solid'],
            'condition'    => ['label' => __('Estado',          'homlity-real-estate'), 'icon' => 'eicon-info-circle-o',  'icon_library' => 'eicons'],
            'age'          => ['label' => __('Edad (años)',     'homlity-real-estate'), 'icon' => 'eicon-calendar',       'icon_library' => 'eicons'],
            'code'         => ['label' => __('Código',          'homlity-real-estate'), 'icon' => 'eicon-barcode',        'icon_library' => 'eicons'],
        ];
    }

    protected function register_controls(): void
    {
        // ── Contenido ────────────────────────────────────────────────────────
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();

        foreach ($this->featuresConfig() as $key => $feature) {
            $this->add_control('show_' . $key, [
                'label'     => $feature['label'],
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'separator' => 'before',
            ]);
            $this->add_control('icon_' . $key, [
                'type'      => Controls_Manager::ICONS,
                'default'   => ['value' => $feature['icon'], 'library' => $feature['icon_library']],
                'condition' => ['show_' . $key => 'yes'],
            ]);
        }

        $this->end_controls_section();

        // ── Estilos ──────────────────────────────────────────────────────────
        $this->start_controls_section('style_primary_features', [
            'label' => __('Estilos', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        // — Layout de lista —
        $this->add_control('layout_heading', [
            'label' => __('Layout de lista', 'homlity-real-estate'),
            'type'  => Controls_Manager::HEADING,
        ]);
        $this->add_responsive_control('list_columns', [
            'label'          => __('Columnas', 'homlity-real-estate'),
            'type'           => Controls_Manager::SELECT,
            'default'        => '1',
            'tablet_default' => '1',
            'mobile_default' => '1',
            'options'        => ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6'],
            'selectors'      => [
                '{{WRAPPER}} .property-features--primary' => 'display: grid; grid-template-columns: repeat({{VALUE}}, 1fr);',
            ],
        ]);
        $this->add_responsive_control('list_gap', [
            'label'      => __('Espacio entre ítems', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['unit' => 'px', 'size' => 10],
            'selectors'  => [
                '{{WRAPPER}} .property-features--primary' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        // — Ítem —
        $this->add_control('item_heading', [
            'label'     => __('Ítem', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_responsive_control('item_direction', [
            'label'   => __('Dirección', 'homlity-real-estate'),
            'type'    => Controls_Manager::CHOOSE,
            'default' => 'row',
            'options' => [
                'row'    => ['title' => __('Horizontal', 'homlity-real-estate'), 'icon' => 'eicon-arrow-right'],
                'column' => ['title' => __('Vertical',   'homlity-real-estate'), 'icon' => 'eicon-arrow-down'],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-features__item' => 'display: flex; flex-direction: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('item_align_items', [
            'label'   => __('Alineación interna', 'homlity-real-estate'),
            'type'    => Controls_Manager::CHOOSE,
            'default' => 'center',
            'options' => [
                'flex-start' => ['title' => __('Inicio', 'homlity-real-estate'), 'icon' => 'eicon-v-align-top'],
                'center'     => ['title' => __('Centro', 'homlity-real-estate'), 'icon' => 'eicon-v-align-middle'],
                'flex-end'   => ['title' => __('Fin',    'homlity-real-estate'), 'icon' => 'eicon-v-align-bottom'],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-features__item' => 'align-items: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('icon_text_gap', [
            'label'      => __('Espacio ícono–texto', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'default'    => ['unit' => 'px', 'size' => 8],
            'selectors'  => [
                '{{WRAPPER}} .property-features__item' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('item_padding', [
            'label'      => __('Padding', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .property-features__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_control('item_bg', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-features__item' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('item_radius', [
            'label'      => __('Radio borde', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .property-features__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_control('item_bg_hover', [
            'label'     => __('Fondo (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-features__item:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        // — Ícono —
        $this->add_control('icon_heading', [
            'label'     => __('Ícono', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_control('icon_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-features__icon'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .property-features__icon svg' => 'fill: {{VALUE}};',
            ],
        ]);
        $this->add_control('icon_color_hover', [
            'label'     => __('Color (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-features__item:hover .property-features__icon'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .property-features__item:hover .property-features__icon svg' => 'fill: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('icon_size', [
            'label'      => __('Tamaño', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 8, 'max' => 64]],
            'selectors'  => [
                '{{WRAPPER}} .property-features__icon'     => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .property-features__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        // — Nombre —
        $this->add_control('name_heading', [
            'label'     => __('Nombre', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'name_typography',
            'selector' => '{{WRAPPER}} .property-features__name',
        ]);
        $this->add_control('name_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-features__name' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('name_color_hover', [
            'label'     => __('Color (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-features__item:hover .property-features__name' => 'color: {{VALUE}};'],
        ]);

        // — Valor —
        $this->add_control('value_heading', [
            'label'     => __('Valor', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'value_typography',
            'selector' => '{{WRAPPER}} .property-features__value',
        ]);
        $this->add_control('value_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-features__value' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('value_color_hover', [
            'label'     => __('Color (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-features__item:hover .property-features__value' => 'color: {{VALUE}};'],
        ]);

        // — Efectos —
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name'      => 'primary_shadow',
            'separator' => 'before',
            'selector'  => '{{WRAPPER}} .property-features--primary',
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        TemplateService::includeComponent('property-features-primary.php', [
            'post_id'  => $this->current_property_id(),
            'settings' => $settings,
        ]);
    }
}
