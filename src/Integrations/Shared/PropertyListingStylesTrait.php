<?php

namespace Homlity\PluginInmobiliario\Integrations\Shared;

trait PropertyListingStylesTrait
{
    private function listingSection(string $id, string $label): void
    {
        $this->start_controls_section($id, ['label' => $label, 'tab' => 'style']);
    }

    private function listingColor(string $id, string $label, string $selector, string $property = 'color'): void
    {
        $this->add_control($id, ['label' => $label, 'type' => 'color', 'selectors' => [$selector => $property . ': {{VALUE}};']]);
    }

    private function listingSize(string $id, string $label, string $selector, string $property, int $max = 200): void
    {
        $this->add_responsive_control($id, [
            'label' => $label,
            'type' => 'slider',
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => $max]],
            'selectors' => [$selector => $property . ': {{SIZE}}{{UNIT}};'],
        ]);
    }

    private function listingChoice(string $id, string $label, string $selector, string $property, array $options): void
    {
        $this->add_responsive_control($id, ['label' => $label, 'type' => 'select', 'options' => ['' => __('Predeterminado', 'homlity-real-estate')] + $options, 'selectors' => [$selector => $property . ': {{VALUE}};']]);
    }

    private function listingPadding(string $id, string $label, string $selector): void
    {
        $this->add_responsive_control($id, ['label' => $label, 'type' => 'dimensions', 'size_units' => ['px', 'em', '%'], 'selectors' => [$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
    }

    private function registerListingExtraStyles(): void
    {
        $root = '{{WRAPPER}} .property-listing';
        $this->listingSection('style_general', __('Estilo general', 'homlity-real-estate'));
        $this->add_control('accent_color', AccentColor::control($root, '--hpl-accent-color: {{VALUE}};'));
        $this->end_controls_section();
        $this->listingSection('style_grid', __('Grilla', 'homlity-real-estate'));
        $this->listingSize('grid_column_gap', __('Espacio entre columnas', 'homlity-real-estate'), $root, '--hpl-column-gap');
        $this->listingSize('grid_row_gap', __('Espacio entre filas', 'homlity-real-estate'), $root, '--hpl-bootstrap-column-margin: 0px; --hpl-row-gap');
        $this->end_controls_section();
        $this->listingSection('style_toolbar', __('Barra de resultados', 'homlity-real-estate'));
        $toolbar = $root . ' .property-listing__toolbar, ' . $root . ' .homlity-search-header';
        $this->listingSize('toolbar_margin_bottom', __('Margen inferior', 'homlity-real-estate'), $root, '--hpl-toolbar-margin');
        $this->listingChoice('toolbar_alignment', __('Alineación', 'homlity-real-estate'), $toolbar, 'display: flex; justify-content', ['flex-start' => __('Inicio', 'homlity-real-estate'), 'center' => __('Centro', 'homlity-real-estate'), 'flex-end' => __('Final', 'homlity-real-estate'), 'space-between' => __('Separado', 'homlity-real-estate')]);
        $this->add_group_control('typography', ['name' => 'count_typography', 'label' => __('Tipografía del contador', 'homlity-real-estate'), 'selector' => $root . ' .property-listing__count']);
        $this->listingColor('count_color', __('Color del contador', 'homlity-real-estate'), $root, '--hpl-count-color');
        $this->listingColor('count_number_color', __('Color del número', 'homlity-real-estate'), $root . ' .property-listing__count-number');
        $sort = $root . ' .property-listing__sort';
        $this->add_group_control('typography', ['name' => 'sort_typography', 'label' => __('Tipografía del orden', 'homlity-real-estate'), 'selector' => $sort]);
        $this->listingColor('sort_text_color', __('Color del orden', 'homlity-real-estate'), $sort);
        $this->listingColor('sort_bg_color', __('Fondo del orden', 'homlity-real-estate'), $sort, 'background-color');
        $this->add_group_control('border', ['name' => 'sort_border', 'label' => __('Borde del orden', 'homlity-real-estate'), 'selector' => $sort]);
        $this->listingSize('sort_radius', __('Radio del orden', 'homlity-real-estate'), $sort, 'border-radius');
        $this->listingSize('sort_height', __('Altura del orden', 'homlity-real-estate'), $sort, 'height');
        $this->listingColor('sort_focus_border_color', __('Borde del orden (foco)', 'homlity-real-estate'), $sort . ':focus', 'border-color');
        $this->listingColor('sort_arrow_color', __('Color de flecha', 'homlity-real-estate'), $root, '--hpl-sort-arrow-color');
        $this->end_controls_section();
    }

    private function registerViewContainerStyles(): void
    {
        $selector = '{{WRAPPER}} .property-listing .property-listing__view-toggle';
        $this->listingColor('view_toggle_container_border_color', __('Borde del grupo', 'homlity-real-estate'), $selector, '--bs-border-color');
        $this->listingSize('view_toggle_container_border_width', __('Grosor del borde del grupo', 'homlity-real-estate'), $selector, '--bs-border-width');
        $this->listingSize('view_toggle_container_radius', __('Radio del grupo', 'homlity-real-estate'), $selector, '--bs-border-radius');
        $this->listingSize('view_toggle_height', __('Altura de los botones', 'homlity-real-estate'), '{{WRAPPER}} .property-listing .property-listing__view-btn', 'height');
    }

    private function registerListingMapStyles(): void
    {
        $this->listingSection('style_map', __('Mapa', 'homlity-real-estate'));
        $selector = '{{WRAPPER}} .property-listing .property-listing__map-container';
        $this->listingSize('map_radius', __('Radio del mapa', 'homlity-real-estate'), $selector, 'border-radius');
        $this->add_group_control('border', ['name' => 'map_border', 'label' => __('Borde del mapa', 'homlity-real-estate'), 'selector' => $selector]);
        $this->add_group_control('box_shadow', ['name' => 'map_shadow', 'label' => __('Sombra del mapa', 'homlity-real-estate'), 'selector' => $selector]);
        $this->end_controls_section();
    }

    private function registerPaginationExtraStyles(): void
    {
        $root = '{{WRAPPER}} .property-listing';
        $disabled = $root . ' .property-listing__page-btn:disabled, ' . $root . ' .disabled .property-listing__page-btn';
        $this->add_control('pagination_disabled_opacity', ['label' => __('Opacidad (deshabilitado)', 'homlity-real-estate'), 'type' => 'slider', 'range' => ['px' => ['min' => 0, 'max' => 1, 'step' => 0.05]], 'selectors' => [$disabled => 'opacity: {{SIZE}};']]);
        $this->listingColor('pagination_disabled_text_color', __('Texto (deshabilitado)', 'homlity-real-estate'), $disabled);
        $this->listingColor('pagination_disabled_bg_color', __('Fondo (deshabilitado)', 'homlity-real-estate'), $disabled, 'background-color');
        $this->listingColor('pagination_disabled_border_color', __('Borde (deshabilitado)', 'homlity-real-estate'), $disabled, 'border-color');
        $this->listingChoice('pagination_edge_labels', __('Etiquetas anterior y siguiente', 'homlity-real-estate'), $root . ' .property-listing__page-label', 'display', ['inline' => __('Mostrar', 'homlity-real-estate'), 'none' => __('Ocultar', 'homlity-real-estate')]);
        $this->listingColor('pagination_ellipsis_color', __('Color puntos suspensivos', 'homlity-real-estate'), $root . ' .property-listing__page-ellipsis');
        $this->listingColor('pagination_ellipsis_bg_color', __('Fondo puntos suspensivos', 'homlity-real-estate'), $root . ' .property-listing__page-ellipsis', 'background-color');
    }

    private function registerListingFinalStyles(): void
    {
        $root = '{{WRAPPER}} .property-listing';
        $this->listingSection('style_empty', __('Mensaje sin resultados', 'homlity-real-estate'));
        $selector = $root . ' .property-listing__empty';
        $this->add_group_control('typography', ['name' => 'empty_typography', 'label' => __('Tipografía del mensaje', 'homlity-real-estate'), 'selector' => $selector]);
        $this->listingColor('empty_color', __('Color del texto', 'homlity-real-estate'), $root, '--hpl-empty-color');
        $this->listingColor('empty_bg_color', __('Fondo', 'homlity-real-estate'), $selector, 'background-color');
        $this->listingPadding('empty_padding', __('Padding', 'homlity-real-estate'), $selector);
        $this->listingChoice('empty_alignment', __('Alineación', 'homlity-real-estate'), $selector, 'text-align', ['left' => __('Izquierda', 'homlity-real-estate'), 'center' => __('Centro', 'homlity-real-estate'), 'right' => __('Derecha', 'homlity-real-estate')]);
        $this->end_controls_section();
        $this->listingSection('style_loading', __('Carga', 'homlity-real-estate'));
        $this->listingColor('loading_overlay_color', __('Fondo del overlay', 'homlity-real-estate'), $root . ' .property-listing__overlay', 'background-color');
        $this->listingColor('loading_spinner_color', __('Color del indicador', 'homlity-real-estate'), $root, '--hpl-spinner-color');
        $this->listingSize('loading_spinner_size', __('Tamaño del indicador', 'homlity-real-estate'), $root, '--hpl-spinner-size');
        $this->end_controls_section();
    }
}
