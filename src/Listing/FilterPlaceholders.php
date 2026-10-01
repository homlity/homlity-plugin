<?php
/**
 * Customisable placeholder texts for the property filter widget.
 *
 * Shared by every page builder integration (Elementor, WPBakery, Divi) so the
 * controls and the template agree on the same setting keys. An empty setting
 * means "keep the built-in text".
 */

namespace Homlity\PluginInmobiliario\Listing;

if (!defined('ABSPATH')) {
    exit;
}

final class FilterPlaceholders
{
    /**
     * Setting key => label shown in the builder, built-in text and the
     * visibility switcher the control depends on (null = always visible).
     *
     * @return array<string, array{label: string, default: string, toggle: ?string}>
     */
    public static function definitions(): array
    {
        return [
            'keyword_placeholder' => [
                'label' => __('Palabra clave', 'homlity-real-estate'),
                'default' => __('Buscar', 'homlity-real-estate'),
                'toggle' => 'show_keyword',
            ],
            'placeholder_category' => [
                'label' => __('Categoría', 'homlity-real-estate'),
                'default' => __('Categoría', 'homlity-real-estate'),
                'toggle' => 'show_category',
            ],
            'placeholder_operation' => [
                'label' => __('Gestión', 'homlity-real-estate'),
                'default' => __('Gestión', 'homlity-real-estate'),
                'toggle' => 'show_operation',
            ],
            'placeholder_type' => [
                'label' => __('Tipo de inmueble', 'homlity-real-estate'),
                'default' => __('Tipo', 'homlity-real-estate'),
                'toggle' => 'show_type',
            ],
            'placeholder_tag' => [
                'label' => __('Etiqueta', 'homlity-real-estate'),
                'default' => __('Etiqueta', 'homlity-real-estate'),
                'toggle' => 'show_tag',
            ],
            'placeholder_country' => [
                'label' => __('País', 'homlity-real-estate'),
                'default' => __('País', 'homlity-real-estate'),
                'toggle' => 'show_country',
            ],
            'placeholder_state' => [
                'label' => __('Departamento / Provincia', 'homlity-real-estate'),
                'default' => __('Departamento', 'homlity-real-estate'),
                'toggle' => 'show_state',
            ],
            'placeholder_city' => [
                'label' => __('Ciudad', 'homlity-real-estate'),
                'default' => __('Ciudad', 'homlity-real-estate'),
                'toggle' => 'show_city',
            ],
            'placeholder_locality' => [
                'label' => __('Localidad', 'homlity-real-estate'),
                'default' => __('Localidad', 'homlity-real-estate'),
                'toggle' => 'show_locality',
            ],
            'placeholder_neighborhood' => [
                'label' => __('Barrio', 'homlity-real-estate'),
                'default' => __('Barrio', 'homlity-real-estate'),
                'toggle' => 'show_neighborhood',
            ],
            'placeholder_nearby' => [
                'label' => __('Lugar cercano', 'homlity-real-estate'),
                'default' => __('Lugar cercano', 'homlity-real-estate'),
                'toggle' => 'show_nearby',
            ],
            'placeholder_price_min' => [
                'label' => __('Precio mínimo', 'homlity-real-estate'),
                'default' => __('Precio mín.', 'homlity-real-estate'),
                'toggle' => 'show_price',
            ],
            'placeholder_price_max' => [
                'label' => __('Precio máximo', 'homlity-real-estate'),
                'default' => __('Precio máx.', 'homlity-real-estate'),
                'toggle' => 'show_price',
            ],
            'placeholder_area_min' => [
                'label' => __('Área mínima', 'homlity-real-estate'),
                'default' => __('Área mín.', 'homlity-real-estate'),
                'toggle' => 'show_area',
            ],
            'placeholder_area_max' => [
                'label' => __('Área máxima', 'homlity-real-estate'),
                'default' => __('Área máx.', 'homlity-real-estate'),
                'toggle' => 'show_area',
            ],
            'placeholder_bedrooms' => [
                'label' => __('Habitaciones', 'homlity-real-estate'),
                'default' => __('Habitaciones', 'homlity-real-estate'),
                'toggle' => 'show_bedrooms',
            ],
            'placeholder_bathrooms' => [
                'label' => __('Baños', 'homlity-real-estate'),
                'default' => __('Baños', 'homlity-real-estate'),
                'toggle' => 'show_bathrooms',
            ],
            'placeholder_parking' => [
                'label' => __('Garajes', 'homlity-real-estate'),
                'default' => __('Garajes', 'homlity-real-estate'),
                'toggle' => 'show_parking',
            ],
            'placeholder_options_search' => [
                'label' => __('Buscador dentro de las listas', 'homlity-real-estate'),
                'default' => __('Buscar opciones…', 'homlity-real-estate'),
                'toggle' => null,
            ],
        ];
    }

    /**
     * The text the administrator typed for this field, or '' to keep the built-in one.
     */
    public static function custom(array $settings, string $key): string
    {
        $value = $settings[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
