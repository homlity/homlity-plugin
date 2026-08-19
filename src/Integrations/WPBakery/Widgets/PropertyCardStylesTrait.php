<?php

namespace Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets;

use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Controls_Manager;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Group_Control_Border;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Group_Control_Box_Shadow;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared WPBakery controls for widgets that render property cards.
 * Used by PropertyListingWidget and PropertyCardWidget.
 */
trait PropertyCardStylesTrait
{
    protected function registerCardContentControls(): void
    {
        $this->start_controls_section('card_content', ['label' => __('Contenido de la tarjeta', 'homlity-real-estate')]);

        $this->add_control('card_media_mode', [
            'label' => __('Galería de fotos', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'single' => __('Imagen principal', 'homlity-real-estate'),
                'slider' => __('Slider de fotos', 'homlity-real-estate'),
            ],
            'default' => 'single',
        ]);

        $this->add_control('card_visual_preset', [
            'label' => __('Preset visual tarjeta', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'default' => __('Clásico', 'homlity-real-estate'),
                'cover_overlay' => __('Portada con overlay', 'homlity-real-estate'),
                'minimal_light' => __('Minimal claro', 'homlity-real-estate'),
            ],
            'default' => 'default',
        ]);

        $this->add_control('card_hover_effect', [
            'label' => __('Efecto hover tarjeta', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'none' => __('Sin efecto', 'homlity-real-estate'),
                'lift' => __('Elevar', 'homlity-real-estate'),
                'zoom' => __('Zoom imagen', 'homlity-real-estate'),
                'glow' => __('Brillo/Sombra', 'homlity-real-estate'),
            ],
            'default' => 'lift',
        ]);

        foreach ([
            'card_show_title'     => __('Mostrar título', 'homlity-real-estate'),
            'card_show_excerpt'   => __('Mostrar descripción corta', 'homlity-real-estate'),
            'card_show_operation' => __('Mostrar gestión (venta/arriendo)', 'homlity-real-estate'),
            'card_show_price'     => __('Mostrar valor de gestión', 'homlity-real-estate'),
            'card_show_features'  => __('Mostrar características', 'homlity-real-estate'),
        ] as $key => $label) {
            $this->add_control($key, [
                'label'   => $label,
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]);
        }

        $this->add_control('card_link_new_tab', [
            'label'   => __('Abrir inmueble en nueva pestaña', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        foreach ([
            'card_feature_area'         => __('Área', 'homlity-real-estate'),
            'card_feature_bedrooms'     => __('Alcobas', 'homlity-real-estate'),
            'card_feature_bathrooms'    => __('Baños', 'homlity-real-estate'),
            'card_feature_parking'      => __('Garajes', 'homlity-real-estate'),
            'card_feature_area_lot'     => __('Área de lote', 'homlity-real-estate'),
            'card_feature_area_private' => __('Área privada', 'homlity-real-estate'),
            'card_feature_area_built'   => __('Área construida', 'homlity-real-estate'),
            'card_feature_age'          => __('Edad inmueble', 'homlity-real-estate'),
            'card_feature_condition'    => __('Estado inmueble', 'homlity-real-estate'),
            'card_feature_code'         => __('Código inmueble', 'homlity-real-estate'),
        ] as $key => $label) {
            $this->add_control($key, [
                'label'     => $label,
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'condition' => ['card_show_features' => 'yes'],
            ]);
        }

        $this->add_control('card_feature_icons_heading', [
            'label' => __('Íconos de características', 'homlity-real-estate'),
            'type' => Controls_Manager::HEADING,
            'condition' => ['card_show_features' => 'yes'],
        ]);

        $this->add_control('card_feature_icon_area', [
            'label' => __('Ícono Área', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-ruler-combined', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_area' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_bedrooms', [
            'label' => __('Ícono Alcobas', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-bed', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_bedrooms' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_bathrooms', [
            'label' => __('Ícono Baños', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-bath', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_bathrooms' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_parking', [
            'label' => __('Ícono Garajes', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-car', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_parking' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_area_lot', [
            'label' => __('Ícono Área lote', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-draw-polygon', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_area_lot' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_area_private', [
            'label' => __('Ícono Área privada', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-house', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_area_private' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_area_built', [
            'label' => __('Ícono Área construida', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-ruler', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_area_built' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_age', [
            'label' => __('Ícono Edad', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-clock', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_age' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_condition', [
            'label' => __('Ícono Estado', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-check-circle', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_condition' => 'yes'],
        ]);
        $this->add_control('card_feature_icon_code', [
            'label' => __('Ícono Código', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-hashtag', 'library' => 'fa-solid'],
            'condition' => ['card_show_features' => 'yes', 'card_feature_code' => 'yes'],
        ]);

        $this->end_controls_section();

        $this->registerCardWhatsappContentControls();
    }

    private function registerCardWhatsappContentControls(): void
    {
        $this->start_controls_section('card_whatsapp_content', [
            'label' => __('Botón WhatsApp', 'homlity-real-estate'),
        ]);

        $this->add_control('card_show_whatsapp', [
            'label'        => __('Mostrar botón de WhatsApp', 'homlity-real-estate'),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __('Sí', 'homlity-real-estate'),
            'label_off'    => __('No', 'homlity-real-estate'),
            'return_value' => 'yes',
            'default'      => 'yes',
        ]);

        $this->add_control('card_whatsapp_label', [
            'label'     => __('Texto del botón', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Hablar por WhatsApp', 'homlity-real-estate'),
            'condition' => ['card_show_whatsapp' => 'yes'],
        ]);
        $this->add_control('card_whatsapp_show_icon', [
            'label'        => __('Mostrar ícono de WhatsApp', 'homlity-real-estate'),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __('Sí', 'homlity-real-estate'),
            'label_off'    => __('No', 'homlity-real-estate'),
            'return_value' => 'yes',
            'default'      => 'yes',
            'condition'    => ['card_show_whatsapp' => 'yes'],
        ]);
        $this->add_control('card_whatsapp_icon_position', [
            'label'     => __('Posición del ícono', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'left',
            'options'   => [
                'left'  => __('Izquierda', 'homlity-real-estate'),
                'right' => __('Derecha', 'homlity-real-estate'),
            ],
            'condition' => [
                'card_show_whatsapp'      => 'yes',
                'card_whatsapp_show_icon' => 'yes',
            ],
        ]);
        $this->add_control('card_whatsapp_icon', [
            'label'     => __('Ícono', 'homlity-real-estate'),
            'type'      => Controls_Manager::ICONS,
            'default'   => ['value' => 'fab fa-whatsapp', 'library' => 'fa-brands'],
            'condition' => [
                'card_show_whatsapp'      => 'yes',
                'card_whatsapp_show_icon' => 'yes',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function registerCardStyleControls(): void
    {
        // ── Tarjeta ───────────────────────────────────────────────────────────
        $this->start_controls_section('style_card', [
            'label' => __('Tarjeta', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('card_bg_color', [
            'label'     => __('Fondo', 'homlity-real-estate'),
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
            'label'      => __('Radio de borde', 'homlity-real-estate'),
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
            'label'      => __('Padding interno', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .property-card__content'       => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                '{{WRAPPER}} .property-card-bs .card-body'  => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
            ],
        ]);

        $this->add_responsive_control('card_width', [
            'label'      => __('Ancho de la card', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'vw'],
            'range'      => [
                'px' => ['min' => 180, 'max' => 1400],
                '%'  => ['min' => 10, 'max' => 100],
                'vw' => ['min' => 10, 'max' => 100],
            ],
            'selectors'  => [
                '{{WRAPPER}} .property-listing:not(.property-listing--bootstrap) .property-card' => 'width: {{SIZE}}{{UNIT}}; max-width: 100%; margin-left: auto; margin-right: auto;',
                '{{WRAPPER}} .property-listing--bootstrap .property-listing__grid > [class*="col-"]' => 'width: {{SIZE}}{{UNIT}}; max-width: {{SIZE}}{{UNIT}}; flex: 0 0 {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .property-listing--bootstrap .property-card-bs' => 'width: 100%;',
            ],
        ]);

        $this->end_controls_section();

        // ── Imagen ────────────────────────────────────────────────────────────
        $this->start_controls_section('style_card_image', [
            'label' => __('Imagen', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('card_image_height', [
            'label'      => __('Altura', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 120, 'max' => 520]],
            'selectors'  => [
                '{{WRAPPER}} .property-card__gallery > img, {{WRAPPER}} .property-card__gallery-slider .swiper-slide img, {{WRAPPER}} .property-card-bs .card-img-top' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_image_width', [
            'label'      => __('Ancho', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['%', 'px', 'vw'],
            'range'      => [
                '%'  => ['min' => 10, 'max' => 100],
                'px' => ['min' => 80, 'max' => 1200],
                'vw' => ['min' => 10, 'max' => 100],
            ],
            'selectors'  => [
                '{{WRAPPER}} .property-card__gallery > img, {{WRAPPER}} .property-card__gallery-slider, {{WRAPPER}} .property-card-bs .card-img-top' => 'width: {{SIZE}}{{UNIT}}; max-width: 100%; margin-left: auto; margin-right: auto;',
            ],
        ]);

        $this->add_responsive_control('card_image_radius', [
            'label'      => __('Radio de borde imagen', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [
                '{{WRAPPER}} .property-card__gallery > img, {{WRAPPER}} .property-card__gallery-slider .swiper-slide img, {{WRAPPER}} .property-card-bs .card-img-top' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        // ── Título y Texto ────────────────────────────────────────────────────
        $this->start_controls_section('style_card_text', [
            'label' => __('Título y Texto', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_title_typography',
            'label'    => __('Tipografía título', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-card__title, {{WRAPPER}} .property-card-bs .card-title, {{WRAPPER}} .property-card__overlay-title',
        ]);

        $this->add_control('card_title_color', [
            'label'     => __('Color título', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__title, {{WRAPPER}} .property-card-bs .card-title, {{WRAPPER}} .property-card__overlay-title' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('card_title_margin', [
            'label'      => __('Margen título', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [
                '{{WRAPPER}} .property-card__title, {{WRAPPER}} .property-card-bs .card-title, {{WRAPPER}} .property-card__overlay-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('card_title_align', [
            'label'   => __('Alineación título', 'homlity-real-estate'),
            'type'    => Controls_Manager::CHOOSE,
            'options' => [
                'left'   => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'), 'icon' => 'eicon-text-align-center'],
                'right'  => ['title' => __('Derecha', 'homlity-real-estate'), 'icon' => 'eicon-text-align-right'],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-card__title, {{WRAPPER}} .property-card-bs .card-title, {{WRAPPER}} .property-card__overlay-title' => 'text-align: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_excerpt_typography',
            'label'    => __('Tipografía descripción', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-card__excerpt, {{WRAPPER}} .property-card-bs .property-card__excerpt, {{WRAPPER}} .property-card__overlay-location',
        ]);

        $this->add_control('card_excerpt_color', [
            'label'     => __('Color descripción', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__excerpt, {{WRAPPER}} .property-card-bs .property-card__excerpt, {{WRAPPER}} .property-card__overlay-location' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('card_excerpt_margin', [
            'label'      => __('Margen descripción', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [
                '{{WRAPPER}} .property-card__excerpt, {{WRAPPER}} .property-card-bs .property-card__excerpt, {{WRAPPER}} .property-card__overlay-location' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('card_excerpt_align', [
            'label'   => __('Alineación descripción', 'homlity-real-estate'),
            'type'    => Controls_Manager::CHOOSE,
            'options' => [
                'left'   => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'), 'icon' => 'eicon-text-align-center'],
                'right'  => ['title' => __('Derecha', 'homlity-real-estate'), 'icon' => 'eicon-text-align-right'],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-card__excerpt, {{WRAPPER}} .property-card-bs .property-card__excerpt, {{WRAPPER}} .property-card__overlay-location' => 'text-align: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_operation_typography',
            'label'    => __('Tipografía gestión', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-card__operation, {{WRAPPER}} .property-card__overlay-operation',
        ]);

        $this->add_control('card_operation_color', [
            'label'     => __('Color gestión', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__operation, {{WRAPPER}} .property-card__overlay-operation' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('card_operation_margin', [
            'label'      => __('Margen gestión', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [
                '{{WRAPPER}} .property-card__operation, {{WRAPPER}} .property-card-bs .property-card__operation, {{WRAPPER}} .property-card__overlay-operation' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        // ── Precio y Características ──────────────────────────────────────────
        $this->start_controls_section('style_card_meta', [
            'label' => __('Precio y Características', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_price_typography',
            'label'    => __('Tipografía precio', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-card__price, {{WRAPPER}} .property-card__operation-price, {{WRAPPER}} .property-card-bs [itemprop="price"], {{WRAPPER}} .property-card__overlay-price',
        ]);

        $this->add_control('card_price_color', [
            'label'     => __('Color precio', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__price, {{WRAPPER}} .property-card__operation-price, {{WRAPPER}} .property-card-bs [itemprop="price"], {{WRAPPER}} .property-card__overlay-price' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_features_typography',
            'label'    => __('Tipografía características', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-card__features, {{WRAPPER}} .property-card-bs .property-card__features, {{WRAPPER}} .property-card__overlay-chip .property-card__feature-value',
        ]);

        $this->add_control('card_features_color', [
            'label'     => __('Color características', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__features, {{WRAPPER}} .property-card-bs .property-card__features, {{WRAPPER}} .property-card__feature-value' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('card_features_margin', [
            'label'      => __('Margen características', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [
                '{{WRAPPER}} .property-card__features, {{WRAPPER}} .property-card-bs .property-card__features, {{WRAPPER}} .property-card__overlay-features' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('card_features_bg_color', [
            'label'     => __('Fondo características', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__feature-item, {{WRAPPER}} .property-card-bs .property-card__feature-item, {{WRAPPER}} .property-card__overlay-chip' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('card_features_icon_heading', [
            'label' => __('Íconos de características', 'homlity-real-estate'),
            'type'  => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('card_features_icon_color', [
            'label'     => __('Color ícono', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__feature-icon, {{WRAPPER}} .property-card--preset-cover-overlay .property-card__overlay-chip .property-card__feature-icon' => 'color: {{VALUE}};',
                '{{WRAPPER}} .property-card__feature-icon svg, {{WRAPPER}} .property-card__feature-icon svg path, {{WRAPPER}} .property-card--preset-cover-overlay .property-card__overlay-chip .property-card__feature-icon svg, {{WRAPPER}} .property-card--preset-cover-overlay .property-card__overlay-chip .property-card__feature-icon svg path' => 'fill: {{VALUE}}; color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('card_features_icon_size', [
            'label'      => __('Tamaño ícono', 'homlity-real-estate'),
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
            'label'      => __('Caja del ícono', 'homlity-real-estate'),
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
            'label'      => __('Radio caja ícono', 'homlity-real-estate'),
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
            'label'     => __('Fondo caja ícono', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__feature-icon' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('card_features_icon_gap', [
            'label'      => __('Espacio ícono/texto', 'homlity-real-estate'),
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
            'label' => __('Badges', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('card_badges_featured_heading', [
            'label' => __('Badge Destacado', 'homlity-real-estate'),
            'type'  => Controls_Manager::HEADING,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_featured_badge_typography',
            'selector' => '{{WRAPPER}} .property-card__featured-badge',
        ]);

        $this->add_control('card_featured_badge_text_color', [
            'label'     => __('Color texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__featured-badge' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('card_featured_badge_bg_color', [
            'label'     => __('Color fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__featured-badge' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('card_featured_badge_border_color', [
            'label'     => __('Color borde', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__featured-badge' => 'border-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('card_featured_badge_padding', [
            'label'      => __('Padding', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .property-card__featured-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_featured_badge_radius', [
            'label'      => __('Radio borde', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .property-card__featured-badge' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_featured_badge_top', [
            'label'      => __('Posición superior', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => ['{{WRAPPER}} .property-card__featured-badge' => 'top: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_featured_badge_right', [
            'label'      => __('Posición derecha', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => ['{{WRAPPER}} .property-card__featured-badge' => 'right: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('card_badges_tags_heading', [
            'label' => __('Badges de etiquetas', 'homlity-real-estate'),
            'type'  => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_tag_badge_typography',
            'selector' => '{{WRAPPER}} .property-card__media-tag',
        ]);

        $this->add_control('card_tag_badge_text_color', [
            'label'     => __('Color texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__media-tag' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('card_tag_badge_bg_color', [
            'label'     => __('Color fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__media-tag' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('card_tag_badge_padding', [
            'label'      => __('Padding', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .property-card__media-tag' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_tag_badge_radius', [
            'label'      => __('Radio borde', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .property-card__media-tag' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();

        // ── Botón WhatsApp ────────────────────────────────────────────────────
        $this->start_controls_section('style_card_whatsapp', [
            'label' => __('Botón WhatsApp', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_whatsapp_typography',
            'selector' => '{{WRAPPER}} .property-card__whatsapp',
        ]);
        $this->add_responsive_control('card_whatsapp_padding', [
            'label'      => __('Padding botón', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .property-card__whatsapp' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('card_whatsapp_margin', [
            'label'      => __('Margen botón', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .property-card__whatsapp' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_control('card_whatsapp_width', [
            'label'               => __('Ancho botón', 'homlity-real-estate'),
            'type'                => Controls_Manager::SELECT,
            'default'             => 'auto',
            'options'             => [
                'auto' => __('Ajustado al contenido', 'homlity-real-estate'),
                'full' => __('Ancho completo', 'homlity-real-estate'),
            ],
            'selectors'           => ['{{WRAPPER}} .property-card__whatsapp' => 'width: {{VALUE}};'],
            'selectors_dictionary' => ['auto' => 'auto', 'full' => '100%'],
        ]);
        $this->add_control('card_whatsapp_text_align', [
            'label'     => __('Alineación del texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'left'   => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centro', 'homlity-real-estate'),    'icon' => 'eicon-text-align-center'],
                'right'  => ['title' => __('Derecha', 'homlity-real-estate'),   'icon' => 'eicon-text-align-right'],
            ],
            'default'   => 'center',
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp' => 'text-align: {{VALUE}};'],
        ]);
        $this->add_control('card_whatsapp_justify', [
            'label'     => __('Alineación interna', 'homlity-real-estate'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'flex-start'    => ['title' => __('Izquierda', 'homlity-real-estate'), 'icon' => 'eicon-h-align-left'],
                'center'        => ['title' => __('Centro', 'homlity-real-estate'),    'icon' => 'eicon-h-align-center'],
                'flex-end'      => ['title' => __('Derecha', 'homlity-real-estate'),   'icon' => 'eicon-h-align-right'],
                'space-between' => ['title' => __('Separado', 'homlity-real-estate'),  'icon' => 'eicon-justify-space-between-h'],
            ],
            'default'   => 'center',
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp' => 'display:flex; justify-content: {{VALUE}};'],
        ]);
        $this->add_control('card_whatsapp_align_items', [
            'label'     => __('Alineación vertical', 'homlity-real-estate'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'flex-start' => ['title' => __('Arriba', 'homlity-real-estate'),  'icon' => 'eicon-v-align-top'],
                'center'     => ['title' => __('Centro', 'homlity-real-estate'),  'icon' => 'eicon-v-align-middle'],
                'flex-end'   => ['title' => __('Abajo', 'homlity-real-estate'),   'icon' => 'eicon-v-align-bottom'],
            ],
            'default'   => 'center',
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp' => 'align-items: {{VALUE}};'],
        ]);
        $this->add_control('card_whatsapp_gap', [
            'label'      => __('Espacio ícono/texto', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 24]],
            'selectors'  => ['{{WRAPPER}} .property-card__whatsapp' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_control('card_whatsapp_icon_size', [
            'label'      => __('Tamaño ícono', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 10, 'max' => 36]],
            'selectors'  => [
                '{{WRAPPER}} .property-card__whatsapp .property-card__whatsapp-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .property-card__whatsapp .property-card__whatsapp-icon i' => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .property-card__whatsapp .property-card__whatsapp-icon .homlity-divi-icon' => 'font-size: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_control('card_whatsapp_icon_color', [
            'label'     => __('Color ícono', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__whatsapp .property-card__whatsapp-icon, {{WRAPPER}} .property-card__whatsapp .property-card__whatsapp-icon i' => 'color: {{VALUE}} !important;',
                '{{WRAPPER}} .property-card__whatsapp .property-card__whatsapp-icon svg, {{WRAPPER}} .property-card__whatsapp .property-card__whatsapp-icon svg path' => 'color: {{VALUE}} !important; fill: {{VALUE}} !important;',
            ],
        ]);

        $this->start_controls_tabs('card_whatsapp_states');

        $this->start_controls_tab('card_whatsapp_state_normal', ['label' => __('Normal', 'homlity-real-estate')]);
        $this->add_control('card_whatsapp_text_color', [
            'label'     => __('Color texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp, {{WRAPPER}} .property-card__whatsapp:visited' => 'color: {{VALUE}} !important;'],
        ]);
        $this->add_control('card_whatsapp_bg_color', [
            'label'     => __('Color fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp' => '--bs-btn-bg: {{VALUE}}; --bs-btn-border-color: {{VALUE}}; background: {{VALUE}} !important; background-color: {{VALUE}} !important;'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('card_whatsapp_state_hover', ['label' => __('Hover', 'homlity-real-estate')]);
        $this->add_control('card_whatsapp_text_color_hover', [
            'label'     => __('Color texto (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp:hover, {{WRAPPER}} .property-card__whatsapp:focus' => 'color: {{VALUE}} !important;'],
        ]);
        $this->add_control('card_whatsapp_bg_color_hover', [
            'label'     => __('Color fondo (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__whatsapp' => '--bs-btn-hover-bg: {{VALUE}}; --bs-btn-hover-border-color: {{VALUE}};',
                '{{WRAPPER}} .property-card__whatsapp:hover, {{WRAPPER}} .property-card__whatsapp:focus' => 'background: {{VALUE}} !important; background-color: {{VALUE}} !important;',
            ],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('card_whatsapp_state_active', ['label' => __('Activo', 'homlity-real-estate')]);
        $this->add_control('card_whatsapp_text_color_active', [
            'label'     => __('Color texto (activo)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-card__whatsapp:active' => 'color: {{VALUE}} !important;'],
        ]);
        $this->add_control('card_whatsapp_bg_color_active', [
            'label'     => __('Color fondo (activo)', 'homlity-real-estate'),
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
            'label'      => __('Radio de borde', 'homlity-real-estate'),
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
