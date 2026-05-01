<?php
/**
 * Seeds default terms and data on activation.
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

class DataSeederService
{
    public function seed(): void
    {
        $this->seedPages();
        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_TYPE, [
            'Casa',
            'Apartamento',
            'Apartaestudio',
            'Local Comercial',
            'Oficina',
        ]);

        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_CATEGORY, [
            'Residencial',
            'Comercial',
            'Lote / Terreno',
        ]);

        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_OPERATION, [
            'Venta',
            'Arriendo',
            'Administración',
            'Permuta',
        ]);

        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_TAG, [
            'Nueva',
            'Remodelada',
            'Amoblada',
        ]);

        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_FEATURE, [
            ['name' => 'Interior', 'parent' => null],
            ['name' => 'Exterior', 'parent' => null],
            ['name' => 'Balcón', 'parent' => 'Interior'],
            ['name' => 'Cocina integral', 'parent' => 'Interior'],
            ['name' => 'Aire acondicionado', 'parent' => 'Interior'],
            ['name' => 'Piscina', 'parent' => 'Exterior'],
            ['name' => 'Jardín', 'parent' => 'Exterior'],
            ['name' => 'Parqueadero', 'parent' => 'Exterior'],
            ['name' => 'Portería 24h', 'parent' => 'Exterior'],
        ]);

        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_COUNTRY, [
            'Colombia',
            'Perú',
            'Panamá',
        ]);

        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_STATE, [
            ['name' => 'Antioquia', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_COUNTRY, 'parent_term' => 'Colombia'],
            ['name' => 'Cundinamarca', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_COUNTRY, 'parent_term' => 'Colombia'],
            ['name' => 'Valle del Cauca', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_COUNTRY, 'parent_term' => 'Colombia'],
            ['name' => 'Lima', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_COUNTRY, 'parent_term' => 'Perú'],
            ['name' => 'Panamá', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_COUNTRY, 'parent_term' => 'Panamá'],
        ]);

        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_CITY, [
            ['name' => 'Medellín', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_STATE, 'parent_term' => 'Antioquia'],
            ['name' => 'Bogotá', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_STATE, 'parent_term' => 'Cundinamarca'],
            ['name' => 'Cali', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_STATE, 'parent_term' => 'Valle del Cauca'],
            ['name' => 'Lima', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_STATE, 'parent_term' => 'Lima'],
            ['name' => 'Ciudad de Panamá', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_STATE, 'parent_term' => 'Panamá'],
        ]);

        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, [
            ['name' => 'El Poblado', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_CITY, 'parent_term' => 'Medellín'],
            ['name' => 'Chapinero', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_CITY, 'parent_term' => 'Bogotá'],
            ['name' => 'Miraflores', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_CITY, 'parent_term' => 'Lima'],
            ['name' => 'San Isidro', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_CITY, 'parent_term' => 'Lima'],
            ['name' => 'San Francisco', 'parent_taxonomy' => PropertyTaxonomies::TAXONOMY_CITY, 'parent_term' => 'Ciudad de Panamá'],
        ]);

        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_LOCATION, [
            'Zona Norte',
            'Zona Sur',
            'Zona Oriente',
            'Zona Occidente',
        ]);

        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_NEARBY, [
            'Centro comercial',
            'Colegios',
            'Parques',
            'Transporte público',
            'Universidades',
        ]);

        $this->seedElementorTemplates();
    }

    private function seedTaxonomy(string $taxonomy, array $terms): void
    {
        if (!taxonomy_exists($taxonomy)) {
            return;
        }

        foreach ($terms as $term) {
            $name = $term;
            $parentId = 0;

            if (is_array($term)) {
                $name = $term['name'] ?? '';
                $parentName = $term['parent'] ?? null;
                if ($parentName) {
                    $parentTax = $term['parent_taxonomy'] ?? $taxonomy;
                    $parentTerm = term_exists($parentName, $parentTax);
                    if (!$parentTerm) {
                        $parentResult = wp_insert_term($parentName, $parentTax);
                        if (!is_wp_error($parentResult)) {
                            $parentId = (int) ($parentResult['term_id'] ?? 0);
                        }
                    } else {
                        $parentId = is_array($parentTerm) ? (int) $parentTerm['term_id'] : (int) $parentTerm;
                    }
                }
            }

            if (term_exists($name, $taxonomy)) {
                continue;
            }
            $inserted = wp_insert_term($name, $taxonomy, $parentId ? ['parent' => $parentId] : []);

            // Save relational meta for cascading selects.
            if (is_array($term) && !is_wp_error($inserted)) {
                $metaKey = $this->metaKeyForTaxonomy($taxonomy);
                if ($metaKey && $parentId) {
                    update_term_meta((int) $inserted['term_id'], $metaKey, $parentId);
                }
            }
        }
    }

    private function metaKeyForTaxonomy(string $taxonomy): ?string
    {
        return match ($taxonomy) {
            PropertyTaxonomies::TAXONOMY_STATE => '_parent_country',
            PropertyTaxonomies::TAXONOMY_CITY => '_parent_state',
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => '_parent_city',
            default => null,
        };
    }

    public function seedPages(): void
    {
        $this->seedArchivePage();
    }

    public function seedElementorTemplates(): void
    {
        if (!post_type_exists('elementor_library') && !class_exists('\Elementor\Plugin') && !defined('ELEMENTOR_VERSION')) {
            return;
        }

        $this->seedArchiveElementorPage();
        $this->seedSingleElementorTemplate();
    }

    private function seedArchivePage(): void
    {
        $optionKey = 'homlity_plugin_archive_page_id';
        $pageId    = (int) get_option($optionKey, 0);

        if ($pageId > 0 && 'publish' === get_post_status($pageId)) {
            if (get_post_field('post_name', $pageId) !== 'inmuebles') {
                wp_update_post([
                    'ID' => $pageId,
                    'post_name' => 'inmuebles',
                ]);
            }

            return;
        }

        $pageId = wp_insert_post([
            'post_title'     => __('Resultados de inmuebles', 'homlity-plugin'),
            'post_name'      => 'inmuebles',
            'post_content'   => '',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ]);

        if (!is_wp_error($pageId) && $pageId > 0) {
            update_option($optionKey, $pageId);
        }
    }

    private function seedArchiveElementorPage(): void
    {
        $pageId = (int) get_option('homlity_plugin_archive_page_id', 0);
        if (!$pageId || get_post_status($pageId) !== 'publish') {
            return;
        }

        if (
            (int) get_option('homlity_plugin_archive_elementor_page_id', 0) === $pageId &&
            get_post_meta($pageId, '_elementor_edit_mode', true) === 'builder'
        ) {
            return;
        }

        $data = [
            $this->elementorSection([
                $this->elementorWidget('heading', [
                    'title' => __('Resultados de inmuebles', 'homlity-plugin'),
                    'header_size' => 'h1',
                ]),
                $this->elementorWidget('property_filter', [
                    'target_page_id' => (string) $pageId,
                    'show_keyword' => 'yes',
                    'show_operation' => 'yes',
                    'show_type' => 'yes',
                    'show_city' => 'yes',
                    'show_price' => 'yes',
                    'show_reset' => 'yes',
                    'submit_label' => __('Buscar', 'homlity-plugin'),
                    'reset_label' => __('Limpiar', 'homlity-plugin'),
                ]),
                $this->elementorWidget('property_listing', [
                    'template' => 'default',
                    'query_mode' => 'current',
                    'default_view' => 'grid',
                    'show_view_toggle' => 'yes',
                    'columns' => '3',
                    'posts_per_page' => 12,
                    'default_orderby' => 'date',
                    'show_sort' => 'yes',
                    'map_height' => ['unit' => 'px', 'size' => 500, 'sizes' => []],
                    'map_zoom' => 12,
                ]),
            ]),
        ];

        $this->saveElementorData($pageId, $data, 'wp-page');
        update_option('homlity_plugin_archive_elementor_page_id', $pageId);
    }

    private function seedSingleElementorTemplate(): void
    {
        $optionKey = 'homlity_plugin_single_template_id';
        $templateId = (int) get_option($optionKey, 0);

        if ($templateId > 0 && get_post_status($templateId)) {
            return;
        }

        $templateId = wp_insert_post([
            'post_title' => __('Detalle de inmueble', 'homlity-plugin'),
            'post_name' => 'homlity-detalle-inmueble',
            'post_status' => 'publish',
            'post_type' => 'elementor_library',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ]);

        if (is_wp_error($templateId) || !$templateId) {
            return;
        }

        $data = [
            $this->elementorSection([
                $this->elementorWidget('property_summary'),
                $this->elementorWidget('property_gallery'),
                $this->elementorWidget('property_features_primary'),
                $this->elementorWidget('property_features_secondary'),
                $this->elementorWidget('property_map'),
                $this->elementorWidget('property_agent'),
                $this->elementorWidget('property_related'),
            ]),
        ];

        $this->saveElementorData((int) $templateId, $data, 'single');

        if (taxonomy_exists('elementor_library_type')) {
            wp_set_object_terms((int) $templateId, 'single', 'elementor_library_type');
        }

        update_option($optionKey, (int) $templateId);
    }

    private function saveElementorData(int $postId, array $data, string $templateType): void
    {
        update_post_meta($postId, '_elementor_edit_mode', 'builder');
        update_post_meta($postId, '_elementor_template_type', $templateType);
        update_post_meta($postId, '_elementor_version', defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '3.0.0');
        update_post_meta($postId, '_elementor_data', wp_slash(wp_json_encode($data)));
    }

    private function elementorSection(array $widgets): array
    {
        return [
            'id' => $this->elementorId(),
            'elType' => 'section',
            'settings' => [],
            'elements' => [
                [
                    'id' => $this->elementorId(),
                    'elType' => 'column',
                    'settings' => ['_column_size' => 100, '_inline_size' => null],
                    'elements' => $widgets,
                    'isInner' => false,
                ],
            ],
            'isInner' => false,
        ];
    }

    private function elementorWidget(string $widgetType, array $settings = []): array
    {
        return [
            'id' => $this->elementorId(),
            'elType' => 'widget',
            'settings' => $settings,
            'elements' => [],
            'widgetType' => $widgetType,
        ];
    }

    private function elementorId(): string
    {
        return substr(md5(uniqid('', true)), 0, 7);
    }
}
