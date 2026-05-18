<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared Elementor controls for widgets that render property cards.
 * Used by PropertyListingWidget and PropertyRelatedWidget.
 */
trait PropertyCardStylesTrait
{
    protected function registerCardContentControls(): void
    {
        $this->start_controls_section('card_content', ['label' => __('Contenido de la tarjeta', 'homlity-plugin')]);

        $this->add_control('card_media_mode', [
            'label' => __('Galería de fotos', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'single' => __('Imagen principal', 'homlity-plugin'),
                'slider' => __('Slider de fotos', 'homlity-plugin'),
            ],
            'default' => 'single',
        ]);

        $this->add_control('card_visual_preset', [
            'label' => __('Preset visual tarjeta', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'default' => __('Clásico', 'homlity-plugin'),
                'cover_overlay' => __('Portada con overlay', 'homlity-plugin'),
                'minimal_light' => __('Minimal claro', 'homlity-plugin'),
            ],
            'default' => 'default',
        ]);

        $this->add_control('card_hover_effect', [
            'label' => __('Efecto hover tarjeta', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'none' => __('Sin efecto', 'homlity-plugin'),
                'lift' => __('Elevar', 'homlity-plugin'),
                'zoom' => __('Zoom imagen', 'homlity-plugin'),
                'glow' => __('Brillo/Sombra', 'homlity-plugin'),
            ],
            'default' => 'lift',
        ]);

        foreach ([
            'card_show_title'     => __('Mostrar título', 'homlity-plugin'),
            'card_show_excerpt'   => __('Mostrar descripción corta', 'homlity-plugin'),
            'card_show_operation' => __('Mostrar gestión (venta/arriendo)', 'homlity-plugin'),
            'card_show_price'     => __('Mostrar valor de gestión', 'homlity-plugin'),
            'card_show_features'  => __('Mostrar características', 'homlity-plugin'),
            'card_show_whatsapp'  => __('Mostrar botón WhatsApp asesor', 'homlity-plugin'),
        ] as $key => $label) {
            $this->add_control($key, [
                'label'   => $label,
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]);
        }

        $this->add_control('card_whatsapp_label', [
            'label'     => __('Texto botón WhatsApp', 'homlity-plugin'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Hablar por WhatsApp', 'homlity-plugin'),
            'condition' => ['card_show_whatsapp' => 'yes'],
        ]);
        $this->add_control('card_whatsapp_show_icon', [
            'label'     => __('Mostrar ícono WhatsApp', 'homlity-plugin'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'condition' => ['card_show_whatsapp' => 'yes'],
        ]);
        $this->add_control('card_whatsapp_icon_position', [
            'label'     => __('Posición del ícono', 'homlity-plugin'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'left',
            'options'   => [
                'left'  => __('Izquierda', 'homlity-plugin'),
                'right' => __('Derecha', 'homlity-plugin'),
            ],
            'condition' => [
                'card_show_whatsapp'      => 'yes',
                'card_whatsapp_show_icon' => 'yes',
            ],
        ]);
        $this->add_control('card_whatsapp_icon', [
            'label'     => __('Ícono', 'homlity-plugin'),
            'type'      => Controls_Manager::ICONS,
            'default'   => ['value' => 'fab fa-whatsapp', 'library' => 'fa-brands'],
            'condition' => [
                'card_show_whatsapp'      => 'yes',
                'card_whatsapp_show_icon' => 'yes',
            ],
        ]);

        foreach ([
            'card_feature_area'         => __('Área', 'homlity-plugin'),
            'card_feature_bedrooms'     => __('Alcobas', 'homlity-plugin'),
            'card_feature_bathrooms'    => __('Baños', 'homlity-plugin'),
            'card_feature_parking'      => __('Garajes', 'homlity-plugin'),
            'card_feature_area_lot'     => __('Área de lote', 'homlity-plugin'),
            'card_feature_area_private' => __('Área privada', 'homlity-plugin'),
            'card_feature_area_built'   => __('Área construida', 'homlity-plugin'),
            'card_feature_age'          => __('Edad inmueble', 'homlity-plugin'),
            'card_feature_condition'    => __('Estado inmueble', 'homlity-plugin'),
            'card_feature_code'         => __('Código inmueble', 'homlity-plugin'),
        ] as $key => $label) {
            $this->add_control($key, [
                'label'     => $label,
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'condition' => ['card_show_features' => 'yes'],
            ]);
        }

        $this->add_control('card_feature_icons_heading', [
            'label' => __('Íconos de características', 'homlity-plugin'),
            'type' => Controls_Manager::HEADING,
            'condition' => ['card_show_features' => 'yes'],
        ]);

        $this->add_control('card_feature_icon_area', [
            'label' => __('Ícono Área', 'homlity-plugin'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-ruler-combined', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_area' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_bedrooms', [
            'label' => __('Ícono Alcobas', 'homlity-plugin'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-bed', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_bedrooms' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_bathrooms', [
            'label' => __('Ícono Baños', 'homlity-plugin'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-bath', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_bathrooms' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_parking', [
            'label' => __('Ícono Garajes', 'homlity-plugin'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-car', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_parking' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_area_lot', [
            'label' => __('Ícono Área lote', 'homlity-plugin'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-draw-polygon', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_area_lot' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_area_private', [
            'label' => __('Ícono Área privada', 'homlity-plugin'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-house', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_area_private' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_area_built', [
            'label' => __('Ícono Área construida', 'homlity-plugin'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-ruler', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_area_built' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_age', [
            'label' => __('Ícono Edad', 'homlity-plugin'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-clock', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_age' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_condition', [
            'label' => __('Ícono Estado', 'homlity-plugin'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-circle-check', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_condition' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_code', [
            'label' => __('Ícono Código', 'homlity-plugin'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-hashtag', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_code' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    protected function registerCardStyleControls(): void
    {
        // ── Tarjeta ───────────────────────────────────────────────────────────
        $this->start_controls_section('style_card', [
            'label' => __('Tarjeta', 'homlity-plugin'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('card_bg_color', [
            'label'     => __('Fondo', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card, {{WRAPPER}} .property-card-bs' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'card_border',
            'selector' => '{{WRAPPER}} .property-card, {{WRAPPER}} .property-card-bs',
        ]);

        $this->add_responsive_control('card_radius', [
            'label'      => __('Radio de borde', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [
                '{{WRAPPER}} .property-card, {{WRAPPER}} .property-card-bs' => 'border-radius: {{SIZE}}{{UNIT}}; overflow: hidden;',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'card_shadow',
            'selector' => '{{WRAPPER}} .property-card, {{WRAPPER}} .property-card-bs',
        ]);

        $this->add_responsive_control('card_padding', [
            'label'      => __('Padding interno', 'homlity-plugin'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .property-card__content'       => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                '{{WRAPPER}} .property-card-bs .card-body'  => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
            ],
        ]);

        $this->end_controls_section();

        // ── Imagen ────────────────────────────────────────────────────────────
        $this->start_controls_section('style_card_image', [
            'label' => __('Imagen', 'homlity-plugin'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('card_image_height', [
            'label'      => __('Altura', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 120, 'max' => 520]],
            'selectors'  => [
                '{{WRAPPER}} .property-card__gallery > img, {{WRAPPER}} .property-card__gallery-slider > img, {{WRAPPER}} .property-card-bs .card-img-top' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_image_radius', [
            'label'      => __('Radio de borde imagen', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [
                '{{WRAPPER}} .property-card__gallery > img, {{WRAPPER}} .property-card__gallery-slider > img, {{WRAPPER}} .property-card-bs .card-img-top' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        // ── Título y Texto ────────────────────────────────────────────────────
        $this->start_controls_section('style_card_text', [
            'label' => __('Título y Texto', 'homlity-plugin'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_title_typography',
            'label'    => __('Tipografía título', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-card__title, {{WRAPPER}} .property-card-bs .card-title, {{WRAPPER}} .property-card__overlay-title',
        ]);

        $this->add_control('card_title_color', [
            'label'     => __('Color título', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__title, {{WRAPPER}} .property-card-bs .card-title, {{WRAPPER}} .property-card__overlay-title' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_excerpt_typography',
            'label'    => __('Tipografía descripción', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-card__excerpt, {{WRAPPER}} .property-card-bs .property-card__excerpt, {{WRAPPER}} .property-card__overlay-location',
        ]);

        $this->add_control('card_excerpt_color', [
            'label'     => __('Color descripción', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__excerpt, {{WRAPPER}} .property-card-bs .property-card__excerpt, {{WRAPPER}} .property-card__overlay-location' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_operation_typography',
            'label'    => __('Tipografía gestión', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-card__operation, {{WRAPPER}} .property-card__overlay-operation',
        ]);

        $this->add_control('card_operation_color', [
            'label'     => __('Color gestión', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__operation, {{WRAPPER}} .property-card__overlay-operation' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        // ── Precio y Características ──────────────────────────────────────────
        $this->start_controls_section('style_card_meta', [
            'label' => __('Precio y Características', 'homlity-plugin'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_price_typography',
            'label'    => __('Tipografía precio', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-card__price, {{WRAPPER}} .property-card-bs [itemprop="price"], {{WRAPPER}} .property-card__overlay-price',
        ]);

        $this->add_control('card_price_color', [
            'label'     => __('Color precio', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__price, {{WRAPPER}} .property-card-bs [itemprop="price"], {{WRAPPER}} .property-card__overlay-price' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_features_typography',
            'label'    => __('Tipografía características', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-card__features, {{WRAPPER}} .property-card-bs .property-card__features, {{WRAPPER}} .property-card__overlay-chip .property-card__feature-value',
        ]);

        $this->add_control('card_features_color', [
            'label'     => __('Color características', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__features, {{WRAPPER}} .property-card-bs .property-card__features, {{WRAPPER}} .property-card__feature-value' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('card_features_bg_color', [
            'label'     => __('Fondo características', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__feature-item, {{WRAPPER}} .property-card-bs .property-card__feature-item, {{WRAPPER}} .property-card__overlay-chip' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('card_features_icon_heading', [
            'label' => __('Íconos de características', 'homlity-plugin'),
            'type'  => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('card_features_icon_color', [
            'label'     => __('Color ícono', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__feature-icon' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('card_features_icon_size', [
            'label'      => __('Tamaño ícono', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem'],
            'range'      => [
                'px' => ['min' => 8, 'max' => 48],
            ],
            'selectors'  => [
                '{{WRAPPER}} .property-card__feature-icon' => 'font-size: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_features_icon_box_size', [
            'label'      => __('Caja del ícono', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => [
                'px' => ['min' => 14, 'max' => 64],
            ],
            'selectors'  => [
                '{{WRAPPER}} .property-card__feature-icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; display: inline-flex; align-items: center; justify-content: center;',
            ],
        ]);

        $this->add_responsive_control('card_features_icon_radius', [
            'label'      => __('Radio caja ícono', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => [
                'px' => ['min' => 0, 'max' => 40],
                '%'  => ['min' => 0, 'max' => 50],
            ],
            'selectors'  => [
                '{{WRAPPER}} .property-card__feature-icon' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('card_features_icon_bg', [
            'label'     => __('Fondo caja ícono', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__feature-icon' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('card_features_icon_gap', [
            'label'      => __('Espacio ícono/texto', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => [
                'px' => ['min' => 0, 'max' => 24],
            ],
            'selectors'  => [
                '{{WRAPPER}} .property-card__feature-item' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_card_badges', [
            'label' => __('Badges', 'homlity-plugin'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('card_badges_featured_heading', [
            'label' => __('Badge Destacado', 'homlity-plugin'),
            'type'  => Controls_Manager::HEADING,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_featured_badge_typography',
            'selector' => '{{WRAPPER}} .property-card__featured-badge',
        ]);

        $this->add_control('card_featured_badge_text_color', [
            'label'     => __('Color texto', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__featured-badge' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('card_featured_badge_bg_color', [
            'label'     => __('Color fondo', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__featured-badge' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('card_featured_badge_border_color', [
            'label'     => __('Color borde', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__featured-badge' => 'border-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('card_featured_badge_padding', [
            'label'      => __('Padding', 'homlity-plugin'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .property-card__featured-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_featured_badge_radius', [
            'label'      => __('Radio borde', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .property-card__featured-badge' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_featured_badge_top', [
            'label'      => __('Posición superior', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => ['{{WRAPPER}} .property-card__featured-badge' => 'top: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_featured_badge_right', [
            'label'      => __('Posición derecha', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => ['{{WRAPPER}} .property-card__featured-badge' => 'right: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('card_badges_tags_heading', [
            'label' => __('Badges de etiquetas', 'homlity-plugin'),
            'type'  => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_tag_badge_typography',
            'selector' => '{{WRAPPER}} .property-card__media-tag',
        ]);

        $this->add_control('card_tag_badge_text_color', [
            'label'     => __('Color texto', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__media-tag' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('card_tag_badge_bg_color', [
            'label'     => __('Color fondo', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__media-tag' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('card_tag_badge_padding', [
            'label'      => __('Padding', 'homlity-plugin'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .property-card__media-tag' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_tag_badge_radius', [
            'label'      => __('Radio borde', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .property-card__media-tag' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();

        // ── Botón WhatsApp ────────────────────────────────────────────────────
        $this->start_controls_section('style_card_whatsapp', [
            'label' => __('Botón WhatsApp', 'homlity-plugin'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_whatsapp_typography',
            'selector' => '{{WRAPPER}} .property-card__whatsapp',
        ]);
        $this->add_responsive_control('card_whatsapp_padding', [
            'label'      => __('Padding botón', 'homlity-plugin'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .property-card__whatsapp' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('card_whatsapp_margin', [
            'label'      => __('Margen botón', 'homlity-plugin'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .property-card__whatsapp' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_control('card_whatsapp_width', [
            'label'               => __('Ancho botón', 'homlity-plugin'),
            'type'                => Controls_Manager::SELECT,
            'default'             => 'auto',
            'options'             => [
                'auto' => __('Ajustado al contenido', 'homlity-plugin'),
                'full' => __('Ancho completo', 'homlity-plugin'),
            ],
            'selectors'           => ['{{WRAPPER}} .property-card__whatsapp' => 'width: {{VALUE}};'],
            'selectors_dictionary' => ['auto' => 'auto', 'full' => '100%'],
        ]);
        $this->add_control('card_whatsapp_text_align', [
            'label'     => __('Alineación del texto', 'homlity-plugin'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'left'   => ['title' => __('Izquierda', 'homlity-plugin'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-plugin'),    'icon' => 'eicon-text-align-center'],
                'right'  => ['title' => __('Derecha', 'homlity-plugin'),   'icon' => 'eicon-text-align-right'],
            ],
            'default'   => 'center',
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp' => 'text-align: {{VALUE}};'],
        ]);
        $this->add_control('card_whatsapp_justify', [
            'label'     => __('Alineación interna', 'homlity-plugin'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'flex-start'    => ['title' => __('Izquierda', 'homlity-plugin'), 'icon' => 'eicon-h-align-left'],
                'center'        => ['title' => __('Centro', 'homlity-plugin'),    'icon' => 'eicon-h-align-center'],
                'flex-end'      => ['title' => __('Derecha', 'homlity-plugin'),   'icon' => 'eicon-h-align-right'],
                'space-between' => ['title' => __('Separado', 'homlity-plugin'),  'icon' => 'eicon-justify-space-between-h'],
            ],
            'default'   => 'center',
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp' => 'display:flex; align-items:center; justify-content: {{VALUE}};'],
        ]);
        $this->add_control('card_whatsapp_align_items', [
            'label'     => __('Alineación vertical', 'homlity-plugin'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'flex-start' => ['title' => __('Arriba', 'homlity-plugin'),  'icon' => 'eicon-v-align-top'],
                'center'     => ['title' => __('Centro', 'homlity-plugin'),  'icon' => 'eicon-v-align-middle'],
                'flex-end'   => ['title' => __('Abajo', 'homlity-plugin'),   'icon' => 'eicon-v-align-bottom'],
            ],
            'default'   => 'center',
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp' => 'display:flex; align-items: {{VALUE}};'],
        ]);
        $this->add_control('card_whatsapp_gap', [
            'label'      => __('Espacio ícono/texto', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 24]],
            'selectors'  => ['{{WRAPPER}} .property-card__whatsapp' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_control('card_whatsapp_icon_size', [
            'label'      => __('Tamaño ícono', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 10, 'max' => 36]],
            'selectors'  => [
                '{{WRAPPER}} .property-card__whatsapp .property-card__whatsapp-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .property-card__whatsapp .property-card__whatsapp-icon i' => 'font-size: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_control('card_whatsapp_icon_color', [
            'label'     => __('Color ícono', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp .property-card__whatsapp-icon' => 'color: {{VALUE}};'],
        ]);

        $this->start_controls_tabs('card_whatsapp_states');

        $this->start_controls_tab('card_whatsapp_state_normal', ['label' => __('Normal', 'homlity-plugin')]);
        $this->add_control('card_whatsapp_text_color', [
            'label'     => __('Color texto', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp, {{WRAPPER}} .property-card__whatsapp:visited' => 'color: {{VALUE}} !important;'],
        ]);
        $this->add_control('card_whatsapp_bg_color', [
            'label'     => __('Color fondo', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp' => '--bs-btn-bg: {{VALUE}}; --bs-btn-border-color: {{VALUE}}; background: {{VALUE}} !important; background-color: {{VALUE}} !important;'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('card_whatsapp_state_hover', ['label' => __('Hover', 'homlity-plugin')]);
        $this->add_control('card_whatsapp_text_color_hover', [
            'label'     => __('Color texto (hover)', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp:hover, {{WRAPPER}} .property-card__whatsapp:focus' => 'color: {{VALUE}} !important;'],
        ]);
        $this->add_control('card_whatsapp_bg_color_hover', [
            'label'     => __('Color fondo (hover)', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__whatsapp' => '--bs-btn-hover-bg: {{VALUE}}; --bs-btn-hover-border-color: {{VALUE}};',
                '{{WRAPPER}} .property-card__whatsapp:hover, {{WRAPPER}} .property-card__whatsapp:focus' => 'background: {{VALUE}} !important; background-color: {{VALUE}} !important;',
            ],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('card_whatsapp_state_active', ['label' => __('Activo', 'homlity-plugin')]);
        $this->add_control('card_whatsapp_text_color_active', [
            'label'     => __('Color texto (activo)', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp:active' => 'color: {{VALUE}} !important;'],
        ]);
        $this->add_control('card_whatsapp_bg_color_active', [
            'label'     => __('Color fondo (activo)', 'homlity-plugin'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__whatsapp' => '--bs-btn-active-bg: {{VALUE}}; --bs-btn-active-border-color: {{VALUE}};',
                '{{WRAPPER}} .property-card__whatsapp:active' => 'background: {{VALUE}} !important; background-color: {{VALUE}} !important;',
            ],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'card_whatsapp_border',
            'selector' => '{{WRAPPER}} .property-card__whatsapp',
        ]);
        $this->add_responsive_control('card_whatsapp_radius', [
            'label'      => __('Radio de borde', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 30]],
            'selectors'  => ['{{WRAPPER}} .property-card__whatsapp' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'card_whatsapp_shadow',
            'selector' => '{{WRAPPER}} .property-card__whatsapp',
        ]);

        $this->end_controls_section();
    }
}
