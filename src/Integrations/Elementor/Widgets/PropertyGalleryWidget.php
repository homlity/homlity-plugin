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

class PropertyGalleryWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_gallery';
    }

    public function get_title(): string
    {
        return __('Galería de inmueble', 'homlity-plugin');
    }

    public function get_icon(): string
    {
        return 'eicon-gallery-grid';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-plugin')]);
        $this->register_property_control();
        $this->add_control('gallery_layout', [
            'label' => __('Modo de galería', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'default' => 'slider_show',
            'options' => [
                'slider_show' => __('Slider Show (Swiper)', 'homlity-plugin'),
                'light_gallery' => __('Light Gallery (detalle)', 'homlity-plugin'),
                'masonry' => __('Masonry (Swiper)', 'homlity-plugin'),
            ],
        ]);
        $this->add_control('slides_desktop', [
            'label' => __('Columnas desktop', 'homlity-plugin'),
            'type' => Controls_Manager::NUMBER,
            'default' => 3,
            'min' => 1,
            'max' => 6,
        ]);
        $this->add_control('slides_tablet', [
            'label' => __('Columnas tablet', 'homlity-plugin'),
            'type' => Controls_Manager::NUMBER,
            'default' => 2,
            'min' => 1,
            'max' => 4,
        ]);
        $this->add_control('slides_mobile', [
            'label' => __('Columnas móvil', 'homlity-plugin'),
            'type' => Controls_Manager::NUMBER,
            'default' => 1,
            'min' => 1,
            'max' => 2,
        ]);
        $this->add_control('autoplay', [
            'label' => __('Autoplay', 'homlity-plugin'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => [
                'gallery_layout' => ['slider_show', 'masonry'],
            ],
        ]);
        $this->add_control('loop', [
            'label' => __('Loop', 'homlity-plugin'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => [
                'gallery_layout' => ['slider_show', 'masonry'],
            ],
        ]);
        $this->add_control('show_arrows', [
            'label' => __('Mostrar flechas', 'homlity-plugin'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => [
                'gallery_layout' => 'slider_show',
            ],
        ]);
        $this->add_control('show_pagination', [
            'label' => __('Mostrar paginación', 'homlity-plugin'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => [
                'gallery_layout' => 'slider_show',
            ],
        ]);

        $this->add_control('style_preset', [
            'label' => __('Preset visual', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'default' => 'minimal',
            'options' => [
                'minimal' => __('Minimal', 'homlity-plugin'),
                'rounded' => __('Bordes redondeados', 'homlity-plugin'),
                'shadow_card' => __('Tarjeta con sombra', 'homlity-plugin'),
            ],
        ]);

        $this->add_control('speed', [
            'label' => __('Velocidad transición (ms)', 'homlity-plugin'),
            'type' => Controls_Manager::NUMBER,
            'default' => 520,
            'min' => 100,
            'max' => 3000,
            'condition' => [
                'gallery_layout' => ['slider_show', 'masonry'],
            ],
        ]);
        $this->add_control('show_thumbs', [
            'label' => __('Mostrar miniaturas', 'homlity-plugin'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => [
                'gallery_layout' => 'slider_show',
            ],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_thumbs', [
            'label' => __('Estilos miniaturas', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => [
                'gallery_layout' => 'slider_show',
            ],
        ]);
        $this->add_control('thumb_height', [
            'label' => __('Alto miniatura', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 40, 'max' => 220]],
            'default' => ['unit' => 'px', 'size' => 86],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .swiper-slide img' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_control('thumb_gap', [
            'label' => __('Separación miniaturas', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 10],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .swiper' => '--homlity-thumb-gap: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_control('thumb_radius', [
            'label' => __('Radio miniatura', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 12],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .swiper-slide' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_control('thumb_border_color', [
            'label' => __('Borde miniatura', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .swiper-slide' => 'border-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('thumb_active_border_color', [
            'label' => __('Borde miniatura activa', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .swiper-slide-thumb-active' => 'border-color: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('thumb_padding', [
            'label' => __('Padding miniaturas', 'homlity-plugin'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .swiper-slide' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'thumb_shadow',
            'label' => __('Sombra miniaturas', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-gallery__thumbs .swiper-slide',
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_main_gallery', [
            'label' => __('Estilos galería principal', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_responsive_control('gallery_margin', [
            'label' => __('Margen galería', 'homlity-plugin'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-gallery' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('gallery_padding', [
            'label' => __('Padding galería', 'homlity-plugin'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-gallery' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'gallery_border',
            'label' => __('Borde galería', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-gallery',
        ]);
        $this->add_responsive_control('gallery_radius', [
            'label' => __('Radio galería', 'homlity-plugin'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .property-gallery' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'gallery_shadow',
            'label' => __('Sombra galería', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-gallery',
        ]);
        $this->add_responsive_control('gallery_image_height', [
            'label' => __('Alto imagen principal', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range' => [
                'px' => ['min' => 120, 'max' => 900],
                'vh' => ['min' => 20, 'max' => 100],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-gallery--swiper .swiper-slide img' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'gallery_image_shadow',
            'label' => __('Sombra imágenes', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-gallery--swiper .swiper-slide, {{WRAPPER}} .property-gallery__item--light',
        ]);
        $this->add_responsive_control('slide_gap', [
            'label' => __('Espacio entre slides', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 12],
            'selectors' => [
                '{{WRAPPER}} .property-gallery--swiper .swiper' => '--homlity-gallery-gap: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_controls', [
            'label' => __('Estilos controles slider', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => [
                'gallery_layout' => 'slider_show',
            ],
        ]);
        $this->add_responsive_control('nav_size', [
            'label' => __('Tamaño flechas', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 16, 'max' => 64]],
            'selectors' => [
                '{{WRAPPER}} .property-gallery--swiper .swiper-button-next, {{WRAPPER}} .property-gallery--swiper .swiper-button-prev' => '--swiper-navigation-size: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_control('nav_color', [
            'label' => __('Color flechas', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery--swiper .swiper-button-next, {{WRAPPER}} .property-gallery--swiper .swiper-button-prev' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('pagination_color', [
            'label' => __('Color paginación activa', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery--swiper .swiper-pagination-bullet-active' => 'background: {{VALUE}};',
            ],
        ]);
        $this->add_control('pagination_color_inactive', [
            'label' => __('Color paginación inactiva', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery--swiper .swiper-pagination-bullet' => 'background: {{VALUE}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'slider_controls_shadow',
            'label' => __('Sombra controles', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-gallery--swiper .swiper-button-next, {{WRAPPER}} .property-gallery--swiper .swiper-button-prev',
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        wp_enqueue_script(
            'homlity-plugin-gallery-tabs',
            HOMLITY_PLUGIN_URL . 'assets/js/property-gallery-tabs.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        $layout = $settings['gallery_layout'] ?? 'slider_show';
        if (in_array($layout, ['slider_show', 'masonry'], true)) {
            wp_enqueue_style(
                'homlity-plugin-swiper',
                HOMLITY_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.css',
                [],
                '11.1.4'
            );
            wp_enqueue_script(
                'homlity-plugin-swiper',
                HOMLITY_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.js',
                [],
                '11.1.4',
                true
            );
            wp_enqueue_script(
                'homlity-plugin-gallery-swiper',
                HOMLITY_PLUGIN_URL . 'assets/js/property-gallery-swiper.js',
                ['homlity-plugin-swiper'],
                HOMLITY_PLUGIN_VERSION,
                true
            );
        }

        if ($layout === 'light_gallery') {
            wp_enqueue_style(
                'homlity-plugin-lightgallery',
                HOMLITY_PLUGIN_URL . 'assets/vendor/lightgallery/lightgallery-bundle.min.css',
                [],
                '2.7.2'
            );
            wp_enqueue_script(
                'homlity-plugin-lightgallery',
                HOMLITY_PLUGIN_URL . 'assets/vendor/lightgallery/lightgallery.min.js',
                [],
                '2.7.2',
                true
            );
            wp_enqueue_script(
                'homlity-plugin-front-gallery',
                HOMLITY_PLUGIN_URL . 'assets/js/front-gallery.js',
                ['homlity-plugin-lightgallery'],
                HOMLITY_PLUGIN_VERSION,
                true
            );
            wp_add_inline_script(
                'homlity-plugin-front-gallery',
                'window.homlityPluginFrontGallery = Object.assign({}, window.homlityPluginFrontGallery || {}, { mode: "light_gallery" });',
                'before'
            );
        }

        TemplateService::includeComponent('property-gallery.php', [
            'post_id' => $this->current_property_id(),
            'layout' => $layout,
            'slides_desktop' => max(1, (int) ($settings['slides_desktop'] ?? 3)),
            'slides_tablet' => max(1, (int) ($settings['slides_tablet'] ?? 2)),
            'slides_mobile' => max(1, (int) ($settings['slides_mobile'] ?? 1)),
            'autoplay' => ($settings['autoplay'] ?? 'yes') === 'yes',
            'loop' => ($settings['loop'] ?? 'yes') === 'yes',
            'show_arrows' => ($settings['show_arrows'] ?? 'yes') === 'yes',
            'show_pagination' => ($settings['show_pagination'] ?? 'yes') === 'yes',
            'show_thumbs' => ($settings['show_thumbs'] ?? 'yes') === 'yes',
            'speed' => max(100, (int) ($settings['speed'] ?? 520)),
            'style_preset' => sanitize_key((string) ($settings['style_preset'] ?? 'minimal')),
        ]);
    }
}
