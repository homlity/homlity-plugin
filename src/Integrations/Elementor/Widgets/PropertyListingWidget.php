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
    use PropertyCardStylesTrait;

    public function get_name(): string  { return 'property_listing'; }
    public function get_title(): string { return __('Listado de inmuebles', 'homlity-real-estate'); }
    public function get_icon(): string  { return 'eicon-posts-grid'; }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }

    protected function register_controls(): void
    {
        // ── Presentación ─────────────────────────────────────────────────────
        $this->start_controls_section('layout', ['label' => __('Presentación', 'homlity-real-estate')]);

        $this->add_control('template', [
            'label'   => __('Diseño de plantilla', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'default'   => __('Predeterminado (CSS propio)', 'homlity-real-estate'),
                'bootstrap' => __('Bootstrap 5', 'homlity-real-estate'),
            ],
            'default' => 'default',
        ]);

        $this->add_control('default_view', [
            'label'   => __('Vista por defecto', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'grid' => __('Grilla / Cards', 'homlity-real-estate'),
                'map'  => __('Mapa', 'homlity-real-estate'),
            ],
            'default' => 'grid',
        ]);

        $this->add_control('show_grid_view', [
            'label'   => __('Mostrar vista Cards', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_map_view', [
            'label'   => __('Mostrar vista Mapa', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_view_toggle', [
            'label'   => __('Botón para cambiar de vista', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => [
                'show_grid_view' => 'yes',
                'show_map_view'  => 'yes',
            ],
        ]);

        $this->add_control('columns', [
            'label'   => __('Columnas en grilla', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
            'default' => '3',
        ]);

        $this->end_controls_section();

        // ── Contenido de la tarjeta (shared via trait) ────────────────────────
        $this->registerCardContentControls();

        // ── Consulta ──────────────────────────────────────────────────────────
        $this->start_controls_section('query', ['label' => __('Consulta', 'homlity-real-estate')]);

        $this->add_control('query_mode', [
            'label'   => __('Origen de la consulta', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'custom'          => __('Filtros configurados en el widget', 'homlity-real-estate'),
                'current'         => __('Consulta actual (archivo, categoría, etiqueta, búsqueda)', 'homlity-real-estate'),
                'related_current' => __('Inmuebles relacionados al inmueble de la página', 'homlity-real-estate'),
            ],
            'default' => 'custom',
        ]);

        $this->add_control('posts_per_page', [
            'label'   => __('Inmuebles por página', 'homlity-real-estate'),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 1,
            'max'     => 100,
            'default' => 12,
        ]);

        $this->add_control('default_orderby', [
            'label'   => __('Orden por defecto', 'homlity-real-estate'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->getSortOptions(),
            'default' => 'date',
        ]);

        $this->add_control('featured_only', [
            'label'   => __('Solo destacados', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        $this->add_control('search_keyword', [
            'label'     => __('Buscar por palabra clave', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_category', [
            'label'     => __('Fijar categoría', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_CATEGORY),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_operation', [
            'label'     => __('Fijar gestión (venta/arriendo)', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_OPERATION),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_type', [
            'label'     => __('Fijar tipo de inmueble', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_TYPE),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_tag', [
            'label'     => __('Fijar etiqueta', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_TAG),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_tag_ids', [
            'label'       => __('Fijar etiquetas (múltiples)', 'homlity-real-estate'),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'label_block' => true,
            'options'     => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_TAG),
            'default'     => [],
            'condition'   => ['query_mode' => 'custom'],
            'description' => __('Si seleccionas varias, se usarán para filtrar el listado por esas etiquetas.', 'homlity-real-estate'),
        ]);

        $this->add_control('use_current_property_tags', [
            'label'       => __('Filtrar por etiquetas del inmueble actual', 'homlity-real-estate'),
            'type'        => Controls_Manager::SWITCHER,
            'default'     => '',
            'condition'   => ['query_mode' => 'custom'],
            'description' => __('En páginas de detalle de inmueble, usa sus etiquetas para listar relacionados.', 'homlity-real-estate'),
        ]);

        $this->add_control('preset_feature', [
            'label'     => __('Fijar característica', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_FEATURE),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_country', [
            'label'     => __('Fijar país', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_COUNTRY),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_state', [
            'label'     => __('Fijar departamento / provincia', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_STATE),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_city', [
            'label'     => __('Fijar ciudad', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_CITY),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_neighborhood', [
            'label'     => __('Fijar barrio', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_nearby', [
            'label'     => __('Fijar lugar cercano', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_NEARBY),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        // ── Opciones de inmuebles relacionados (solo visible en modo related_current) ──
        $this->add_control('related_property_id', [
            'label'       => __('Inmueble de referencia (solo editor)', 'homlity-real-estate'),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 0,
            'default'     => 0,
            'condition'   => ['query_mode' => 'related_current'],
            'description' => __('ID del inmueble a usar como referencia en la vista previa del editor. En el frontend se detecta automáticamente el inmueble de la página actual.', 'homlity-real-estate'),
        ]);

        $this->add_control('related_taxonomies', [
            'label'       => __('Taxonomías para encontrar relacionados', 'homlity-real-estate'),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'label_block' => true,
            'options'     => [
                'property_tag'          => __('Etiquetas', 'homlity-real-estate'),
                'property_type'         => __('Tipo de inmueble', 'homlity-real-estate'),
                'property_operation'    => __('Gestión (venta / arriendo)', 'homlity-real-estate'),
                'property_category'     => __('Categoría', 'homlity-real-estate'),
                'property_city'         => __('Ciudad', 'homlity-real-estate'),
                'property_state'        => __('Departamento / Provincia', 'homlity-real-estate'),
                'property_country'      => __('País', 'homlity-real-estate'),
                'property_neighborhood' => __('Barrio', 'homlity-real-estate'),
            ],
            'default'     => [],
            'condition'   => ['query_mode' => 'related_current'],
            'description' => __('Vacío = usa todas las taxonomías disponibles.', 'homlity-real-estate'),
        ]);

        $this->add_control('related_strategy', [
            'label'     => __('Estrategia de relación', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'any'        => __('Al menos una coincidencia (OR)', 'homlity-real-estate'),
                'all'        => __('Coincidencia en todas las taxonomías (AND)', 'homlity-real-estate'),
                'tags_first' => __('Etiquetas primero, luego cualquier coincidencia', 'homlity-real-estate'),
            ],
            'default'   => 'any',
            'condition' => ['query_mode' => 'related_current'],
        ]);

        $this->add_control('related_fallback', [
            'label'     => __('Si no hay relacionados…', 'homlity-real-estate'),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'recent'    => __('Mostrar los más recientes', 'homlity-real-estate'),
                'same_city' => __('Mostrar inmuebles de la misma ciudad', 'homlity-real-estate'),
                'empty'     => __('Mostrar mensaje personalizado', 'homlity-real-estate'),
                'hide'      => __('Ocultar el widget', 'homlity-real-estate'),
            ],
            'default'   => 'recent',
            'condition' => ['query_mode' => 'related_current'],
        ]);

        $this->add_control('related_empty_message', [
            'label'     => __('Mensaje de vacío', 'homlity-real-estate'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('No hay inmuebles relacionados disponibles.', 'homlity-real-estate'),
            'condition' => ['query_mode' => 'related_current', 'related_fallback' => 'empty'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('geo_query', ['label' => __('Georreferenciación', 'homlity-real-estate')]);

        $this->add_control('geo_latitude', [
            'label'   => __('Latitud centro', 'homlity-real-estate'),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ]);

        $this->add_control('geo_longitude', [
            'label'   => __('Longitud centro', 'homlity-real-estate'),
            'type'    => Controls_Manager::TEXT,
            'default' => '',
        ]);

        $this->add_control('geo_radius_km', [
            'label'   => __('Radio en kilómetros', 'homlity-real-estate'),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 0,
            'step'    => 0.5,
            'default' => 0,
        ]);

        $this->end_controls_section();

        $this->start_controls_section('toolbar', ['label' => __('Barra de resultados', 'homlity-real-estate')]);

        $this->add_control('show_results_count', [
            'label'   => __('Mostrar cantidad de resultados', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_sort', [
            'label'   => __('Mostrar selector de orden', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_pagination', [
            'label'   => __('Mostrar paginación', 'homlity-real-estate'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->end_controls_section();

        // ── Mapa ──────────────────────────────────────────────────────────────
        $this->start_controls_section('map_settings', ['label' => __('Configuración del mapa', 'homlity-real-estate')]);

        $this->add_control('map_height', [
            'label'      => __('Altura del mapa', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 200, 'max' => 1000, 'step' => 10]],
            'default'    => ['size' => 500, 'unit' => 'px'],
            'selectors'  => ['{{WRAPPER}} .property-listing__map' => 'height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('map_zoom', [
            'label'   => __('Zoom inicial', 'homlity-real-estate'),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 1,
            'max'     => 18,
            'default' => 12,
        ]);

        $this->end_controls_section();

        // ── Estilos tarjeta (shared via trait) ────────────────────────────────
        $this->registerCardStyleControls();

        // ── Botones grilla/mapa (listing-only style) ──────────────────────────
        $this->start_controls_section('style_view_toggle', [
            'label'     => __('Botones grilla/mapa', 'homlity-real-estate'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => [
                'show_grid_view'   => 'yes',
                'show_map_view'    => 'yes',
                'show_view_toggle' => 'yes',
            ],
        ]);

        $this->add_responsive_control('view_toggle_icon_size', [
            'label'      => __('Tamaño icono', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 10, 'max' => 36]],
            'selectors'  => ['{{WRAPPER}} .property-listing__view-btn svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('view_toggle_padding', [
            'label'      => __('Padding botón', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em'],
            'selectors'  => ['{{WRAPPER}} .property-listing__view-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'view_toggle_border',
            'selector' => '{{WRAPPER}} .property-listing__view-btn',
        ]);

        $this->add_responsive_control('view_toggle_radius', [
            'label'      => __('Radio botón', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .property-listing__view-btn' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->start_controls_tabs('view_toggle_states');

        $this->start_controls_tab('view_toggle_normal', ['label' => __('Normal', 'homlity-real-estate')]);
        $this->add_control('view_toggle_text_color', [
            'label'     => __('Color icono/texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-listing__view-btn' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('view_toggle_bg_color', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-listing__view-btn' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('view_toggle_hover', ['label' => __('Hover', 'homlity-real-estate')]);
        $this->add_control('view_toggle_text_color_hover', [
            'label'     => __('Color icono/texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-listing__view-btn:hover' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('view_toggle_bg_color_hover', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-listing__view-btn:hover' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('view_toggle_active', ['label' => __('Activo', 'homlity-real-estate')]);
        $this->add_control('view_toggle_text_color_active', [
            'label'     => __('Color icono/texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-listing__view-btn.is-active, {{WRAPPER}} .property-listing__view-btn.active' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('view_toggle_bg_color_active', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-listing__view-btn.is-active, {{WRAPPER}} .property-listing__view-btn.active' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'view_toggle_shadow',
            'selector' => '{{WRAPPER}} .property-listing__view-btn',
        ]);

        $this->end_controls_section();

        // ── Paginación (listing-only style) ───────────────────────────────────
        $this->start_controls_section('style_pagination', [
            'label'     => __('Paginador', 'homlity-real-estate'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['show_pagination' => 'yes'],
        ]);

        $this->add_responsive_control('pagination_gap', [
            'label'      => __('Espaciado entre botones', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 30]],
            'selectors'  => ['{{WRAPPER}} .property-listing__pagination' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('pagination_margin_top', [
            'label'      => __('Margen superior', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 120]],
            'selectors'  => ['{{WRAPPER}} .property-listing__pagination' => 'margin-top: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'pagination_typography',
            'selector' => '{{WRAPPER}} .property-listing__page-btn',
        ]);

        $this->add_responsive_control('pagination_btn_width', [
            'label'      => __('Ancho mínimo botón', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 28, 'max' => 120]],
            'selectors'  => ['{{WRAPPER}} .property-listing__page-btn' => 'min-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('pagination_btn_height', [
            'label'      => __('Alto botón', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 28, 'max' => 80]],
            'selectors'  => ['{{WRAPPER}} .property-listing__page-btn' => 'height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('pagination_btn_padding', [
            'label'      => __('Padding botón', 'homlity-real-estate'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => ['{{WRAPPER}} .property-listing__page-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'pagination_btn_border',
            'selector' => '{{WRAPPER}} .property-listing__page-btn',
        ]);

        $this->add_responsive_control('pagination_btn_radius', [
            'label'      => __('Radio botón', 'homlity-real-estate'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .property-listing__page-btn' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'pagination_btn_shadow',
            'selector' => '{{WRAPPER}} .property-listing__page-btn',
        ]);

        $this->start_controls_tabs('pagination_states');

        $this->start_controls_tab('pagination_state_normal', ['label' => __('Normal', 'homlity-real-estate')]);
        $this->add_control('pagination_btn_text_color', [
            'label'     => __('Color texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-listing__page-btn' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('pagination_btn_bg_color', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-listing__page-btn' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_control('pagination_btn_border_color', [
            'label'     => __('Color borde', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-listing__page-btn' => 'border-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('pagination_state_hover', ['label' => __('Hover', 'homlity-real-estate')]);
        $this->add_control('pagination_btn_text_color_hover', [
            'label'     => __('Color texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-listing__page-btn:hover' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('pagination_btn_bg_color_hover', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-listing__page-btn:hover' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_control('pagination_btn_border_color_hover', [
            'label'     => __('Color borde', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .property-listing__page-btn:hover' => 'border-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('pagination_state_active', ['label' => __('Activo', 'homlity-real-estate')]);
        $this->add_control('pagination_btn_text_color_active', [
            'label'     => __('Color texto', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-listing__page-btn.is-active' => 'color: {{VALUE}};',
                '{{WRAPPER}} .property-listing__pagination .page-item.active .property-listing__page-btn' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('pagination_btn_bg_color_active', [
            'label'     => __('Fondo', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-listing__page-btn.is-active' => 'background-color: {{VALUE}};',
                '{{WRAPPER}} .property-listing__pagination .page-item.active .property-listing__page-btn' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('pagination_btn_border_color_active', [
            'label'     => __('Color borde', 'homlity-real-estate'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .property-listing__page-btn.is-active' => 'border-color: {{VALUE}};',
                '{{WRAPPER}} .property-listing__pagination .page-item.active .property-listing__page-btn' => 'border-color: {{VALUE}};',
            ],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

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
        $options = ['' => __('Todos', 'homlity-real-estate')];
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
            'date'       => __('Más recientes', 'homlity-real-estate'),
            'price_asc'  => __('Precio: menor a mayor', 'homlity-real-estate'),
            'price_desc' => __('Precio: mayor a menor', 'homlity-real-estate'),
            'title'      => __('Nombre A–Z', 'homlity-real-estate'),
        ];
    }
}
