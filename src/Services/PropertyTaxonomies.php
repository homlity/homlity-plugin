<?php
/**
 * Registers taxonomies for properties.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyTaxonomies implements ServiceInterface
{
    public const TAXONOMY_TYPE = 'property_type';
    public const TAXONOMY_OPERATION = 'property_operation';
    public const TAXONOMY_LOCATION = 'property_location';
    public const TAXONOMY_CATEGORY = 'property_category';
    public const TAXONOMY_TAG = 'property_tag';
    public const TAXONOMY_FEATURE = 'property_feature';
    public const TAXONOMY_COUNTRY = 'property_country';
    public const TAXONOMY_STATE = 'property_state';
    public const TAXONOMY_CITY = 'property_city';
    public const TAXONOMY_NEIGHBORHOOD = 'property_neighborhood';
    public const TAXONOMY_NEARBY = 'property_nearby';

    public function register(): void
    {
        add_action('init', [$this, 'registerTaxonomies']);
        add_filter('pll_get_taxonomies', [$this, 'registerWithPolylang'], 10, 2);
    }

    public function registerTaxonomies(): void
    {
        register_taxonomy(
            self::TAXONOMY_TYPE,
            [PropertyPostType::POST_TYPE],
            [
                'label' => __('Tipo de propiedad', 'homlity-plugin'),
                'labels' => [
                    'name' => __('Tipos de propiedad', 'homlity-plugin'),
                    'singular_name' => __('Tipo de propiedad', 'homlity-plugin'),
                ],
                'hierarchical' => true,
                'show_in_rest' => true,
                'meta_box_cb' => false,
                'rewrite' => ['slug' => 'property-type'],
            ]
        );

        register_taxonomy(
            self::TAXONOMY_OPERATION,
            [PropertyPostType::POST_TYPE],
            [
                'label' => __('Gestión', 'homlity-plugin'),
                'labels' => [
                    'name' => __('Gestiones', 'homlity-plugin'),
                    'singular_name' => __('Gestión', 'homlity-plugin'),
                ],
                'hierarchical' => false,
                'show_in_rest' => true,
                'show_admin_column' => true,
                'rewrite' => ['slug' => 'operation'],
                'meta_box_cb' => false,
            ]
        );

        register_taxonomy(
            self::TAXONOMY_LOCATION,
            [PropertyPostType::POST_TYPE],
            [
                'label' => __('Ubicación', 'homlity-plugin'),
                'labels' => [
                    'name' => __('Ubicaciones', 'homlity-plugin'),
                    'singular_name' => __('Ubicación', 'homlity-plugin'),
                ],
                'hierarchical' => true,
                'show_in_rest' => true,
                'rewrite' => ['slug' => 'property-location'],
            ]
        );

        register_taxonomy(
            self::TAXONOMY_CATEGORY,
            [PropertyPostType::POST_TYPE],
            [
                'label' => __('Categoría', 'homlity-plugin'),
                'labels' => [
                    'name' => __('Categorías', 'homlity-plugin'),
                    'singular_name' => __('Categoría', 'homlity-plugin'),
                ],
                'hierarchical' => true,
                'show_in_rest' => true,
                'rewrite' => ['slug' => 'property-category'],
            ]
        );

        register_taxonomy(
            self::TAXONOMY_TAG,
            [PropertyPostType::POST_TYPE],
            [
                'label' => __('Etiquetas', 'homlity-plugin'),
                'labels' => [
                    'name' => __('Etiquetas', 'homlity-plugin'),
                    'singular_name' => __('Etiqueta', 'homlity-plugin'),
                ],
                'hierarchical' => false,
                'show_in_rest' => true,
                'rewrite' => ['slug' => 'property-tag'],
            ]
        );

        register_taxonomy(
            self::TAXONOMY_FEATURE,
            [PropertyPostType::POST_TYPE],
            [
                'label' => __('Características', 'homlity-plugin'),
                'labels' => [
                    'name' => __('Características', 'homlity-plugin'),
                    'singular_name' => __('Característica', 'homlity-plugin'),
                ],
                'hierarchical' => true,
                'show_in_rest' => true,
                'show_admin_column' => true,
                'rewrite' => ['slug' => 'property-feature'],
            ]
        );

        register_taxonomy(
            self::TAXONOMY_COUNTRY,
            [PropertyPostType::POST_TYPE],
            [
                'label' => __('País', 'homlity-plugin'),
                'labels' => [
                    'name' => __('Países', 'homlity-plugin'),
                    'singular_name' => __('País', 'homlity-plugin'),
                ],
                'hierarchical' => true,
                'show_in_rest' => true,
                'show_admin_column' => true,
                'rewrite' => ['slug' => 'property-country'],
            ]
        );

        register_taxonomy(
            self::TAXONOMY_STATE,
            [PropertyPostType::POST_TYPE],
            [
                'label' => __('Departamento / Provincia', 'homlity-plugin'),
                'labels' => [
                    'name' => __('Departamentos / Provincias', 'homlity-plugin'),
                    'singular_name' => __('Departamento / Provincia', 'homlity-plugin'),
                ],
                'hierarchical' => true,
                'show_in_rest' => true,
                'show_admin_column' => true,
                'rewrite' => ['slug' => 'property-state'],
            ]
        );

        register_taxonomy(
            self::TAXONOMY_CITY,
            [PropertyPostType::POST_TYPE],
            [
                'label' => __('Ciudad / Municipio', 'homlity-plugin'),
                'labels' => [
                    'name' => __('Ciudades / Municipios', 'homlity-plugin'),
                    'singular_name' => __('Ciudad / Municipio', 'homlity-plugin'),
                ],
                'hierarchical' => true,
                'show_in_rest' => true,
                'show_admin_column' => true,
                'rewrite' => ['slug' => 'property-city'],
            ]
        );

        register_taxonomy(
            self::TAXONOMY_NEIGHBORHOOD,
            [PropertyPostType::POST_TYPE],
            [
                'label' => __('Barrio', 'homlity-plugin'),
                'labels' => [
                    'name' => __('Barrios', 'homlity-plugin'),
                    'singular_name' => __('Barrio', 'homlity-plugin'),
                ],
                'hierarchical' => true,
                'show_in_rest' => true,
                'show_admin_column' => true,
                'rewrite' => ['slug' => 'property-neighborhood'],
            ]
        );

        register_taxonomy(
            self::TAXONOMY_NEARBY,
            [PropertyPostType::POST_TYPE],
            [
                'label' => __('Lugares cercanos', 'homlity-plugin'),
                'labels' => [
                    'name' => __('Lugares cercanos', 'homlity-plugin'),
                    'singular_name' => __('Lugar cercano', 'homlity-plugin'),
                ],
                'hierarchical' => false,
                'show_in_rest' => true,
                'show_admin_column' => true,
                'rewrite' => ['slug' => 'property-nearby'],
            ]
        );
    }

    public function registerWithPolylang(array $taxonomies, $isSettings): array
    {
        if ($isSettings) {
            $taxonomies[] = self::TAXONOMY_TYPE;
            $taxonomies[] = self::TAXONOMY_OPERATION;
            $taxonomies[] = self::TAXONOMY_LOCATION;
            $taxonomies[] = self::TAXONOMY_CATEGORY;
            $taxonomies[] = self::TAXONOMY_TAG;
            $taxonomies[] = self::TAXONOMY_FEATURE;
            $taxonomies[] = self::TAXONOMY_COUNTRY;
            $taxonomies[] = self::TAXONOMY_STATE;
            $taxonomies[] = self::TAXONOMY_CITY;
            $taxonomies[] = self::TAXONOMY_NEIGHBORHOOD;
            $taxonomies[] = self::TAXONOMY_NEARBY;
        }
        return $taxonomies;
    }
}
