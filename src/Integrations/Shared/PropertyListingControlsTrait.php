<?php

namespace Homlity\PluginInmobiliario\Integrations\Shared;

use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Services\AgentProfileService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\LocalityPostType;

trait PropertyListingControlsTrait
{
    use PropertyListingStylesTrait;
    protected function registerListingControls(): void
    {
        // ── Presentación ─────────────────────────────────────────────────────
        $this->start_controls_section('layout', ['label' => __('Presentación', 'homlity-real-estate')]);

        $this->add_control('template', [
            'label'   => __('Diseño de plantilla', 'homlity-real-estate'),
            'type'    => 'select',
            'options' => [
                'default'   => __('Predeterminado (CSS propio)', 'homlity-real-estate'),
                'bootstrap' => __('Bootstrap 5', 'homlity-real-estate'),
            ],
            'default' => 'default',
        ]);

        $this->add_control('default_view', [
            'label'   => __('Vista por defecto', 'homlity-real-estate'),
            'type'    => 'select',
            'options' => [
                'grid' => __('Grilla / Cards', 'homlity-real-estate'),
                'map'  => __('Mapa', 'homlity-real-estate'),
            ],
            'default' => 'grid',
        ]);

        $this->add_control('show_grid_view', [
            'label'   => __('Mostrar vista Cards', 'homlity-real-estate'),
            'type'    => 'switcher',
            'default' => 'yes',
        ]);

        $this->add_control('show_map_view', [
            'label'   => __('Mostrar vista Mapa', 'homlity-real-estate'),
            'type'    => 'switcher',
            'default' => 'yes',
        ]);

        $this->add_control('show_view_toggle', [
            'label'   => __('Botón para cambiar de vista', 'homlity-real-estate'),
            'type'    => 'switcher',
            'default' => 'yes',
            'condition' => [
                'show_grid_view' => 'yes',
                'show_map_view'  => 'yes',
            ],
        ]);

        $this->add_control('view_toggle_grid_icon', [
            'label'   => __('Icono vista Cards', 'homlity-real-estate'),
            'type'    => 'icons',
            'default' => [
                'value'   => 'fas fa-th-large',
                'library' => 'fa-solid',
            ],
            'condition' => [
                'show_grid_view'   => 'yes',
                'show_map_view'    => 'yes',
                'show_view_toggle' => 'yes',
            ],
        ]);

        $this->add_control('view_toggle_map_icon', [
            'label'   => __('Icono vista Mapa', 'homlity-real-estate'),
            'type'    => 'icons',
            'default' => [
                'value'   => 'fas fa-map-marker-alt',
                'library' => 'fa-solid',
            ],
            'condition' => [
                'show_grid_view'   => 'yes',
                'show_map_view'    => 'yes',
                'show_view_toggle' => 'yes',
            ],
        ]);

        $this->add_control('custom_columns', ['label' => __('Aplicar columnas a Bootstrap', 'homlity-real-estate'), 'description' => __('Activa esta opción para sustituir las columnas históricas de Bootstrap.', 'homlity-real-estate'), 'type' => 'switcher', 'condition' => ['template' => 'bootstrap']]);

        $this->add_responsive_control('columns', [
            'label'   => __('Columnas en grilla', 'homlity-real-estate'),
            'type'    => 'select',
            'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6'],
            'selectors' => ['{{WRAPPER}} .property-listing' => '--columns: {{VALUE}}; --hpl-columns: {{VALUE}};', '{{WRAPPER}} .property-listing--responsive-columns' => '--hpl-responsive-columns: {{VALUE}};'],
            'default' => '3',
        ]);

        $this->end_controls_section();

        // ── Consulta ──────────────────────────────────────────────────────────
        $this->start_controls_section('query', ['label' => __('Consulta', 'homlity-real-estate')]);

        $this->add_control('query_mode', [
            'label'   => __('Origen de la consulta', 'homlity-real-estate'),
            'type'    => 'select',
            'options' => [
                'custom'          => __('Filtros configurados en el widget', 'homlity-real-estate'),
                'current'         => __('Consulta actual (archivo, categoría, etiqueta, búsqueda)', 'homlity-real-estate'),
                'related_current' => __('Inmuebles relacionados al inmueble de la página', 'homlity-real-estate'),
                'related_agent'   => __('Inmuebles del asesor de la página', 'homlity-real-estate'),
            ],
            'default' => 'custom',
        ]);

        $this->add_control('posts_per_page', [
            'label'   => __('Inmuebles por página', 'homlity-real-estate'),
            'type'    => 'number',
            'min'     => 1,
            'max'     => 100,
            'default' => 12,
        ]);

