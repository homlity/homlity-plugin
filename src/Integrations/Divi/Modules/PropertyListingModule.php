<?php
/**
 * Divi Builder module: Listado de inmuebles.
 *
 * Extends ET_Builder_Module so it appears in Divi's visual builder as a draggable
 * module. All rendering is delegated to ListingRenderer via ListingConfig.
 *
 * NOTE: This file is loaded only after ET_Builder_Module exists (see DiviIntegrationService).
 *       It must NOT be autoloaded directly because ET_Builder_Module is not always available.
 */

use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;
use Homlity\PluginInmobiliario\Integrations\Shortcode\ShortcodeIntegrationService;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class Homlity_Property_Listing_Module extends ET_Builder_Module
{
    public $name       = 'Listado de inmuebles';
    public $slug       = 'homlity_listing_divi';
    public $vb_support = 'on';
    public $show_in_visual_builder = false;

    public function init(): void
    {
        $this->name = esc_html__('Listado de inmuebles', 'homlity-real-estate');
    }

    public function get_fields(): array
    {
        $defaults = ShortcodeIntegrationService::defaults();

        return [
            // ── Presentación ──────────────────────────────────────────────────
            'template' => [
                'label'           => esc_html__('Diseño de plantilla', 'homlity-real-estate'),
                'type'            => 'select',
                'option_category' => 'layout',
                'options'         => [
                    'default'   => esc_html__('Predeterminado (CSS propio)', 'homlity-real-estate'),
                    'bootstrap' => esc_html__('Bootstrap 5',                 'homlity-real-estate'),
                ],
                'default'         => $defaults['template'],
                'tab_slug'        => 'general',
                'toggle_slug'     => 'main_content',
            ],
            'view' => [
                'label'           => esc_html__('Vista por defecto', 'homlity-real-estate'),
                'type'            => 'select',
                'option_category' => 'layout',
                'options'         => [
                    'grid' => esc_html__('Grilla / Cards', 'homlity-real-estate'),
                    'map'  => esc_html__('Mapa',           'homlity-real-estate'),
                ],
                'default'         => $defaults['view'],
                'tab_slug'        => 'general',
                'toggle_slug'     => 'main_content',
            ],
            'view_toggle' => [
                'label'           => esc_html__('Botón para cambiar de vista', 'homlity-real-estate'),
                'type'            => 'yes_no_button',
                'option_category' => 'configuration',
                'options'         => ['on' => esc_html__('Sí', 'homlity-real-estate'), 'off' => esc_html__('No', 'homlity-real-estate')],
                'default'         => 'on',
                'tab_slug'        => 'general',
                'toggle_slug'     => 'main_content',
            ],
            'columns' => [
                'label'           => esc_html__('Columnas en grilla', 'homlity-real-estate'),
                'type'            => 'select',
                'option_category' => 'layout',
                'options'         => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
                'default'         => $defaults['columns'],
                'tab_slug'        => 'general',
                'toggle_slug'     => 'main_content',
            ],

            // ── Consulta ──────────────────────────────────────────────────────
            'per_page' => [
                'label'           => esc_html__('Inmuebles por página', 'homlity-real-estate'),
                'type'            => 'text',
                'option_category' => 'configuration',
                'default'         => $defaults['per_page'],
                'tab_slug'        => 'general',
                'toggle_slug'     => 'query',
            ],
            'orderby' => [
                'label'           => esc_html__('Orden por defecto', 'homlity-real-estate'),
                'type'            => 'select',
                'option_category' => 'configuration',
                'options'         => [
                    'date'       => esc_html__('Más recientes',         'homlity-real-estate'),
                    'price_asc'  => esc_html__('Precio: menor a mayor', 'homlity-real-estate'),
                    'price_desc' => esc_html__('Precio: mayor a menor', 'homlity-real-estate'),
                    'title'      => esc_html__('Nombre A–Z',            'homlity-real-estate'),
                ],
                'default'         => $defaults['orderby'],
                'tab_slug'        => 'general',
                'toggle_slug'     => 'query',
            ],
            'featured' => [
                'label'           => esc_html__('Solo destacados', 'homlity-real-estate'),
                'type'            => 'yes_no_button',
                'option_category' => 'configuration',
                'options'         => ['on' => esc_html__('Sí', 'homlity-real-estate'), 'off' => esc_html__('No', 'homlity-real-estate')],
                'default'         => 'off',
                'tab_slug'        => 'general',
                'toggle_slug'     => 'query',
            ],
            'operation' => [
                'label'           => esc_html__('ID: Gestión fija', 'homlity-real-estate'),
                'type'            => 'text',
                'option_category' => 'configuration',
                'default'         => '0',
                'tab_slug'        => 'general',
                'toggle_slug'     => 'query',
            ],
            'type' => [
                'label'           => esc_html__('ID: Tipo fijo', 'homlity-real-estate'),
                'type'            => 'text',
                'option_category' => 'configuration',
                'default'         => '0',
                'tab_slug'        => 'general',
                'toggle_slug'     => 'query',
            ],

            // ── Filtros ───────────────────────────────────────────────────────
            'filters' => [
                'label'           => esc_html__('Mostrar panel de filtros', 'homlity-real-estate'),
                'type'            => 'yes_no_button',
                'option_category' => 'configuration',
                'options'         => ['on' => esc_html__('Sí', 'homlity-real-estate'), 'off' => esc_html__('No', 'homlity-real-estate')],
                'default'         => 'on',
                'tab_slug'        => 'general',
                'toggle_slug'     => 'filters',
            ],
            'sort' => [
                'label'           => esc_html__('Mostrar selector de orden', 'homlity-real-estate'),
                'type'            => 'yes_no_button',
                'option_category' => 'configuration',
                'options'         => ['on' => esc_html__('Sí', 'homlity-real-estate'), 'off' => esc_html__('No', 'homlity-real-estate')],
                'default'         => 'on',
                'tab_slug'        => 'general',
                'toggle_slug'     => 'filters',
            ],

            // ── Mapa ──────────────────────────────────────────────────────────
            'map_height' => [
                'label'           => esc_html__('Altura del mapa (px)', 'homlity-real-estate'),
                'type'            => 'text',
                'option_category' => 'configuration',
                'default'         => $defaults['map_height'],
                'tab_slug'        => 'general',
                'toggle_slug'     => 'map',
            ],
            'map_zoom' => [
                'label'           => esc_html__('Zoom inicial del mapa', 'homlity-real-estate'),
                'type'            => 'text',
                'option_category' => 'configuration',
                'default'         => $defaults['map_zoom'],
                'tab_slug'        => 'general',
                'toggle_slug'     => 'map',
            ],
        ];
    }

    // Divi uses yes_no_button with 'on'/'off' – normalise to true/false booleans.
    private function diviAtts(array $props): array
    {
        $map = [
            'view_toggle'      => 'view_toggle',
            'featured'         => 'featured',
            'filters'          => 'filters',
            'filter_operation' => 'filter_operation',
            'filter_type'      => 'filter_type',
            'filter_city'      => 'filter_city',
            'filter_price'     => 'filter_price',
            'filter_bedrooms'  => 'filter_bedrooms',
            'sort'             => 'sort',
        ];

        foreach ($map as $key => $propKey) {
            if (isset($props[$propKey])) {
                $props[$key] = $props[$propKey] === 'on' ? 'true' : 'false';
            }
        }

        return $props;
    }

    public function render($attrs, $content = null, $function_name = ''): string
    {
        $config = ListingConfig::fromAtts($this->diviAtts($this->props));

        ob_start();
        (new ListingRenderer())->render($config);
        return ob_get_clean() ?: '';
    }
}

new Homlity_Property_Listing_Module();
