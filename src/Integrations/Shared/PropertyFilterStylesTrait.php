<?php

namespace Homlity\PluginInmobiliario\Integrations\Shared;

if (!defined('ABSPATH')) {
    exit;
}

trait PropertyFilterStylesTrait
{
    protected function registerFilterStyleControls(): void
    {
        $this->start_controls_section('style_form', [
            'label' => __('Formulario', 'homlity-real-estate'),
            'tab' => 'style',
        ]);

        $this->add_control('form_bg_color', [
            'label' => __('Fondo del formulario', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('form_border_color', [
            'label' => __('Color de borde', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('form_border_radius', [
            'label' => __('Radio de borde', 'homlity-real-estate'),
            'type' => 'slider',
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 0, 'max' => 40],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('fields_gap', [
            'label' => __('Espacio entre campos', 'homlity-real-estate'),
            'type' => 'slider',
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 0, 'max' => 40],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters-row' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('form_padding', [
            'label' => __('Padding del formulario', 'homlity-real-estate'),
            'type' => 'dimensions',
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('form_margin', [
            'label' => __('Margin del formulario', 'homlity-real-estate'),
            'type' => 'dimensions',
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('form_layout', [
            'label' => __('Dirección del formulario', 'homlity-real-estate'),
            'type' => 'choose',
            'default' => 'horizontal',
            'options' => [
                'horizontal' => [
                    'title' => __('Horizontal', 'homlity-real-estate'),
                    'icon' => 'eicon-ellipsis-h',
                ],
                'vertical' => [
                    'title' => __('Vertical', 'homlity-real-estate'),
                    'icon' => 'eicon-ellipsis-v',
                ],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters-row' => '{{VALUE}}',
            ],
            'selectors_dictionary' => [
                'horizontal' => '--hpf-direction:row;',
                'vertical' => '--hpf-direction:column;--hpf-wrap:nowrap;--hpf-align:stretch;',
            ],
        ]);

        $this->add_control('form_layout_vertical_helper', [
            'label' => '',
            'type' => 'hidden',
            'default' => '1',
            'condition' => [
                'form_layout' => 'vertical',
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters-row' => 'display:flex;flex-direction:column;',
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-group' => 'min-width:100%;flex:1 1 100%;',
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-actions' => 'align-items:stretch;justify-content:flex-start;',
                '{{WRAPPER}} .property-filter-widget .property-listing__btn' => 'width:100%;',
            ],
        ]);

        $this->registerFilterFormExtras();

        $this->end_controls_section();

        $this->start_controls_section('style_labels', [
            'label' => __('Etiquetas', 'homlity-real-estate'),
            'tab' => 'style',
        ]);

        $this->add_group_control('typography', [
            'name' => 'label_typography',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__filter-label',
        ]);

        $this->add_control('label_color', [
            'label' => __('Color de texto', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-label' => 'color: {{VALUE}};',
            ],
        ]);

        $this->registerFilterLabelsExtras();

        $this->end_controls_section();

        $this->start_controls_section('style_fields', [
            'label' => __('Campos', 'homlity-real-estate'),
            'tab' => 'style',
        ]);

        $this->add_group_control('typography', [
            'name' => 'field_typography',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input, {{WRAPPER}} .property-filter-widget .hpf-multi__trigger',
        ]);

        $this->start_controls_tabs('field_states');
        $this->start_controls_tab('field_normal', ['label' => __('Normal', 'homlity-real-estate')]);

        $this->add_control('field_text_color', [
            'label' => __('Color de texto', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input, {{WRAPPER}} .property-filter-widget .hpf-multi__trigger' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('field_bg_color', [
            'label' => __('Color de fondo', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input, {{WRAPPER}} .property-filter-widget .hpf-multi__trigger' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->registerFilterStates('field');
        $this->end_controls_tabs();

        $this->add_group_control('border', [
            'name' => 'field_border',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input, {{WRAPPER}} .property-filter-widget .hpf-multi__trigger',
        ]);

        $this->add_responsive_control('field_border_radius', [
            'label' => __('Radio de borde', 'homlity-real-estate'),
            'type' => 'slider',
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 0, 'max' => 30],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input, {{WRAPPER}} .property-filter-widget .hpf-multi__trigger' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('field_padding', [
            'label' => __('Padding de campos', 'homlity-real-estate'),
            'type' => 'dimensions',
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input, {{WRAPPER}} .property-filter-widget .hpf-multi__trigger' => '--hpf-padding-top: {{TOP}}{{UNIT}}; --hpf-padding-bottom: {{BOTTOM}}{{UNIT}}; padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('field_margin', [
            'label' => __('Margin de campos', 'homlity-real-estate'),
            'type' => 'dimensions',
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-group' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->registerFilterFieldsExtras();

        $this->end_controls_section();

        $this->start_controls_section('style_multi_selector', [
            'label' => __('Selector (campo cerrado)', 'homlity-real-estate'),
            'tab' => 'style',
        ]);

        $this->add_control('multi_field_heading', [
            'label' => __('Campo multiselección', 'homlity-real-estate'),
            'type' => 'heading',
        ]);

        $this->add_control('multi_trigger_text_color', [
            'label' => __('Color texto campo', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__trigger' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_summary_color', [
            'label' => __('Color texto resumen', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__summary' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_trigger_bg_color', [
            'label' => __('Fondo campo', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__trigger' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_trigger_border_color', [
            'label' => __('Borde campo', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__trigger' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_placeholder_color', [
            'label' => __('Color placeholder', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__placeholder' => 'color: {{VALUE}};',
            ],
        ]);

        $this->registerFilterSelectorExtras();

        $this->end_controls_section();

        $this->start_controls_section('style_multi_menu', [
            'label' => __('Lista desplegable', 'homlity-real-estate'),
            'tab' => 'style',
        ]);

        $this->add_control('multi_menu_heading', [
            'label' => __('Lista de opciones', 'homlity-real-estate'),
            'type' => 'heading',
            'separator' => 'before',
        ]);

        $this->add_control('multi_menu_bg_color', [
            'label' => __('Fondo lista', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__menu' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_menu_border_color', [
            'label' => __('Borde lista', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__menu' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_item_text_color', [
            'label' => __('Texto opción', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__item' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_item_bg_color', [
            'label' => __('Fondo opción', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__item' => 'background-color: {{VALUE}}; --hpf-item-bg: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_item_hover_bg_color', [
            'label' => __('Fondo opción hover', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__item:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_item_selected_bg_color', [
            'label' => __('Fondo opción seleccionada', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__item.is-selected' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->registerFilterMenuExtras();

        $this->end_controls_section();

        $this->start_controls_section('style_multiselect_chips', [
            'label' => __('Chips', 'homlity-real-estate'),
            'tab' => 'style',
        ]);

        $this->add_group_control('typography', [
            'name' => 'multi_chip_typography',
            'selector' => '{{WRAPPER}} .property-filter-widget .hpf-multi__chip',
        ]);

        $this->add_control('multi_chip_text_color', [
            'label' => __('Color texto chip', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_chip_bg_color', [
            'label' => __('Fondo chip', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control('border', [
            'name' => 'multi_chip_border',
            'selector' => '{{WRAPPER}} .property-filter-widget .hpf-multi__chip',
        ]);

        $this->add_responsive_control('multi_chip_border_radius', [
            'label' => __('Radio chip', 'homlity-real-estate'),
            'type' => 'slider',
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 0, 'max' => 30],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('multi_chip_padding', [
            'label' => __('Padding chip', 'homlity-real-estate'),
            'type' => 'dimensions',
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('multi_chip_heading_hover', [
            'label' => __('Hover chip', 'homlity-real-estate'),
            'type' => 'heading',
            'separator' => 'before',
        ]);

        $this->add_control('multi_chip_hover_text_color', [
            'label' => __('Texto chip hover', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_chip_hover_bg_color', [
            'label' => __('Fondo chip hover', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_chip_remove_heading', [
            'label' => __('Botón quitar (×)', 'homlity-real-estate'),
            'type' => 'heading',
            'separator' => 'before',
        ]);

        $this->add_responsive_control('multi_chip_remove_size', [
            'label' => __('Tamaño icono ×', 'homlity-real-estate'),
            'type' => 'slider',
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 8, 'max' => 30],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip-remove' => 'font-size: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('multi_chip_remove_color', [
            'label' => __('Color icono ×', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip-remove' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_chip_remove_hover_color', [
            'label' => __('Color icono × hover', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip-remove:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_chip_remove_bg_color', [
            'label' => __('Fondo botón ×', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip-remove' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_chip_remove_hover_bg_color', [
            'label' => __('Fondo botón × hover', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip-remove:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_buttons', [
            'label' => __('Botones', 'homlity-real-estate'),
            'tab' => 'style',
        ]);

        $this->add_group_control('typography', [
            'name' => 'button_typography',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__btn',
        ]);

        $this->start_controls_tabs('button_states');
        $this->start_controls_tab('button_normal', ['label' => __('Normal', 'homlity-real-estate')]);

        $this->add_control('button_text_color', [
            'label' => __('Texto botón buscar', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_bg_color', [
            'label' => __('Fondo botón buscar', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_reset_text_color', [
            'label' => __('Texto botón limpiar', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--ghost' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_reset_bg_color', [
            'label' => __('Fondo botón limpiar', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--ghost' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->registerFilterStates('button');
        $this->end_controls_tabs();

        $this->add_group_control('border', [
            'name' => 'button_border',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__btn',
        ]);

        $this->add_responsive_control('button_border_radius', [
            'label' => __('Radio de borde', 'homlity-real-estate'),
            'type' => 'slider',
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 0, 'max' => 30],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_padding', [
            'label' => __('Padding de botones', 'homlity-real-estate'),
            'type' => 'dimensions',
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_margin', [
            'label' => __('Margin de botones', 'homlity-real-estate'),
            'type' => 'dimensions',
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('button_primary_heading', [
            'label' => __('Botón Buscar', 'homlity-real-estate'),
            'type' => 'heading',
            'separator' => 'before',
        ]);

        $this->add_responsive_control('button_primary_width', [
            'label' => __('Ancho botón buscar', 'homlity-real-estate'),
            'type' => 'slider',
            'size_units' => ['px', '%'],
            'range' => [
                'px' => ['min' => 40, 'max' => 480],
                '%' => ['min' => 10, 'max' => 100],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary' => 'width: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control('border', [
            'name' => 'button_primary_border',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary',
        ]);

        $this->add_responsive_control('button_primary_padding', [
            'label' => __('Padding botón buscar', 'homlity-real-estate'),
            'type' => 'dimensions',
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_primary_margin', [
            'label' => __('Margin botón buscar', 'homlity-real-estate'),
            'type' => 'dimensions',
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('button_mobile_heading', [
            'label' => __('Botón Filtrar (Móvil)', 'homlity-real-estate'),
            'type' => 'heading',
            'separator' => 'before',
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_group_control('typography', [
            'name' => 'mobile_filter_button_typography',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle',
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_control('mobile_filter_button_text_color', [
            'label' => __('Color texto (móvil)', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle' => 'color: {{VALUE}};',
            ],
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_control('mobile_filter_button_bg_color', [
            'label' => __('Fondo (móvil)', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle' => 'background-color: {{VALUE}};',
            ],
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_group_control('border', [
            'name' => 'mobile_filter_button_border',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle',
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_responsive_control('mobile_filter_button_width', [
            'label' => __('Ancho botón filtrar', 'homlity-real-estate'),
            'type' => 'slider',
            'size_units' => ['px', '%'],
            'range' => [
                'px' => ['min' => 40, 'max' => 480],
                '%' => ['min' => 10, 'max' => 100],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle' => 'width: {{SIZE}}{{UNIT}};',
            ],
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_responsive_control('mobile_filter_button_radius', [
            'label' => __('Radio borde (móvil)', 'homlity-real-estate'),
            'type' => 'slider',
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 0, 'max' => 40],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_responsive_control('mobile_filter_button_padding', [
            'label' => __('Padding botón filtrar', 'homlity-real-estate'),
            'type' => 'dimensions',
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_responsive_control('mobile_filter_button_margin', [
            'label' => __('Margin botón filtrar', 'homlity-real-estate'),
            'type' => 'dimensions',
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->registerFilterButtonsExtras();

        $this->end_controls_section();
        $this->registerFilterMobileStyles();
    }

    private function filterSelector(string $suffix = ''): string
    {
        return '{{WRAPPER}} .property-filter-widget' . ($suffix !== '' ? ' ' . $suffix : '');
    }

    private function filterFields(string $state = ''): string
    {
        $selectors = [];
        foreach (['.property-listing__filter-input', '.property-listing__filter-select', '.hpf-multi__trigger'] as $selector) {
            $selectors[] = $this->filterSelector($selector . $state);
        }
        if ($state === ':focus') {
            $selectors[] = $this->filterSelector('.hpf-multi:focus-within .hpf-multi__trigger');
            $selectors[] = $this->filterSelector('.hpf-multi.is-open .hpf-multi__trigger');
        }
        return implode(', ', $selectors);
    }

    private function filterColor(string $id, string $label, string $selector, string $property, array $condition = []): void
    {
        $this->add_control($id, [
            'label' => $label,
            'type' => 'color',
            'selectors' => [$selector => $property . ': {{VALUE}};'],
            'condition' => $condition,
        ]);
    }

    private function filterSize(string $id, string $label, string $selector, string $property, int $max = 100, array $condition = []): void
    {
        $this->add_responsive_control($id, [
            'label' => $label,
            'type' => 'slider',
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => $max]],
            'selectors' => [$selector => $property . ': {{SIZE}}{{UNIT}};'],
            'condition' => $condition,
        ]);
    }

    private function filterChoice(string $id, string $label, string $selector, array $options, array $dictionary): void
    {
        $this->add_responsive_control($id, [
            'label' => $label,
            'type' => 'select',
            'options' => $options,
            'selectors_dictionary' => $dictionary,
            'selectors' => [$selector => '{{VALUE}}'],
        ]);
    }

    private function filterShadow(string $id, string $label, string $selector): void
    {
        $this->add_group_control(GroupControlType::resolve($this, 'box_shadow'), [
            'name' => $id,
            'label' => $label,
            'selector' => $selector,
        ]);
        $this->filterChoice($id . '_disabled', __('Quitar sombra', 'homlity-real-estate'), $selector, [
            '' => __('Predeterminada / personalizada', 'homlity-real-estate'),
            'yes' => __('Sin sombra', 'homlity-real-estate'),
        ], ['yes' => 'box-shadow:none;']);
    }

    private function registerFilterFormExtras(): void
    {
        $root = $this->filterSelector();
        $form = $this->filterSelector('.property-listing__filters');
        $row = $this->filterSelector('.property-listing__filters-row');
        $this->add_control('accent_color', AccentColor::control($root));
        $this->add_group_control('border', ['name' => 'form_border_style', 'selector' => $form]);
        $this->filterShadow('form_shadow', __('Sombra del formulario', 'homlity-real-estate'), $form);
        $this->add_group_control('background', [
            'name' => 'form_background',
            'types' => is_a($this, 'Homlity\\PluginInmobiliario\\Integrations\\Divi\\Compatibility\\Widget_Base') ? ['classic'] : ['classic', 'gradient'],
            'selector' => $form,
            'exclude' => ['image'],
        ]);
        $this->filterChoice('form_align_items', __('Alineación vertical de la fila', 'homlity-real-estate'), $row, [
            'start' => __('Arriba', 'homlity-real-estate'),
            'center' => __('Centro', 'homlity-real-estate'),
            'end' => __('Abajo', 'homlity-real-estate'),
            'stretch' => __('Estirar', 'homlity-real-estate'),
        ], ['start' => 'align-items:flex-start;', 'center' => 'align-items:center;', 'end' => 'align-items:flex-end;', 'stretch' => 'align-items:stretch;']);
        $this->filterChoice('actions_alignment', __('Alineación horizontal de las acciones', 'homlity-real-estate'), $this->filterSelector('.property-listing__filter-actions'), [
            'start' => __('Inicio', 'homlity-real-estate'),
            'center' => __('Centro', 'homlity-real-estate'),
            'end' => __('Final', 'homlity-real-estate'),
            'between' => __('Separar', 'homlity-real-estate'),
        ], ['start' => 'justify-content:flex-start;', 'center' => 'justify-content:center;', 'end' => 'justify-content:flex-end;', 'between' => 'justify-content:space-between;']);
        $this->filterChoice('form_distribution', __('Distribución', 'homlity-real-estate'), $root, [
            'flexible' => __('Flexible', 'homlity-real-estate'),
            'columns' => __('Columnas', 'homlity-real-estate'),
        ], ['flexible' => '--hpf-display:flex;', 'columns' => '--hpf-display:grid;']);
        $this->add_responsive_control('form_columns', [
            'label' => __('Número de columnas', 'homlity-real-estate'),
            'type' => 'slider',
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 1, 'max' => 12, 'step' => 1]],
            'selectors' => [$root => '--hpf-columns: {{SIZE}};'],
        ]);
        $this->filterSize('field_min_width', __('Ancho mínimo de campo', 'homlity-real-estate'), $root, '--hpf-field-min-width', 600);
        $this->filterChoice('actions_full_row', __('Distribución de las acciones', 'homlity-real-estate'), $root, [
            'inline' => __('En línea', 'homlity-real-estate'),
            'full' => __('Toda la fila', 'homlity-real-estate'),
        ], ['inline' => '--hpf-actions-basis:auto;--hpf-actions-column:auto;', 'full' => '--hpf-actions-basis:100%;--hpf-actions-column:1 / -1;']);
    }

    private function registerFilterLabelsExtras(): void
    {
        $this->filterSize('label_spacing', __('Espacio entre etiqueta y campo', 'homlity-real-estate'), $this->filterSelector('.property-listing__filter-group'), 'gap');
    }

    private function registerFilterFieldsExtras(): void
    {
        $this->filterSize('field_height', __('Altura de campos', 'homlity-real-estate'), $this->filterSelector(), '--hpf-field-height', 200);
        $this->filterColor('field_placeholder_color', __('Color del placeholder de inputs', 'homlity-real-estate'), $this->filterSelector('.property-listing__filter-input::placeholder'), 'color');
        $this->filterColor('range_separator_color', __('Color del separador de rangos', 'homlity-real-estate'), $this->filterSelector('.property-listing__price-sep'), 'color');
    }

    private function registerFilterStates(string $kind): void
    {
        if ($kind === 'field') {
            $this->filterColor('field_normal_border_color', __('Color de borde (normal)', 'homlity-real-estate'), $this->filterFields(), 'border-color');
        } else {
            $this->filterColor('button_normal_border_color', __('Borde buscar (normal)', 'homlity-real-estate'), $this->filterSelector('.property-listing__btn--primary'), 'border-color');
            $this->filterColor('button_reset_border_color', __('Borde limpiar (normal)', 'homlity-real-estate'), $this->filterSelector('.property-listing__btn--ghost'), 'border-color');
        }
        $this->end_controls_tab();
        foreach (['hover' => __('Hover', 'homlity-real-estate'), 'focus' => __('Focus', 'homlity-real-estate')] as $state => $label) {
            if ($kind === 'button' && $state === 'focus') {
                continue;
            }
            $this->start_controls_tab($kind . '_' . $state, ['label' => $label]);
            $targets = $kind === 'field'
                ? ['field' => [$this->filterFields(':' . $state), __('Campo', 'homlity-real-estate')]]
                : [
                    'button' => [$this->filterSelector('.property-listing__btn--primary:' . $state), __('Buscar', 'homlity-real-estate')],
                    'button_reset' => [$this->filterSelector('.property-listing__btn--ghost:' . $state), __('Limpiar', 'homlity-real-estate')],
                ];
            foreach ($targets as $prefix => [$selector, $targetLabel]) {
                foreach ([
                    'text' => ['color', __('Texto', 'homlity-real-estate')],
                    'bg' => ['background-color', __('Fondo', 'homlity-real-estate')],
                    'border' => ['border-color', __('Borde', 'homlity-real-estate')],
                ] as $part => [$property, $partLabel]) {
                    $this->filterColor($prefix . '_' . $state . '_' . $part . '_color', sprintf(__('%1$s %2$s (%3$s)', 'homlity-real-estate'), $partLabel, $targetLabel, $state), $selector, $property);
                }
            }
            if ($kind === 'field' && $state === 'focus') {
                $this->filterShadow('field_focus_shadow', __('Sombra de foco', 'homlity-real-estate'), $this->filterFields(':focus'));
            }
            $this->end_controls_tab();
        }
    }

    private function registerFilterSelectorExtras(): void
    {
        $this->filterColor('select_arrow_color', __('Color de la flecha', 'homlity-real-estate'), $this->filterSelector(), '--hpf-arrow-color');
        $this->filterSize('select_arrow_size', __('Tamaño de la flecha', 'homlity-real-estate'), $this->filterSelector(), '--hpf-arrow-size', 40);
    }

    private function registerFilterMenuExtras(): void
    {
        $menu = $this->filterSelector('.hpf-multi__menu');
        $this->filterSize('multi_menu_radius', __('Radio de la lista', 'homlity-real-estate'), $menu, 'border-radius');
        $this->filterSize('multi_menu_border_width', __('Grosor de borde de la lista', 'homlity-real-estate'), $menu, 'border-width', 20);
        $this->filterShadow('multi_menu_shadow', __('Sombra de la lista', 'homlity-real-estate'), $menu);
        $this->filterSize('multi_menu_max_height', __('Altura máxima de la lista', 'homlity-real-estate'), $menu, 'max-height', 1000);
        $this->filterSize('multi_menu_spacing', __('Separación respecto al campo', 'homlity-real-estate'), $menu, 'margin-top');
        $this->add_group_control('typography', ['name' => 'multi_item_typography', 'selector' => $this->filterSelector('.hpf-multi__item')]);
        $this->add_responsive_control('multi_item_padding', [
            'label' => __('Padding de las opciones', 'homlity-real-estate'),
            'type' => 'dimensions',
            'size_units' => ['px', 'em'],
            'selectors' => [$this->filterSelector('.hpf-multi__item') => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->filterChoice('multi_alternate_rows', __('Filas alternas', 'homlity-real-estate'), $this->filterSelector(), [
            'yes' => __('Activadas', 'homlity-real-estate'),
            'no' => __('Desactivadas', 'homlity-real-estate'),
        ], ['yes' => '--hpf-alternate-bg:#f9fafb;', 'no' => '--hpf-alternate-bg:var(--hpf-item-bg, #fff);']);
        $this->filterColor('multi_item_hover_text_color', __('Texto opción (hover)', 'homlity-real-estate'), $this->filterSelector('.hpf-multi__item:hover'), 'color');
        $this->filterColor('multi_item_selected_text_color', __('Texto opción seleccionada', 'homlity-real-estate'), $this->filterSelector('.hpf-multi__item.is-selected'), 'color');
        $this->filterColor('multi_checkbox_color', __('Color del checkbox seleccionado', 'homlity-real-estate'), $this->filterSelector('.hpf-multi__item.is-selected::before'), 'background-color');
        $this->filterSize('multi_checkbox_radius', __('Radio del checkbox', 'homlity-real-estate'), $this->filterSelector('.hpf-multi__item::before'), 'border-radius', 30);
        $this->add_group_control('border', ['name' => 'multi_checkbox_border', 'selector' => $this->filterSelector('.hpf-multi__item::before')]);
        $this->filterColor('multi_search_bg_color', __('Fondo del buscador interno', 'homlity-real-estate'), $this->filterSelector('.hpf-multi__search'), 'background-color');
        $this->filterColor('multi_search_text_color', __('Texto del buscador interno', 'homlity-real-estate'), $this->filterSelector('.hpf-multi__search'), 'color');
        $this->add_group_control('border', ['name' => 'multi_search_border', 'selector' => $this->filterSelector('.hpf-multi__search')]);
        $this->filterSize('multi_search_radius', __('Radio del buscador interno', 'homlity-real-estate'), $this->filterSelector('.hpf-multi__search'), 'border-radius');
        $this->filterColor('multi_empty_color', __('Color del mensaje vacío', 'homlity-real-estate'), $this->filterSelector('.hpf-multi__empty'), 'color');
    }

    private function registerFilterButtonsExtras(): void
    {
        $this->filterSize('button_height', __('Altura de botones', 'homlity-real-estate'), $this->filterSelector(), '--hpf-button-height', 200);
    }

    private function registerFilterMobileStyles(): void
    {
        $condition = ['mobile_sidebar_enabled' => 'yes'];
        $this->start_controls_section('style_mobile_panel', [
            'label' => __('Panel móvil', 'homlity-real-estate'),
            'tab' => 'style',
            'condition' => $condition,
        ]);
        $this->filterSize('mobile_panel_width', __('Ancho del panel', 'homlity-real-estate'), $this->filterSelector(), '--hpf-panel-width', 1000, $condition);
        $this->filterColor('mobile_panel_bg_color', __('Fondo del panel', 'homlity-real-estate'), $this->filterSelector(), '--hpf-panel-bg', $condition);
        $this->filterColor('mobile_overlay_color', __('Color del overlay', 'homlity-real-estate'), $this->filterSelector('.property-listing__mobile-sidebar-overlay'), 'background-color', $condition);
        $header = $this->filterSelector('.property-listing__mobile-sidebar-header strong');
        $this->add_group_control('typography', ['name' => 'mobile_header_typography', 'selector' => $header, 'condition' => $condition]);
        $this->filterColor('mobile_header_color', __('Color de la cabecera', 'homlity-real-estate'), $header, 'color', $condition);
        $close = $this->filterSelector('.property-listing__mobile-close');
        $this->filterColor('mobile_close_color', __('Color del botón cerrar', 'homlity-real-estate'), $close, 'color', $condition);
        $this->filterSize('mobile_close_size', __('Tamaño del botón cerrar', 'homlity-real-estate'), $close, 'font-size', 80, $condition);
        $this->end_controls_section();
    }
}
