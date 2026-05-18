<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyRelatedWidget extends BasePropertyWidget
{
    use PropertyCardStylesTrait;

    public function get_name(): string
    {
        return 'property_related';
    }

    public function get_title(): string
    {
        return __('Propiedades relacionadas', 'homlity-plugin');
    }

    public function get_icon(): string
    {
        return 'eicon-posts-carousel';
    }

    protected function register_controls(): void
    {
        // ── Presentación ─────────────────────────────────────────────────────
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-plugin')]);
        $this->register_property_control();

        $this->add_control('columns', [
            'label'   => __('Columnas en grilla', 'homlity-plugin'),
            'type'    => Controls_Manager::SELECT,
            'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
            'default' => '3',
            'selectors' => [
                '{{WRAPPER}} .property-related__grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
            ],
        ]);

        $this->add_control('posts_per_page', [
            'label'   => __('Cantidad de inmuebles', 'homlity-plugin'),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 1,
            'max'     => 12,
            'default' => 3,
        ]);

        $this->end_controls_section();

        // ── Contenido de la tarjeta (shared via trait) ────────────────────────
        $this->registerCardContentControls();

        // ── Estilos del contenedor ────────────────────────────────────────────
        $this->start_controls_section('style_related', [
            'label' => __('Contenedor', 'homlity-plugin'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_control('related_bg', [
            'label'     => __('Color fondo', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-related' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'related_border',
            'selector' => '{{WRAPPER}} .property-related',
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'related_shadow',
            'selector' => '{{WRAPPER}} .property-related',
        ]);
        $this->add_responsive_control('related_padding', [
            'label'      => __('Padding', 'homlity-plugin'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => ['{{WRAPPER}} .property-related' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('related_margin', [
            'label'      => __('Margen', 'homlity-plugin'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => ['{{WRAPPER}} .property-related' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('related_grid_gap', [
            'label'      => __('Espacio entre inmuebles', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'selectors'  => ['{{WRAPPER}} .property-related__grid' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'related_empty_typography',
            'selector' => '{{WRAPPER}} .property-related__empty',
        ]);
        $this->add_control('related_empty_color', [
            'label'     => __('Color mensaje vacío', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-related__empty' => 'color: {{VALUE}};'],
        ]);
        $this->add_responsive_control('related_empty_align', [
            'label'   => __('Alineación mensaje vacío', 'homlity-plugin'),
            'type'    => Controls_Manager::CHOOSE,
            'options' => [
                'left'   => ['title' => __('Izquierda', 'homlity-plugin'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-plugin'),    'icon' => 'eicon-text-align-center'],
                'right'  => ['title' => __('Derecha', 'homlity-plugin'),   'icon' => 'eicon-text-align-right'],
            ],
            'selectors' => ['{{WRAPPER}} .property-related__empty' => 'text-align: {{VALUE}};'],
        ]);
        $this->end_controls_section();

        // ── Estilos tarjeta (shared via trait) ────────────────────────────────
        $this->registerCardStyleControls();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $cardOptions = [
            'media_mode'          => sanitize_key((string) ($settings['card_media_mode']          ?? 'single')),
            'visual_preset'       => sanitize_key((string) ($settings['card_visual_preset']       ?? 'default')),
            'hover_effect'        => sanitize_key((string) ($settings['card_hover_effect']        ?? 'lift')),
            'show_title'          => ($settings['card_show_title']     ?? 'yes') === 'yes',
            'show_excerpt'        => ($settings['card_show_excerpt']   ?? 'yes') === 'yes',
            'show_operation'      => ($settings['card_show_operation'] ?? 'yes') === 'yes',
            'show_price'          => ($settings['card_show_price']     ?? 'yes') === 'yes',
            'show_features'       => ($settings['card_show_features']  ?? 'yes') === 'yes',
            'show_whatsapp'       => ($settings['card_show_whatsapp']  ?? 'yes') === 'yes',
            'whatsapp_label'      => sanitize_text_field((string) ($settings['card_whatsapp_label']         ?? '')),
            'whatsapp_show_icon'  => ($settings['card_whatsapp_show_icon']      ?? 'yes') === 'yes',
            'whatsapp_icon_position' => sanitize_key((string) ($settings['card_whatsapp_icon_position'] ?? 'left')),
            'whatsapp_icon'       => is_array($settings['card_whatsapp_icon'] ?? null) ? $settings['card_whatsapp_icon'] : [],
            'feature_area'        => ($settings['card_feature_area']         ?? 'yes') === 'yes',
            'feature_bedrooms'    => ($settings['card_feature_bedrooms']     ?? 'yes') === 'yes',
            'feature_bathrooms'   => ($settings['card_feature_bathrooms']    ?? 'yes') === 'yes',
            'feature_parking'     => ($settings['card_feature_parking']      ?? 'yes') === 'yes',
            'feature_area_lot'    => ($settings['card_feature_area_lot']     ?? 'yes') === 'yes',
            'feature_area_private'=> ($settings['card_feature_area_private'] ?? 'yes') === 'yes',
            'feature_area_built'  => ($settings['card_feature_area_built']   ?? 'yes') === 'yes',
            'feature_age'         => ($settings['card_feature_age']          ?? 'yes') === 'yes',
            'feature_condition'   => ($settings['card_feature_condition']    ?? 'yes') === 'yes',
            'feature_code'        => ($settings['card_feature_code']         ?? 'yes') === 'yes',
            'feature_icon_area'   => is_array($settings['card_feature_icon_area'] ?? null) ? $settings['card_feature_icon_area'] : [],
            'feature_icon_bedrooms' => is_array($settings['card_feature_icon_bedrooms'] ?? null) ? $settings['card_feature_icon_bedrooms'] : [],
            'feature_icon_bathrooms' => is_array($settings['card_feature_icon_bathrooms'] ?? null) ? $settings['card_feature_icon_bathrooms'] : [],
            'feature_icon_parking' => is_array($settings['card_feature_icon_parking'] ?? null) ? $settings['card_feature_icon_parking'] : [],
            'feature_icon_area_lot' => is_array($settings['card_feature_icon_area_lot'] ?? null) ? $settings['card_feature_icon_area_lot'] : [],
            'feature_icon_area_private' => is_array($settings['card_feature_icon_area_private'] ?? null) ? $settings['card_feature_icon_area_private'] : [],
            'feature_icon_area_built' => is_array($settings['card_feature_icon_area_built'] ?? null) ? $settings['card_feature_icon_area_built'] : [],
            'feature_icon_age' => is_array($settings['card_feature_icon_age'] ?? null) ? $settings['card_feature_icon_age'] : [],
            'feature_icon_condition' => is_array($settings['card_feature_icon_condition'] ?? null) ? $settings['card_feature_icon_condition'] : [],
            'feature_icon_code' => is_array($settings['card_feature_icon_code'] ?? null) ? $settings['card_feature_icon_code'] : [],
        ];

        TemplateService::includeComponent('property-related.php', [
            'post_id'        => $this->current_property_id(),
            'posts_per_page' => max(1, (int) ($settings['posts_per_page'] ?? 3)),
            'columns'        => max(1, (int) ($settings['columns'] ?? 3)),
            'card_options'   => $cardOptions,
        ]);
    }
}
