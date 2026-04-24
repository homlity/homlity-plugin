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

    private function seedArchivePage(): void
    {
        $optionKey = 'homlity_plugin_archive_page_id';
        $pageId    = (int) get_option($optionKey, 0);

        if ($pageId > 0 && 'publish' === get_post_status($pageId)) {
            return;
        }

        $pageId = wp_insert_post([
            'post_title'     => __('Resultados de inmuebles', 'homlity-plugin'),
            'post_name'      => 'propiedades',
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
}
