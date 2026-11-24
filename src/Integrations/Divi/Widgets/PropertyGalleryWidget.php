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

class PropertyGalleryWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_gallery';
    }

    public function get_title(): string
    {
        return __('Galería de inmueble', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-gallery-grid';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();
        $this->add_control('gallery_layout', [
            'label' => __('Modo de galería', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'slider_show',
            'options' => [
                'slider_show' => __('Slider con miniaturas', 'homlity-real-estate'),
                'light_gallery' => __('Light Gallery (detalle)', 'homlity-real-estate'),
                'masonry' => __('Carrusel libre', 'homlity-real-estate'),
            ],
        ]);
        $this->add_control('slides_desktop', [
            'label' => __('Columnas desktop', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'default' => 3,
            'min' => 1,
            'max' => 6,
        ]);
        $this->add_control('slides_tablet', [
            'label' => __('Columnas tablet', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'default' => 2,
            'min' => 1,
            'max' => 4,
        ]);
        $this->add_control('slides_mobile', [
            'label' => __('Columnas móvil', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'default' => 1,
            'min' => 1,
            'max' => 2,
        ]);
        $this->add_control('autoplay', [
            'label' => __('Autoplay', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => [
                'gallery_layout' => ['slider_show', 'masonry'],
            ],
        ]);
        $this->add_control('loop', [
            'label' => __('Loop', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => [
                'gallery_layout' => ['slider_show', 'masonry'],
            ],
        ]);
        $this->add_control('show_arrows', [
            'label' => __('Mostrar flechas', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => [
                'gallery_layout' => 'slider_show',
            ],
        ]);
        $this->add_control('show_pagination', [
            'label' => __('Mostrar paginación', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => [
                'gallery_layout' => 'slider_show',
            ],
        ]);

        $this->add_control('style_preset', [
            'label' => __('Preset visual', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'minimal',
            'options' => [
                'minimal' => __('Minimal', 'homlity-real-estate'),
                'rounded' => __('Bordes redondeados', 'homlity-real-estate'),
                'shadow_card' => __('Tarjeta con sombra', 'homlity-real-estate'),
            ],
        ]);

        $this->add_control('speed', [
            'label' => __('Velocidad transición (ms)', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'default' => 520,
            'min' => 100,
            'max' => 3000,
            'condition' => [
                'gallery_layout' => ['slider_show', 'masonry'],
            ],
        ]);
        $this->add_control('show_thumbs', [
            'label' => __('Mostrar miniaturas', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => [
                'gallery_layout' => 'slider_show',
            ],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_thumbs', [
            'label' => __('Estilos miniaturas', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => [
                'gallery_layout' => 'slider_show',
            ],
        ]);
        $this->add_control('thumb_height', [
            'label' => __('Alto miniatura', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 40, 'max' => 220]],
            'default' => ['unit' => 'px', 'size' => 86],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .splide__slide img' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_control('thumb_gap', [
            'label' => __('Separación miniaturas', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 10],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs' => '--homlity-thumb-gap: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_control('thumb_radius', [
            'label' => __('Radio miniatura', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 12],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .splide__slide' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_control('thumb_border_color', [
            'label' => __('Borde miniatura', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .splide__slide' => 'border-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('thumb_active_border_color', [
            'label' => __('Borde miniatura activa', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .splide__slide.is-active' => 'border-color: {{VALUE}};',
            ],
        ]);
        $this->add_responsive_control('thumb_padding', [
            'label' => __('Padding miniaturas', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-gallery__thumbs .splide__slide' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'thumb_shadow',
            'label' => __('Sombra miniaturas', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-gallery__thumbs .splide__slide',
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_main_gallery', [
            'label' => __('Estilos galería principal', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_responsive_control('gallery_margin', [
            'label' => __('Margen galería', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-gallery' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('gallery_padding', [
            'label' => __('Padding galería', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-gallery' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'gallery_border',
            'label' => __('Borde galería', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-gallery',
        ]);
        $this->add_responsive_control('gallery_radius', [
            'label' => __('Radio galería', 'homlity-real-estate'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [
                '{{WRAPPER}} .property-gallery' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'gallery_shadow',
            'label' => __('Sombra galería', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-gallery',
        ]);
        $this->add_responsive_control('gallery_image_height', [
            'label' => __('Alto imagen principal', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range' => [
                'px' => ['min' => 120, 'max' => 900],
                'vh' => ['min' => 20, 'max' => 100],
            ],
            'selectors' => [
                '{{WRAPPER}} .property-gallery .splide__slide img' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'gallery_image_shadow',
            'label' => __('Sombra imágenes', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-gallery .splide__slide, {{WRAPPER}} .property-gallery__item--light',
        ]);
        $this->add_responsive_control('slide_gap', [
            'label' => __('Espacio entre slides', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 12],
            'selectors' => [
                '{{WRAPPER}} .property-gallery' => '--homlity-gallery-gap: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_controls', [
            'label' => __('Estilos controles slider', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => [
                'gallery_layout' => 'slider_show',
            ],
        ]);
        $this->add_responsive_control('nav_size', [
            'label' => __('Tamaño flechas', 'homlity-real-estate'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 16, 'max' => 64]],
            'selectors' => [
                '{{WRAPPER}} .property-gallery' => '--hml-arrow-size: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_control('nav_color', [
            'label' => __('Color flechas', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery .splide__arrow' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('pagination_color', [
            'label' => __('Color paginación activa', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery .splide__pagination__page.is-active' => 'background: {{VALUE}};',
            ],
        ]);
        $this->add_control('pagination_color_inactive', [
            'label' => __('Color paginación inactiva', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-gallery .splide__pagination__page' => 'background: {{VALUE}};',
            ],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'slider_controls_shadow',
            'label' => __('Sombra controles', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-gallery .splide__arrow',
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $layout = $settings['gallery_layout'] ?? 'slider_show';
        \Homlity\PluginInmobiliario\Services\MediaGalleryAssets::enqueue();

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
