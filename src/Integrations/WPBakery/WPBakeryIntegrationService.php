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
            'name'        => __('Listado de inmuebles', 'homlity-real-estate'),
            'base'        => 'homlity_listing',
            'category'    => __('Homlity Plugin', 'homlity-real-estate'),
            'icon'        => HOMLITY_PLUGIN_URL . 'icono.png',
            'description' => __('Grilla/mapa de propiedades con filtros y orden.', 'homlity-real-estate'),
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
                'heading'    => __('Diseño de plantilla', 'homlity-real-estate'),
                'param_name' => 'template',
                'value'      => [
                    __('Predeterminado (CSS propio)', 'homlity-real-estate') => 'default',
                    __('Bootstrap 5',                 'homlity-real-estate') => 'bootstrap',
                ],
                'std'   => 'default',
                'group' => __('Presentación', 'homlity-real-estate'),
            ],
            [
                'type'       => 'dropdown',
                'heading'    => __('Vista por defecto', 'homlity-real-estate'),
                'param_name' => 'view',
                'value'      => [
                    __('Grilla / Cards', 'homlity-real-estate') => 'grid',
                    __('Mapa',          'homlity-real-estate') => 'map',
                ],
                'std'   => 'grid',
                'group' => __('Presentación', 'homlity-real-estate'),
            ],
            [
                'type'        => 'checkbox',
                'heading'     => __('Botón para cambiar de vista', 'homlity-real-estate'),
                'param_name'  => 'view_toggle',
                'value'       => ['Sí' => 'true'],
                'std'         => 'true',
                'group'       => __('Presentación', 'homlity-real-estate'),
            ],
            [
                'type'       => 'dropdown',
                'heading'    => __('Columnas en grilla', 'homlity-real-estate'),
                'param_name' => 'columns',
                'value'      => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
                'std'        => '3',
                'group'      => __('Presentación', 'homlity-real-estate'),
            ],

            // ── Consulta ──────────────────────────────────────────────────────
            [
                'type'        => 'textfield',
                'heading'     => __('Inmuebles por página', 'homlity-real-estate'),
                'param_name'  => 'per_page',
                'value'       => '12',
                'description' => __('Número entre 1 y 100.', 'homlity-real-estate'),
                'group'       => __('Consulta', 'homlity-real-estate'),
            ],
            [
                'type'       => 'dropdown',
                'heading'    => __('Orden por defecto', 'homlity-real-estate'),
                'param_name' => 'orderby',
                'value'      => [
                    __('Más recientes',         'homlity-real-estate') => 'date',
                    __('Precio: menor a mayor', 'homlity-real-estate') => 'price_asc',
                    __('Precio: mayor a menor', 'homlity-real-estate') => 'price_desc',
                    __('Nombre A–Z',            'homlity-real-estate') => 'title',
                ],
                'std'   => 'date',
                'group' => __('Consulta', 'homlity-real-estate'),
            ],
            [
                'type'        => 'checkbox',
                'heading'     => __('Solo destacados', 'homlity-real-estate'),
                'param_name'  => 'featured',
                'value'       => ['Sí' => 'true'],
                'group'       => __('Consulta', 'homlity-real-estate'),
            ],
            [
                'type'        => 'textfield',
                'heading'     => __('ID de término: Gestión fija', 'homlity-real-estate'),
                'param_name'  => 'operation',
                'value'       => '0',
                'description' => __('ID del término de taxonomía operation_type para filtrar siempre.', 'homlity-real-estate'),
                'group'       => __('Consulta', 'homlity-real-estate'),
            ],
            [
                'type'        => 'textfield',
                'heading'     => __('ID de término: Tipo fijo', 'homlity-real-estate'),
                'param_name'  => 'type',
                'value'       => '0',
                'description' => __('ID del término de taxonomía property_type para filtrar siempre.', 'homlity-real-estate'),
                'group'       => __('Consulta', 'homlity-real-estate'),
            ],

            // ── Filtros ───────────────────────────────────────────────────────
            [
                'type'       => 'checkbox',
                'heading'    => __('Mostrar panel de filtros', 'homlity-real-estate'),
                'param_name' => 'filters',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-real-estate'),
            ],
            [
                'type'       => 'checkbox',
                'heading'    => __('Filtro: Gestión', 'homlity-real-estate'),
                'param_name' => 'filter_operation',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-real-estate'),
            ],
            [
                'type'       => 'checkbox',
                'heading'    => __('Filtro: Tipo de inmueble', 'homlity-real-estate'),
                'param_name' => 'filter_type',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-real-estate'),
            ],
            [
                'type'       => 'checkbox',
                'heading'    => __('Filtro: Ciudad', 'homlity-real-estate'),
                'param_name' => 'filter_city',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-real-estate'),
            ],
            [
                'type'       => 'checkbox',
                'heading'    => __('Filtro: Rango de precio', 'homlity-real-estate'),
                'param_name' => 'filter_price',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-real-estate'),
            ],
            [
                'type'       => 'checkbox',
                'heading'    => __('Filtro: Habitaciones', 'homlity-real-estate'),
                'param_name' => 'filter_bedrooms',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-real-estate'),
            ],
            [
                'type'       => 'checkbox',
                'heading'    => __('Mostrar selector de orden', 'homlity-real-estate'),
                'param_name' => 'sort',
                'value'      => ['Sí' => 'true'],
                'std'        => 'true',
                'group'      => __('Filtros', 'homlity-real-estate'),
            ],

            // ── Mapa ──────────────────────────────────────────────────────────
            [
                'type'        => 'textfield',
                'heading'     => __('Altura del mapa (px)', 'homlity-real-estate'),
                'param_name'  => 'map_height',
                'value'       => '500',
                'group'       => __('Mapa', 'homlity-real-estate'),
            ],
            [
                'type'        => 'textfield',
                'heading'     => __('Zoom inicial del mapa', 'homlity-real-estate'),
                'param_name'  => 'map_zoom',
                'value'       => '12',
                'group'       => __('Mapa', 'homlity-real-estate'),
            ],
        ];
    }
}