        $this->add_control('default_orderby', [
            'label'   => __('Orden por defecto', 'homlity-real-estate'),
            'type'    => 'select',
            'options' => ListingConfig::sortOptions(),
            'default' => 'date',
        ]);

        $this->add_control('featured_only', [
            'condition' => ['query_mode' => 'custom'],
            'label'   => __('Solo destacados', 'homlity-real-estate'),
            'type'    => 'switcher',
            'default' => '',
        ]);

        $this->add_control('query_heading_search_keyword', ['type' => 'heading', 'label' => __('Filtros fijos', 'homlity-real-estate'), 'separator' => 'before', 'condition' => ['query_mode' => 'custom']]);

        $this->add_control('search_keyword', [
            'label'     => __('Buscar por palabra clave', 'homlity-real-estate'),
            'type'      => 'text',
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_category', [
            'label'     => __('Fijar categoría', 'homlity-real-estate'),
            'type'      => 'select',
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_CATEGORY),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_operation', [
            'label'     => __('Fijar gestión (venta/arriendo)', 'homlity-real-estate'),
            'type'      => 'select',
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_OPERATION),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_type', [
            'label'     => __('Fijar tipo de inmueble', 'homlity-real-estate'),
            'type'      => 'select',
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_TYPE),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_tag', [
            'label'     => __('Fijar etiqueta', 'homlity-real-estate'),
            'type'      => 'select',
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_TAG),
            'default'   => '',
            'condition' => ['query_mode' => '__legacy_hidden__'],
        ]);

        $this->add_control('preset_tag_ids', [
            'label'       => __('Fijar etiquetas (múltiples)', 'homlity-real-estate'),
            'type'        => 'select2',
            'multiple'    => true,
            'label_block' => true,
            'options'     => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_TAG),
            'default'     => [],
            'condition'   => ['query_mode' => 'custom'],
            'description' => __('Si seleccionas varias, se usarán para filtrar el listado por esas etiquetas.', 'homlity-real-estate'),
        ]);

        $this->add_control('use_current_property_tags', [
            'label'       => __('Filtrar por etiquetas del inmueble actual', 'homlity-real-estate'),
            'type'        => 'switcher',
            'default'     => '',
            'condition'   => ['query_mode' => 'custom'],
            'description' => __('Combina las etiquetas del inmueble actual con los filtros fijos. Para buscar relacionados con estrategia y respaldo, usa el modo Inmuebles relacionados.', 'homlity-real-estate'),
        ]);

        $this->add_control('preset_feature', [
            'label'     => __('Fijar característica', 'homlity-real-estate'),
            'type'      => 'select',
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_FEATURE),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('query_heading_preset_country', ['type' => 'heading', 'label' => __('Ubicación', 'homlity-real-estate'), 'separator' => 'before', 'condition' => ['query_mode' => 'custom']]);

        $this->add_control('preset_country', [
            'label'     => __('Fijar país', 'homlity-real-estate'),
            'type'      => 'select',
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_COUNTRY),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_state', [
            'label'     => __('Fijar departamento / provincia', 'homlity-real-estate'),
            'type'      => 'select',
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_STATE),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_city', [
            'label'     => __('Fijar ciudad', 'homlity-real-estate'),
            'type'      => 'select',
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_CITY),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_locality', [
            'label'     => __('Fijar localidad', 'homlity-real-estate'),
            'type'      => 'select',
            'options'   => ['' => __('Todas', 'homlity-real-estate')] + LocalityPostType::publishedOptions(),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_neighborhood', [
            'label'     => __('Fijar barrio', 'homlity-real-estate'),
            'type'      => 'select',
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('preset_nearby', [
            'label'     => __('Fijar lugar cercano', 'homlity-real-estate'),
            'type'      => 'select',
            'options'   => $this->getTermsOptions(PropertyTaxonomies::TAXONOMY_NEARBY),
            'default'   => '',
            'condition' => ['query_mode' => 'custom'],
        ]);

        $this->add_control('query_heading_use_current_agent', ['type' => 'heading', 'label' => __('Asesor', 'homlity-real-estate'), 'separator' => 'before', 'condition' => ['query_mode' => ['custom', 'related_agent']]]);

        $this->add_control('use_current_agent', [
            'label'       => __('Inmuebles del asesor de la página', 'homlity-real-estate'),
            'type'        => 'switcher',
            'default'     => '',
            'description' => __('En la página del asesor, /author/{asesor}/, muestra únicamente los inmuebles de ese asesor.', 'homlity-real-estate'),
            'condition'   => ['query_mode' => '__legacy_hidden__'],
        ]);

        $this->add_control('preset_agent', [
            'label'       => __('Filtrar por asesor', 'homlity-real-estate'),
            'type'        => 'select',
            'options'     => AgentProfileService::agentChoices(),
            'default'     => '',
            'description' => __('Tiene prioridad sobre el asesor de la página.', 'homlity-real-estate'),
            'condition'   => ['query_mode' => 'custom'],
        ]);

        // ── Opciones del asesor de la página (solo visible en modo related_agent) ──
        $this->add_control('related_agent_id', [
            'label'       => __('Asesor (vacío = el de la página)', 'homlity-real-estate'),
            'type'        => 'select',
            'options'     => AgentProfileService::agentChoices(),
            'default'     => '',
            'condition'   => ['query_mode' => 'related_agent'],
            'description' => __('Vacío: toma el asesor de la página que se está viendo, /author/{asesor}/. Elige uno para fijarlo o previsualizarlo en el editor.', 'homlity-real-estate'),
        ]);

        // ── Opciones de inmuebles relacionados (solo visible en modo related_current) ──
        $this->add_control('query_heading_related_property_id', ['type' => 'heading', 'label' => __('Relacionados', 'homlity-real-estate'), 'separator' => 'before', 'condition' => ['query_mode' => 'related_current']]);

        $this->add_control('related_property_id', [
            'label'       => __('Inmueble de referencia (solo editor)', 'homlity-real-estate'),
            'type'        => 'number',
            'min'         => 0,
            'default'     => 0,
            'condition'   => ['query_mode' => 'related_current'],
            'description' => __('ID del inmueble a usar como referencia en la vista previa del editor. En el frontend se detecta automáticamente el inmueble de la página actual.', 'homlity-real-estate'),
        ]);

        $this->add_control('related_taxonomies', [
            'label'       => __('Taxonomías para encontrar relacionados', 'homlity-real-estate'),
            'type'        => 'select2',
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
            'type'      => 'select',
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
            'type'      => 'select',
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
            'type'      => 'text',
            'default'   => __('No hay inmuebles relacionados disponibles.', 'homlity-real-estate'),
            'condition' => ['query_mode' => 'related_current', 'related_fallback' => 'empty'],
        ]);

        $this->add_control('geo_query', ['label' => __('Georreferenciación', 'homlity-real-estate'), 'type' => 'heading', 'separator' => 'before', 'condition' => ['query_mode' => 'custom']]);

        $this->add_control('geo_latitude', [
            'condition' => ['query_mode' => 'custom'],
            'label'   => __('Latitud centro', 'homlity-real-estate'),
            'type'    => 'text',
            'default' => '',
        ]);

        $this->add_control('geo_longitude', [
            'condition' => ['query_mode' => 'custom'],
            'label'   => __('Longitud centro', 'homlity-real-estate'),
            'type'    => 'text',
            'default' => '',
        ]);

        $this->add_control('geo_radius_km', [
            'condition' => ['query_mode' => 'custom'],
            'label'   => __('Radio en kilómetros', 'homlity-real-estate'),
            'type'    => 'number',
            'min'     => 0,
            'step'    => 0.5,
            'default' => 0,
        ]);

        $this->end_controls_section();

        $this->start_controls_section('toolbar', ['label' => __('Barra de resultados', 'homlity-real-estate')]);

        $this->add_control('show_results_count', [
            'label'   => __('Mostrar cantidad de resultados', 'homlity-real-estate'),
            'type'    => 'switcher',
            'default' => 'yes',
        ]);

        $this->add_control('show_sort', [
            'label'   => __('Mostrar selector de orden', 'homlity-real-estate'),
            'type'    => 'switcher',
            'default' => 'yes',
        ]);

        $this->add_control('show_pagination', [
            'label'   => __('Mostrar paginación', 'homlity-real-estate'),
            'type'    => 'switcher',
            'default' => 'yes',
        ]);

        $this->end_controls_section();

        $this->registerCardContentControls();

        // ── Mapa ──────────────────────────────────────────────────────────────
        $this->start_controls_section('map_settings', ['label' => __('Configuración del mapa', 'homlity-real-estate')]);

        $this->add_responsive_control('map_height', [
            'label'      => __('Altura del mapa', 'homlity-real-estate'),
            'type'       => 'slider',
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 200, 'max' => 1000, 'step' => 10]],
            'default'    => ['size' => 500, 'unit' => 'px'],
            'selectors'  => ['{{WRAPPER}} .property-listing' => '--hpl-map-height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('map_zoom', [
            'label'   => __('Zoom inicial', 'homlity-real-estate'),
            'type'    => 'number',
            'min'     => 1,
            'max'     => 18,
            'default' => 12,
        ]);

        $this->end_controls_section();

        $this->start_controls_section('empty_content', ['label' => __('Mensaje sin resultados', 'homlity-real-estate')]);
        $this->add_control('empty_message', ['label' => __('Texto', 'homlity-real-estate'), 'type' => 'text', 'default' => __('No se han encontrado inmuebles para esta consulta.', 'homlity-real-estate')]);
        $this->end_controls_section();

        // ── Estilos tarjeta (shared via trait) ────────────────────────────────
        $this->registerListingExtraStyles();

        // ── Botones grilla/mapa (listing-only style) ──────────────────────────
        $this->start_controls_section('style_view_toggle', [
            'label'     => __('Selector de vista', 'homlity-real-estate'),
            'tab'       => 'style',
            'condition' => [
                'show_grid_view'   => 'yes',
                'show_map_view'    => 'yes',
                'show_view_toggle' => 'yes',
            ],
        ]);

        $this->add_responsive_control('view_toggle_icon_size', [
            'label'      => __('Tamaño icono', 'homlity-real-estate'),
            'type'       => 'slider',
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 10, 'max' => 36]],
            'selectors'  => [
                '{{WRAPPER}} .property-listing__view-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .property-listing__view-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .property-listing__view-icon i, {{WRAPPER}} .property-listing__view-icon .homlity-divi-icon' => 'font-size: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('view_toggle_padding', [
            'label'      => __('Padding botón', 'homlity-real-estate'),
            'type'       => 'dimensions',
            'size_units' => ['px', '%', 'em'],
            'selectors'  => ['{{WRAPPER}} .property-listing__view-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control('border', [
            'name'     => 'view_toggle_border',
            'selector' => '{{WRAPPER}} .property-listing__view-btn',
        ]);

        $this->add_responsive_control('view_toggle_radius', [
            'label'      => __('Radio botón', 'homlity-real-estate'),
            'type'       => 'slider',
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .property-listing__view-btn' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->registerViewContainerStyles();
        $this->start_controls_tabs('view_toggle_states');

        $this->start_controls_tab('view_toggle_normal', ['label' => __('Normal', 'homlity-real-estate')]);
        $this->add_control('view_toggle_text_color', [
            'label'     => __('Color del texto (normal)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing' => '--homlity-view-toggle-text: {{VALUE}};'],
        ]);
        $this->add_control('view_toggle_icon_color', [
            'label' => __('Color del icono (normal)', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing' => '--homlity-view-toggle-icon: {{VALUE}};'],
        ]);
        $this->add_control('view_toggle_bg_color', [
            'label'     => __('Fondo (normal)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing' => '--homlity-view-toggle-background: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('view_toggle_hover', ['label' => __('Hover', 'homlity-real-estate')]);
        $this->add_control('view_toggle_text_color_hover', [
            'label'     => __('Color del texto (hover)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing' => '--homlity-view-toggle-text-hover: {{VALUE}};'],
        ]);
        $this->add_control('view_toggle_icon_color_hover', [
            'label' => __('Color del icono (hover)', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing' => '--homlity-view-toggle-icon-hover: {{VALUE}};'],
        ]);
        $this->add_control('view_toggle_bg_color_hover', [
            'label'     => __('Fondo (hover)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing' => '--homlity-view-toggle-background-hover: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('view_toggle_active', ['label' => __('Activo', 'homlity-real-estate')]);
        $this->add_control('view_toggle_text_color_active', [
            'label'     => __('Color del texto (activo)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing' => '--homlity-view-toggle-text-active: {{VALUE}};'],
        ]);
        $this->add_control('view_toggle_icon_color_active', [
            'label' => __('Color del icono (activo)', 'homlity-real-estate'),
            'type' => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing' => '--homlity-view-toggle-icon-active: {{VALUE}};'],
        ]);
        $this->add_control('view_toggle_bg_color_active', [
            'label'     => __('Fondo (activo)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing' => '--homlity-view-toggle-background-active: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control('box_shadow', [
            'name'     => 'view_toggle_shadow',
            'selector' => '{{WRAPPER}} .property-listing__view-btn',
        ]);

        $this->end_controls_section();

        $this->registerCardStyleControls();
        $this->registerListingMapStyles();

        // ── Paginación (listing-only style) ───────────────────────────────────
        $this->start_controls_section('style_pagination', [
            'label'     => __('Paginador', 'homlity-real-estate'),
            'tab'       => 'style',
            'condition' => ['show_pagination' => 'yes'],
        ]);

        $this->add_responsive_control('pagination_gap', [
            'label'      => __('Espaciado entre botones', 'homlity-real-estate'),
            'type'       => 'slider',
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 30]],
            'selectors'  => ['{{WRAPPER}} .property-listing__pagination' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('pagination_margin_top', [
            'label'      => __('Margen superior', 'homlity-real-estate'),
            'type'       => 'slider',
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 120]],
            'selectors'  => ['{{WRAPPER}} .property-listing__pagination' => 'margin-top: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control('typography', [
            'name'     => 'pagination_typography',
            'selector' => '{{WRAPPER}} .property-listing__page-btn',
        ]);

        $this->add_responsive_control('pagination_btn_width', [
            'label'      => __('Ancho mínimo botón', 'homlity-real-estate'),
            'type'       => 'slider',
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 28, 'max' => 120]],
            'selectors'  => ['{{WRAPPER}} .property-listing__page-btn' => 'min-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('pagination_btn_height', [
            'label'      => __('Alto botón', 'homlity-real-estate'),
            'type'       => 'slider',
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 28, 'max' => 80]],
            'selectors'  => ['{{WRAPPER}} .property-listing__page-btn' => 'min-height: {{SIZE}}{{UNIT}}; height: auto;'],
        ]);

        $this->add_responsive_control('pagination_btn_padding', [
            'label'      => __('Padding botón', 'homlity-real-estate'),
            'type'       => 'dimensions',
            'size_units' => ['px', 'em', '%'],
            'selectors'  => ['{{WRAPPER}} .property-listing__page-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control('border', [
            'name'     => 'pagination_btn_border',
            'selector' => '{{WRAPPER}} .property-listing__page-btn',
        ]);

        $this->add_responsive_control('pagination_btn_radius', [
            'label'      => __('Radio botón', 'homlity-real-estate'),
            'type'       => 'slider',
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .property-listing__page-btn' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control('box_shadow', [
            'name'     => 'pagination_btn_shadow',
            'selector' => '{{WRAPPER}} .property-listing__page-btn',
        ]);

        $this->registerPaginationExtraStyles();
        $this->start_controls_tabs('pagination_states');

        $this->start_controls_tab('pagination_state_normal', ['label' => __('Normal', 'homlity-real-estate')]);
        $this->add_control('pagination_btn_text_color', [
            'label'     => __('Color texto (normal)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing__page-btn' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('pagination_btn_bg_color', [
            'label'     => __('Fondo (normal)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing__page-btn' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_control('pagination_btn_border_color_normal', [
            'label'     => __('Color borde (normal)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing__page-btn' => 'border-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('pagination_state_hover', ['label' => __('Hover', 'homlity-real-estate')]);
        $this->add_control('pagination_btn_text_color_hover', [
            'label'     => __('Color texto (hover)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing__page-btn:hover' => 'color: {{VALUE}};'],
        ]);
        $this->add_control('pagination_btn_bg_color_hover', [
            'label'     => __('Fondo (hover)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing__page-btn:hover' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_control('pagination_btn_border_color_hover', [
            'label'     => __('Color borde (hover)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => ['{{WRAPPER}} .property-listing__page-btn:hover' => 'border-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('pagination_state_active', ['label' => __('Activo', 'homlity-real-estate')]);
        $this->add_control('pagination_btn_text_color_active', [
            'label'     => __('Color texto (activo)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-listing__page-btn.is-active' => 'color: {{VALUE}};',
                '{{WRAPPER}} .property-listing__pagination .page-item.active .property-listing__page-btn' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('pagination_btn_bg_color_active', [
            'label'     => __('Fondo (activo)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-listing__page-btn.is-active' => 'background-color: {{VALUE}};',
                '{{WRAPPER}} .property-listing__pagination .page-item.active .property-listing__page-btn' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('pagination_btn_border_color_active', [
            'label'     => __('Color borde (activo)', 'homlity-real-estate'),
            'type'      => 'color',
            'selectors' => [
                '{{WRAPPER}} .property-listing__page-btn.is-active' => 'border-color: {{VALUE}};',
                '{{WRAPPER}} .property-listing__pagination .page-item.active .property-listing__page-btn' => 'border-color: {{VALUE}};',
            ],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
        $this->registerListingFinalStyles();
    }

    private function getTermsOptions(string $taxonomy): array
    {
        static $cache = [];

        if (isset($cache[$taxonomy])) {
            return $cache[$taxonomy];
        }

        $options = ['' => __('Todos', 'homlity-real-estate')];
        $terms   = get_terms([
            'taxonomy'              => $taxonomy,
            'hide_empty'            => false,
            'fields'                => 'id=>name',
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

}
