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
    private const FILTER_SCRIPT_HANDLE = 'homlity-real-estate-filter';
    private const FRONT_COMPONENTS_STYLE_HANDLE = 'homlity-real-estate-front-components';
    private const LISTING_STYLE_HANDLE = 'homlity-real-estate-listing';

    public function get_name(): string
    {
        return 'property_filter';
    }

    public function get_title(): string
    {
        return __('Filtro de inmuebles', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-filter';
    }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }

    public function get_script_depends(): array
    {
        wp_register_script(
            self::FILTER_SCRIPT_HANDLE,
            HOMLITY_PLUGIN_URL . 'assets/js/property-filter.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        return [self::FILTER_SCRIPT_HANDLE];
    }

    public function get_style_depends(): array
    {
        wp_register_style(
            self::FRONT_COMPONENTS_STYLE_HANDLE,
            HOMLITY_PLUGIN_URL . 'assets/css/front-components.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );
        wp_register_style(
            self::LISTING_STYLE_HANDLE,
            HOMLITY_PLUGIN_URL . 'assets/css/property-listing.css',
            [self::FRONT_COMPONENTS_STYLE_HANDLE],
            HOMLITY_PLUGIN_VERSION
        );

        return [self::LISTING_STYLE_HANDLE];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Campos', 'homlity-real-estate')]);

        $this->add_control('target_page_id', [
            'label' => __('Página de resultados', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT2,
            'options' => $this->getPagesOptions(),
            'default' => (string) get_option('homlity_plugin_archive_page_id', 0),
        ]);

        foreach ([
            'show_keyword' => __('Palabra clave', 'homlity-real-estate'),
            'show_category' => __('Categoría', 'homlity-real-estate'),
            'show_operation' => __('Gestión', 'homlity-real-estate'),
            'show_type' => __('Tipo de inmueble', 'homlity-real-estate'),
            'show_tag' => __('Etiqueta', 'homlity-real-estate'),
            'show_country' => __('País', 'homlity-real-estate'),
            'show_state' => __('Departamento / Provincia', 'homlity-real-estate'),
            'show_city' => __('Ciudad', 'homlity-real-estate'),
            'show_locality' => __('Localidad', 'homlity-real-estate'),
            'show_neighborhood' => __('Barrio', 'homlity-real-estate'),
            'show_nearby' => __('Lugar cercano', 'homlity-real-estate'),
            'show_price' => __('Rango de precio', 'homlity-real-estate'),
            'show_area' => __('Rango de área', 'homlity-real-estate'),
            'show_bedrooms' => __('Habitaciones', 'homlity-real-estate'),
            'show_bathrooms' => __('Baños', 'homlity-real-estate'),
            'show_parking' => __('Garajes', 'homlity-real-estate'),
        ] as $key => $label) {
            $this->add_control($key, [
                'label' => $label,
                'type' => Controls_Manager::SWITCHER,
                'default' => in_array($key, ['show_keyword', 'show_operation', 'show_type', 'show_city', 'show_price'], true) ? 'yes' : '',
            ]);
        }

        $this->add_control('ignore_default_location', [
            'label' => __('Ignorar ubicación por defecto', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'separator' => 'before',
            'description' => __('En Ajustes → Ubicación base puedes hacer que el buscador aparezca con tu ciudad o barrio ya elegidos. Actívalo aquí si este buscador en concreto debe empezar vacío.', 'homlity-real-estate'),
        ]);

        $this->add_control('multiple_operation', [
            'label' => __('Gestión múltiple', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'condition' => ['show_operation' => 'yes'],
        ]);

        $this->add_control('multiple_type', [
            'label' => __('Tipo de inmueble múltiple', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => ['show_type' => 'yes'],
        ]);

        $this->add_control('multiple_tag', [
            'label' => __('Etiquetas múltiples', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => ['show_tag' => 'yes'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('actions', ['label' => __('Acciones', 'homlity-real-estate')]);

        $this->add_control('submit_label', [
            'label' => __('Texto del botón buscar', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Buscar', 'homlity-real-estate'),
        ]);

        $this->add_control('reset_label', [
            'label' => __('Texto del botón limpiar', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Limpiar', 'homlity-real-estate'),
        ]);

        $this->add_control('show_reset', [
            'label' => __('Mostrar botón limpiar', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('mobile_sidebar_enabled', [
            'label' => __('Modo móvil: botón + sidebar', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('mobile_filter_button_label', [
            'label' => __('Texto botón móvil', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Filtrar inmuebles', 'homlity-real-estate'),
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_control('field_label_mode', [
            'label' => __('Mostrar etiqueta', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'outside',
            'options' => [
                'outside' => __('Afuera del campo', 'homlity-real-estate'),
                'placeholder' => __('Dentro del campo (placeholder)', 'homlity-real-estate'),
            ],
        ]);

        $this->add_control('select_first_option_mode', [
            'label' => __('Texto primer option en selects', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'auto',
            'options' => [
                'auto' => __('Automático (según modo de etiqueta)', 'homlity-real-estate'),
                'generic' => __('Genérico (Todos/Cualquiera)', 'homlity-real-estate'),
                'label' => __('Usar nombre del campo (Gestión, Tipo, etc.)', 'homlity-real-estate'),
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_form', [
            'label' => __('Formulario', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('form_bg_color', [
            'label' => __('Fondo del formulario', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('form_border_color', [
            'label' => __('Color de borde', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('form_border_radius', [
            'label' => __('Radio de borde', 'homlity-real-estate'),
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
            'label' => __('Espacio entre campos', 'homlity-real-estate'),
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
            'label' => __('Padding del formulario', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('form_margin', [
            'label' => __('Margin del formulario', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filters' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('form_layout', [
            'label' => __('Dirección del formulario', 'homlity-real-estate'),
            'type' => Controls_Manager::CHOOSE,
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
                '{{WRAPPER}} .property-filter-widget .property-listing__filters-row' => 'flex-direction:column;',
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-group' => 'min-width:100%;flex:1 1 100%;',
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-actions' => 'align-items:stretch;justify-content:flex-start;',
                '{{WRAPPER}} .property-filter-widget .property-listing__btn' => 'width:100%;',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_labels', [
            'label' => __('Etiquetas', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'label_typography',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__filter-label',
        ]);

        $this->add_control('label_color', [
            'label' => __('Color de texto', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-label' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_fields', [
            'label' => __('Campos', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'field_typography',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input, {{WRAPPER}} .property-filter-widget .hpf-multi__trigger',
        ]);

        $this->add_control('field_text_color', [
            'label' => __('Color de texto', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input, {{WRAPPER}} .property-filter-widget .hpf-multi__trigger' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('field_bg_color', [
            'label' => __('Color de fondo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input, {{WRAPPER}} .property-filter-widget .hpf-multi__trigger' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'field_border',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input, {{WRAPPER}} .property-filter-widget .hpf-multi__trigger',
        ]);

        $this->add_responsive_control('field_border_radius', [
            'label' => __('Radio de borde', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
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
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-select, {{WRAPPER}} .property-filter-widget .property-listing__filter-input, {{WRAPPER}} .property-filter-widget .hpf-multi__trigger' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('field_margin', [
            'label' => __('Margin de campos', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__filter-group' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_multiselect_chips', [
            'label' => __('Chips multiselección', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'multi_chip_typography',
            'selector' => '{{WRAPPER}} .property-filter-widget .hpf-multi__chip',
        ]);

        $this->add_control('multi_field_heading', [
            'label' => __('Campo multiselección', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
        ]);

        $this->add_control('multi_trigger_text_color', [
            'label' => __('Color texto campo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__trigger' => 'color: {{VALUE}} !important;',
            ],
        ]);

        $this->add_control('multi_summary_color', [
            'label' => __('Color texto resumen', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__summary' => 'color: {{VALUE}} !important;',
            ],
        ]);

        $this->add_control('multi_trigger_bg_color', [
            'label' => __('Fondo campo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__trigger' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_trigger_border_color', [
            'label' => __('Borde campo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__trigger' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_placeholder_color', [
            'label' => __('Color placeholder', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__placeholder' => 'color: {{VALUE}} !important;',
            ],
        ]);

        $this->add_control('multi_menu_heading', [
            'label' => __('Lista de opciones', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('multi_menu_bg_color', [
            'label' => __('Fondo lista', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__menu' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_menu_border_color', [
            'label' => __('Borde lista', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__menu' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_item_text_color', [
            'label' => __('Texto opción', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__item' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_item_bg_color', [
            'label' => __('Fondo opción', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__item' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_item_hover_bg_color', [
            'label' => __('Fondo opción hover', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__item:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_item_selected_bg_color', [
            'label' => __('Fondo opción seleccionada', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__item.is-selected' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_chip_text_color', [
            'label' => __('Color texto chip', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_chip_bg_color', [
            'label' => __('Fondo chip', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'multi_chip_border',
            'selector' => '{{WRAPPER}} .property-filter-widget .hpf-multi__chip',
        ]);

        $this->add_responsive_control('multi_chip_border_radius', [
            'label' => __('Radio chip', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
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
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('multi_chip_heading_hover', [
            'label' => __('Hover chip', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('multi_chip_hover_text_color', [
            'label' => __('Texto chip hover', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_chip_hover_bg_color', [
            'label' => __('Fondo chip hover', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_chip_remove_heading', [
            'label' => __('Botón quitar (×)', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('multi_chip_remove_size', [
            'label' => __('Tamaño icono ×', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
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
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip-remove' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_chip_remove_hover_color', [
            'label' => __('Color icono × hover', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip-remove:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_chip_remove_bg_color', [
            'label' => __('Fondo botón ×', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip-remove' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('multi_chip_remove_hover_bg_color', [
            'label' => __('Fondo botón × hover', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .hpf-multi__chip-remove:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_buttons', [
            'label' => __('Botones', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'button_typography',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__btn',
        ]);

        $this->add_control('button_text_color', [
            'label' => __('Texto botón buscar', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_bg_color', [
            'label' => __('Fondo botón buscar', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_reset_text_color', [
            'label' => __('Texto botón limpiar', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--ghost' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_reset_bg_color', [
            'label' => __('Fondo botón limpiar', 'homlity-real-estate'),
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
            'label' => __('Radio de borde', 'homlity-real-estate'),
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
            'label' => __('Padding de botones', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_margin', [
            'label' => __('Margin de botones', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('button_primary_heading', [
            'label' => __('Botón Buscar', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('button_primary_width', [
            'label' => __('Ancho botón buscar', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => [
                'px' => ['min' => 40, 'max' => 480],
                '%' => ['min' => 10, 'max' => 100],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary' => 'width: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'button_primary_border',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary',
        ]);

        $this->add_responsive_control('button_primary_padding', [
            'label' => __('Padding botón buscar', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_primary_margin', [
            'label' => __('Margin botón buscar', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__btn--primary' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('button_mobile_heading', [
            'label' => __('Botón Filtrar (Móvil)', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'mobile_filter_button_typography',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle',
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_control('mobile_filter_button_text_color', [
            'label' => __('Color texto (móvil)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle' => 'color: {{VALUE}};',
            ],
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_control('mobile_filter_button_bg_color', [
            'label' => __('Fondo (móvil)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle' => 'background-color: {{VALUE}};',
            ],
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'mobile_filter_button_border',
            'selector' => '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle',
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_responsive_control('mobile_filter_button_width', [
            'label' => __('Ancho botón filtrar', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
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
            'type' => Controls_Manager::SLIDER,
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
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_responsive_control('mobile_filter_button_margin', [
            'label' => __('Margin botón filtrar', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-filter-widget .property-listing__mobile-toggle' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
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
        $options = ['0' => __('Página automática de resultados', 'homlity-real-estate')];

        foreach ($pages as $page) {
            $options[(string) $page->ID] = $page->post_title;
        }

        return $options;
    }
}
