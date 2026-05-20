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

/**
 * Widget enfocado en pestañas multimedia del inmueble:
 * Fotos, Video, Fotos 360 y Recorrido 360.
 */
class PropertyMediaTabsWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_media_tabs';
    }

    public function get_title(): string
    {
        return __('Tabs multimedia inmueble', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-tabs';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();
        $this->add_control('initial_tab', [
            'label' => __('Pestaña inicial', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'photos',
            'options' => [
                'photos' => __('Fotos', 'homlity-real-estate'),
                'videos' => __('Video', 'homlity-real-estate'),
                'photos360' => __('Fotos 360°', 'homlity-real-estate'),
                'tours360' => __('Recorrido 360°', 'homlity-real-estate'),
            ],
            'description' => __('Si la pestaña elegida no tiene contenido, se mostrará la primera disponible.', 'homlity-real-estate'),
        ]);
        $this->add_control('photos_view_mode', [
            'label' => __('Modo fotos', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'swiper_lightbox',
            'options' => [
                'swiper_lightbox' => __('Slider Swiper + Lightbox', 'homlity-real-estate'),
                'swiper' => __('Solo slider Swiper', 'homlity-real-estate'),
                'lightbox' => __('Mosaico con Lightbox', 'homlity-real-estate'),
            ],
        ]);
        $this->add_control('slides_desktop', [
            'label' => __('Imágenes por vista (escritorio)', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1, 'max' => 6, 'step' => 1,
            'default' => 1,
            'condition' => ['photos_view_mode!' => 'lightbox'],
        ]);
        $this->add_control('slides_tablet', [
            'label' => __('Imágenes por vista (tablet)', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1, 'max' => 4, 'step' => 1,
            'default' => 1,
            'condition' => ['photos_view_mode!' => 'lightbox'],
        ]);
        $this->add_control('slides_mobile', [
            'label' => __('Imágenes por vista (móvil)', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1, 'max' => 2, 'step' => 1,
            'default' => 1,
            'condition' => ['photos_view_mode!' => 'lightbox'],
        ]);
        $this->add_control('thumbs_per_view', [
            'label' => __('Miniaturas visibles', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'min' => 2, 'max' => 12, 'step' => 1,
            'default' => 4,
            'description' => __('Cantidad de miniaturas visibles en la tira inferior de fotos.', 'homlity-real-estate'),
            'condition' => ['photos_view_mode' => ['swiper_lightbox', 'swiper']],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_tabs', [
            'label' => __('Estilos pestañas', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'tabs_typography',
            'selector' => '{{WRAPPER}} .property-gallery-tabs__btn',
        ]);
        $this->add_control('tabs_text_color', [
            'label' => __('Color texto', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery-tabs__btn' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('tabs_text_color_active', [
            'label' => __('Color texto activo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery-tabs__btn.is-active' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('tabs_bg_color', [
            'label' => __('Fondo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery-tabs__btn' => 'background: {{VALUE}};',
            ],
        ]);
        $this->add_control('tabs_bg_color_active', [
            'label' => __('Fondo activo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery-tabs__btn.is-active' => 'background: {{VALUE}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'tabs_border',
            'selector' => '{{WRAPPER}} .property-gallery-tabs__btn',
        ]);
        $this->add_responsive_control('tabs_radius', [
            'label' => __('Radio botones', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .property-gallery-tabs__btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('tabs_padding', [
            'label' => __('Padding botones', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-gallery-tabs__btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'tabs_shadow',
            'selector' => '{{WRAPPER}} .property-gallery-tabs__btn',
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_images', [
            'label' => __('Estilos imágenes', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_responsive_control('images_height', [
            'label' => __('Alto slider principal', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range' => [
                'px' => ['min' => 120, 'max' => 900],
                'vh' => ['min' => 20, 'max' => 100],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__slide img' => 'height: {{SIZE}}{{UNIT}}; object-fit: cover;',
                '{{WRAPPER}} .property-gallery-tabs__panel .property-gallery--light img' => 'height: {{SIZE}}{{UNIT}}; object-fit: cover;',
            ],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'images_border',
            'selector' => '{{WRAPPER}} .property-gallery-tabs__panel img',
        ]);
        $this->add_responsive_control('images_radius', [
            'label' => __('Radio imágenes', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .property-gallery-tabs__panel img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'images_shadow',
            'selector' => '{{WRAPPER}} .property-gallery-tabs__panel img',
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_arrows', [
            'label' => __('Estilos flechas', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_control('arrows_color', [
            'label' => __('Color flecha', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .swiper-button-prev, {{WRAPPER}} .swiper-button-next' => '--swiper-navigation-color: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('arrows_size', [
            'label' => __('Tamaño icono', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 10, 'max' => 60]],
            'selectors' => [
                '{{WRAPPER}} .swiper-button-prev, {{WRAPPER}} .swiper-button-next' => '--swiper-navigation-size: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_control('arrows_bg', [
            'label' => __('Fondo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .swiper-button-prev, {{WRAPPER}} .swiper-button-next' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('arrows_bg_hover', [
            'label' => __('Fondo (hover)', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .swiper-button-prev:hover, {{WRAPPER}} .swiper-button-next:hover' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('arrows_radius', [
            'label' => __('Radio', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .swiper-button-prev, {{WRAPPER}} .swiper-button-next' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('arrows_padding', [
            'label' => __('Padding', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors' => [
                '{{WRAPPER}} .swiper-button-prev, {{WRAPPER}} .swiper-button-next' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_thumbs', [
            'label' => __('Estilos miniaturas', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_responsive_control('thumbs_max_width', [
            'label' => __('Ancho máximo tira', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => [
                'px' => ['min' => 100, 'max' => 1600],
                '%'  => ['min' => 10,  'max' => 100],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs' => 'max-width: {{SIZE}}{{UNIT}}; margin-inline: auto;',
            ],
        ]);
        $this->add_responsive_control('thumbs_height', [
            'label' => __('Alto miniatura', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 40, 'max' => 200]],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .swiper-slide img' => 'height: {{SIZE}}{{UNIT}}; object-fit: cover;',
            ],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'thumbs_border',
            'selector' => '{{WRAPPER}} .property-gallery__thumbs .swiper-slide img',
        ]);
        $this->add_responsive_control('thumbs_radius', [
            'label' => __('Radio', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .swiper-slide img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_control('thumbs_opacity', [
            'label' => __('Opacidad inactiva', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'range' => ['px' => ['min' => 0.1, 'max' => 1, 'step' => 0.05]],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .swiper-slide img' => 'opacity: {{SIZE}};',
            ],
        ]);
        $this->add_control('thumbs_active_border_color', [
            'label' => __('Color borde activo', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .swiper-slide-thumb-active img' => 'border-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('thumbs_active_opacity', [
            'label' => __('Opacidad activa', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'range' => ['px' => ['min' => 0.1, 'max' => 1, 'step' => 0.05]],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .swiper-slide-thumb-active img' => 'opacity: {{SIZE}};',
            ],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_video', [
            'label' => __('Estilos video/360', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_responsive_control('iframe_height', [
            'label' => __('Alto iframe', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range' => [
                'px' => ['min' => 180, 'max' => 900],
                'vh' => ['min' => 20, 'max' => 100],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-gallery-tabs__panel iframe' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'iframe_border',
            'selector' => '{{WRAPPER}} .property-gallery-tabs__panel iframe',
        ]);
        $this->add_responsive_control('iframe_radius', [
            'label' => __('Radio iframe', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .property-gallery-tabs__panel iframe' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'iframe_shadow',
            'selector' => '{{WRAPPER}} .property-gallery-tabs__panel iframe',
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $photosMode = sanitize_key((string) ($settings['photos_view_mode'] ?? 'swiper_lightbox'));
        $photosMode = in_array($photosMode, ['swiper_lightbox', 'swiper', 'lightbox'], true) ? $photosMode : 'swiper_lightbox';
        $layout = $photosMode === 'lightbox' ? 'light_gallery' : 'slider_show';
        $photosEnableLightbox = $photosMode === 'lightbox' || $photosMode === 'swiper_lightbox';

        wp_enqueue_script(
            'homlity-real-estate-gallery-tabs',
            HOMLITY_PLUGIN_URL . 'assets/js/property-gallery-tabs.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        wp_enqueue_style(
            'homlity-real-estate-swiper',
            HOMLITY_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.css',
            [],
            '11.1.4'
        );
        wp_enqueue_script(
            'homlity-real-estate-swiper',
            HOMLITY_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.js',
            [],
            '11.1.4',
            true
        );
        wp_enqueue_script(
            'homlity-real-estate-gallery-swiper',
            HOMLITY_PLUGIN_URL . 'assets/js/property-gallery-swiper.js',
            ['homlity-real-estate-swiper'],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        TemplateService::includeComponent('property-gallery.php', [
            'post_id' => $this->current_property_id(),
            'preferred_first_tab' => sanitize_key((string) ($settings['initial_tab'] ?? 'photos')),
            'layout' => $layout,
            'photos_enable_lightbox' => $photosEnableLightbox,
            'slides_desktop'  => max(1, (int) ($settings['slides_desktop']  ?? 1)),
            'slides_tablet'   => max(1, (int) ($settings['slides_tablet']   ?? 1)),
            'slides_mobile'   => max(1, (int) ($settings['slides_mobile']   ?? 1)),
            'thumbs_per_view' => max(2, (int) ($settings['thumbs_per_view'] ?? 4)),
            'autoplay' => true,
            'loop' => true,
            'show_arrows' => true,
            'show_pagination' => true,
            'show_thumbs' => true,
            'speed' => 520,
            'style_preset' => 'minimal',
        ]);
    }
}
