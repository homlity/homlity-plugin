<?php
/**
 * Elementor widget: property listing with grid/map view, filters and sort.
 *
 * This widget is a thin adapter: it translates Elementor controls into a
 * ListingConfig value object and delegates all rendering to ListingRenderer.
 */

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
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

        $this->add_control('show_sort', [
            'label'   => __('Mostrar selector de orden', 'homlity-plugin'),
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
