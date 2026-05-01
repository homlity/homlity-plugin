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
        return __('Compartir inmueble', 'homlity-plugin');
    }

    public function get_icon(): string
    {
        return 'eicon-share';
    }

    private function platformsConfig(): array
    {
        return [
            'whatsapp'  => ['label' => __('WhatsApp',      'homlity-plugin'), 'icon' => 'fab fa-whatsapp',     'icon_library' => 'fa-brands'],
            'facebook'  => ['label' => __('Facebook',      'homlity-plugin'), 'icon' => 'fab fa-facebook-f',   'icon_library' => 'fa-brands'],
            'x'         => ['label' => __('X',             'homlity-plugin'), 'icon' => 'fab fa-x-twitter',    'icon_library' => 'fa-brands'],
            'linkedin'  => ['label' => __('LinkedIn',      'homlity-plugin'), 'icon' => 'fab fa-linkedin-in',  'icon_library' => 'fa-brands'],
            'telegram'  => ['label' => __('Telegram',      'homlity-plugin'), 'icon' => 'fab fa-telegram',     'icon_library' => 'fa-brands'],
            'pinterest' => ['label' => __('Pinterest',     'homlity-plugin'), 'icon' => 'fab fa-pinterest-p',  'icon_library' => 'fa-brands'],
            'reddit'    => ['label' => __('Reddit',        'homlity-plugin'), 'icon' => 'fab fa-reddit-alien', 'icon_library' => 'fa-brands'],
            'email'     => ['label' => __('Correo',        'homlity-plugin'), 'icon' => 'fas fa-envelope',     'icon_library' => 'fa-solid'],
            'copy'      => ['label' => __('Copiar enlace', 'homlity-plugin'), 'icon' => 'fas fa-link',         'icon_library' => 'fa-solid'],
        ];
    }

    protected function register_controls(): void
    {
        // ── Contenido ────────────────────────────────────────────────────────
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-plugin')]);
        $this->register_property_control();

        $this->add_control('share_text', [
            'label'       => __('Texto a compartir', 'homlity-plugin'),
            'type'        => Controls_Manager::TEXTAREA,
            'default'     => __('Mira este inmueble: {title}', 'homlity-plugin'),
            'description' => __('Usa {title} para el nombre y {url} para el enlace.', 'homlity-plugin'),
            'separator'   => 'before',
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
                'label'     => __('Etiqueta', 'homlity-plugin'),
                'type'      => Controls_Manager::TEXT,
                'default'   => $platform['label'],
                'condition' => ['show_' . $key => 'yes'],
            ]);
        }

        $this->end_controls_section();

        // ── Estilos ──────────────────────────────────────────────────────────
        $this->start_controls_section('style_share', [
            'label' => __('Estilos', 'homlity-plugin'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        // — Layout de lista —
        $this->add_control('layout_heading', [
            'label' => __('Layout de lista', 'homlity-plugin'),
            'type'  => Controls_Manager::HEADING,
        ]);
        $this->add_responsive_control('list_columns', [
            'label'          => __('Columnas', 'homlity-plugin'),
            'type'           => Controls_Manager::SELECT,
            'default'        => '1',
            'tablet_default' => '1',
            'mobile_default' => '1',
            'options'        => ['1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6','7'=>'7','8'=>'8','9'=>'9'],
            'selectors'      => [
                '{{WRAPPER}} .property-share-widget' => 'display: grid; grid-template-columns: repeat({{VALUE}}, 1fr);',
            ],
        ]);
        $this->add_responsive_control('list_gap', [
            'label'      => __('Espacio entre ítems', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['unit' => 'px', 'size' => 8],
            'selectors'  => [
                '{{WRAPPER}} .property-share-widget' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        // — Ítem —
        $this->add_control('item_heading', [
            'label'     => __('Ítem', 'homlity-plugin'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_responsive_control('item_direction', [
            'label'   => __('Dirección', 'homlity-plugin'),
            'type'    => Controls_Manager::CHOOSE,
            'default' => 'row',
            'options' => [
                'row'    => ['title' => __('Horizontal', 'homlity-plugin'), 'icon' => 'eicon-arrow-right'],
                'column' => ['title' => __('Vertical',   'homlity-plugin'), 'icon' => 'eicon-arrow-down'],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-share__link' => 'display: flex; flex-direction: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('item_align_items', [
            'label'   => __('Alineación interna', 'homlity-plugin'),
            'type'    => Controls_Manager::CHOOSE,
            'default' => 'center',
            'options' => [
                'flex-start' => ['title' => __('Inicio', 'homlity-plugin'), 'icon' => 'eicon-v-align-top'],
                'center'     => ['title' => __('Centro', 'homlity-plugin'), 'icon' => 'eicon-v-align-middle'],
                'flex-end'   => ['title' => __('Fin',    'homlity-plugin'), 'icon' => 'eicon-v-align-bottom'],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-share__link' => 'align-items: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('icon_text_gap', [
            'label'      => __('Espacio ícono–texto', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'default'    => ['unit' => 'px', 'size' => 6],
            'selectors'  => [
                '{{WRAPPER}} .property-share__link' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('item_padding', [
            'label'      => __('Padding', 'homlity-plugin'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .property-share__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_control('item_bg', [
            'label'     => __('Fondo', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__link' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_responsive_control('item_radius', [
            'label'      => __('Radio borde', 'homlity-plugin'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} .property-share__link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_control('item_bg_hover', [
            'label'     => __('Fondo (hover)', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__link:hover' => 'background-color: {{VALUE}};'],
        ]);

        // — Ícono —
        $this->add_control('icon_heading', [
            'label'     => __('Ícono', 'homlity-plugin'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_control('icon_color', [
            'label'     => __('Color', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__icon' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('icon_color_hover', [
            'label'     => __('Color (hover)', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__link:hover .property-share__icon' => 'color: {{VALUE}};'],
        ]);
        $this->add_responsive_control('icon_size', [
            'label'      => __('Tamaño', 'homlity-plugin'),
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
            'label'     => __('Texto', 'homlity-plugin'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'label_typography',
            'selector' => '{{WRAPPER}} .property-share__label',
        ]);
        $this->add_control('label_color', [
            'label'     => __('Color', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__label' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('label_color_hover', [
            'label'     => __('Color (hover)', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__link:hover .property-share__label' => 'color: {{VALUE}};'],
        ]);

        // — Valor (URL compartida) —
        $this->add_control('value_heading', [
            'label'     => __('Valor', 'homlity-plugin'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'value_typography',
            'selector' => '{{WRAPPER}} .property-share__value',
        ]);
        $this->add_control('value_color', [
            'label'     => __('Color', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__value' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('value_color_hover', [
            'label'     => __('Color (hover)', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-share__link:hover .property-share__value' => 'color: {{VALUE}};'],
        ]);

        // — Efectos —
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name'      => 'share_shadow',
            'separator' => 'before',
            'selector'  => '{{WRAPPER}} .property-share-widget',
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        TemplateService::includeComponent('property-share.php', [
            'post_id'  => $this->current_property_id(),
            'settings' => $settings,
        ]);
    }
}
