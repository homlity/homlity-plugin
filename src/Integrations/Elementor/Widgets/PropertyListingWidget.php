<?php
/**
 * Elementor widget: property listing with grid/map view, filters and sort.
 *
 * This widget is a thin adapter: it translates Elementor controls into a
 * ListingConfig value object and delegates all rendering to ListingRenderer.
 */

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyListingWidget extends Widget_Base
{
    public function get_name(): string  { return 'property_listing'; }
    public function get_title(): string { return __('Listado de inmuebles', 'homlity-plugin'); }
    public function get_icon(): string  { return 'eicon-posts-grid'; }

    public function get_categories(): array
    {
        return ['homlity-plugin'];
    }

    protected function register_controls(): void
    {
        // ── Presentación ─────────────────────────────────────────────────────
        $this->start_controls_section('layout', ['label' => __('Presentación', 'homlity-plugin')]);

        $this->add_control('template', [
            'label'   => __('Diseño de plantilla', 'homlity-plugin'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'default'   => __('Predeterminado (CSS propio)', 'homlity-plugin'),
                'bootstrap' => __('Bootstrap 5', 'homlity-plugin'),
            ],
            'default' => 'default',
        ]);

        $this->add_control('default_view', [
            'label'   => __('Vista por defecto', 'homlity-plugin'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'grid' => __('Grilla / Cards', 'homlity-plugin'),
                'map'  => __('Mapa', 'homlity-plugin'),
            ],
            'default' => 'grid',
        ]);

        $this->add_control('show_view_toggle', [
            'label'   => __('Botón para cambiar de vista', 'homlity-plugin'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('columns', [
            'label'   => __('Columnas en grilla', 'homlity-plugin'),
            'type'    => Controls_Manager::SELECT,
            'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
            'default' => '3',
        ]);

        $this->end_controls_section();

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

        foreach ([
            'card_show_title' => __('Mostrar título', 'homlity-plugin'),
            'card_show_excerpt' => __('Mostrar descripción corta', 'homlity-plugin'),
            'card_show_operation' => __('Mostrar gestión (venta/arriendo)', 'homlity-plugin'),
            'card_show_price' => __('Mostrar valor de gestión', 'homlity-plugin'),
            'card_show_features' => __('Mostrar características', 'homlity-plugin'),
            'card_show_whatsapp' => __('Mostrar botón WhatsApp asesor', 'homlity-plugin'),
        ] as $key => $label) {
            $this->add_control($key, [
                'label' => $label,
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]);
        }

        $this->add_control('card_whatsapp_label', [
            'label' => __('Texto botón WhatsApp', 'homlity-plugin'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Hablar por WhatsApp', 'homlity-plugin'),
            'condition' => ['card_show_whatsapp' => 'yes'],
        ]);

        foreach ([
            'card_feature_area' => __('Área', 'homlity-plugin'),
            'card_feature_bedrooms' => __('Alcobas', 'homlity-plugin'),
            'card_feature_bathrooms' => __('Baños', 'homlity-plugin'),
            'card_feature_parking' => __('Garajes', 'homlity-plugin'),
            'card_feature_area_lot' => __('Área de lote', 'homlity-plugin'),
            'card_feature_area_private' => __('Área privada', 'homlity-plugin'),
            'card_feature_area_built' => __('Área construida', 'homlity-plugin'),
            'card_feature_age' => __('Edad inmueble', 'homlity-plugin'),
            'card_feature_condition' => __('Estado inmueble', 'homlity-plugin'),
            'card_feature_code' => __('Código inmueble', 'homlity-plugin'),
        ] as $key => $label) {
            $this->add_control($key, [
                'label' => $label,
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => ['card_show_features' => 'yes'],
            ]);
        }

        $this->end_controls_section();

        // ── Consulta ──────────────────────────────────────────────────────────
        $this->start_controls_section('query', ['label' => __('Consulta', 'homlity-plugin')]);

        $this->add_control('query_mode', [
            'label' => __('Origen de la consulta', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'custom' => __('Filtros configurados en el widget', 'homlity-plugin'),
                'current' => __('Consulta actual (archivo, categoría, etiqueta, búsqueda)', 'homlity-plugin'),
            ],
            'default' => 'custom',
        ]);

        $this->add_control('posts_per_page', [
            'label'   => __('Inmuebles por página', 'homlity-plugin'),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 1,
            'max'     => 100,
            'default' => 12,
        ]);

        $this->add_control('default_orderby', [
            'label'   => __('Orden por defecto', 'homlity-plugin'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->getSortOptions(),
            'default' => 'date',
        ]);

        $this->add_control('featured_only', [
            'label'   => __('Solo destacados', 'homlity-plugin'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        $this->add_control('search_keyword', [
            'label' => __('Buscar por palabra clave', 'homlity-plugin'),
            'type' => Controls_Manager::TEXT,
            'default' => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_category', [
            'label' => __('Fijar categoría', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_CATEGORY),
            'default' => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_operation', [
            'label'   => __('Fijar gestión (venta/arriendo)', 'homlity-plugin'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_OPERATION),
            'default' => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_type', [
            'label'   => __('Fijar tipo de inmueble', 'homlity-plugin'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_TYPE),
            'default' => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_tag', [
            'label' => __('Fijar etiqueta', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_TAG),
            'default' => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_feature', [
            'label' => __('Fijar característica', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_FEATURE),
            'default' => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_country', [
            'label' => __('Fijar país', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_COUNTRY),
            'default' => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_state', [
            'label' => __('Fijar departamento / provincia', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_STATE),
            'default' => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_city', [
            'label' => __('Fijar ciudad', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_CITY),
            'default' => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_neighborhood', [
            'label' => __('Fijar barrio', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD),
            'default' => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_nearby', [
            'label' => __('Fijar lugar cercano', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT,
            'options' => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_NEARBY),
            'default' => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('geo_query', ['label' => __('Georreferenciación', 'homlity-plugin')]);

        $this->add_control('geo_latitude', [
            'label' => __('Latitud centro', 'homlity-plugin'),
            'type' => Controls_Manager::TEXT,
            'default' => '',
        ]);

        $this->add_control('geo_longitude', [
            'label' => __('Longitud centro', 'homlity-plugin'),
            'type' => Controls_Manager::TEXT,
            'default' => '',
        ]);

        $this->add_control('geo_radius_km', [
            'label' => __('Radio en kilómetros', 'homlity-plugin'),
            'type' => Controls_Manager::NUMBER,
            'min' => 0,
            'step' => 0.5,
            'default' => 0,
        ]);

        $this->end_controls_section();

        $this->start_controls_section('toolbar', ['label' => __('Barra de resultados', 'homlity-plugin')]);

        $this->add_control('show_results_count', [
            'label'   => __('Mostrar cantidad de resultados', 'homlity-plugin'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_sort', [
            'label'   => __('Mostrar selector de orden', 'homlity-plugin'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_pagination', [
            'label'   => __('Mostrar paginación', 'homlity-plugin'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->end_controls_section();

        // ── Mapa ──────────────────────────────────────────────────────────────
        $this->start_controls_section('map_settings', ['label' => __('Configuración del mapa', 'homlity-plugin')]);

        $this->add_control('map_height', [
            'label'      => __('Altura del mapa', 'homlity-plugin'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 200, 'max' => 1000, 'step' => 10]],
            'default'    => ['size' => 500, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .property-listing__map' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('map_zoom', [
            'label'   => __('Zoom inicial', 'homlity-plugin'),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 1,
            'max'     => 18,
            'default' => 12,
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_card', [
            'label' => __('Tarjeta', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('card_bg_color', [
            'label' => __('Fondo', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card, {{WRAPPER}} .property-card-bs' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'card_border',
            'selector' => '{{WRAPPER}} .property-card, {{WRAPPER}} .property-card-bs',
        ]);

        $this->add_responsive_control('card_radius', [
            'label' => __('Radio de borde', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'selectors' => [
                '{{WRAPPER}} .property-card, {{WRAPPER}} .property-card-bs' => 'border-radius: {{SIZE}}{{UNIT}}; overflow: hidden;',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'card_shadow',
            'selector' => '{{WRAPPER}} .property-card, {{WRAPPER}} .property-card-bs',
        ]);

        $this->add_responsive_control('card_padding', [
            'label' => __('Padding interno', 'homlity-plugin'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors' => [
                '{{WRAPPER}} .property-card__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                '{{WRAPPER}} .property-card-bs .card-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_card_image', [
            'label' => __('Imagen', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('card_image_height', [
            'label' => __('Altura', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 120, 'max' => 520]],
            'selectors' => [
                '{{WRAPPER}} .property-card__gallery > img, {{WRAPPER}} .property-card__gallery-slider > img, {{WRAPPER}} .property-card-bs .card-img-top' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_image_radius', [
            'label' => __('Radio de borde imagen', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'selectors' => [
                '{{WRAPPER}} .property-card__gallery > img, {{WRAPPER}} .property-card__gallery-slider > img, {{WRAPPER}} .property-card-bs .card-img-top' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_card_text', [
            'label' => __('Título y Texto', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'card_title_typography',
            'label' => __('Tipografía título', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-card__title, {{WRAPPER}} .property-card-bs .card-title, {{WRAPPER}} .property-card__overlay-title',
        ]);

        $this->add_control('card_title_color', [
            'label' => __('Color título', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__title, {{WRAPPER}} .property-card-bs .card-title, {{WRAPPER}} .property-card__overlay-title' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'card_excerpt_typography',
            'label' => __('Tipografía descripción', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-card__excerpt, {{WRAPPER}} .property-card-bs .property-card__excerpt, {{WRAPPER}} .property-card__overlay-location',
        ]);

        $this->add_control('card_excerpt_color', [
            'label' => __('Color descripción', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__excerpt, {{WRAPPER}} .property-card-bs .property-card__excerpt, {{WRAPPER}} .property-card__overlay-location' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'card_operation_typography',
            'label' => __('Tipografía gestión', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-card__operation, {{WRAPPER}} .property-card__overlay-operation',
        ]);

        $this->add_control('card_operation_color', [
            'label' => __('Color gestión', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__operation, {{WRAPPER}} .property-card__overlay-operation' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_card_meta', [
            'label' => __('Precio y Características', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'card_price_typography',
            'label' => __('Tipografía precio', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-card__price, {{WRAPPER}} .property-card-bs [itemprop="price"], {{WRAPPER}} .property-card__overlay-price',
        ]);

        $this->add_control('card_price_color', [
            'label' => __('Color precio', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__price, {{WRAPPER}} .property-card-bs [itemprop="price"], {{WRAPPER}} .property-card__overlay-price' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'card_features_typography',
            'label' => __('Tipografía características', 'homlity-plugin'),
            'selector' => '{{WRAPPER}} .property-card__features, {{WRAPPER}} .property-card-bs .property-card__features',
        ]);

        $this->add_control('card_features_color', [
            'label' => __('Color características', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__features, {{WRAPPER}} .property-card-bs .property-card__features' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_card_whatsapp', [
            'label' => __('Botón WhatsApp', 'homlity-plugin'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'card_whatsapp_typography',
            'selector' => '{{WRAPPER}} .property-card__whatsapp',
        ]);

        $this->add_control('card_whatsapp_text_color', [
            'label' => __('Color texto', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__whatsapp' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('card_whatsapp_bg_color', [
            'label' => __('Color fondo', 'homlity-plugin'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-card__whatsapp' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'card_whatsapp_border',
            'selector' => '{{WRAPPER}} .property-card__whatsapp',
        ]);

        $this->add_responsive_control('card_whatsapp_radius', [
            'label' => __('Radio de borde', 'homlity-plugin'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'selectors' => [
                '{{WRAPPER}} .property-card__whatsapp' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $config = ListingConfig::fromElementor($this->get_settings_for_display());
        (new ListingRenderer())->render($config);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getTermsOptions(string $taxonomy): array
    {
        $options = ['' => __('Todos', 'homlity-plugin')];
        $terms   = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $options[(string) $term->term_id] = $term->name;
            }
        }

        return $options;
    }

    private function getSortOptions(): array
    {
        return [
            'date'       => __('Más recientes', 'homlity-plugin'),
            'price_asc'  => __('Precio: menor a mayor', 'homlity-plugin'),
            'price_desc' => __('Precio: mayor a menor', 'homlity-plugin'),
            'title'      => __('Nombre A–Z', 'homlity-plugin'),
        ];
    }
}
