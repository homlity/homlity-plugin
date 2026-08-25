<?php
/**
 * Elementor widget: hero slider of the latest properties.
 *
 * Built for the main hero of a real-estate home page: each slide is a
 * full-bleed property image with its headline data overlaid, and every part of
 * it is configurable from Elementor. It can also fall back to a carousel of
 * the plugin's regular property cards.
 *
 * Like PropertyListingWidget, this is a thin adapter: the Elementor controls
 * become a HeroSliderConfig, which normalises them and builds the query, and
 * the markup lives in the overridable property-hero-slider.php template. The
 * behaviour lives in that config precisely so it can be tested — a widget
 * class cannot be instantiated without Elementor loaded.
 */

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Homlity\PluginInmobiliario\Listing\HeroSliderConfig;
use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyHeroSliderWidget extends BasePropertyWidget
{
    use PropertyCardStylesTrait;

    public function get_name(): string  { return 'property_hero_slider'; }
    public function get_title(): string { return __('Slider hero de inmuebles', 'homlity-real-estate'); }
    public function get_icon(): string  { return 'eicon-slides'; }

    public function get_keywords(): array
    {
        return ['slider', 'hero', 'carrusel', 'inmuebles', 'propiedades', 'destacados', 'homlity'];
    }

    /**
     * Swiper and the hero stylesheet are declared as dependencies rather than
     * enqueued inside render(), so Elementor also loads them for the editor
     * preview and for pages rendered from cache.
     */
    public function get_style_depends(): array
    {
        wp_register_style(
            'homlity-real-estate-swiper',
            HOMLITY_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.css',
            [],
            '11.1.4'
        );

        wp_register_style(
            'homlity-real-estate-hero-slider',
            HOMLITY_PLUGIN_URL . 'assets/css/property-hero-slider.css',
            ['homlity-real-estate-swiper'],
            HOMLITY_PLUGIN_VERSION
        );

        return array_merge(
            parent::get_style_depends(),
            ['homlity-real-estate-swiper', 'homlity-real-estate-hero-slider']
        );
    }

    public function get_script_depends(): array
    {
        wp_register_script(
            'homlity-real-estate-swiper',
            HOMLITY_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.js',
            [],
            '11.1.4',
            true
        );

        wp_register_script(
            'homlity-real-estate-hero-slider',
            HOMLITY_PLUGIN_URL . 'assets/js/property-hero-slider.js',
            ['homlity-real-estate-swiper'],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        return ['homlity-real-estate-swiper', 'homlity-real-estate-hero-slider'];
    }

    protected function register_controls(): void
    {
        $this->registerLayoutSection();
        $this->registerQuerySection();
        $this->registerSlideContentSection();
        $this->registerSliderSection();

        // The card layout renders the regular property card, so it reuses the
        // card's own content and style controls verbatim — hidden while the
        // hero layout is selected, where they have nothing to act on.
        $cardOnly = ['condition' => ['layout' => 'cards']];
        $this->registerCardContentControls($cardOnly);

        $this->registerSlideStyleSection();
        $this->registerBadgeStyleSection();
        $this->registerTitleStyleSection();
        $this->registerLocationStyleSection();
        $this->registerExcerptStyleSection();
        $this->registerFeaturesStyleSection();
        $this->registerPriceStyleSection();
        $this->registerButtonStyleSection();
        $this->registerNavigationStyleSection();

        $this->registerCardStyleControls($cardOnly);
    }

    // ── Content tab ───────────────────────────────────────────────────────────

    private function registerLayoutSection(): void
    {
        $this->start_controls_section('hero_layout', ['label' => __('Presentación', 'homlity-real-estate')]);

        $this->add_control('layout', [
            'label'   => __('Tipo de slider', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'hero'  => __('Hero a pantalla completa (imagen + datos encima)', 'homlity-real-estate'),
                'split' => __('Dividido (imagen + panel de datos)', 'homlity-real-estate'),
                'cards' => __('Carrusel de tarjetas de inmueble', 'homlity-real-estate'),
            ],
            'default'     => 'hero',
            'description' => __('Hero y Dividido sirven para la cabecera principal; el carrusel de tarjetas, para secciones como "Últimos inmuebles".', 'homlity-real-estate'),
        ]);

        $this->add_responsive_control('slide_height', [
            'label'      => __('Alto del slide', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range'      => [
                'px' => ['min' => 240, 'max' => 1200],
                'vh' => ['min' => 20, 'max' => 100],
            ],
            'default'   => ['unit' => 'px', 'size' => 620],
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-height: {{SIZE}}{{UNIT}};',
            ],
            'condition' => ['layout' => ['hero', 'split']],
        ]);

        // Written as custom properties rather than a class, which is what lets
        // the position differ per device: bottom-left on a desktop hero and
        // centred on a phone, from the same widget.
        $this->add_responsive_control('content_position', [
            'label'   => __('Posición del contenido', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'bottom-left'   => __('Abajo izquierda', 'homlity-real-estate'),
                'bottom-center' => __('Abajo centro', 'homlity-real-estate'),
                'bottom-right'  => __('Abajo derecha', 'homlity-real-estate'),
                'center-left'   => __('Centro izquierda', 'homlity-real-estate'),
                'center'        => __('Centro', 'homlity-real-estate'),
                'center-right'  => __('Centro derecha', 'homlity-real-estate'),
                'top-left'      => __('Arriba izquierda', 'homlity-real-estate'),
                'top-center'    => __('Arriba centro', 'homlity-real-estate'),
            ],
            'default'        => 'bottom-left',
            'tablet_default' => 'bottom-left',
            'mobile_default' => 'bottom-center',
            'selectors_dictionary' => self::positionDictionary(),
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider' => '{{VALUE}}',
            ],
            'condition' => ['layout' => ['hero', 'split']],
        ]);

        $this->add_control('kenburns', [
            'label'       => __('Efecto Ken Burns (zoom lento)', 'homlity-real-estate'),
            'type'        => Controls_Manager::SWITCHER,
            'default'     => '',
            'description' => __('Se desactiva automáticamente si el visitante pidió reducir el movimiento.', 'homlity-real-estate'),
            'condition'   => ['layout' => ['hero', 'split']],
        ]);

        $this->add_responsive_control('split_media_side', [
            'label'   => __('Lado de la imagen', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'left'  => __('Imagen a la izquierda', 'homlity-real-estate'),
                'right' => __('Imagen a la derecha', 'homlity-real-estate'),
                'top'   => __('Imagen arriba (apilado)', 'homlity-real-estate'),
            ],
            'default'        => 'left',
            'tablet_default' => 'left',
            'mobile_default' => 'top',
            'selectors_dictionary' => self::splitSideDictionary(),
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider' => '{{VALUE}}',
            ],
            'condition' => ['layout' => 'split'],
        ]);

        $this->add_responsive_control('split_media_width', [
            'label'      => __('Ancho de la imagen', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['%'],
            'range'      => ['%' => ['min' => 20, 'max' => 80]],
            'default'    => ['unit' => '%', 'size' => 55],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-split-media: {{SIZE}}{{UNIT}};',
            ],
            'condition' => [
                'layout'              => 'split',
                'split_media_side!'   => 'top',
            ],
        ]);

        $this->add_responsive_control('split_media_min_height', [
            'label'      => __('Alto mínimo de la imagen', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range'      => [
                'px' => ['min' => 120, 'max' => 900],
                'vh' => ['min' => 10, 'max' => 90],
            ],
            'default'     => ['unit' => 'px', 'size' => 280],
            'description' => __('Sobre todo útil cuando la imagen queda apilada arriba en celular.', 'homlity-real-estate'),
            'selectors'   => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-media-min-height: {{SIZE}}{{UNIT}};',
            ],
            'condition' => ['layout' => 'split'],
        ]);

        $this->add_control('split_panel_background', [
            'label'     => __('Fondo del panel', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#111827',
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-panel-bg: {{VALUE}};',
            ],
            'condition' => ['layout' => 'split'],
        ]);

        $this->end_controls_section();
    }

    /**
     * Maps each position option to the custom properties that place the
     * content block on both axes. Kept as a dictionary so the control can be
     * responsive: Elementor emits one declaration per device breakpoint.
     *
     * @return array<string,string>
     */
    private static function positionDictionary(): array
    {
        $rule = static function (string $vertical, string $horizontal, string $align, string $items): string {
            return sprintf(
                '--hml-hero-align-v: %s; --hml-hero-align-h: %s; --hml-hero-text-align: %s; --hml-hero-items: %s;',
                $vertical,
                $horizontal,
                $align,
                $items
            );
        };

        return [
            'bottom-left'   => $rule('end', 'start', 'left', 'flex-start'),
            'bottom-center' => $rule('end', 'center', 'center', 'center'),
            'bottom-right'  => $rule('end', 'end', 'right', 'flex-end'),
            'center-left'   => $rule('center', 'start', 'left', 'flex-start'),
            'center'        => $rule('center', 'center', 'center', 'center'),
            'center-right'  => $rule('center', 'end', 'right', 'flex-end'),
            'top-left'      => $rule('start', 'start', 'left', 'flex-start'),
            'top-center'    => $rule('start', 'center', 'center', 'center'),
        ];
    }

    /**
     * Column sizes and source order for the split layout, so the image can sit
     * left on a desktop and stack above the panel on a phone.
     *
     * @return array<string,string>
     */
    private static function splitSideDictionary(): array
    {
        return [
            'left'  => '--hml-hero-split-cols: var(--hml-hero-split-media) 1fr; --hml-hero-media-order: 1; --hml-hero-content-order: 2;',
            'right' => '--hml-hero-split-cols: 1fr var(--hml-hero-split-media); --hml-hero-media-order: 2; --hml-hero-content-order: 1;',
            'top'   => '--hml-hero-split-cols: 1fr; --hml-hero-media-order: 1; --hml-hero-content-order: 2;',
        ];
    }

    private function registerQuerySection(): void
    {
        $this->start_controls_section('hero_query', ['label' => __('Consulta', 'homlity-real-estate')]);

        $this->add_control('posts_per_page', [
            'label'       => __('Cantidad de inmuebles', 'homlity-real-estate'),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 1,
            'max'         => 30,
            'default'     => 6,
            'description' => __('Número de slides que traerá el slider.', 'homlity-real-estate'),
        ]);

        $this->add_control('orderby', [
            'label'   => __('Ordenar por', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'date'       => __('Más recientes', 'homlity-real-estate'),
                'price_desc' => __('Precio: mayor a menor', 'homlity-real-estate'),
                'price_asc'  => __('Precio: menor a mayor', 'homlity-real-estate'),
                'title'      => __('Nombre A–Z', 'homlity-real-estate'),
                'rand'       => __('Aleatorio', 'homlity-real-estate'),
            ],
            'default' => 'date',
        ]);

        $this->add_control('featured_only', [
            'label'   => __('Solo destacados', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        $this->add_control('preset_operation', [
            'label'   => __('Fijar gestión (venta/arriendo)', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_OPERATION),
            'default' => '',
        ]);

        $this->add_control('preset_type', [
            'label'   => __('Fijar tipo de inmueble', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_TYPE),
            'default' => '',
        ]);

        $this->add_control('preset_category', [
            'label'   => __('Fijar categoría', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_CATEGORY),
            'default' => '',
        ]);

        $this->add_control('preset_city', [
            'label'   => __('Fijar ciudad', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_CITY),
            'default' => '',
        ]);

        $this->add_control('preset_tag', [
            'label'   => __('Fijar etiqueta', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_TAG),
            'default' => '',
        ]);

        $this->add_control('empty_message', [
            'label'       => __('Mensaje cuando no hay inmuebles', 'homlity-real-estate'),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'description' => __('Si se deja vacío, el widget no muestra nada cuando la consulta no devuelve resultados.', 'homlity-real-estate'),
        ]);

        $this->end_controls_section();
    }

    private function registerSlideContentSection(): void
    {
        $this->start_controls_section('hero_content', [
            'label'     => __('Contenido del slide', 'homlity-real-estate'),
            'condition' => ['layout' => ['hero', 'split']],
        ]);

        $this->add_control('show_operation', [
            'label'   => __('Mostrar gestión (Venta/Arriendo)', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_title', [
            'label'   => __('Mostrar título', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_location', [
            'label'   => __('Mostrar ubicación', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('location_icon', [
            'label'     => __('Ícono de ubicación', 'homlity-real-estate'),
            'type'      => Controls_Manager::ICONS,
            'default'   => ['value' => 'fas fa-location-dot', 'library' => 'fa-solid'],
            'condition' => ['show_location' => 'yes'],
        ]);

        $this->add_control('show_price', [
            'label'   => __('Mostrar precio', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_excerpt', [
            'label'   => __('Mostrar descripción corta', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        $this->add_control('excerpt_words', [
            'label'     => __('Palabras de la descripción', 'homlity-real-estate'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 5,
            'max'       => 80,
            'default'   => 22,
            'condition' => ['show_excerpt' => 'yes'],
        ]);

        $this->add_control('show_code', [
            'label'   => __('Mostrar código del inmueble', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        $this->add_control('show_features', [
            'label'     => __('Mostrar características', 'homlity-real-estate'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'separator' => 'before',
        ]);

        $features = [
            'area'      => [__('Área', 'homlity-real-estate'), 'fas fa-ruler-combined', 'fa-solid'],
            'bedrooms'  => [__('Alcobas', 'homlity-real-estate'), 'fas fa-bed', 'fa-solid'],
            'bathrooms' => [__('Baños', 'homlity-real-estate'), 'fas fa-bath', 'fa-solid'],
            'parking'   => [__('Parqueaderos', 'homlity-real-estate'), 'fas fa-car', 'fa-solid'],
        ];

        foreach ($features as $key => [$label, $iconValue, $iconLibrary]) {
            $this->add_control('feature_' . $key, [
                'label'     => $label,
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'condition' => ['show_features' => 'yes'],
            ]);

            $this->add_control('feature_icon_' . $key, [
                'label'     => sprintf(
                    /* translators: %s: nombre de la característica (alcobas, baños, área...). */
                    __('Ícono: %s', 'homlity-real-estate'),
                    $label
                ),
                'type'      => Controls_Manager::ICONS,
                'default'   => ['value' => $iconValue, 'library' => $iconLibrary],
                'condition' => [
                    'show_features'      => 'yes',
                    'feature_' . $key    => 'yes',
                ],
            ]);
        }

        $this->add_control('show_button', [
            'label'     => __('Mostrar botón', 'homlity-real-estate'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'separator' => 'before',
        ]);

        $this->add_control('button_label', [
            'label'       => __('Texto del botón', 'homlity-real-estate'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('Ver inmueble', 'homlity-real-estate'),
            'condition'   => ['show_button' => 'yes'],
        ]);

        $this->add_control('button_icon', [
            'label'     => __('Ícono del botón', 'homlity-real-estate'),
            'type'      => Controls_Manager::ICONS,
            'default'   => ['value' => 'fas fa-arrow-right', 'library' => 'fa-solid'],
            'condition' => ['show_button' => 'yes'],
        ]);

        $this->add_control('show_whatsapp', [
            'label'     => __('Mostrar botón de WhatsApp', 'homlity-real-estate'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => '',
            'separator' => 'before',
        ]);

        $this->add_control('whatsapp_label', [
            'label'     => __('Texto de WhatsApp', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('WhatsApp', 'homlity-real-estate'),
            'condition' => ['show_whatsapp' => 'yes'],
        ]);

        $this->add_control('whatsapp_icon', [
            'label'     => __('Ícono de WhatsApp', 'homlity-real-estate'),
            'type'      => Controls_Manager::ICONS,
            'default'   => ['value' => 'fab fa-whatsapp', 'library' => 'fa-brands'],
            'condition' => ['show_whatsapp' => 'yes'],
        ]);

        $this->add_control('link_whole_slide', [
            'label'       => __('Todo el slide es enlace', 'homlity-real-estate'),
            'type'        => Controls_Manager::SWITCHER,
            'default'     => 'yes',
            'separator'   => 'before',
            'description' => __('Los botones siguen funcionando por encima del enlace.', 'homlity-real-estate'),
        ]);

        $this->add_control('link_new_tab', [
            'label'   => __('Abrir en pestaña nueva', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        $this->end_controls_section();
    }

    private function registerSliderSection(): void
    {
        $this->start_controls_section('hero_slider', ['label' => __('Comportamiento del slider', 'homlity-real-estate')]);

        $this->add_control('slides_desktop', [
            'label'     => __('Slides visibles (escritorio)', 'homlity-real-estate'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1,
            'max'       => 6,
            'default'   => 3,
            'condition' => ['layout' => 'cards'],
        ]);

        $this->add_control('slides_tablet', [
            'label'     => __('Slides visibles (tablet)', 'homlity-real-estate'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1,
            'max'       => 6,
            'default'   => 2,
            'condition' => ['layout' => 'cards'],
        ]);

        $this->add_control('slides_mobile', [
            'label'     => __('Slides visibles (móvil)', 'homlity-real-estate'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1,
            'max'       => 4,
            'default'   => 1,
            'condition' => ['layout' => 'cards'],
        ]);

        $this->add_responsive_control('slide_gap', [
            'label'      => __('Espacio entre slides', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'default'    => ['unit' => 'px', 'size' => 24],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-slide-gap: {{SIZE}}{{UNIT}};',
            ],
            'condition' => ['layout' => 'cards'],
        ]);

        $this->add_control('effect', [
            'label'   => __('Transición', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'slide' => __('Deslizar', 'homlity-real-estate'),
                'fade'  => __('Desvanecer', 'homlity-real-estate'),
            ],
            'default'     => 'slide',
            'description' => __('El desvanecido solo aplica cuando se muestra un slide a la vez.', 'homlity-real-estate'),
        ]);

        $this->add_control('speed', [
            'label'   => __('Velocidad de transición (ms)', 'homlity-real-estate'),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 100,
            'max'     => 3000,
            'step'    => 50,
            'default' => 600,
        ]);

        $this->add_control('autoplay', [
            'label'   => __('Reproducción automática', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('autoplay_delay', [
            'label'     => __('Tiempo entre slides (ms)', 'homlity-real-estate'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1000,
            'max'       => 20000,
            'step'      => 500,
            'default'   => 5000,
            'condition' => ['autoplay' => 'yes'],
        ]);

        $this->add_control('pause_on_hover', [
            'label'     => __('Pausar al pasar el mouse', 'homlity-real-estate'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'condition' => ['autoplay' => 'yes'],
        ]);

        $this->add_control('loop', [
            'label'   => __('Repetir en bucle', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_arrows', [
            'label'     => __('Mostrar flechas', 'homlity-real-estate'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'separator' => 'before',
        ]);

        $this->add_control('show_pagination', [
            'label'   => __('Mostrar paginación', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('pagination_type', [
            'label'   => __('Tipo de paginación', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'bullets'     => __('Puntos', 'homlity-real-estate'),
                'fraction'    => __('Fracción (1 / 6)', 'homlity-real-estate'),
                'progressbar' => __('Barra de progreso', 'homlity-real-estate'),
            ],
            'default'   => 'bullets',
            'condition' => ['show_pagination' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    // ── Style tab ─────────────────────────────────────────────────────────────

    private function registerSlideStyleSection(): void
    {
        $this->start_controls_section('style_slide', [
            'label'     => __('Slide', 'homlity-real-estate'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['layout' => ['hero', 'split']],
        ]);

        $this->add_responsive_control('slider_radius', [
            'label'      => __('Radio de borde', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('scrim_heading', [
            'label'     => __('Degradado sobre la imagen', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['layout' => 'hero'],
        ]);

        $this->add_control('scrim_from', [
            'label'     => __('Color inferior', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'rgba(0,0,0,0.72)',
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-scrim-from: {{VALUE}};',
            ],
            'condition' => ['layout' => 'hero'],
        ]);

        $this->add_control('scrim_to', [
            'label'     => __('Color superior', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'rgba(0,0,0,0)',
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-scrim-to: {{VALUE}};',
            ],
            'condition' => ['layout' => 'hero'],
        ]);

        $this->add_control('content_box_heading', [
            'label'     => __('Bloque de contenido', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('content_width', [
            'label'      => __('Ancho máximo', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => [
                'px' => ['min' => 240, 'max' => 1200],
                '%'  => ['min' => 20, 'max' => 100],
            ],
            'default'   => ['unit' => 'px', 'size' => 640],
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-content-width: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('content_padding', [
            'label'      => __('Relleno interno', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 140]],
            'default'    => ['unit' => 'px', 'size' => 48],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-content-padding: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('content_gap', [
            'label'      => __('Espacio entre elementos', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 48]],
            'default'    => ['unit' => 'px', 'size' => 14],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-content-gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('content_background', [
            'label'     => __('Fondo del bloque', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__content' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('content_radius', [
            'label'      => __('Radio del bloque', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 48]],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider__content' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('accent_color', [
            'label'       => __('Color de acento', 'homlity-real-estate'),
            'type'        => Controls_Manager::COLOR,
            'default'     => '#ffffff',
            'separator'   => 'before',
            'description' => __('Se usa en la etiqueta de gestión y en el botón principal.', 'homlity-real-estate'),
            'selectors'   => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-accent: {{VALUE}};',
            ],
        ]);

        $this->add_control('text_color', [
            'label'     => __('Color de texto general', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-text: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function registerBadgeStyleSection(): void
    {
        $this->start_controls_section('style_badge', [
            'label'     => __('Etiqueta de gestión', 'homlity-real-estate'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['layout' => ['hero', 'split'], 'show_operation' => 'yes'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'badge_typography',
            'selector' => '{{WRAPPER}} .hml-hero-slider__operation',
        ]);

        $this->add_control('badge_color', [
            'label'     => __('Color del texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__operation' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('badge_background', [
            'label'     => __('Color de fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__operation' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('badge_padding', [
            'label'      => __('Relleno', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider__operation' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('badge_radius', [
            'label'      => __('Radio de borde', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 999]],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider__operation' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function registerTitleStyleSection(): void
    {
        $this->start_controls_section('style_title', [
            'label'     => __('Título', 'homlity-real-estate'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['layout' => ['hero', 'split'], 'show_title' => 'yes'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'title_typography',
            'selector' => '{{WRAPPER}} .hml-hero-slider__title, {{WRAPPER}} .hml-hero-slider__title a',
        ]);

        $this->add_control('title_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__title'   => 'color: {{VALUE}};',
                '{{WRAPPER}} .hml-hero-slider__title a' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name'     => 'title_text_shadow',
            'selector' => '{{WRAPPER}} .hml-hero-slider__title',
        ]);

        $this->end_controls_section();
    }

    private function registerLocationStyleSection(): void
    {
        $this->start_controls_section('style_location', [
            'label'     => __('Ubicación', 'homlity-real-estate'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['layout' => ['hero', 'split'], 'show_location' => 'yes'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'location_typography',
            'selector' => '{{WRAPPER}} .hml-hero-slider__location',
        ]);

        $this->add_control('location_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__location' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('location_icon_color', [
            'label'     => __('Color del ícono', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__location-icon'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .hml-hero-slider__location-icon svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('location_icon_size', [
            'label'      => __('Tamaño del ícono', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 8, 'max' => 48]],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider__location-icon' => 'font-size: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function registerExcerptStyleSection(): void
    {
        $this->start_controls_section('style_excerpt', [
            'label'     => __('Descripción', 'homlity-real-estate'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['layout' => ['hero', 'split'], 'show_excerpt' => 'yes'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'excerpt_typography',
            'selector' => '{{WRAPPER}} .hml-hero-slider__excerpt',
        ]);

        $this->add_control('excerpt_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__excerpt' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function registerFeaturesStyleSection(): void
    {
        $this->start_controls_section('style_features', [
            'label'     => __('Características', 'homlity-real-estate'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['layout' => ['hero', 'split'], 'show_features' => 'yes'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'features_typography',
            'selector' => '{{WRAPPER}} .hml-hero-slider__feature',
        ]);

        $this->add_control('features_color', [
            'label'     => __('Color del texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__feature' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('features_icon_color', [
            'label'     => __('Color de los íconos', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__feature-icon'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .hml-hero-slider__feature-icon svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_control('features_background', [
            'label'     => __('Fondo de cada chip', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__feature' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('features_padding', [
            'label'      => __('Relleno del chip', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider__feature' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('features_radius', [
            'label'      => __('Radio del chip', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 999]],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider__feature' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('features_gap', [
            'label'      => __('Espacio entre chips', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider__features' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function registerPriceStyleSection(): void
    {
        $this->start_controls_section('style_price', [
            'label'     => __('Precio', 'homlity-real-estate'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['layout' => ['hero', 'split'], 'show_price' => 'yes'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'price_typography',
            'selector' => '{{WRAPPER}} .hml-hero-slider__price',
        ]);

        $this->add_control('price_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__price' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function registerButtonStyleSection(): void
    {
        $this->start_controls_section('style_button', [
            'label'     => __('Botones', 'homlity-real-estate'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['layout' => ['hero', 'split']],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'button_typography',
            'selector' => '{{WRAPPER}} .hml-hero-slider__button, {{WRAPPER}} .hml-hero-slider__whatsapp',
        ]);

        $this->add_responsive_control('button_padding', [
            'label'      => __('Relleno', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider__button, {{WRAPPER}} .hml-hero-slider__whatsapp' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('button_radius', [
            'label'      => __('Radio de borde', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 999]],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider__button, {{WRAPPER}} .hml-hero-slider__whatsapp' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->start_controls_tabs('button_tabs');

        $this->start_controls_tab('button_tab_normal', ['label' => __('Normal', 'homlity-real-estate')]);

        $this->add_control('button_color', [
            'label'     => __('Color del texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__button' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_background', [
            'label'     => __('Color de fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__button' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'button_border',
            'selector' => '{{WRAPPER}} .hml-hero-slider__button',
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('button_tab_hover', ['label' => __('Hover', 'homlity-real-estate')]);

        $this->add_control('button_color_hover', [
            'label'     => __('Color del texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__button:hover, {{WRAPPER}} .hml-hero-slider__button:focus-visible' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_background_hover', [
            'label'     => __('Color de fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__button:hover, {{WRAPPER}} .hml-hero-slider__button:focus-visible' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('button_border_color_hover', [
            'label'     => __('Color del borde', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__button:hover, {{WRAPPER}} .hml-hero-slider__button:focus-visible' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_control('whatsapp_heading', [
            'label'     => __('Botón de WhatsApp', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['show_whatsapp' => 'yes'],
        ]);

        $this->add_control('whatsapp_color', [
            'label'     => __('Color del texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'condition' => ['show_whatsapp' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__whatsapp' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('whatsapp_background', [
            'label'     => __('Color de fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'condition' => ['show_whatsapp' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider__whatsapp' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'button_shadow',
            'label'    => __('Sombra', 'homlity-real-estate'),
            'selector' => '{{WRAPPER}} .hml-hero-slider__button, {{WRAPPER}} .hml-hero-slider__whatsapp',
        ]);

        $this->end_controls_section();
    }

    private function registerNavigationStyleSection(): void
    {
        $this->start_controls_section('style_navigation', [
            'label' => __('Flechas y paginación', 'homlity-real-estate'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('arrows_heading', [
            'label'     => __('Flechas', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'condition' => ['show_arrows' => 'yes'],
        ]);

        $this->add_control('arrows_color', [
            'label'     => __('Color', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'condition' => ['show_arrows' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-arrow-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('arrows_size', [
            'label'      => __('Tamaño', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 14, 'max' => 72]],
            'condition'  => ['show_arrows' => 'yes'],
            'selectors'  => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-arrow-size: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('arrows_background', [
            'label'     => __('Color de fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'condition' => ['show_arrows' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider .swiper-button-prev, {{WRAPPER}} .hml-hero-slider .swiper-button-next' => 'background-color: {{VALUE}}; width: calc(var(--hml-hero-arrow-size) * 1.9); height: calc(var(--hml-hero-arrow-size) * 1.9); border-radius: 50%;',
            ],
        ]);

        $this->add_control('pagination_heading', [
            'label'     => __('Paginación', 'homlity-real-estate'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['show_pagination' => 'yes'],
        ]);

        $this->add_control('bullet_color', [
            'label'     => __('Color inactivo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'condition' => ['show_pagination' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-bullet: {{VALUE}};',
            ],
        ]);

        $this->add_control('bullet_color_active', [
            'label'     => __('Color activo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'condition' => ['show_pagination' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider' => '--hml-hero-bullet-active: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('bullet_size', [
            'label'      => __('Tamaño de los puntos', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 4, 'max' => 24]],
            'condition'  => [
                'show_pagination' => 'yes',
                'pagination_type' => 'bullets',
            ],
            'selectors' => [
                '{{WRAPPER}} .hml-hero-slider .swiper-pagination-bullet' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    // ── Render ────────────────────────────────────────────────────────────────

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $config   = HeroSliderConfig::fromElementor($settings);

        $query = new \WP_Query($config->queryArgs());

        // An empty hero is invisible in the editor, which reads as a broken
        // widget. On the front end the configured message (or nothing) is
        // still what the visitor gets.
        if (!$query->have_posts() && $this->isElementorEditorRequest()) {
            printf(
                '<p class="hml-hero-slider__empty">%s</p>',
                esc_html__(
                    'Vista previa: la consulta no devolvió inmuebles. Ajusta los filtros en la pestaña "Consulta".',
                    'homlity-real-estate'
                )
            );

            return;
        }

        TemplateService::includeComponent('property-hero-slider.php', [
            'query'        => $query,
            'options'      => $config->templateOptions(),
            'card_options' => ListingConfig::fromElementor($settings)->cardOptions(),
        ]);
    }

    /**
     * @return array<string,string>
     */
    private function getTermsOptions(string $taxonomy): array
    {
        static $cache = [];

        if (isset($cache[$taxonomy])) {
            return $cache[$taxonomy];
        }

        $options = ['' => __('Todos', 'homlity-real-estate')];
        $terms   = get_terms([
            'taxonomy'               => $taxonomy,
            'hide_empty'             => false,
            'fields'                 => 'id=>name',
            'update_term_meta_cache' => false,
        ]);

        if (!is_wp_error($terms)) {
            foreach ($terms as $termId => $termName) {
                $options[(string) $termId] = (string) $termName;
            }
        }

        $cache[$taxonomy] = $options;

        return $cache[$taxonomy];
    }

    private function isElementorEditorRequest(): bool
    {
        if (!class_exists('\\Elementor\\Plugin')) {
            return false;
        }

        $elementor = \Elementor\Plugin::instance();

        return (
            isset($elementor->editor)
            && method_exists($elementor->editor, 'is_edit_mode')
            && $elementor->editor->is_edit_mode()
        ) || (
            isset($elementor->preview)
            && method_exists($elementor->preview, 'is_preview_mode')
            && $elementor->preview->is_preview_mode()
        );
    }
}
