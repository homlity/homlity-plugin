<?php

namespace Homlity\PluginInmobiliario\Integrations\Divi\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Controls_Manager;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Text_Shadow;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyOperationPriceWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_operation_price';
    }

    public function get_title(): string
    {
        return __('Gestión y valor', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-price-table';
    }

    private function itemsConfig(): array
    {
        return [
            'operation'   => ['label' => __('Gestión',        'homlity-real-estate'), 'icon' => 'eicon-tags',       'icon_library' => 'eicons'],
            'price_sale'  => ['label' => __('Venta',          'homlity-real-estate'), 'icon' => 'fas fa-tag',       'icon_library' => 'fa-solid'],
            'price_rent'  => ['label' => __('Arriendo',       'homlity-real-estate'), 'icon' => 'fas fa-house',     'icon_library' => 'fa-solid'],
            'price_admin' => ['label' => __('Administración', 'homlity-real-estate'), 'icon' => 'eicon-wrench',     'icon_library' => 'eicons'],
        ];
    }

    protected function register_controls(): void
    {
        // ── Contenido ────────────────────────────────────────────────────────
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();

        $this->add_control('hide_zero_values', [
            'label'     => __('Ocultar valores en 0', 'homlity-real-estate'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'no',
            'separator' => 'before',
        ]);

        foreach ($this->itemsConfig() as $key => $item) {
            $this->add_control('show_' . $key, [
                'label'     => $item['label'],
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'separator' => 'before',
            ]);
            $this->add_control('icon_' . $key, [
                'type'      => Controls_Manager::ICONS,
                'default'   => ['value' => $item['icon'], 'library' => $item['icon_library']],
                'condition' => ['show_' . $key => 'yes'],
            ]);
        }

        $this->end_controls_section();

        // ── Estilos ──────────────────────────────────────────────────────────
        $this->start_controls_section('style_operation_price', [
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
            'options'        => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
            'selectors'      => [
                '{{WRAPPER}} .property-operation-price-widget' => 'display: grid; grid-template-columns: repeat({{VALUE}}, 1fr);',
            ],
        ]);
        $this->add_responsive_control('list_gap', [
            'label'      => __('Espacio entre ítems', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['unit' => 'px', 'size' => 10],
            'selectors'  => [
                '{{WRAPPER}} .property-operation-price-widget' => 'gap: {{SIZE}}{{UNIT}};',
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
                '{{WRAPPER}} .property-operation-price__item' => 'display: flex; flex-direction: {{VALUE}};',
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
                '{{WRAPPER}} .property-operation-price__item' => 'align-items: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('icon_text_gap', [
            'label'      => __('Espacio ícono–texto', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'default'    => ['unit' => 'px', 'size' => 8],
            'selectors'  => [
                '{{WRAPPER}} .property-operation-price__item' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('item_padding', [
            'label'      => __('Padding', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .property-operation-price__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_control('item_bg', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-operation-price__item' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('item_radius', [
            'label'      => __('Radio borde', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .property-operation-price__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_control('item_bg_hover', [
            'label'     => __('Fondo (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-operation-price__item:hover' => 'background-color: {{VALUE}};',
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
            'selectors' => ['{{WRAPPER}} .property-operation-price__icon' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('icon_color_hover', [
            'label'     => __('Color (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-operation-price__item:hover .property-operation-price__icon' => 'color: {{VALUE}};'],
        ]);
        $this->add_responsive_control('icon_size', [
            'label'      => __('Tamaño', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 8, 'max' => 64]],
            'selectors'  => [
                '{{WRAPPER}} .property-operation-price__icon'     => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .property-operation-price__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        // — Texto (etiqueta) —
        $this->add_control('label_heading', [
            'label'     => __('Texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'label_typography',
            'selector' => '{{WRAPPER}} .property-operation-price__label',
        ]);
        $this->add_control('label_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-operation-price__label' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('label_color_hover', [
            'label'     => __('Color (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-operation-price__item:hover .property-operation-price__label' => 'color: {{VALUE}};'],
        ]);

        // — Valor —
        $this->add_control('value_heading', [
            'label'     => __('Valor', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'value_typography',
            'selector' => '{{WRAPPER}} .property-operation-price__value',
        ]);
        $this->add_control('value_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-operation-price__value' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('value_color_hover', [
            'label'     => __('Color (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-operation-price__item:hover .property-operation-price__value' => 'color: {{VALUE}};'],
        ]);

        // — Efectos —
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name'      => 'op_shadow',
            'separator' => 'before',
            'selector'  => '{{WRAPPER}} .property-operation-price-widget',
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        TemplateService::includeComponent('property-operation-price.php', [
            'post_id'  => $this->current_property_id(),
            'settings' => $settings,
        ]);
    }
}
