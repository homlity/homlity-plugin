<?php

namespace Homlity\PluginInmobiliario\Integrations\Divi\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Controls_Manager;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Border;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Box_Shadow;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyAgentWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_agent';
    }

    public function get_title(): string
    {
        return __('Asesor del inmueble', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-user-circle-o';
    }

    protected function register_controls(): void
    {
        // ── Contenido ────────────────────────────────────────────────────────
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);

        $this->add_control('data_source', [
            'label'     => __('Fuente de datos', 'homlity-real-estate'),
            'type'      => Controls_Manager::CHOOSE,
            'default'   => 'dynamic',
            'options'   => [
                'dynamic' => ['title' => __('Del inmueble', 'homlity-real-estate'), 'icon' => 'eicon-post-info'],
                'static'  => ['title' => __('Datos fijos',  'homlity-real-estate'), 'icon' => 'eicon-edit'],
            ],
            'toggle' => false,
        ]);

        // — Selector de inmueble (modo dinámico) —
        $this->register_property_control();

        // — Datos fijos —
        $this->add_control('static_heading', [
            'label'     => __('Datos del asesor', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['data_source' => 'static'],
        ]);
        $this->add_control('static_photo', [
            'label'     => __('Foto', 'homlity-real-estate'),
            'type'      => Controls_Manager::MEDIA,
            'default'   => ['url' => ''],
            'condition' => ['data_source' => 'static'],
        ]);
        $this->add_control('static_name', [
            'label'     => __('Nombre', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => '',
            'condition' => ['data_source' => 'static'],
        ]);
        $this->add_control('static_role', [
            'label'     => __('Cargo', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => '',
            'condition' => ['data_source' => 'static'],
        ]);
        $this->add_control('static_phone', [
            'label'     => __('Teléfono', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => '',
            'input_type' => 'tel',
            'condition' => ['data_source' => 'static'],
        ]);
        $this->add_control('static_email', [
            'label'     => __('Correo electrónico', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => '',
            'input_type' => 'email',
            'condition' => ['data_source' => 'static'],
        ]);

        // — Call to action —
        $this->add_control('cta_heading', [
            'label'     => __('Call to action', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        // CTAs modo dinámico
        $this->add_control('show_cta_whatsapp', [
            'label'     => __('Botón WhatsApp', 'homlity-real-estate'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'condition' => ['data_source' => 'dynamic'],
        ]);
        $this->add_control('cta_whatsapp_label', [
            'label'     => __('Texto botón WhatsApp', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Contactar por WhatsApp', 'homlity-real-estate'),
            'condition' => ['data_source' => 'dynamic', 'show_cta_whatsapp' => 'yes'],
        ]);
        $this->add_control('show_cta_profile', [
            'label'     => __('Botón ver perfil', 'homlity-real-estate'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'condition' => ['data_source' => 'dynamic'],
        ]);
        $this->add_control('cta_profile_label', [
            'label'     => __('Texto botón perfil', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Ver perfil del asesor', 'homlity-real-estate'),
            'condition' => ['data_source' => 'dynamic', 'show_cta_profile' => 'yes'],
        ]);

        // CTAs modo estático — botón 1
        $this->add_control('cta1_show', [
            'label'     => __('Botón 1', 'homlity-real-estate'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'condition' => ['data_source' => 'static'],
        ]);
        $this->add_control('cta1_text', [
            'label'     => __('Texto botón 1', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Contactar', 'homlity-real-estate'),
            'condition' => ['data_source' => 'static', 'cta1_show' => 'yes'],
        ]);
        $this->add_control('cta1_url', [
            'label'     => __('Enlace botón 1', 'homlity-real-estate'),
            'type'      => Controls_Manager::URL,
            'default'   => ['url' => '', 'is_external' => false, 'nofollow' => false],
            'condition' => ['data_source' => 'static', 'cta1_show' => 'yes'],
        ]);

        // CTAs modo estático — botón 2
        $this->add_control('cta2_show', [
            'label'     => __('Botón 2', 'homlity-real-estate'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => '',
            'condition' => ['data_source' => 'static'],
        ]);
        $this->add_control('cta2_text', [
            'label'     => __('Texto botón 2', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => '',
            'condition' => ['data_source' => 'static', 'cta2_show' => 'yes'],
        ]);
        $this->add_control('cta2_url', [
            'label'     => __('Enlace botón 2', 'homlity-real-estate'),
            'type'      => Controls_Manager::URL,
            'default'   => ['url' => '', 'is_external' => false, 'nofollow' => false],
            'condition' => ['data_source' => 'static', 'cta2_show' => 'yes'],
        ]);

        $this->end_controls_section();

        // ── Estilos ──────────────────────────────────────────────────────────
        $this->start_controls_section('style_agent', [
            'label' => __('Estilos', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        // — Card —
        $this->add_control('card_heading', [
            'label' => __('Card', 'homlity-real-estate'),
            'type'  => Controls_Manager::HEADING,
        ]);
        $this->add_control('agent_bg', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-agent-block__card' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_responsive_control('agent_padding', [
            'label'      => __('Padding', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => ['{{WRAPPER}} .property-agent-block__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('agent_radius', [
            'label'      => __('Radio borde', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} .property-agent-block__card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'agent_border',
            'selector' => '{{WRAPPER}} .property-agent-block__card',
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'agent_shadow',
            'selector' => '{{WRAPPER}} .property-agent-block__card',
        ]);
        $this->add_responsive_control('info_items_gap', [
            'label'      => __('Espacio entre ítems', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 48]],
            'default'    => ['unit' => 'px', 'size' => 8],
            'selectors'  => [
                '{{WRAPPER}} .property-agent-block__info > p' => 'margin-bottom: {{SIZE}}{{UNIT}};',
            ],
        ]);

        // — Foto —
        $this->add_control('photo_heading', [
            'label'     => __('Foto', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_responsive_control('photo_size', [
            'label'      => __('Tamaño cuadrado', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 32, 'max' => 200]],
            'default'    => ['unit' => 'px', 'size' => 96],
            'selectors'  => [
                '{{WRAPPER}} .property-agent-block__avatar img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('photo_align', [
            'label'   => __('Alineación foto', 'homlity-real-estate'),
            'type'    => Controls_Manager::CHOOSE,
            'options' => [
                'left' => [
                    'title' => __('Izquierda', 'homlity-real-estate'),
                    'icon'  => 'eicon-text-align-left',
                ],
                'center' => [
                    'title' => __('Centro', 'homlity-real-estate'),
                    'icon'  => 'eicon-text-align-center',
                ],
                'right' => [
                    'title' => __('Derecha', 'homlity-real-estate'),
                    'icon'  => 'eicon-text-align-right',
                ],
            ],
            'default'   => 'left',
            'selectors' => [
                '{{WRAPPER}} .property-agent-block__avatar' => 'text-align: {{VALUE}};',
                '{{WRAPPER}} .property-agent-block__avatar img' => 'display: inline-block;',
            ],
        ]);
        $this->add_responsive_control('photo_radius', [
            'label'      => __('Radio borde', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default'    => ['top' => '50', 'right' => '50', 'bottom' => '50', 'left' => '50', 'unit' => '%', 'isLinked' => true],
            'selectors'  => ['{{WRAPPER}} .property-agent-block__avatar img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        // — Nombre —
        $this->add_control('name_heading', [
            'label'     => __('Nombre', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'name_typography',
            'selector' => '{{WRAPPER}} .property-agent-block__name',
        ]);
        $this->add_control('name_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-agent-block__name, {{WRAPPER}} .property-agent-block__name a' => 'color: {{VALUE}};'],
        ]);

        // — Cargo —
        $this->add_control('role_heading', [
            'label'     => __('Cargo', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'role_typography',
            'selector' => '{{WRAPPER}} .property-agent-block__role',
        ]);
        $this->add_control('role_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-agent-block__role' => 'color: {{VALUE}};'],
        ]);

        // — Contacto (teléfono y correo) —
        $this->add_control('contact_heading', [
            'label'     => __('Teléfono y correo', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'contact_typography',
            'selector' => '{{WRAPPER}} .property-agent-block__phone, {{WRAPPER}} .property-agent-block__email',
        ]);
        $this->add_control('contact_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-agent-block__phone, {{WRAPPER}} .property-agent-block__email, {{WRAPPER}} .property-agent-block__email a' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('contact_color_hover', [
            'label'     => __('Color enlace (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-agent-block__email a:hover' => 'color: {{VALUE}};'],
        ]);

        // — Icono teléfono —
        $this->add_control('icon_phone_heading', [
            'label'     => __('Icono teléfono', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_control('icon_phone', [
            'label'   => __('Icono', 'homlity-real-estate'),
            'type'    => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-phone', 'library' => 'fa-solid'],
        ]);
        $this->add_responsive_control('icon_phone_size', [
            'label'      => __('Tamaño', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 10, 'max' => 64]],
            'default'    => ['unit' => 'px', 'size' => 16],
            'selectors'  => [
                '{{WRAPPER}} .property-agent-block__icon--phone i'   => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .property-agent-block__icon--phone svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_control('icon_phone_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-agent-block__icon--phone i'   => 'color: {{VALUE}};',
                '{{WRAPPER}} .property-agent-block__icon--phone svg' => 'fill: {{VALUE}};',
            ],
        ]);

        // — Icono correo —
        $this->add_control('icon_email_heading', [
            'label'     => __('Icono correo', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_control('icon_email', [
            'label'   => __('Icono', 'homlity-real-estate'),
            'type'    => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-envelope', 'library' => 'fa-solid'],
        ]);
        $this->add_responsive_control('icon_email_size', [
            'label'      => __('Tamaño', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 10, 'max' => 64]],
            'default'    => ['unit' => 'px', 'size' => 16],
            'selectors'  => [
                '{{WRAPPER}} .property-agent-block__icon--email i'   => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .property-agent-block__icon--email svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_control('icon_email_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-agent-block__icon--email i'   => 'color: {{VALUE}};',
                '{{WRAPPER}} .property-agent-block__icon--email svg' => 'fill: {{VALUE}};',
            ],
        ]);

        // — Botones CTA —
        $this->add_control('btn_heading', [
            'label'     => __('Botones', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_responsive_control('btn_width', [
            'label'   => __('Ancho de los botones', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'auto',
            'options' => [
                'auto' => __('Ajustado al contenido', 'homlity-real-estate'),
                'full' => __('Ancho completo', 'homlity-real-estate'),
            ],
            'selectors' => [
                '{{WRAPPER}} .property-agent-block__actions' => 'flex-direction: {{VALUE}};',
            ],
            'selectors_dictionary' => [
                'auto' => 'row',
                'full' => 'column',
            ],
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'btn_typography',
            'selector' => '{{WRAPPER}} a.property-agent-block__cta',
        ]);
        $this->add_responsive_control('btn_padding', [
            'label'      => __('Padding', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} a.property-agent-block__cta' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('btn_radius', [
            'label'      => __('Radio borde', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} a.property-agent-block__cta' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_control('btn_bg', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} a.property-agent-block__cta' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_control('btn_color', [
            'label'     => __('Color texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} a.property-agent-block__cta' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('btn_bg_hover', [
            'label'     => __('Fondo (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} a.property-agent-block__cta:hover' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_control('btn_color_hover', [
            'label'     => __('Color texto (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} a.property-agent-block__cta:hover' => 'color: {{VALUE}};'],
        ]);
        $this->add_responsive_control('actions_gap', [
            'label'      => __('Espacio entre botones', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 48]],
            'default'    => ['unit' => 'px', 'size' => 10],
            'selectors'  => ['{{WRAPPER}} .property-agent-block__actions' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        TemplateService::includeComponent('property-agent-info.php', [
            'post_id'  => $this->current_property_id(),
            'settings' => $settings,
        ]);
    }
}
