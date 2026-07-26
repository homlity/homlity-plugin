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
            'show_grid_view' => [
                'label'           => esc_html__('Mostrar vista Cards', 'homlity-real-estate'),
                'type'            => 'yes_no_button',
                'option_category' => 'configuration',
                'options'         => ['on' => esc_html__('Sí', 'homlity-real-estate'), 'off' => esc_html__('No', 'homlity-real-estate')],
                'default'         => 'on',
                'tab_slug'        => 'general',
                'toggle_slug'     => 'main_content',
            ],
            'show_map_view' => [
                'label'           => esc_html__('Mostrar vista Mapa', 'homlity-real-estate'),
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

            // ── Contenido de la tarjeta ─────────────────────────────────────
            'card_media' => [
                'label'       => esc_html__('Galería de fotos', 'homlity-real-estate'),
                'type'        => 'select',
                'options'     => [
                    'single' => esc_html__('Imagen principal', 'homlity-real-estate'),
                    'slider' => esc_html__('Slider de fotos', 'homlity-real-estate'),
                ],
                'default'     => 'single',
                'tab_slug'    => 'general',
                'toggle_slug' => 'card_content',
            ],
            'card_preset' => [
                'label'       => esc_html__('Preset visual de la tarjeta', 'homlity-real-estate'),
                'type'        => 'select',
                'options'     => [
                    'default'       => esc_html__('Clásico', 'homlity-real-estate'),
                    'cover_overlay' => esc_html__('Portada con overlay', 'homlity-real-estate'),
                    'minimal_light' => esc_html__('Minimal claro', 'homlity-real-estate'),
                ],
                'default'     => 'default',
                'tab_slug'    => 'general',
                'toggle_slug' => 'card_content',
            ],
            'card_hover_effect' => [
                'label'       => esc_html__('Efecto hover', 'homlity-real-estate'),
                'type'        => 'select',
                'options'     => [
                    'none' => esc_html__('Sin efecto', 'homlity-real-estate'),
                    'lift' => esc_html__('Elevar', 'homlity-real-estate'),
                    'zoom' => esc_html__('Zoom imagen', 'homlity-real-estate'),
                    'glow' => esc_html__('Brillo / sombra', 'homlity-real-estate'),
                ],
                'default'     => 'lift',
                'tab_slug'    => 'general',
                'toggle_slug' => 'card_content',
            ],
        ] + $this->cardVisibilityFields() + $this->cardWhatsappFields() + $this->cardFeatureIconFields() + [

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

    /**
     * Keep old Divi layouts editable: pages saved with homlity_listing_divi
     * must expose the same card visibility switches as the current module.
     */
    private function cardVisibilityFields(): array
    {
        $labels = [
            'card_title'        => __('Mostrar título', 'homlity-real-estate'),
            'card_excerpt'      => __('Mostrar descripción corta', 'homlity-real-estate'),
            'card_operation'    => __('Mostrar gestión (venta/arriendo)', 'homlity-real-estate'),
            'card_price'        => __('Mostrar valor de gestión', 'homlity-real-estate'),
            'card_features'     => __('Mostrar características', 'homlity-real-estate'),
            'card_whatsapp'     => __('Mostrar botón WhatsApp asesor', 'homlity-real-estate'),
            'card_area'         => __('Mostrar área', 'homlity-real-estate'),
            'card_bedrooms'     => __('Mostrar alcobas', 'homlity-real-estate'),
            'card_bathrooms'    => __('Mostrar baños', 'homlity-real-estate'),
            'card_parking'      => __('Mostrar garajes', 'homlity-real-estate'),
            'card_area_lot'     => __('Mostrar área de lote', 'homlity-real-estate'),
            'card_area_private' => __('Mostrar área privada', 'homlity-real-estate'),
            'card_area_built'   => __('Mostrar área construida', 'homlity-real-estate'),
            'card_age'          => __('Mostrar edad del inmueble', 'homlity-real-estate'),
            'card_condition'    => __('Mostrar estado del inmueble', 'homlity-real-estate'),
            'card_code'         => __('Mostrar código del inmueble', 'homlity-real-estate'),
            'card_link_new_tab' => __('Abrir inmueble en nueva pestaña', 'homlity-real-estate'),
        ];

        $fields = [];
        foreach ($labels as $name => $label) {
            $fields[$name] = [
                'label'       => esc_html($label),
                'type'        => 'yes_no_button',
                'options'     => [
                    'on'  => esc_html__('Sí', 'homlity-real-estate'),
                    'off' => esc_html__('No', 'homlity-real-estate'),
                ],
                'default'     => $name === 'card_link_new_tab' ? 'off' : 'on',
                'tab_slug'    => 'general',
                'toggle_slug' => 'card_content',
            ];
        }

        return $fields;
    }

    /**
     * WhatsApp controls for layouts created with the legacy Divi module.
     */
    private function cardWhatsappFields(): array
    {
        return [
            'card_whatsapp_label' => [
                'label'       => esc_html__('Texto del botón', 'homlity-real-estate'),
                'type'        => 'text',
                'default'     => esc_html__('Hablar por WhatsApp', 'homlity-real-estate'),
                'tab_slug'    => 'general',
                'toggle_slug' => 'card_content',
                'show_if'     => ['card_whatsapp' => 'on'],
            ],
            'card_whatsapp_show_icon' => [
                'label'       => esc_html__('Mostrar ícono de WhatsApp', 'homlity-real-estate'),
                'type'        => 'yes_no_button',
                'options'     => [
                    'on'  => esc_html__('Sí', 'homlity-real-estate'),
                    'off' => esc_html__('No', 'homlity-real-estate'),
                ],
                'default'     => 'on',
                'tab_slug'    => 'general',
                'toggle_slug' => 'card_content',
                'show_if'     => ['card_whatsapp' => 'on'],
            ],
            'card_whatsapp_icon_position' => [
                'label'       => esc_html__('Posición del ícono', 'homlity-real-estate'),
                'type'        => 'select',
                'options'     => [
                    'left'  => esc_html__('Izquierda', 'homlity-real-estate'),
                    'right' => esc_html__('Derecha', 'homlity-real-estate'),
                ],
                'default'     => 'left',
                'tab_slug'    => 'general',
                'toggle_slug' => 'card_content',
                'show_if'     => [
                    'card_whatsapp'           => 'on',
                    'card_whatsapp_show_icon' => 'on',
                ],
            ],
            'card_whatsapp_icon_value' => [
                'label'       => esc_html__('Ícono de WhatsApp', 'homlity-real-estate'),
                'type'        => 'select_icon',
                'class'       => ['et-pb-font-icon'],
                'default'     => '&#xf232;||fa||400',
                'tab_slug'    => 'general',
                'toggle_slug' => 'card_content',
                'show_if'     => [
                    'card_whatsapp'           => 'on',
                    'card_whatsapp_show_icon' => 'on',
                ],
            ],
        ];
    }

    /**
     * Native Divi icon selectors for every feature rendered by a property card.
     */
    private function cardFeatureIconFields(): array
    {
        $icons = [
            'area'         => [__('Ícono Área', 'homlity-real-estate'), '&#xf546;||fa||900'],
            'bedrooms'     => [__('Ícono Alcobas', 'homlity-real-estate'), '&#xf236;||fa||900'],
            'bathrooms'    => [__('Ícono Baños', 'homlity-real-estate'), '&#xf2cd;||fa||900'],
            'parking'      => [__('Ícono Garajes', 'homlity-real-estate'), '&#xf1b9;||fa||900'],
            'area_lot'     => [__('Ícono Área lote', 'homlity-real-estate'), '&#xf5ee;||fa||900'],
            'area_private' => [__('Ícono Área privada', 'homlity-real-estate'), '&#xf015;||fa||900'],
            'area_built'   => [__('Ícono Área construida', 'homlity-real-estate'), '&#xf545;||fa||900'],
            'age'          => [__('Ícono Edad', 'homlity-real-estate'), '&#xf017;||fa||900'],
            'condition'    => [__('Ícono Estado', 'homlity-real-estate'), '&#xf058;||fa||900'],
            'code'         => [__('Ícono Código', 'homlity-real-estate'), '&#xf292;||fa||900'],
        ];

        $fields = [];
        foreach ($icons as $name => [$label, $default]) {
            $fields['card_feature_icon_' . $name . '_value'] = [
                'label'       => esc_html($label),
                'type'        => 'select_icon',
                'class'       => ['et-pb-font-icon'],
                'default'     => $default,
                'tab_slug'    => 'general',
                'toggle_slug' => 'card_content',
                'show_if'     => [
                    'card_features'          => 'on',
                    'card_' . $name          => 'on',
                ],
            ];
        }

        return $fields;
    }

    // Divi uses yes_no_button with 'on'/'off' – normalise to true/false booleans.
    private function diviAtts(array $props): array
    {
        $map = [
            'view_toggle'      => 'view_toggle',
            'show_grid_view'   => 'show_grid_view',
            'show_map_view'    => 'show_map_view',
            'featured'         => 'featured',
            'filters'          => 'filters',
            'filter_operation' => 'filter_operation',
            'filter_type'      => 'filter_type',
            'filter_city'      => 'filter_city',
            'filter_price'     => 'filter_price',
            'filter_bedrooms'  => 'filter_bedrooms',
            'sort'             => 'sort',
            'card_title'       => 'card_title',
            'card_excerpt'     => 'card_excerpt',
            'card_operation'   => 'card_operation',
            'card_price'       => 'card_price',
            'card_features'    => 'card_features',
            'card_whatsapp'    => 'card_whatsapp',
            'card_whatsapp_show_icon' => 'card_whatsapp_show_icon',
            'card_area'        => 'card_area',
            'card_bedrooms'    => 'card_bedrooms',
            'card_bathrooms'   => 'card_bathrooms',
            'card_parking'     => 'card_parking',
            'card_area_lot'    => 'card_area_lot',
            'card_area_private' => 'card_area_private',
            'card_area_built'  => 'card_area_built',
            'card_age'         => 'card_age',
            'card_condition'   => 'card_condition',
            'card_code'        => 'card_code',
            'card_link_new_tab' => 'card_link_new_tab',
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
