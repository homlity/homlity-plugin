<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Repeater;

class Homlity_Property_FAQ_Widget extends \Elementor\Widget_Base
{
    private static int $schema_instance_counter = 0;

    public function get_name(): string
    {
        return 'homlity_property_faq';
    }

    public function get_title(): string
    {
        return __('Homlity FAQs del Inmueble', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-help-o';
    }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }

    public function get_keywords(): array
    {
        return ['homlity', 'inmueble', 'propiedad', 'faq', 'preguntas', 'frecuentes', 'inmobiliaria', 'schema'];
    }

    public function get_script_depends(): array
    {
        return ['homlity-property-faq'];
    }

    public function get_style_depends(): array
    {
        return ['homlity-property-faq'];
    }

    protected function register_controls(): void
    {
        // ── Fuente de información ────────────────────────────────────────────
        $this->start_controls_section('source', [
            'label' => __('Fuente de información', 'homlity-real-estate'),
        ]);

        $this->add_control('use_current', [
            'label'        => __('Usar inmueble actual', 'homlity-real-estate'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ]);

        $this->add_control('manual_post_id', [
            'label'       => __('ID del inmueble', 'homlity-real-estate'),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 1,
            'description' => __('Ingresa el ID del post del inmueble.', 'homlity-real-estate'),
            'condition'   => ['use_current!' => 'yes'],
        ]);

        $this->add_control('show_empty_message', [
            'label'        => __('Mostrar mensaje si no hay FAQs', 'homlity-real-estate'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'separator'    => 'before',
        ]);

        $this->add_control('empty_message', [
            'label'     => __('Mensaje vacío', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('No hay preguntas frecuentes disponibles para este inmueble.', 'homlity-real-estate'),
            'condition' => ['show_empty_message' => 'yes'],
        ]);

        $this->end_controls_section();

        // ── FAQs automáticas ────────────────────────────────────────────────
        $this->start_controls_section('auto_faqs', [
            'label' => __('FAQs automáticas', 'homlity-real-estate'),
        ]);

        $this->add_control('enable_auto_faqs', [
            'label'        => __('Activar FAQs automáticas', 'homlity-real-estate'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ]);

        $auto_questions = [
            'price'     => __('Precio', 'homlity-real-estate'),
            'operation' => __('Operación (venta/arriendo)', 'homlity-real-estate'),
            'location'  => __('Ubicación', 'homlity-real-estate'),
            'type'      => __('Tipo de inmueble', 'homlity-real-estate'),
            'area'      => __('Área', 'homlity-real-estate'),
            'bedrooms'  => __('Habitaciones', 'homlity-real-estate'),
            'bathrooms' => __('Baños', 'homlity-real-estate'),
            'parking'   => __('Parqueaderos', 'homlity-real-estate'),
            'admin'     => __('Administración', 'homlity-real-estate'),
            'stratum'   => __('Estrato', 'homlity-real-estate'),
            'features'  => __('Características', 'homlity-real-estate'),
            'code'      => __('Código del inmueble', 'homlity-real-estate'),
            'contact'   => __('Información de contacto', 'homlity-real-estate'),
        ];

        foreach ($auto_questions as $key => $label) {
            $this->add_control('show_' . $key, [
                'label'        => $label,
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
                'separator'    => 'before',
                'condition'    => ['enable_auto_faqs' => 'yes'],
            ]);
        }

        $this->add_control('auto_limit', [
            'label'     => __('Límite de FAQs automáticas', 'homlity-real-estate'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 0,
            'default'   => 0,
            'separator' => 'before',
            'description' => __('0 = sin límite.', 'homlity-real-estate'),
            'condition' => ['enable_auto_faqs' => 'yes'],
        ]);

        $this->end_controls_section();

        // ── FAQs manuales ───────────────────────────────────────────────────
        $this->start_controls_section('manual_faqs', [
            'label' => __('FAQs manuales', 'homlity-real-estate'),
        ]);

        $repeater = new Repeater();

        $repeater->add_control('faq_question', [
            'label'       => __('Pregunta', 'homlity-real-estate'),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'label_block' => true,
        ]);

        $repeater->add_control('faq_answer', [
            'label'       => __('Respuesta', 'homlity-real-estate'),
            'type'        => Controls_Manager::WYSIWYG,
            'default'     => '',
            'label_block' => true,
        ]);

        $repeater->add_control('faq_visible', [
            'label'        => __('Visible', 'homlity-real-estate'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ]);

        $repeater->add_control('faq_position', [
            'label'   => __('Posición respecto a automáticas', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'before' => __('Antes de automáticas', 'homlity-real-estate'),
                'after'  => __('Después de automáticas', 'homlity-real-estate'),
            ],
            'default' => 'after',
        ]);

        $this->add_control('manual_items', [
            'label'       => __('Preguntas manuales', 'homlity-real-estate'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'default'     => [],
            'title_field' => '{{{ faq_question }}}',
        ]);

        $this->end_controls_section();

        // ── FAQs globales del plugin ─────────────────────────────────────────
        $this->start_controls_section('global_faqs', [
            'label' => __('FAQs globales del plugin', 'homlity-real-estate'),
        ]);

        $this->add_control('include_global_faqs', [
            'label'        => __('Incluir FAQs globales', 'homlity-real-estate'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => '',
            'return_value' => 'yes',
            'description'  => __('Muestra las preguntas globales configuradas en Homlity → SEO & GEO → FAQs globales.', 'homlity-real-estate'),
        ]);

        $this->add_control('global_faqs_context', [
            'label'     => __('Contexto de página', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'auto'     => __('Detectar automáticamente', 'homlity-real-estate'),
                'all'      => __('Todas las páginas', 'homlity-real-estate'),
                'home'     => __('Página principal', 'homlity-real-estate'),
                'property' => __('Páginas de inmuebles', 'homlity-real-estate'),
                'zone'     => __('Páginas de barrio/zona', 'homlity-real-estate'),
                'contact'  => __('Página de contacto', 'homlity-real-estate'),
            ],
            'default'   => 'auto',
            'condition' => ['include_global_faqs' => 'yes'],
        ]);

        $this->add_control('global_faqs_position', [
            'label'     => __('Posición', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'after'  => __('Después de las demás FAQs', 'homlity-real-estate'),
                'before' => __('Antes de las demás FAQs', 'homlity-real-estate'),
            ],
            'default'   => 'after',
            'condition' => ['include_global_faqs' => 'yes'],
        ]);

        $this->end_controls_section();

        // ── Configuración general ───────────────────────────────────────────
        $this->start_controls_section('general', [
            'label' => __('Configuración general', 'homlity-real-estate'),
        ]);

        $this->add_control('block_title', [
            'label'   => __('Título del bloque', 'homlity-real-estate'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('Preguntas frecuentes del inmueble', 'homlity-real-estate'),
        ]);

        $this->add_control('title_tag', [
            'label'   => __('Etiqueta HTML del título', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div'],
            'default' => 'h2',
        ]);

        $this->add_control('block_description', [
            'label'   => __('Descripción', 'homlity-real-estate'),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => __('Resuelve las dudas más comunes sobre este inmueble antes de solicitar más información o agendar una visita.', 'homlity-real-estate'),
            'rows'    => 3,
        ]);

        $this->add_control('layout', [
            'label'     => __('Layout', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'accordion' => __('Acordeón', 'homlity-real-estate'),
                'open'      => __('Lista abierta', 'homlity-real-estate'),
                'cards'     => __('Tarjetas', 'homlity-real-estate'),
            ],
            'default'   => 'accordion',
            'separator' => 'before',
        ]);

        $this->add_control('first_open', [
            'label'        => __('Primera pregunta abierta', 'homlity-real-estate'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'condition'    => ['layout' => 'accordion'],
        ]);

        $this->add_control('allow_multiple', [
            'label'        => __('Permitir múltiples abiertas', 'homlity-real-estate'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => '',
            'return_value' => 'yes',
            'condition'    => ['layout' => 'accordion'],
        ]);

        $this->add_control('icon_closed', [
            'label'     => __('Ícono cerrado', 'homlity-real-estate'),
            'type'      => Controls_Manager::ICONS,
            'default'   => ['value' => 'fas fa-chevron-down', 'library' => 'fa-solid'],
            'separator' => 'before',
        ]);

        $this->add_control('icon_open', [
            'label'   => __('Ícono abierto', 'homlity-real-estate'),
            'type'    => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-chevron-up', 'library' => 'fa-solid'],
        ]);

        $this->add_control('enable_schema', [
            'label'        => __('Activar Schema FAQPage (JSON-LD)', 'homlity-real-estate'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'separator'    => 'before',
        ]);

        $this->add_control('custom_id', [
            'label'     => __('ID personalizado del bloque', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'separator' => 'before',
        ]);

        $this->add_control('custom_class', [
            'label' => __('Clase CSS personalizada', 'homlity-real-estate'),
            'type'  => Controls_Manager::TEXT,
        ]);

        $this->end_controls_section();

        // ══ ESTILO ══════════════════════════════════════════════════════════

        // ── Contenedor ──────────────────────────────────────────────────────
        $this->start_controls_section('style_container', [
            'label' => __('Contenedor', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('max_width', [
            'label'      => __('Ancho máximo', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'vw'],
            'range'      => ['px' => ['min' => 300, 'max' => 1600]],
            'selectors'  => ['{{WRAPPER}} .homlity-property-faq-widget' => 'max-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('container_align', [
            'label'     => __('Alineación', 'homlity-real-estate'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'left'   => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-h-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'),    'icon' => 'eicon-h-align-center'],
                'right'  => ['title' => __('Derecha', 'homlity-real-estate'),   'icon' => 'eicon-h-align-right'],
            ],
            'selectors_dictionary' => [
                'left'   => 'margin-left: 0; margin-right: auto;',
                'center' => 'margin-left: auto; margin-right: auto;',
                'right'  => 'margin-left: auto; margin-right: 0;',
            ],
            'selectors' => [
                '{{WRAPPER}} .homlity-property-faq-widget' => '{{VALUE}}',
            ],
        ]);

        $this->add_control('container_bg', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-widget' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('container_padding', [
            'label'      => __('Padding', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => ['{{WRAPPER}} .homlity-property-faq-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('container_margin', [
            'label'      => __('Margen', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => ['{{WRAPPER}} .homlity-property-faq-widget' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'container_border',
            'selector' => '{{WRAPPER}} .homlity-property-faq-widget',
        ]);

        $this->add_responsive_control('container_radius', [
            'label'      => __('Border radius', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} .homlity-property-faq-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'container_shadow',
            'selector' => '{{WRAPPER}} .homlity-property-faq-widget',
        ]);

        $this->add_responsive_control('items_gap', [
            'label'      => __('Espacio entre ítems', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['unit' => 'px', 'size' => 12],
            'selectors'  => ['{{WRAPPER}} .homlity-property-faq-item + .homlity-property-faq-item' => 'margin-top: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();

        // ── Título ──────────────────────────────────────────────────────────
        $this->start_controls_section('style_title', [
            'label' => __('Título', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('title_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-title' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'title_typography',
            'selector' => '{{WRAPPER}} .homlity-property-faq-title',
        ]);

        $this->add_responsive_control('title_align', [
            'label'     => __('Alineación', 'homlity-real-estate'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'left'   => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'),    'icon' => 'eicon-text-align-center'],
                'right'  => ['title' => __('Derecha', 'homlity-real-estate'),   'icon' => 'eicon-text-align-right'],
            ],
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-title' => 'text-align: {{VALUE}};'],
        ]);

        $this->add_responsive_control('title_margin', [
            'label'      => __('Espaciado inferior', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'selectors'  => ['{{WRAPPER}} .homlity-property-faq-title' => 'margin-bottom: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();

        // ── Descripción ─────────────────────────────────────────────────────
        $this->start_controls_section('style_description', [
            'label' => __('Descripción', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('desc_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-description' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'desc_typography',
            'selector' => '{{WRAPPER}} .homlity-property-faq-description',
        ]);

        $this->add_responsive_control('desc_align', [
            'label'     => __('Alineación', 'homlity-real-estate'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'left'   => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'),    'icon' => 'eicon-text-align-center'],
                'right'  => ['title' => __('Derecha', 'homlity-real-estate'),   'icon' => 'eicon-text-align-right'],
            ],
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-description' => 'text-align: {{VALUE}};'],
        ]);

        $this->add_responsive_control('desc_margin', [
            'label'      => __('Espaciado inferior', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'selectors'  => ['{{WRAPPER}} .homlity-property-faq-description' => 'margin-bottom: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();

        // ── Item FAQ ────────────────────────────────────────────────────────
        $this->start_controls_section('style_item', [
            'label' => __('Ítem FAQ', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->start_controls_tabs('item_tabs');

        $this->start_controls_tab('item_tab_normal', ['label' => __('Normal', 'homlity-real-estate')]);
        $this->add_control('item_bg', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-item' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('item_tab_hover', ['label' => __('Hover', 'homlity-real-estate')]);
        $this->add_control('item_bg_hover', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-item:hover' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('item_tab_active', ['label' => __('Activo', 'homlity-real-estate')]);
        $this->add_control('item_bg_active', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-item.is-active' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'      => 'item_border',
            'selector'  => '{{WRAPPER}} .homlity-property-faq-item',
            'separator' => 'before',
        ]);

        $this->add_responsive_control('item_radius', [
            'label'      => __('Border radius', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} .homlity-property-faq-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('item_padding', [
            'label'      => __('Padding', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => ['{{WRAPPER}} .homlity-property-faq-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'item_shadow',
            'selector' => '{{WRAPPER}} .homlity-property-faq-item',
        ]);

        $this->end_controls_section();

        // ── Pregunta ────────────────────────────────────────────────────────
        $this->start_controls_section('style_question', [
            'label' => __('Pregunta', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->start_controls_tabs('question_tabs');

        $this->start_controls_tab('q_tab_normal', ['label' => __('Normal', 'homlity-real-estate')]);
        $this->add_control('question_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-question' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('icon_color', [
            'label'     => __('Color ícono', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-icon' => 'color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('q_tab_hover', ['label' => __('Hover', 'homlity-real-estate')]);
        $this->add_control('question_color_hover', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-item:hover .homlity-property-faq-question' => 'color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('q_tab_active', ['label' => __('Activo', 'homlity-real-estate')]);
        $this->add_control('question_color_active', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-item.is-active .homlity-property-faq-question' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('icon_color_active', [
            'label'     => __('Color ícono', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-item.is-active .homlity-property-faq-icon' => 'color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'      => 'question_typography',
            'selector'  => '{{WRAPPER}} .homlity-property-faq-question-text',
            'separator' => 'before',
        ]);

        $this->add_responsive_control('question_padding', [
            'label'      => __('Padding', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => ['{{WRAPPER}} .homlity-property-faq-question' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('icon_size', [
            'label'      => __('Tamaño del ícono', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 8, 'max' => 40]],
            'selectors'  => [
                '{{WRAPPER}} .homlity-property-faq-icon'     => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .homlity-property-faq-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        // ── Respuesta ───────────────────────────────────────────────────────
        $this->start_controls_section('style_answer', [
            'label' => __('Respuesta', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('answer_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-answer-inner' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'answer_typography',
            'selector' => '{{WRAPPER}} .homlity-property-faq-answer-inner',
        ]);

        $this->add_control('answer_bg', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-answer' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('answer_padding', [
            'label'      => __('Padding', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => ['{{WRAPPER}} .homlity-property-faq-answer-inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('answer_align', [
            'label'     => __('Alineación', 'homlity-real-estate'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'left'   => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'),    'icon' => 'eicon-text-align-center'],
                'right'  => ['title' => __('Derecha', 'homlity-real-estate'),   'icon' => 'eicon-text-align-right'],
            ],
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-answer-inner' => 'text-align: {{VALUE}};'],
        ]);

        $this->add_control('answer_link_color', [
            'label'     => __('Color de enlaces', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-answer-inner a' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('answer_link_hover_color', [
            'label'     => __('Color de enlaces (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .homlity-property-faq-answer-inner a:hover' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    private function resolve_post_id(array $settings): int
    {
        if (($settings['use_current'] ?? 'yes') === 'yes') {
            $id = (int) get_queried_object_id();
            if ($id <= 0) {
                $id = (int) get_the_ID();
            }
            return $id > 0 ? $id : 0;
        }
        return max(0, (int) ($settings['manual_post_id'] ?? 0));
    }

    private function is_editor(): bool
    {
        return isset(\Elementor\Plugin::$instance) && \Elementor\Plugin::$instance->editor->is_edit_mode();
    }

    private function detect_page_context(): ?string
    {
        if ($this->is_editor()) {
            return null;
        }
        if (is_front_page()) {
            return 'home';
        }
        if (is_singular(apply_filters('homlity_faq_property_post_type', 'property'))) {
            return 'property';
        }
        if (is_tax()) {
            return 'zone';
        }
        return null;
    }

    private function render_icon(array $icon, array $attrs = []): void
    {
        if (!empty($icon['value']) && class_exists('\Elementor\Icons_Manager')) {
            \Elementor\Icons_Manager::render_icon($icon, $attrs);
        }
    }

    protected function render(): void
    {
        if (class_exists('\Elementor\Icons_Manager')) {
            \Elementor\Icons_Manager::enqueue_shim();
        }

        wp_enqueue_style(
            'homlity-property-faq',
            HOMLITY_PLUGIN_URL . 'assets/css/property-faq.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );
        wp_enqueue_script(
            'homlity-property-faq',
            HOMLITY_PLUGIN_URL . 'assets/js/property-faq.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        $settings = $this->get_settings_for_display();
        $post_id  = $this->resolve_post_id($settings);

        $generator = new Homlity_Property_FAQ_Generator($post_id);

        $auto_faqs = [];
        if (($settings['enable_auto_faqs'] ?? 'yes') === 'yes') {
            $auto_faqs = $generator->generate_auto_faqs($settings);
            $limit     = (int) ($settings['auto_limit'] ?? 0);
            if ($limit > 0) {
                $auto_faqs = array_slice($auto_faqs, 0, $limit);
            }
        }

        $manual_faqs = $generator->get_manual_faqs($settings['manual_items'] ?? []);
        $all_faqs    = $generator->combine_faqs($auto_faqs, $manual_faqs);

        if (($settings['include_global_faqs'] ?? '') === 'yes' && function_exists('homlity_get_global_faqs')) {
            $ctx_setting = $settings['global_faqs_context'] ?? 'auto';
            $ctx         = $ctx_setting === 'auto'
                ? $this->detect_page_context()
                : ($ctx_setting === 'all' ? null : $ctx_setting);

            $global_faqs = [];
            foreach (homlity_get_global_faqs($ctx) as $gfaq) {
                $q = sanitize_text_field($gfaq['question'] ?? '');
                $a = wp_kses_post($gfaq['answer'] ?? '');
                if ($q === '' || $a === '') {
                    continue;
                }
                $global_faqs[] = [
                    'key'      => 'global_' . sanitize_key($q),
                    'question' => $q,
                    'answer'   => $a,
                ];
            }

            if (!empty($global_faqs)) {
                $all_faqs = ($settings['global_faqs_position'] ?? 'after') === 'before'
                    ? array_merge($global_faqs, $all_faqs)
                    : array_merge($all_faqs, $global_faqs);
                $all_faqs = $generator->deduplicate_faqs($all_faqs);
            }
        }

        $all_faqs = apply_filters('homlity_faq_final_questions', $all_faqs, $post_id, $settings);

        if ($this->is_editor() && empty($all_faqs) && !$post_id) {
            $all_faqs = $this->get_preview_placeholder();
        }

        $empty_message = apply_filters('homlity_faq_empty_message', sanitize_text_field($settings['empty_message'] ?? ''), $post_id);
        $show_empty    = ($settings['show_empty_message'] ?? 'yes') === 'yes';

        if (empty($all_faqs)) {
            if ($show_empty && $empty_message !== '') {
                echo '<div class="homlity-property-faq-widget"><p class="homlity-property-faq-empty">' . esc_html($empty_message) . '</p></div>';
            }
            return;
        }

        $layout         = sanitize_key($settings['layout'] ?? 'accordion');
        $first_open     = ($settings['first_open'] ?? 'yes') === 'yes' && $layout === 'accordion';
        $allow_multiple = ($settings['allow_multiple'] ?? '') === 'yes';
        $title_tag      = in_array($settings['title_tag'] ?? 'h2', ['h2', 'h3', 'h4', 'div'], true) ? $settings['title_tag'] : 'h2';
        $block_title    = sanitize_text_field($settings['block_title'] ?? '');
        $description    = wp_kses_post($settings['block_description'] ?? '');
        $icon_closed    = is_array($settings['icon_closed'] ?? null) ? $settings['icon_closed'] : [];
        $icon_open_cfg  = is_array($settings['icon_open'] ?? null) ? $settings['icon_open'] : [];
        $custom_id      = sanitize_html_class($settings['custom_id'] ?? '');
        $custom_class   = sanitize_html_class($settings['custom_class'] ?? '');
        $widget_uid     = 'hfaq-' . $this->get_id();

        $wrapper_id    = $custom_id ?: $widget_uid;
        $wrapper_class = 'homlity-property-faq-widget homlity-property-faq--' . $layout;
        if ($custom_class) {
            $wrapper_class .= ' ' . $custom_class;
        }

        $schema_enabled = apply_filters('homlity_faq_schema_enabled', ($settings['enable_schema'] ?? 'yes') === 'yes' && !$this->is_editor(), $post_id);

        $html = apply_filters('homlity_faq_render_html', '', $all_faqs, $settings, $this);
        if ($html !== '') {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }
        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>" id="<?php echo esc_attr($wrapper_id); ?>">

            <?php if ($block_title !== '' || $description !== ''): ?>
            <div class="homlity-property-faq-header">
                <?php if ($block_title !== ''): ?>
                    <<?php echo esc_attr($title_tag); ?> class="homlity-property-faq-title">
                        <?php echo esc_html($block_title); ?>
                    </<?php echo esc_attr($title_tag); ?>>
                <?php endif; ?>
                <?php if ($description !== ''): ?>
                    <div class="homlity-property-faq-description"><?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="homlity-property-faq-list"
                 data-layout="<?php echo esc_attr($layout); ?>"
                 data-first-open="<?php echo $first_open ? 'true' : 'false'; ?>"
                 data-allow-multiple="<?php echo $allow_multiple ? 'true' : 'false'; ?>"
            >
                <?php foreach ($all_faqs as $index => $faq): ?>
                    <?php
                    $faq_id   = esc_attr($widget_uid . '-answer-' . $index);
                    $btn_id   = esc_attr($widget_uid . '-btn-' . $index);
                    $is_open  = $layout !== 'accordion' || ($first_open && $index === 0);
                    ?>
                    <div class="homlity-property-faq-item<?php echo $is_open ? ' is-active' : ''; ?>">
                        <button
                            class="homlity-property-faq-question"
                            id="<?php echo esc_attr($btn_id); ?>"
                            aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
                            aria-controls="<?php echo esc_attr($faq_id); ?>"
                            type="button"
                        >
                            <span class="homlity-property-faq-question-text"><?php echo esc_html($faq['question']); ?></span>
                            <span class="homlity-property-faq-icon homlity-property-faq-icon--open" aria-hidden="true">
                                <?php $this->render_icon($icon_open_cfg ?: ['value' => 'fas fa-chevron-up', 'library' => 'fa-solid'], ['aria-hidden' => 'true']); ?>
                            </span>
                            <span class="homlity-property-faq-icon homlity-property-faq-icon--closed" aria-hidden="true">
                                <?php $this->render_icon($icon_closed ?: ['value' => 'fas fa-chevron-down', 'library' => 'fa-solid'], ['aria-hidden' => 'true']); ?>
                            </span>
                        </button>
                        <div
                            class="homlity-property-faq-answer"
                            id="<?php echo esc_attr($faq_id); ?>"
                            role="region"
                            aria-labelledby="<?php echo esc_attr($btn_id); ?>"
                            <?php echo $is_open ? '' : 'hidden'; ?>
                        >
                            <div class="homlity-property-faq-answer-inner">
                                <?php echo wp_kses_post($faq['answer']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php

        if ($schema_enabled && !empty($all_faqs)) {
            $this->schedule_schema($all_faqs, $post_id);
        }
    }

    private function schedule_schema(array $faqs, int $post_id): void
    {
        self::$schema_instance_counter++;
        $instance = self::$schema_instance_counter;
        $hook     = 'homlity_faq_schema_output_' . $instance;

        add_action('wp_footer', static function () use ($faqs, $post_id, $hook) {
            $url = get_permalink($post_id) ?: get_bloginfo('url');

            $entities = [];
            foreach ($faqs as $faq) {
                $q = sanitize_text_field($faq['question']);
                $a = wp_strip_all_tags(wp_kses_post($faq['answer']));
                if ($q === '' || $a === '') {
                    continue;
                }
                $entities[] = [
                    '@type'          => 'Question',
                    'name'           => $q,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => $a,
                    ],
                ];
            }

            if (empty($entities)) {
                return;
            }

            $schema = apply_filters('homlity_faq_schema_data', [
                '@context'    => 'https://schema.org',
                '@type'       => 'FAQPage',
                '@id'         => $url . '#homlity-faq',
                'mainEntity'  => $entities,
            ], $faqs, $post_id);

            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD output; wp_json_encode handles XSS escaping.
        }, 20);
    }

    private function get_preview_placeholder(): array
    {
        return [
            [
                'key'      => 'preview_price',
                'question' => __('¿Cuál es el precio de este inmueble?', 'homlity-real-estate'),
                'answer'   => __('El precio de este inmueble se mostrará aquí automáticamente.', 'homlity-real-estate'),
            ],
            [
                'key'      => 'preview_location',
                'question' => __('¿Dónde está ubicado este inmueble?', 'homlity-real-estate'),
                'answer'   => __('La ubicación del inmueble aparecerá aquí.', 'homlity-real-estate'),
            ],
            [
                'key'      => 'preview_contact',
                'question' => __('¿Cómo puedo contactar a la inmobiliaria?', 'homlity-real-estate'),
                'answer'   => __('Los canales de contacto se mostrarán en la ficha real del inmueble.', 'homlity-real-estate'),
            ],
        ];
    }
}
