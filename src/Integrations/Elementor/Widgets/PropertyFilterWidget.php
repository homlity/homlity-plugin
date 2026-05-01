<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyFilterWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'property_filter';
    }

    public function get_title(): string
    {
        return __('Filtro de inmuebles', 'homlity-plugin');
    }

    public function get_icon(): string
    {
        return 'eicon-filter';
    }

    public function get_categories(): array
    {
        return ['homlity-plugin'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Campos', 'homlity-plugin')]);

        $this->add_control('target_page_id', [
            'label' => __('Página de resultados', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT2,
            'options' => $this->getPagesOptions(),
            'default' => (string) get_option('homlity_plugin_archive_page_id', 0),
        ]);

        foreach ([
            'show_keyword' => __('Palabra clave', 'homlity-plugin'),
            'show_category' => __('Categoría', 'homlity-plugin'),
            'show_operation' => __('Gestión', 'homlity-plugin'),
            'show_type' => __('Tipo de inmueble', 'homlity-plugin'),
            'show_tag' => __('Etiqueta', 'homlity-plugin'),
            'show_country' => __('País', 'homlity-plugin'),
            'show_state' => __('Departamento / Provincia', 'homlity-plugin'),
            'show_city' => __('Ciudad', 'homlity-plugin'),
            'show_neighborhood' => __('Barrio', 'homlity-plugin'),
            'show_nearby' => __('Lugar cercano', 'homlity-plugin'),
            'show_price' => __('Rango de precio', 'homlity-plugin'),
            'show_area' => __('Rango de área', 'homlity-plugin'),
            'show_bedrooms' => __('Habitaciones', 'homlity-plugin'),
            'show_bathrooms' => __('Baños', 'homlity-plugin'),
            'show_parking' => __('Garajes', 'homlity-plugin'),
        ] as $key => $label) {
            $this->add_control($key, [
                'label' => $label,
                'type' => Controls_Manager::SWITCHER,
                'default' => in_array($key, ['show_keyword', 'show_operation', 'show_type', 'show_city', 'show_price'], true) ? 'yes' : '',
            ]);
        }

        $this->add_control('multiple_operation', [
            'label' => __('Gestión múltiple', 'homlity-plugin'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'condition' => ['show_operation' => 'yes'],
        ]);

        $this->add_control('multiple_type', [
            'label' => __('Tipo de inmueble múltiple', 'homlity-plugin'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'condition' => ['show_type' => 'yes'],
        ]);

        $this->add_control('multiple_tag', [
            'label' => __('Etiquetas múltiples', 'homlity-plugin'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => ['show_tag' => 'yes'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('actions', ['label' => __('Acciones', 'homlity-plugin')]);

        $this->add_control('submit_label', [
            'label' => __('Texto del botón buscar', 'homlity-plugin'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Buscar', 'homlity-plugin'),
        ]);

        $this->add_control('reset_label', [
            'label' => __('Texto del botón limpiar', 'homlity-plugin'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Limpiar', 'homlity-plugin'),
        ]);

        $this->add_control('show_reset', [
            'label' => __('Mostrar botón limpiar', 'homlity-plugin'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_form', [
            'label' => __('Formulario', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('form_bg_color', [
            'label' => __('Fondo del formulario', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('form_border_color', [
            'label' => __('Color de borde', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('form_border_radius', [
            'label' => __('Radio de borde', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 0, 'max' => 40],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('fields_gap', [
            'label' => __('Espacio entre campos', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 0, 'max' => 40],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters-row' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('form_padding', [
            'label' => __('Padding del formulario', 'homlity-plugin'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('form_margin', [
            'label' => __('Margin del formulario', 'homlity-plugin'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('form_layout', [
            'label' => __('Dirección del formulario', 'homlity-plugin'),
            'type' => Controls_Manager::CHOOSE,
            'default' => 'horizontal',
            'options' => [
                'horizontal' => [
                    'title' => __('Horizontal', 'homlity-plugin'),
                    'icon' => 'eicon-ellipsis-h',
                ],
                'vertical' => [
                    'title' => __('Vertical', 'homlity-plugin'),
                    'icon' => 'eicon-ellipsis-v',
                ],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters-row' => 'display:flex;flex-wrap:wrap;align-items:flex-end;',
            ],
            'selectors_dictionary' => [
                'horizontal' => 'display:flex;flex-wrap:wrap;align-items:flex-end;',
                'vertical' => 'display:flex;flex-direction:column;flex-wrap:nowrap;align-items:stretch;',
            ],
        ]);

        $this->add_control('form_layout_vertical_helper', [
            'label' => '',
            'type' => Controls_Manager::HIDDEN,
            'condition' => [
                'form_layout' => 'vertical',
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters-row' => 'flex-direction:column;align-items:stretch;',
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-group' => 'min-width:100%;flex:1 1 100%;',
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-actions' => 'align-items:stretch;justify-content:flex-start;',
                '{{WRAPPER}} .property-filter-widget .property-listing__btn' => 'width:100%;',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_labels', [
            'label' => __('Etiquetas', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'label_typography',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__filter-label',
        ]);

        $this->add_control('label_color', [
            'label' => __('Color de texto', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-label' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_fields', [
            'label' => __('Campos', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'field_typography',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input',
        ]);

        $this->add_control('field_text_color', [
            'label' => __('Color de texto', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('field_bg_color', [
            'label' => __('Color de fondo', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'field_border',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input',
        ]);

        $this->add_responsive_control('field_border_radius', [
            'label' => __('Radio de borde', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 0, 'max' => 30],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('field_padding', [
            'label' => __('Padding de campos', 'homlity-plugin'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('field_margin', [
            'label' => __('Margin de campos', 'homlity-plugin'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-group' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_buttons', [
            'label' => __('Botones', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'button_typography',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__btn',
        ]);

        $this->add_control('button_text_color', [
            'label' => __('Texto botón buscar', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_bg_color', [
            'label' => __('Fondo botón buscar', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_reset_text_color', [
            'label' => __('Texto botón limpiar', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--ghost' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_reset_bg_color', [
            'label' => __('Fondo botón limpiar', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--ghost' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'button_border',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__btn',
        ]);

        $this->add_responsive_control('button_border_radius', [
            'label' => __('Radio de borde', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 0, 'max' => 30],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_padding', [
            'label' => __('Padding de botones', 'homlity-plugin'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_margin', [
            'label' => __('Margin de botones', 'homlity-plugin'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        TemplateService::includeComponent('property-filter.php', [
            'settings' => $settings,
        ]);
    }

    private function getPagesOptions(): array
    {
        $pages = get_pages(['sort_column' => 'post_title', 'sort_order' => 'ASC']);
        $options = ['0' => __('Página automática de resultados', 'homlity-plugin')];

        foreach ($pages as $page) {
            $options[(string) $page->ID] = $page->post_title;
        }

        return $options;
    }
}
