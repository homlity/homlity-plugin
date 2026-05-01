<?php
/**
 * WPBakery (Visual Composer) integration for the property listing.
 *
 * Registers a Visual Composer element that maps to the [homlity_listing] shortcode.
 * The shortcode itself is registered independently by ShortcodeIntegrationService so it
 * also works without WPBakery installed.
 *
 * Activation is conditional: the whole service is a no-op when WPBakery is absent.
 */

namespace Homlity\PluginInmobiliario\Integrations\WPBakery;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class WPBakeryIntegrationService implements ServiceInterface
{
    public function register(): void
    {
        if (!defined('WPB_VC_VERSION')) {
            return;
        }

        add_action('vc_before_init', [$this, 'mapElement']);
    }

    public function mapElement(): void
    {
        vc_map([
            'name'        => __('Listado de inmuebles', 'homlity-plugin'),
            'base'        => 'homlity_listing',
            'category'    => __('Homlity Plugin', 'homlity-plugin'),
            'icon'        => HOMLITY_PLUGIN_URL . 'icono.png',
            'description' => __('Grilla/mapa de propiedades con filtros y orden.', 'homlity-plugin'),
            'params'      => $this->params(),
        ]);
    }

    // ── WPBakery param definitions ────────────────────────────────────────────

    private function params(): array
    {
        return [
            // ── Presentación ─────────────────────────────────────────────────
            [
                'type'       => 'dropdown',
                'heading'    => __('Diseño de plantilla', 'homlity-plugin'),
                'param_name' => 'template',
                'value'      => [
                    __('Predeterminado (CSS propio)', 'homlity-plugin') => 'default',
                    __('Bootstrap 5',                 'homlity-plugin') => 'bootstrap',
                ],
                'std'   => 'default',
                'group' => __('Presentación', 'homlity-plugin'),
            ],
            [
                'type'       => 'dropdown',
                'heading'    => __('Vista por defecto', 'homlity-plugin'),
                'param_name' => 'view',
                'value'      => [
                    __('Grilla / Cards', 'homlity-plugin') => 'grid',
                    __('Mapa',          'homlity-plugin') => 'map',
                ],
                'std'   => 'grid',
                'group' => __('Presentación', 'homlity-plugin'),
            ],
            [
                'type'        => 'checkbox',
                'heading'     => __('Botón para cambiar de vista', 'homlity-plugin'),
                'param_name'  => 'view_toggle',
                'value'       => ['Sí' => 'true'],
                'std'         => 'true',
                'group'       => __('Presentación', 'homlity-plugin'),
            ],
            [
                'type'       => 'dropdown',
                'heading'    => __('Columnas en grilla', 'homlity-plugin'),
                'param_name' => 'columns',
                'value'      => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
                'std'        => '3',
                'group'      => __('Presentación', 'homlity-plugin'),
            ],

            // ── Consulta ──────────────────────────────────────────────────────
            [
                'type'        => 'textfield',
                'heading'     => __('Inmuebles por página', 'homlity-plugin'),
                'param_name'  => 'per_page',
                'value'       => '12',
                'description' => __('Número entre 1 y 100.', 'homlity-plugin'),
                'group'       => __('Consulta', 'homlity-plugin'),
            ],
            [
                'type'       => 'dropdown',
                'heading'    => __('Orden por defecto', 'homlity-plugin'),
                'param_name' => 'orderby',
                'value'      => [
                    __('Más recientes',         'homlity-plugin') => 'date',
                    __('Precio: menor a mayor', 'homlity-plugin') => 'price_asc',
                    __('Precio: mayor a menor', 'homlity-plugin') => 'price_desc',
                    __('Nombre A–Z',            'homlity-plugin') => 'title',
                ],
                'std'   => 'date',
                'group' => __('Consulta', 'homlity-plugin'),
            ],
            [
                'type'        => 'checkbox',
                'heading'     => __('Solo destacados', 'homlity-plugin'),
                'param_name'  => 'featured',
                'value'       => ['Sí' => 'true'],
                'group'       => __('Consulta', 'homlity-plugin'),
            ],
            [
                'type'        => 'textfield',
                'heading'     => __('ID de término: Gestión fija', 'homlity-plugin'),
                'param_name'  => 'operation',
                'value'       => '0',
                'description' => __('ID del término de taxonomía operation_type para filtrar siempre.', 'homlity-plugin'),
                'group'       => __('Consulta', 'homlity-plugin'),
            ],
            [
                'type'        => 'textfield',
                'heading'     => __('ID de término: Tipo fijo', 'homlity-plugin'),
                'param_name'  => 'type',
                'value'       => '0',
                'description' => __('ID del término de taxonomía property_type para filtrar siempre.', 'homlity-plugin'),
                'group'       => __('Consulta', 'homlity-plugin'),
            ],

            // ── Filtros ───────────────────────────────────────────────────────
            [
                'type'       => 'checkbox',
                'heading'    => __('Mostrar panel de filtros', 'homlity-plugin'),
                'param_name' => 'filters',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-plugin'),
            ],
            [
                'type'       => 'checkbox',
                'heading'    => __('Filtro: Gestión', 'homlity-plugin'),
                'param_name' => 'filter_operation',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-plugin'),
            ],
            [
                'type'       => 'checkbox',
                'heading'    => __('Filtro: Tipo de inmueble', 'homlity-plugin'),
                'param_name' => 'filter_type',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-plugin'),
            ],
            [
                'type'       => 'checkbox',
                'heading'    => __('Filtro: Ciudad', 'homlity-plugin'),
                'param_name' => 'filter_city',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-plugin'),
            ],
            [
                'type'       => 'checkbox',
                'heading'    => __('Filtro: Rango de precio', 'homlity-plugin'),
                'param_name' => 'filter_price',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-plugin'),
            ],
            [
                'type'       => 'checkbox',
                'heading'    => __('Filtro: Habitaciones', 'homlity-plugin'),
                'param_name' => 'filter_bedrooms',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-plugin'),
            ],
            [
                'type'       => 'checkbox',
                'heading'    => __('Mostrar selector de orden', 'homlity-plugin'),
                'param_name' => 'sort',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-plugin'),
            ],

            // ── Mapa ──────────────────────────────────────────────────────────
            [
                'type'        => 'textfield',
                'heading'     => __('Altura del mapa (px)', 'homlity-plugin'),
                'param_name'  => 'map_height',
                'value'       => '500',
                'group'       => __('Mapa', 'homlity-plugin'),
            ],
            [
                'type'        => 'textfield',
                'heading'     => __('Zoom inicial del mapa', 'homlity-plugin'),
                'param_name'  => 'map_zoom',
                'value'       => '12',
                'group'       => __('Mapa', 'homlity-plugin'),
            ],
        ];
    }
}
