<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyShareWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_share';
    }

    public function get_title(): string
    {
        return __('Compartir inmueble', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-share';
    }

    private function platformsConfig(): array
    {
        return [
            'whatsapp'  => ['label' => __('WhatsApp',      'homlity-real-estate'), 'icon' => 'fab fa-whatsapp',     'icon_library' => 'fa-brands'],
            'facebook'  => ['label' => __('Facebook',      'homlity-real-estate'), 'icon' => 'fab fa-facebook-f',   'icon_library' => 'fa-brands'],
            'x'         => ['label' => __('X',             'homlity-real-estate'), 'icon' => 'fab fa-x-twitter',    'icon_library' => 'fa-brands'],
            'linkedin'  => ['label' => __('LinkedIn',      'homlity-real-estate'), 'icon' => 'fab fa-linkedin-in',  'icon_library' => 'fa-brands'],
            'telegram'  => ['label' => __('Telegram',      'homlity-real-estate'), 'icon' => 'fab fa-telegram',     'icon_library' => 'fa-brands'],
            'pinterest' => ['label' => __('Pinterest',     'homlity-real-estate'), 'icon' => 'fab fa-pinterest-p',  'icon_library' => 'fa-brands'],
            'reddit'    => ['label' => __('Reddit',        'homlity-real-estate'), 'icon' => 'fab fa-reddit-alien', 'icon_library' => 'fa-brands'],
            'email'     => ['label' => __('Correo',        'homlity-real-estate'), 'icon' => 'fas fa-envelope',     'icon_library' => 'fa-solid'],
            'copy'      => ['label' => __('Copiar enlace', 'homlity-real-estate'), 'icon' => 'fas fa-link',         'icon_library' => 'fa-solid'],
        ];
    }

    protected function register_controls(): void
    {
        // ── Contenido ────────────────────────────────────────────────────────
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();

        $this->add_control('share_text', [
            'label'       => __('Sobrescribir mensajes globales', 'homlity-real-estate'),
            'type'        => Controls_Manager::TEXTAREA,
            'default'     => '',
            'description' => __('Déjalo vacío para usar Homlity → Configuración → Mensajes sociales. Variables: {summary}, {title}, {code}, {url}, {bedrooms}, {bathrooms}, {parking}, {area}, {price}.', 'homlity-real-estate'),
            'separator'   => 'before',
        ]);
        $this->add_control('heading_text', [
            'label' => __('Título del bloque', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Compartir en:', 'homlity-real-estate'),
        ]);
        $this->add_control('show_labels', [
            'label'     => __('Mostrar etiqueta', 'homlity-real-estate'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'separator' => 'before',
        ]);
        $this->add_control('show_label_toggle', [
            'label'       => __('Botón alternar etiquetas', 'homlity-real-estate'),
            'type'        => Controls_Manager::SWITCHER,
            'description' => __('Añade un botón en el frontend para que el visitante muestre u oculte las etiquetas.', 'homlity-real-estate'),
        ]);
        $this->add_control('label_toggle_hide_text', [
            'label'     => __('Texto "ocultar"', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Ocultar etiquetas', 'homlity-real-estate'),
            'condition' => ['show_label_toggle' => 'yes'],
        ]);
        $this->add_control('label_toggle_show_text', [
            'label'     => __('Texto "mostrar"', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Mostrar etiquetas', 'homlity-real-estate'),
            'condition' => ['show_label_toggle' => 'yes'],
        ]);

        foreach ($this->platformsConfig() as $key => $platform) {
            $this->add_control('show_' . $key, [
                'label'     => $platform['label'],
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'separator' => 'before',
            ]);
            $this->add_control('icon_' . $key, [
                'type'      => Controls_Manager::ICONS,
                'default'   => ['value' => $platform['icon'], 'library' => $platform['icon_library']],
                'condition' => ['show_' . $key => 'yes'],
            ]);
            $this->add_control('label_' . $key, [
                'label'     => __('Etiqueta', 'homlity-real-estate'),
                'type'      => Controls_Manager::TEXT,
                'default'   => $platform['label'],
                'condition' => ['show_' . $key => 'yes'],
            ]);
        }

        $this->end_controls_section();

        // ── Estilos ──────────────────────────────────────────────────────────
        $this->start_controls_section('style_share', [
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
            'options'        => ['1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6','7'=>'7','8'=>'8','9'=>'9'],
            'selectors'      => [
                '{{WRAPPER}} .property-share-widget__list' => 'display: grid; grid-template-columns: repeat({{VALUE}}, 1fr);',
            ],
        ]);
        $this->add_responsive_control('list_gap', [
            'label'      => __('Espacio entre ítems', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['unit' => 'px', 'size' => 8],
            'selectors'  => [
                '{{WRAPPER}} .property-share-widget__list' => 'gap: {{SIZE}}{{UNIT}};',
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
                '{{WRAPPER}} .property-share__link' => 'display: flex; flex-direction: {{VALUE}};',
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
                '{{WRAPPER}} .property-share__link' => 'align-items: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('icon_text_gap', [
            'label'      => __('Espacio ícono–texto', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'default'    => ['unit' => 'px', 'size' => 6],
            'selectors'  => [
                '{{WRAPPER}} .property-share__link' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('item_padding', [
            'label'      => __('Padding', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .property-share__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_control('item_bg', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__link' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_responsive_control('item_radius', [
            'label'      => __('Radio borde', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} .property-share__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_control('item_bg_hover', [
            'label'     => __('Fondo (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__link:hover' => 'background-color: {{VALUE}};'],
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
                '{{WRAPPER}} .property-share__icon'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .property-share__icon svg' => 'fill: {{VALUE}};',
            ],
        ]);
        $this->add_control('icon_color_hover', [
            'label'     => __('Color (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-share__link:hover .property-share__icon'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .property-share__link:hover .property-share__icon svg' => 'fill: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('icon_size', [
            'label'      => __('Tamaño', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 8, 'max' => 64]],
            'selectors'  => [
                '{{WRAPPER}} .property-share__icon'     => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .property-share__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        // — Texto —
        $this->add_control('label_heading', [
            'label'     => __('Texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['show_labels' => 'yes'],
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'      => 'label_typography',
            'selector'  => '{{WRAPPER}} .property-share__label',
            'condition' => ['show_labels' => 'yes'],
        ]);
        $this->add_control('label_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__label' => 'color: {{VALUE}};'],
            'condition' => ['show_labels' => 'yes'],
        ]);
        $this->add_control('label_color_hover', [
            'label'     => __('Color (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__link:hover .property-share__label' => 'color: {{VALUE}};'],
            'condition' => ['show_labels' => 'yes'],
        ]);

        // — Botón alternar etiquetas —
        $this->add_control('toggle_btn_heading', [
            'label'     => __('Botón alternar etiquetas', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['show_label_toggle' => 'yes'],
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'      => 'toggle_btn_typography',
            'selector'  => '{{WRAPPER}} .property-share__toggle-btn',
            'condition' => ['show_label_toggle' => 'yes'],
        ]);
        $this->add_control('toggle_btn_color', [
            'label'     => __('Color texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__toggle-btn' => 'color: {{VALUE}};'],
            'condition' => ['show_label_toggle' => 'yes'],
        ]);
        $this->add_control('toggle_btn_bg', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__toggle-btn' => 'background-color: {{VALUE}};'],
            'condition' => ['show_label_toggle' => 'yes'],
        ]);
        $this->add_control('toggle_btn_color_hover', [
            'label'     => __('Color texto (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__toggle-btn:hover' => 'color: {{VALUE}};'],
            'condition' => ['show_label_toggle' => 'yes'],
        ]);
        $this->add_control('toggle_btn_bg_hover', [
            'label'     => __('Fondo (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__toggle-btn:hover' => 'background-color: {{VALUE}};'],
            'condition' => ['show_label_toggle' => 'yes'],
        ]);
        $this->add_responsive_control('toggle_btn_padding', [
            'label'      => __('Padding', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => ['{{WRAPPER}} .property-share__toggle-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            'condition'  => ['show_label_toggle' => 'yes'],
        ]);
        $this->add_responsive_control('toggle_btn_radius', [
            'label'      => __('Radio borde', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} .property-share__toggle-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            'condition'  => ['show_label_toggle' => 'yes'],
        ]);

        // — Valor (URL compartida) —
        $this->add_control('value_heading', [
            'label'     => __('Valor', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'value_typography',
            'selector' => '{{WRAPPER}} .property-share__value',
        ]);
        $this->add_control('value_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__value' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('value_color_hover', [
            'label'     => __('Color (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__link:hover .property-share__value' => 'color: {{VALUE}};'],
        ]);

        // — Efectos —
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name'      => 'share_shadow',
            'separator' => 'before',
            'selector'  => '{{WRAPPER}} .property-share-widget, {{WRAPPER}} .property-share-widget__heading, {{WRAPPER}} .property-share__icon',
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        if (class_exists('\Elementor\Icons_Manager')) {
            \Elementor\Icons_Manager::enqueue_shim();
        }

        wp_enqueue_script(
            'homlity-real-estate-share',
            HOMLITY_PLUGIN_URL . 'assets/js/property-share.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        $settings = $this->get_settings_for_display();
        TemplateService::includeComponent('property-share.php', [
            'post_id'  => $this->current_property_id(),
            'settings' => $settings,
        ]);
    }
}
