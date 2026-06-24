<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

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

        $this->add_control('show_tab_photos', ['label' => __('Mostrar tab Fotos', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('show_tab_videos', ['label' => __('Mostrar tab Video', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('show_tab_photos360', ['label' => __('Mostrar tab Fotos 360°', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('show_tab_tours360', ['label' => __('Mostrar tab Recorrido 360°', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('hide_empty_tabs', ['label' => __('Ocultar tabs sin contenido', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);

        $this->add_control('initial_tab', [
            'label' => __('Pestaña inicial', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'first_available',
            'options' => [
                'first_available' => __('Primera disponible', 'homlity-real-estate'),
                'photos' => __('Fotos', 'homlity-real-estate'),
                'videos' => __('Video', 'homlity-real-estate'),
                'photos360' => __('Fotos 360°', 'homlity-real-estate'),
                'tours360' => __('Recorrido 360°', 'homlity-real-estate'),
            ],
        ]);
        $this->add_control('initial_tab_fallback', [
            'label' => __('Si la pestaña inicial está vacía', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'first_available',
            'options' => [
                'first_available' => __('Usar primera disponible', 'homlity-real-estate'),
                'show_empty' => __('Mostrar estado vacío', 'homlity-real-estate'),
            ],
        ]);

        $this->add_control('photos_view_mode', [
            'label' => __('Modo fotos', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'swiper_lightbox',
            'options' => [
                'swiper_lightbox' => __('Slider + Lightbox', 'homlity-real-estate'),
                'swiper' => __('Solo slider', 'homlity-real-estate'),
                'lightbox' => __('Mosaico + Lightbox', 'homlity-real-estate'),
            ],
        ]);
        $this->add_control('slides_desktop', ['label' => __('Imágenes por vista desktop', 'homlity-real-estate'), 'type' => Controls_Manager::NUMBER, 'default' => 1, 'min' => 1, 'max' => 6]);
        $this->add_control('slides_tablet', ['label' => __('Imágenes por vista tablet', 'homlity-real-estate'), 'type' => Controls_Manager::NUMBER, 'default' => 1, 'min' => 1, 'max' => 4]);
        $this->add_control('slides_mobile', ['label' => __('Imágenes por vista móvil', 'homlity-real-estate'), 'type' => Controls_Manager::NUMBER, 'default' => 1, 'min' => 1, 'max' => 2]);
        $this->add_control('thumbs_per_view', ['label' => __('Miniaturas por vista', 'homlity-real-estate'), 'type' => Controls_Manager::NUMBER, 'default' => 4, 'min' => 2, 'max' => 12]);
        $this->add_control('autoplay', ['label' => __('Autoplay fotos', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('loop', ['label' => __('Loop fotos', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('show_arrows', ['label' => __('Mostrar flechas', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('show_pagination', ['label' => __('Mostrar paginación', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('show_thumbs', ['label' => __('Mostrar miniaturas', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('speed', ['label' => __('Velocidad transición (ms)', 'homlity-real-estate'), 'type' => Controls_Manager::NUMBER, 'default' => 520, 'min' => 100, 'max' => 3000]);

        $this->add_control('video_privacy_mode', ['label' => __('YouTube modo privacidad', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('video_autoplay', ['label' => __('Video autoplay', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER]);
        $this->add_control('video_muted', ['label' => __('Video muted', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER]);
        $this->add_control('video_controls', ['label' => __('Mostrar controles video', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('video_rel', ['label' => __('Mostrar videos relacionados', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER]);
        $this->add_control('video_lazy', ['label' => __('Lazy load iframe de video', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);

        $this->add_control('photos360_enable_viewer', ['label' => __('Activar visor 360', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('photos360_viewer_autoload', ['label' => __('360 AutoLoad', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER]);
        $this->add_control('photos360_show_zoom', ['label' => __('Mostrar zoom 360', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('photos360_show_fullscreen', ['label' => __('Mostrar fullscreen 360', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('photos360_yaw', ['label' => __('Yaw inicial', 'homlity-real-estate'), 'type' => Controls_Manager::NUMBER, 'default' => 0]);
        $this->add_control('photos360_pitch', ['label' => __('Pitch inicial', 'homlity-real-estate'), 'type' => Controls_Manager::NUMBER, 'default' => 0]);
        $this->add_control('photos360_hfov', ['label' => __('HFOV inicial', 'homlity-real-estate'), 'type' => Controls_Manager::NUMBER, 'default' => 100]);

        $this->add_control('tour_embed_mode', [
            'label' => __('Recorrido 360', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'embed',
            'options' => [
                'embed' => __('Intentar embebido seguro', 'homlity-real-estate'),
                'button' => __('Solo botón externo', 'homlity-real-estate'),
            ],
        ]);
        $this->add_control('tour_button_label', ['label' => __('Texto botón recorrido', 'homlity-real-estate'), 'type' => Controls_Manager::TEXT, 'default' => __('Abrir recorrido virtual', 'homlity-real-estate')]);
        $this->add_control('tour_new_tab', ['label' => __('Abrir recorrido en nueva pestaña', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => '']);
        $this->add_control('tour_lazy', ['label' => __('Lazy load iframe recorrido', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);

        $this->add_control('show_empty_message', ['label' => __('Mostrar mensajes vacíos', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('show_empty_icon', ['label' => __('Mostrar ícono en vacío', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('empty_text_photos', ['label' => __('Texto vacío fotos', 'homlity-real-estate'), 'type' => Controls_Manager::TEXT, 'default' => __('No hay fotos disponibles.', 'homlity-real-estate')]);
        $this->add_control('empty_text_videos', ['label' => __('Texto vacío video', 'homlity-real-estate'), 'type' => Controls_Manager::TEXT, 'default' => __('No hay videos disponibles.', 'homlity-real-estate')]);
        $this->add_control('empty_text_photos360', ['label' => __('Texto vacío fotos 360', 'homlity-real-estate'), 'type' => Controls_Manager::TEXT, 'default' => __('No hay fotos 360° disponibles.', 'homlity-real-estate')]);
        $this->add_control('empty_text_tours360', ['label' => __('Texto vacío recorrido 360', 'homlity-real-estate'), 'type' => Controls_Manager::TEXT, 'default' => __('No hay recorridos 360° disponibles.', 'homlity-real-estate')]);

        $this->add_control('show_tab_icons', ['label' => __('Mostrar íconos en tabs', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('show_tab_count', ['label' => __('Mostrar contador en tabs', 'homlity-real-estate'), 'type' => Controls_Manager::SWITCHER]);
        $this->add_control('tabs_layout', ['label' => __('Layout tabs', 'homlity-real-estate'), 'type' => Controls_Manager::SELECT, 'default' => 'horizontal', 'options' => ['horizontal' => __('Horizontal', 'homlity-real-estate'), 'vertical' => __('Vertical', 'homlity-real-estate')]]);
        $this->add_control('tabs_align', ['label' => __('Alineación tabs', 'homlity-real-estate'), 'type' => Controls_Manager::SELECT, 'default' => 'left', 'options' => ['left' => __('Izquierda', 'homlity-real-estate'), 'center' => __('Centro', 'homlity-real-estate'), 'right' => __('Derecha', 'homlity-real-estate'), 'justify' => __('Justificar', 'homlity-real-estate')]]);

        $this->end_controls_section();

        $this->start_controls_section('style_tabs', ['label' => __('Estilos pestañas', 'homlity-real-estate'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'tabs_typography', 'selector' => '{{WRAPPER}} .property-gallery-tabs__btn']);
        $this->add_control('tabs_text_color', ['label' => __('Color texto', 'homlity-real-estate'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .property-gallery-tabs__btn' => 'color: {{VALUE}};']]);
        $this->add_control('tabs_text_color_active', ['label' => __('Color texto activo', 'homlity-real-estate'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .property-gallery-tabs__btn.is-active' => 'color: {{VALUE}};']]);
        $this->add_control('tabs_bg_color', ['label' => __('Fondo', 'homlity-real-estate'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .property-gallery-tabs__btn' => 'background: {{VALUE}};']]);
        $this->add_control('tabs_bg_color_active', ['label' => __('Fondo activo', 'homlity-real-estate'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .property-gallery-tabs__btn.is-active' => 'background: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name' => 'tabs_border', 'selector' => '{{WRAPPER}} .property-gallery-tabs__btn']);
        $this->add_responsive_control('tabs_radius', ['label' => __('Radio botones', 'homlity-real-estate'), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => ['px', '%'], 'selectors' => ['{{WRAPPER}} .property-gallery-tabs__btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('tabs_padding', ['label' => __('Padding botones', 'homlity-real-estate'), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => ['px', '%', 'em'], 'selectors' => ['{{WRAPPER}} .property-gallery-tabs__btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name' => 'tabs_shadow', 'selector' => '{{WRAPPER}} .property-gallery-tabs__btn']);
        $this->end_controls_section();

        $this->start_controls_section('style_media', ['label' => __('Estilos multimedia', 'homlity-real-estate'), 'tab' => Controls_Manager::TAB_STYLE]);

        // — Imagen principal —
        $this->add_control('media_main_heading', [
            'label' => __('Foto principal', 'homlity-real-estate'),
            'type'  => Controls_Manager::HEADING,
        ]);
        $this->add_responsive_control('images_height', [
            'label'      => __('Alto imagen/slider', 'homlity-real-estate'),
            'type'        => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range'      => ['px' => ['min' => 120, 'max' => 900], 'vh' => ['min' => 20, 'max' => 100]],
            'selectors'  => ['{{WRAPPER}} .property-gallery__slide img, {{WRAPPER}} .property-gallery-tabs__panel .property-gallery--light img' => 'height: {{SIZE}}{{UNIT}}; object-fit: cover;'],
        ]);
        $this->add_responsive_control('iframe_height', [
            'label'      => __('Alto iframe', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range'      => ['px' => ['min' => 180, 'max' => 900], 'vh' => ['min' => 20, 'max' => 100]],
            'selectors'  => [
                '{{WRAPPER}} .property-gallery-tabs__video-wrap, {{WRAPPER}} .property-gallery-tabs__360-item, {{WRAPPER}} .property-gallery-tabs__tour-wrap' => 'padding-bottom: 0; height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('main_image_radius', [
            'label'      => __('Radio borde foto', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} .property-gallery__slide img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'main_image_border',
            'label'    => __('Borde foto principal', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-gallery__slide img',
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'main_image_shadow',
            'label'    => __('Sombra foto principal', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-gallery__slide img',
        ]);

        // — Flechas del slider —
        $this->add_control('arrows_heading', [
            'label'     => __('Flechas del slider', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['show_arrows' => 'yes'],
        ]);
        $this->add_responsive_control('arrow_size', [
            'label'      => __('Tamaño flechas', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 10, 'max' => 80]],
            'default'    => ['unit' => 'px', 'size' => 44],
            'selectors'  => ['{{WRAPPER}} .property-gallery--swiper' => '--swiper-navigation-size: {{SIZE}}{{UNIT}};'],
            'condition'  => ['show_arrows' => 'yes'],
        ]);
        $this->add_control('arrow_color', [
            'label'     => __('Color flechas', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-gallery--swiper' => '--swiper-navigation-color: {{VALUE}};'],
            'condition' => ['show_arrows' => 'yes'],
        ]);
        $this->add_control('arrow_color_hover', [
            'label'     => __('Color flechas (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .swiper-button-prev:hover, {{WRAPPER}} .swiper-button-next:hover' => 'color: {{VALUE}};',
            ],
            'condition' => ['show_arrows' => 'yes'],
        ]);
        $this->add_control('arrow_bg', [
            'label'     => __('Fondo flechas', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .swiper-button-prev, {{WRAPPER}} .swiper-button-next' => 'background-color: {{VALUE}};',
            ],
            'condition' => ['show_arrows' => 'yes'],
        ]);
        $this->add_control('arrow_bg_hover', [
            'label'     => __('Fondo flechas (hover)', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .swiper-button-prev:hover, {{WRAPPER}} .swiper-button-next:hover' => 'background-color: {{VALUE}};',
            ],
            'condition' => ['show_arrows' => 'yes'],
        ]);
        $this->add_responsive_control('arrow_padding', [
            'label'      => __('Padding flechas', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .swiper-button-prev, {{WRAPPER}} .swiper-button-next' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
            'condition' => ['show_arrows' => 'yes'],
        ]);
        $this->add_responsive_control('arrow_radius', [
            'label'      => __('Radio borde flechas', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .swiper-button-prev, {{WRAPPER}} .swiper-button-next' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
            'condition' => ['show_arrows' => 'yes'],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'      => 'arrow_shadow',
            'label'     => __('Sombra flechas', 'homlity-real-estate'),
            'selector'  => '{{WRAPPER}} .swiper-button-prev, {{WRAPPER}} .swiper-button-next',
            'condition' => ['show_arrows' => 'yes'],
        ]);

        // — Miniaturas —
        $this->add_control('thumbs_heading', [
            'label'     => __('Miniaturas', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['show_thumbs' => 'yes'],
        ]);
        $this->add_responsive_control('thumbs_height', [
            'label'      => __('Alto miniaturas', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 40, 'max' => 200]],
            'default'    => ['unit' => 'px', 'size' => 86],
            'selectors'  => ['{{WRAPPER}} .property-gallery__thumbs .swiper-slide img' => 'height: {{SIZE}}{{UNIT}};'],
            'condition'  => ['show_thumbs' => 'yes'],
        ]);
        $this->add_responsive_control('thumbs_radius', [
            'label'      => __('Radio borde miniaturas', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} .property-gallery__thumbs .swiper-slide' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;'],
            'condition'  => ['show_thumbs' => 'yes'],
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'      => 'thumbs_border',
            'label'     => __('Borde miniaturas', 'homlity-real-estate'),
            'selector'  => '{{WRAPPER}} .property-gallery__thumbs .swiper-slide',
            'condition' => ['show_thumbs' => 'yes'],
        ]);
        $this->add_control('thumbs_border_active', [
            'label'     => __('Color borde miniatura activa', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-gallery__thumbs .swiper-slide-thumb-active' => 'border-color: {{VALUE}};'],
            'condition' => ['show_thumbs' => 'yes'],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'      => 'thumbs_shadow',
            'label'     => __('Sombra miniaturas', 'homlity-real-estate'),
            'selector'  => '{{WRAPPER}} .property-gallery__thumbs .swiper-slide',
            'condition' => ['show_thumbs' => 'yes'],
        ]);

        // — Media general (iframes / video) —
        $this->add_control('media_general_heading', [
            'label'     => __('Iframe y video', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'media_border',
            'label'    => __('Borde iframe/video', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-gallery-tabs__panel iframe, {{WRAPPER}} .property-gallery-tabs__panel video',
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'media_shadow',
            'label'    => __('Sombra iframe/video', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .property-gallery-tabs__panel iframe, {{WRAPPER}} .property-gallery-tabs__panel video',
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $photosMode = sanitize_key((string) ($settings['photos_view_mode'] ?? 'swiper_lightbox'));
        $photosMode = in_array($photosMode, ['swiper_lightbox', 'swiper', 'lightbox'], true) ? $photosMode : 'swiper_lightbox';
        $layout = $photosMode === 'lightbox' ? 'light_gallery' : 'slider_show';
        $photosEnableLightbox = in_array($photosMode, ['lightbox', 'swiper_lightbox'], true);

        wp_enqueue_script('homlity-real-estate-gallery-tabs', HOMLITY_PLUGIN_URL . 'assets/js/property-gallery-tabs.js', [], HOMLITY_PLUGIN_VERSION, true);
        wp_enqueue_style('homlity-real-estate-swiper', HOMLITY_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.css', [], '11.1.4');
        wp_enqueue_script('homlity-real-estate-swiper', HOMLITY_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.js', [], '11.1.4', true);
        wp_enqueue_script('homlity-real-estate-gallery-swiper', HOMLITY_PLUGIN_URL . 'assets/js/property-gallery-swiper.js', ['homlity-real-estate-swiper'], HOMLITY_PLUGIN_VERSION, true);

        $widgetUid = sanitize_key('hml-widget-' . $this->get_id() . '-' . wp_unique_id());
        $hasPhotos360 = !empty(get_post_meta($this->current_property_id(), '_property_photos_360', true)) || !empty(get_post_meta($this->current_property_id(), '_homlity_sync_media_photos_360', true));
        if ($hasPhotos360 && !wp_script_is('homlity-real-estate-pannellum', 'registered')) {
            wp_register_style('homlity-real-estate-pannellum', HOMLITY_PLUGIN_URL . 'assets/vendor/pannellum/pannellum.css', [], '2.5.6');
            wp_register_script('homlity-real-estate-pannellum', HOMLITY_PLUGIN_URL . 'assets/vendor/pannellum/pannellum.js', [], '2.5.6', true);
        }
        if ($hasPhotos360) {
            wp_enqueue_style('homlity-real-estate-pannellum');
            wp_enqueue_script('homlity-real-estate-pannellum');
        }

        TemplateService::includeComponent('property-gallery.php', [
            'widget_uid' => $widgetUid,
            'post_id' => $this->current_property_id(),
            'preferred_first_tab' => sanitize_key((string) ($settings['initial_tab'] ?? 'first_available')),
            'initial_tab_fallback' => sanitize_key((string) ($settings['initial_tab_fallback'] ?? 'first_available')),
            'layout' => $layout,
            'photos_enable_lightbox' => $photosEnableLightbox,
            'slides_desktop' => max(1, (int) ($settings['slides_desktop'] ?? 1)),
            'slides_tablet' => max(1, (int) ($settings['slides_tablet'] ?? 1)),
            'slides_mobile' => max(1, (int) ($settings['slides_mobile'] ?? 1)),
            'thumbs_per_view' => max(2, (int) ($settings['thumbs_per_view'] ?? 4)),
            'autoplay' => ($settings['autoplay'] ?? 'yes') === 'yes',
            'loop' => ($settings['loop'] ?? 'yes') === 'yes',
            'show_arrows' => ($settings['show_arrows'] ?? 'yes') === 'yes',
            'show_pagination' => ($settings['show_pagination'] ?? 'yes') === 'yes',
            'show_thumbs' => ($settings['show_thumbs'] ?? 'yes') === 'yes',
            'speed' => max(100, (int) ($settings['speed'] ?? 520)),
            'style_preset' => 'minimal',
            'show_tab_photos' => ($settings['show_tab_photos'] ?? 'yes') === 'yes',
            'show_tab_videos' => ($settings['show_tab_videos'] ?? 'yes') === 'yes',
            'show_tab_photos360' => ($settings['show_tab_photos360'] ?? 'yes') === 'yes',
            'show_tab_tours360' => ($settings['show_tab_tours360'] ?? 'yes') === 'yes',
            'hide_empty_tabs' => ($settings['hide_empty_tabs'] ?? 'yes') === 'yes',
            'show_empty_message' => ($settings['show_empty_message'] ?? 'yes') === 'yes',
            'show_empty_icon' => ($settings['show_empty_icon'] ?? 'yes') === 'yes',
            'empty_text_photos' => sanitize_text_field((string) ($settings['empty_text_photos'] ?? '')),
            'empty_text_videos' => sanitize_text_field((string) ($settings['empty_text_videos'] ?? '')),
            'empty_text_photos360' => sanitize_text_field((string) ($settings['empty_text_photos360'] ?? '')),
            'empty_text_tours360' => sanitize_text_field((string) ($settings['empty_text_tours360'] ?? '')),
            'video_privacy_mode' => ($settings['video_privacy_mode'] ?? 'yes') === 'yes',
            'video_autoplay' => ($settings['video_autoplay'] ?? '') === 'yes',
            'video_muted' => ($settings['video_muted'] ?? '') === 'yes',
            'video_controls' => ($settings['video_controls'] ?? 'yes') === 'yes',
            'video_rel' => ($settings['video_rel'] ?? '') === 'yes',
            'video_lazy' => ($settings['video_lazy'] ?? 'yes') === 'yes',
            'photos360_enable_viewer' => ($settings['photos360_enable_viewer'] ?? 'yes') === 'yes',
            'photos360_viewer_autoload' => ($settings['photos360_viewer_autoload'] ?? '') === 'yes',
            'photos360_show_zoom' => ($settings['photos360_show_zoom'] ?? 'yes') === 'yes',
            'photos360_show_fullscreen' => ($settings['photos360_show_fullscreen'] ?? 'yes') === 'yes',
            'photos360_yaw' => (float) ($settings['photos360_yaw'] ?? 0),
            'photos360_pitch' => (float) ($settings['photos360_pitch'] ?? 0),
            'photos360_hfov' => (float) ($settings['photos360_hfov'] ?? 100),
            'tour_embed_mode' => sanitize_key((string) ($settings['tour_embed_mode'] ?? 'embed')),
            'tour_button_label' => sanitize_text_field((string) ($settings['tour_button_label'] ?? __('Abrir recorrido virtual', 'homlity-real-estate'))),
            'tour_new_tab' => ($settings['tour_new_tab'] ?? '') === 'yes',
            'tour_lazy' => ($settings['tour_lazy'] ?? 'yes') === 'yes',
            'show_tab_icons' => ($settings['show_tab_icons'] ?? 'yes') === 'yes',
            'show_tab_count' => ($settings['show_tab_count'] ?? '') === 'yes',
            'tabs_layout' => sanitize_key((string) ($settings['tabs_layout'] ?? 'horizontal')),
            'tabs_align' => sanitize_key((string) ($settings['tabs_align'] ?? 'left')),
        ]);
    }
}
