<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
/**
 * Seeds default terms and data on activation.
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

class DataSeederService
{
    private const BUILDER_OPTION = 'homlity_plugin_visual_builder';
    private const SINGLE_TEMPLATE_VERSION = '2';
    private const WPBAKERY_SINGLE_TEMPLATE_VERSION = '3';
    private const WPBAKERY_ARCHIVE_TEMPLATE_VERSION = '2';
    private const AGENT_PROFILE_TEMPLATE_VERSION = '2';
    private const TECHNICAL_SHEET_TEMPLATE_VERSION = '1';

    public function seed(): void
    {
        $this->seedPages();
        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_TYPE, [
            'Apartamento',
            'Casa',
            'Lote',
            'Finca',
            'Apartaestudio',
            'Penthouse',
            'Local',
            'Casa Comercial',
            'Parqueadero',
            'Edificio',
        ]);

        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_CATEGORY, [
            'Residencial',
            'Comercial',
            'Lote / Terreno',
        ]);

        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_OPERATION, [
            'Arriendo',
            'Venta',
            'Arriendo/Venta',
            'Permuta',
        ]);

        $this->seedTaxonomy(PropertyTaxonomies::TAXONOMY_TAG, [
            'Destacado',
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

        $this->seedBuilderTemplates();
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
        if ($this->preferredBuilder() !== 'elementor') {
            return;
        }
        if (!post_type_exists('elementor_library') && !class_exists('\Elementor\Plugin') && !defined('ELEMENTOR_VERSION')) {
            return;
        }

        $this->seedArchiveElementorPage();
        $this->seedSingleElementorTemplate();
        $this->seedAgentProfileElementorPage();
        $this->seedTechnicalSheetElementorPage();
        $this->seedUnavailableElementorPage();
    }

    public function seedBuilderTemplates(): void
    {
        $builder = $this->preferredBuilder();
        update_option(self::BUILDER_OPTION, $builder);

        if ($builder === 'elementor') {
            $this->seedElementorTemplates();
            return;
        }

        $this->seedBuilderPage(
            (int) get_option('homlity_plugin_archive_page_id', 0),
            'archive',
            $builder,
            $this->archiveBuilderContent($builder, (int) get_option('homlity_plugin_archive_page_id', 0))
        );
        $agentPageId = $this->ensurePage('homlity_plugin_agent_profile_page_id', 'perfil-asesor', __('Perfil del asesor', 'homlity-real-estate'));
        $sheetPageId = $this->ensurePage('homlity_plugin_sheet_page_id', 'ficha-tecnica-inmueble', __('Ficha técnica', 'homlity-real-estate'));
        $unavailablePageId = $this->ensurePage('homlity_plugin_unavailable_template_id', 'inmueble-no-disponible', __('Inmueble no disponible', 'homlity-real-estate'));
        $singlePageId = $this->ensurePage('homlity_plugin_single_template_id', 'plantilla-detalle-inmueble', __('Detalle de inmueble', 'homlity-real-estate'));
        $this->seedBuilderPage(
            $agentPageId,
            'agent_profile',
            $builder,
            $this->agentProfileBuilderContent($builder)
        );
        $this->seedBuilderPage(
            $sheetPageId,
            'technical_sheet',
            $builder,
            $this->technicalSheetBuilderContent($builder)
        );
        $this->seedBuilderPage(
            $unavailablePageId,
            'unavailable',
            $builder,
            $this->unavailableBuilderContent($builder)
        );
        $this->seedBuilderPage($singlePageId, 'single_property', $builder, $this->singleBuilderContent($builder));
        if ($unavailablePageId > 0) {
            update_option('homlity_plugin_unavailable_page_layout', 'default');
        }
    }

    /**
     * Ensures the technical sheet page exists for the active builder, without
     * re-seeding the rest of the templates. Called on upgrade so an existing
     * install gets the page that makes the sheet editable with its builder.
     *
     * Skipped on 'native': there is no builder to configure and seeding would
     * only move the sheet URL for no gain.
     */
    public function seedTechnicalSheetPage(): void
    {
        $builder = $this->preferredBuilder();

        if ($builder === 'elementor') {
            if (!post_type_exists('elementor_library') && !class_exists('\Elementor\Plugin') && !defined('ELEMENTOR_VERSION')) {
                return;
            }
            $this->seedTechnicalSheetElementorPage();
            return;
        }

        if ($builder !== 'divi' && $builder !== 'wpbakery') {
            return;
        }

        $sheetPageId = $this->ensurePage(
            'homlity_plugin_sheet_page_id',
            'ficha-tecnica-inmueble',
            __('Ficha técnica', 'homlity-real-estate')
        );
        $this->seedBuilderPage(
            $sheetPageId,
            'technical_sheet',
            $builder,
            $this->technicalSheetBuilderContent($builder)
        );
    }

    private function preferredBuilder(): string
    {
        $stored = sanitize_key((string) get_option(self::BUILDER_OPTION, ''));
        $explicit = (string) get_option('homlity_plugin_visual_builder_explicit', '') === '1';
        if ($explicit && $stored !== '' && $this->builderActive($stored)) {
            return $stored;
        }

        $archiveId = (int) get_option('homlity_plugin_archive_page_id', 0);
        if ($archiveId > 0) {
            $seededBuilder = sanitize_key((string) get_post_meta($archiveId, '_homlity_seeded_builder', true));
            $hasVisualBuilder = $this->elementorActive() || $this->diviActive() || $this->wpBakeryActive();

            // The live builder metadata is the authoritative signal. A page
            // converted from Divi to Elementor can legitimately retain the
            // old Homlity seed marker until the migration completes.
            if (get_post_meta($archiveId, '_elementor_edit_mode', true) === 'builder' && $this->elementorActive()) {
                return 'elementor';
            }
            if (get_post_meta($archiveId, '_et_pb_use_builder', true) === 'on' && $this->diviActive()) {
                return 'divi';
            }
            if (get_post_meta($archiveId, '_wpb_vc_js_status', true) === 'true' && $this->wpBakeryActive()) {
                return 'wpbakery';
            }

            if (
                $seededBuilder !== ''
                && $this->builderActive($seededBuilder)
                && ($seededBuilder !== 'native' || !$hasVisualBuilder)
            ) {
                return $seededBuilder;
            }
        }

        $visualBuilderAvailable = $this->elementorActive() || $this->diviActive() || $this->wpBakeryActive();
        if (
            $stored !== ''
            && $this->builderActive($stored)
            && ($stored !== 'native' || !$visualBuilderAvailable)
        ) {
            return $stored;
        }

        $scores = [
            'elementor' => $this->builderUsageCount('_elementor_edit_mode', 'builder'),
            'divi' => $this->builderUsageCount('_et_pb_use_builder', 'on'),
            'wpbakery' => $this->builderUsageCount('_wpb_vc_js_status', 'true'),
        ];
        foreach ($scores as $builder => $score) {
            if (!$this->builderActive($builder)) {
                $scores[$builder] = -1;
            }
        }

        $highest = max($scores);
        if ($highest > 0) {
            $candidates = array_keys($scores, $highest, true);
            if (count($candidates) === 1) {
                return $candidates[0];
            }
        }

        // An active Divi/Extra theme is a stronger signal than the standalone
        // plugin list because it controls the current page renderer.
        if ($this->diviThemeActive()) {
            return 'divi';
        }
        if ($this->elementorActive()) {
            return 'elementor';
        }
        if ($this->diviActive()) {
            return 'divi';
        }
        if ($this->wpBakeryActive()) {
            return 'wpbakery';
        }
        return 'native';
    }

    private function builderActive(string $builder): bool
    {
        return match ($builder) {
            'elementor' => $this->elementorActive(),
            'divi' => $this->diviActive(),
            'wpbakery' => $this->wpBakeryActive(),
            'native' => true,
            default => false,
        };
    }

    private function builderUsageCount(string $metaKey, string $metaValue): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.post_id)
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s
               AND pm.meta_value = %s
               AND p.post_status NOT IN ('trash', 'auto-draft')
               AND p.post_type IN ('page', 'post', 'property', 'elementor_library')",
            $metaKey,
            $metaValue
        ));
    }

    private function pluginActive(string $plugin): bool
    {
        if (!function_exists('is_plugin_active')) {
            $file = ABSPATH . 'wp-admin/includes/plugin.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        return function_exists('is_plugin_active') && is_plugin_active($plugin);
    }

    private function diviActive(): bool
    {
        return $this->diviThemeActive()
            || $this->pluginActive('divi-builder/divi-builder.php');
    }

    private function diviThemeActive(): bool
    {
        $template = strtolower((string) get_template());
        $stylesheet = strtolower((string) get_stylesheet());
        return in_array($template, ['divi', 'extra'], true)
            || in_array($stylesheet, ['divi', 'extra'], true);
    }

    private function elementorActive(): bool
    {
        return defined('ELEMENTOR_VERSION') || class_exists('\\Elementor\\Plugin') || $this->pluginActive('elementor/elementor.php');
    }

    private function wpBakeryActive(): bool
    {
        return defined('WPB_VC_VERSION') || class_exists('Vc_Manager') || $this->pluginActive('js_composer/js_composer.php');
    }

    private function ensurePage(string $optionKey, string $slug, string $title): int
    {
        $pageId = (int) get_option($optionKey, 0);
        if ($pageId > 0 && get_post_status($pageId) && get_post_type($pageId) === 'page') {
            return $pageId;
        }
        $existing = get_posts([
            'name' => $slug, 'post_type' => 'page', 'post_status' => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1, 'no_found_rows' => true, 'fields' => 'ids',
        ]);
        if ($existing !== []) {
            $pageId = (int) $existing[0];
        } else {
            $created = wp_insert_post([
                'post_title' => $title, 'post_name' => $slug, 'post_content' => '',
                'post_status' => 'publish', 'post_type' => 'page',
                'comment_status' => 'closed', 'ping_status' => 'closed',
            ]);
            $pageId = is_wp_error($created) ? 0 : (int) $created;
        }
        if ($pageId > 0) {
            update_option($optionKey, $pageId);
        }
        return $pageId;
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
            'post_title'     => __('Resultados de inmuebles', 'homlity-real-estate'),
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
                $this->elementorWidget('property_results_title', [
                    'base_text' => __('Resultados de inmuebles', 'homlity-real-estate'),
                    'title_tag' => 'h1',
                    'show_total' => 'yes',
                ]),
                $this->elementorWidget('property_filter', [
                    'target_page_id' => (string) $pageId,
                    'show_keyword' => 'yes',
                    'show_operation' => 'yes',
                    'show_type' => 'yes',
                    'show_city' => 'yes',
                    'show_price' => 'yes',
                    'show_reset' => 'yes',
                    'submit_label' => __('Buscar', 'homlity-real-estate'),
                    'reset_label' => __('Limpiar', 'homlity-real-estate'),
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

        $this->saveElementorData($pageId, $data, 'wp-page', 'archive');
        update_option('homlity_plugin_archive_elementor_page_id', $pageId);
    }

    private function seedSingleElementorTemplate(): void
    {
        $optionKey  = 'homlity_plugin_single_template_id';
        $templateId = (int) get_option($optionKey, 0);

        // Fast path: option exists and post is live.
        if (
            $templateId > 0
            && in_array(get_post_status($templateId), ['publish', 'draft', 'pending'], true)
            && get_post_type($templateId) === 'elementor_library'
            && get_post_meta($templateId, '_elementor_edit_mode', true) === 'builder'
        ) {
            return;
        }

        // Secondary check by slug — handles reactivations where the option was lost
        // but the post was never deleted.
        $existing = get_posts([
            'name'           => 'homlity-detalle-inmueble',
            'post_type'      => 'elementor_library',
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
        ]);

        if (!empty($existing)) {
            $templateId = (int) $existing[0];
            if (get_post_meta($templateId, '_elementor_edit_mode', true) === 'builder') {
                update_option($optionKey, $templateId);
                $this->removeSingleTemplateDuplicates($templateId);
                return;
            }
        } else {
            // Nothing found — create the template for the first time.
            $templateId = wp_insert_post([
                'post_title'     => __('Detalle de inmueble', 'homlity-real-estate'),
                'post_name'      => 'homlity-detalle-inmueble',
                'post_status'    => 'publish',
                'post_type'      => 'elementor_library',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ]);

            if (is_wp_error($templateId) || !$templateId) {
                return;
            }
        }

        $data = [
            $this->elementorSection([
                $this->elementorWidget('property_title', [
                    'title_align' => 'center',
                ]),
                $this->elementorWidget('property_breadcrumb'),
                $this->elementorWidget('property_media_tabs'),
                $this->elementorWidget('property_operation_price'),
                $this->elementorWidget('property_content'),
                $this->elementorWidget('property_features_primary', [
                    'list_columns' => '4',
                    'list_columns_tablet' => '2',
                    'list_columns_mobile' => '1',
                ]),
                $this->elementorWidget('property_features_secondary', [
                    'list_columns' => '4',
                    'list_columns_tablet' => '2',
                    'list_columns_mobile' => '1',
                ]),
                $this->elementorWidget('property_share'),
                $this->elementorWidget('property_map'),
                $this->elementorWidget('property_agent'),
                $this->elementorWidget('homlity_property_faq', [
                    'enable_auto_faqs' => 'yes',
                    'include_global_faqs' => 'yes',
                ]),
            ]),
        ];

        $this->saveElementorData((int) $templateId, $data, 'single', 'single_property');

        if (taxonomy_exists('elementor_library_type')) {
            wp_set_object_terms((int) $templateId, 'single', 'elementor_library_type');
        }

        update_option($optionKey, (int) $templateId);
    }

    private function removeSingleTemplateDuplicates(int $keepId): void
    {
        global $wpdb;

        $duplicateIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type   = 'elementor_library'
                   AND post_name   LIKE %s
                   AND post_status NOT IN ('trash', 'auto-draft')
                   AND ID          != %d
                 LIMIT 50",
                'homlity-detalle-inmueble%',
                $keepId
            )
        );

        foreach ($duplicateIds as $id) {
            wp_trash_post((int) $id);
        }
    }

    private function seedAgentProfileElementorPage(): void
    {
        $optionKey = 'homlity_plugin_agent_profile_page_id';
        $pageId = (int) get_option($optionKey, 0);

        if (
            $pageId > 0
            && in_array(get_post_status($pageId), ['publish', 'draft', 'pending'], true)
            && get_post_meta($pageId, '_elementor_edit_mode', true) === 'builder'
            && !$this->agentProfileElementorNeedsUpgrade($pageId)
        ) {
            return;
        }

        $existing = get_posts([
            'name' => 'perfil-asesor',
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'fields' => 'ids',
        ]);

        if (!empty($existing)) {
            $pageId = (int) $existing[0];
            update_option($optionKey, $pageId);
        } else {
            $pageId = wp_insert_post([
                'post_title' => __('Perfil del asesor', 'homlity-real-estate'),
                'post_name' => 'perfil-asesor',
                'post_content' => '',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ]);
            if (is_wp_error($pageId) || !$pageId) {
                return;
            }
            update_option($optionKey, (int) $pageId);
        }

        if (
            (int) get_option('homlity_plugin_agent_profile_elementor_page_id', 0) === (int) $pageId &&
            get_post_meta((int) $pageId, '_elementor_edit_mode', true) === 'builder' &&
            !$this->agentProfileElementorNeedsUpgrade((int) $pageId)
        ) {
            return;
        }

        $data = [
            $this->elementorSection([
                $this->elementorWidget('property_agent', [
                    'data_source' => 'current_agent',
                    'show_property_count' => 'yes',
                    'show_bio' => 'yes',
                ]),
                $this->elementorWidget('property_listing', [
                    'query_mode' => 'custom',
                    'use_current_agent' => 'yes',
                    'default_view' => 'grid',
                    'columns' => 3,
                    'posts_per_page' => 12,
                    'show_grid_view' => 'yes',
                    'show_map_view' => '',
                    'show_view_toggle' => '',
                    'show_sort' => 'yes',
                    'show_results_count' => 'yes',
                    'show_pagination' => 'yes',
                ]),
            ]),
        ];

        $this->saveElementorData((int) $pageId, $data, 'wp-page', 'agent_profile');
        update_post_meta((int) $pageId, '_homlity_seeded_template_version', self::AGENT_PROFILE_TEMPLATE_VERSION);
        update_option('homlity_plugin_agent_profile_elementor_page_id', (int) $pageId);
    }

    private function seedTechnicalSheetElementorPage(): void
    {
        $optionKey = 'homlity_plugin_sheet_page_id';
        $pageId = (int) get_option($optionKey, 0);

        if (
            $pageId > 0
            && in_array(get_post_status($pageId), ['publish', 'draft', 'pending'], true)
            && get_post_meta($pageId, '_elementor_edit_mode', true) === 'builder'
        ) {
            return;
        }

        $existing = get_posts([
            'name' => 'ficha-tecnica-inmueble',
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'fields' => 'ids',
        ]);

        if (!empty($existing)) {
            $pageId = (int) $existing[0];
            update_option($optionKey, $pageId);
        } else {
            $pageId = wp_insert_post([
                'post_title' => __('Ficha técnica', 'homlity-real-estate'),
                'post_name' => 'ficha-tecnica-inmueble',
                'post_content' => '',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ]);
            if (is_wp_error($pageId) || !$pageId) {
                return;
            }
            update_option($optionKey, (int) $pageId);
        }

        if (
            (int) get_option('homlity_plugin_sheet_elementor_page_id', 0) === (int) $pageId &&
            get_post_meta((int) $pageId, '_elementor_edit_mode', true) === 'builder'
        ) {
            return;
        }

        $data = [
            $this->elementorSection([
                $this->elementorWidget('property_technical_sheet', [
                    'show_address' => '',
                ]),
            ]),
        ];

        $this->saveElementorData((int) $pageId, $data, 'wp-page', 'technical_sheet');
        update_post_meta((int) $pageId, '_homlity_seeded_template_version', self::TECHNICAL_SHEET_TEMPLATE_VERSION);
        update_option('homlity_plugin_sheet_elementor_page_id', (int) $pageId);
    }

    /**
     * True when the page still holds the untouched v1 layout (a lone
     * [homlity_agent_profile] shortcode widget). Any page the site owner has
     * actually edited is left alone.
     */
    private function agentProfileElementorNeedsUpgrade(int $pageId): bool
    {
        if ((string) get_post_meta($pageId, '_homlity_seeded_purpose', true) !== 'agent_profile') {
            return false;
        }
        if ((string) get_post_meta($pageId, '_homlity_seeded_template_version', true) === self::AGENT_PROFILE_TEMPLATE_VERSION) {
            return false;
        }

        $data = (string) get_post_meta($pageId, '_elementor_data', true);
        if ($data === '' || !str_contains($data, '[homlity_agent_profile]')) {
            return false;
        }

        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            return false;
        }

        return $this->countElementorWidgets($decoded) === 1;
    }

    private function countElementorWidgets(array $elements): int
    {
        $total = 0;
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }
            if (($element['elType'] ?? '') === 'widget') {
                $total++;
            }
            if (!empty($element['elements']) && is_array($element['elements'])) {
                $total += $this->countElementorWidgets($element['elements']);
            }
        }

        return $total;
    }

    private function seedUnavailableElementorPage(): void
    {
        $optionKey = 'homlity_plugin_unavailable_template_id';
        $pageId = (int) get_option($optionKey, 0);

        if (
            $pageId > 0
            && in_array(get_post_status($pageId), ['publish', 'draft', 'pending'], true)
            && get_post_meta($pageId, '_elementor_edit_mode', true) === 'builder'
        ) {
            return;
        }

        $existing = get_posts([
            'name' => 'inmueble-no-disponible',
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'fields' => 'ids',
        ]);

        if (!empty($existing)) {
            $pageId = (int) $existing[0];
            update_option($optionKey, $pageId);
        } else {
            $pageId = wp_insert_post([
                'post_title' => __('Inmueble no disponible', 'homlity-real-estate'),
                'post_name' => 'inmueble-no-disponible',
                'post_content' => '',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ]);
            if (is_wp_error($pageId) || !$pageId) {
                return;
            }
            update_option($optionKey, (int) $pageId);
        }

        if ((string) get_option('homlity_plugin_unavailable_page_layout', '') === '') {
            update_option('homlity_plugin_unavailable_page_layout', 'elementor_canvas');
        }

        if (
            (int) get_option('homlity_plugin_unavailable_elementor_page_id', 0) === (int) $pageId &&
            get_post_meta((int) $pageId, '_elementor_edit_mode', true) === 'builder'
        ) {
            return;
        }

        $archivePageId = (int) get_option('homlity_plugin_archive_page_id', 0);
        $archiveUrl = $archivePageId > 0 ? get_permalink($archivePageId) : home_url('/inmuebles/');
        $buttonUrl = $archiveUrl ? $archiveUrl : home_url('/inmuebles/');

        $data = [
            $this->elementorSection([
                $this->elementorWidget('heading', [
                    'title' => __('Inmueble no disponible', 'homlity-real-estate'),
                    'header_size' => 'h1',
                    'align' => 'center',
                ]),
                $this->elementorWidget('text-editor', [
                    'editor' => __('El inmueble que buscas fue retirado o está fuera de publicación. Explora otras opciones disponibles.', 'homlity-real-estate'),
                    'align' => 'center',
                ]),
                $this->elementorWidget('button', [
                    'text' => __('Ver otros inmuebles', 'homlity-real-estate'),
                    'link' => [
                        'url' => $buttonUrl,
                        'is_external' => '',
                        'nofollow' => '',
                        'custom_attributes' => '',
                    ],
                    'align' => 'center',
                    'size' => 'md',
                ]),
            ]),
        ];

        $this->saveElementorData((int) $pageId, $data, 'wp-page', 'unavailable');
        update_option('homlity_plugin_unavailable_elementor_page_id', (int) $pageId);
    }

    private function seedBuilderPage(int $pageId, string $purpose, string $builder, string $content): void
    {
        if ($pageId <= 0 || !get_post_status($pageId)) {
            return;
        }
        $seededBy = (string) get_post_meta($pageId, '_homlity_seeded_builder', true);
        $existingContent = trim((string) get_post_field('post_content', $pageId));
        if ($existingContent !== '' && $seededBy === '') {
            return; // Never overwrite user-authored builder content.
        }
        $sameSeed = $seededBy === $builder
            && (string) get_post_meta($pageId, '_homlity_seeded_purpose', true) === $purpose;
        if ($sameSeed) {
            $templateVersion = (string) get_post_meta($pageId, '_homlity_seeded_template_version', true);
            $expectedVersion = $this->builderTemplateVersion($builder, $purpose);
            if ($expectedVersion === '' || $templateVersion === $expectedVersion) {
                return;
            }

            // Upgrade only an untouched default layout. A page edited
            // by the site owner must never be replaced during a plugin update.
            $legacyContent = match ($purpose) {
                'archive' => $this->legacyArchiveBuilderContent($builder, $pageId),
                'agent_profile' => $this->wrapBuilderContent($builder, '[homlity_agent_profile]'),
                'technical_sheet' => $this->wrapBuilderContent($builder, '[homlity_technical_sheet]'),
                default => $this->legacySingleBuilderContent($builder),
            };
            if ($existingContent !== trim($legacyContent)) {
                return;
            }
        }

        wp_update_post(['ID' => $pageId, 'post_content' => wp_slash($content)]);
        update_post_meta($pageId, '_homlity_seeded_builder', $builder);
        update_post_meta($pageId, '_homlity_seeded_purpose', $purpose);
        $templateVersion = $this->builderTemplateVersion($builder, $purpose);
        if ($templateVersion !== '') {
            update_post_meta($pageId, '_homlity_seeded_template_version', $templateVersion);
        }

        if ($builder === 'divi') {
            delete_post_meta($pageId, '_elementor_edit_mode');
            delete_post_meta($pageId, '_elementor_data');
            delete_post_meta($pageId, '_elementor_template_type');
            update_post_meta($pageId, '_et_pb_use_builder', 'on');
            update_post_meta($pageId, '_et_pb_page_layout', 'et_full_width_page');
            update_post_meta($pageId, '_et_pb_built_for_post_type', 'page');
        } elseif ($builder === 'wpbakery') {
            delete_post_meta($pageId, '_elementor_edit_mode');
            delete_post_meta($pageId, '_elementor_data');
            delete_post_meta($pageId, '_elementor_template_type');
            delete_post_meta($pageId, '_et_pb_use_builder');
            delete_post_meta($pageId, '_et_pb_page_layout');
            delete_post_meta($pageId, '_et_pb_built_for_post_type');
            update_post_meta($pageId, '_wpb_vc_js_status', 'true');
            update_post_meta($pageId, '_vc_post_settings', ['vc_grid_id' => []]);
        }
    }

    private function archiveBuilderContent(string $builder, int $archivePageId = 0): string
    {
        $title = esc_html__('Resultados de inmuebles', 'homlity-real-estate');
        $listing = '[homlity_listing view="grid" columns="3" per_page="12" filters="true" sort="true"]';
        if ($builder === 'divi') {
            $filterTarget = $archivePageId > 0 ? ' target_page_id="' . $archivePageId . '"' : '';
            return '[et_pb_section][et_pb_row][et_pb_column type="4_4"]'
                . '[homlity_divi_property_results_title base_text="' . esc_attr($title) . '"][/homlity_divi_property_results_title]'
                . '[homlity_divi_property_filter' . $filterTarget . '][/homlity_divi_property_filter]'
                . '[homlity_divi_property_listing query_mode="current" default_view="grid" columns="3" posts_per_page="12" show_sort="on"][/homlity_divi_property_listing]'
                . '[/et_pb_column][/et_pb_row][/et_pb_section]';
        }
        if ($builder === 'wpbakery') {
            $filterTarget = $archivePageId > 0 ? ' target_page_id="' . $archivePageId . '"' : '';
            return '[vc_row][vc_column]'
                . '[homlity_wpb_property_results_title base_text="' . esc_attr($title) . '"][/homlity_wpb_property_results_title]'
                . '[homlity_wpb_property_filter' . $filterTarget . '][/homlity_wpb_property_filter]'
                . '[homlity_wpb_property_listing query_mode="current" default_view="grid" columns="3" posts_per_page="12" show_sort="yes"][/homlity_wpb_property_listing]'
                . '[/vc_column][/vc_row]';
        }
        return '<h1>' . $title . '</h1>' . $listing;
    }

    /**
     * Default layout of the advisor profile page: the advisor card bound to the
     * advisor of the request, plus a listing scoped to that advisor.
     */
    private function agentProfileBuilderContent(string $builder): string
    {
        if ($builder === 'divi') {
            return '[et_pb_section][et_pb_row][et_pb_column type="4_4"]'
                . '[homlity_divi_property_agent data_source="current_agent" show_property_count="on" show_bio="on"][/homlity_divi_property_agent]'
                . '[homlity_divi_property_listing query_mode="custom" use_current_agent="on" default_view="grid" columns="3" posts_per_page="12" show_sort="on" show_map_view="off" show_view_toggle="off"][/homlity_divi_property_listing]'
                . '[/et_pb_column][/et_pb_row][/et_pb_section]';
        }
        if ($builder === 'wpbakery') {
            return '[vc_row][vc_column]'
                . '[homlity_wpb_property_agent data_source="current_agent" show_property_count="yes" show_bio="yes"][/homlity_wpb_property_agent]'
                . '[homlity_wpb_property_listing query_mode="custom" use_current_agent="yes" default_view="grid" columns="3" posts_per_page="12" show_sort="yes" show_map_view="" show_view_toggle=""][/homlity_wpb_property_listing]'
                . '[/vc_column][/vc_row]';
        }

        return $this->wrapBuilderContent($builder, '[homlity_agent_profile]');
    }

    /**
     * Default layout of the technical sheet page: the sheet bound to the
     * property of the request.
     */
    private function technicalSheetBuilderContent(string $builder): string
    {
        if ($builder === 'divi') {
            return '[et_pb_section][et_pb_row][et_pb_column type="4_4"]'
                . '[homlity_divi_property_technical_sheet show_address="off"][/homlity_divi_property_technical_sheet]'
                . '[/et_pb_column][/et_pb_row][/et_pb_section]';
        }
        if ($builder === 'wpbakery') {
            return '[vc_row][vc_column]'
                . '[homlity_wpb_property_technical_sheet show_address=""][/homlity_wpb_property_technical_sheet]'
                . '[/vc_column][/vc_row]';
        }

        return $this->wrapBuilderContent($builder, '[homlity_technical_sheet]');
    }

    private function unavailableBuilderContent(string $builder): string
    {
        $content = '[homlity_unavailable_notice][homlity_unavailable_search_context][homlity_unavailable_similar_properties]';
        return $this->wrapBuilderContent($builder, $content);
    }

    private function singleBuilderContent(string $builder): string
    {
        if ($builder === 'divi') {
            $modules = [
                'property_title' => ' title_align="center"',
                'property_breadcrumb' => '',
                'property_media_tabs' => '',
                'property_operation_price' => '',
                'property_content' => '',
                'property_features_primary' => ' list_columns="4"',
                'property_features_secondary' => ' list_columns="4"',
                'property_share' => '',
                'property_map' => '',
                'property_agent' => '',
                'property_faq' => ' enable_auto_faqs="on" include_global_faqs="on"',
            ];
            $content = '';
            foreach ($modules as $module => $attributes) {
                $content .= '[homlity_divi_' . $module . $attributes . '][/homlity_divi_' . $module . ']';
            }
            return '[et_pb_section][et_pb_row][et_pb_column type="4_4"]' . $content
                . '[/et_pb_column][/et_pb_row][/et_pb_section]';
        }
        if ($builder === 'wpbakery') {
            $modules = [
                'property_title' => ' title_align="center"',
                'property_breadcrumb' => '',
                'property_media_tabs' => '',
                'property_operation_price' => '',
                'property_content' => '',
                'property_features_primary' => ' list_columns="4"',
                'property_features_secondary' => ' list_columns="4"',
                'property_share' => '',
                'property_map' => '',
                'property_agent' => '',
                'property_faq' => ' enable_auto_faqs="yes" include_global_faqs="yes"',
            ];
            $content = '';
            foreach ($modules as $module => $attributes) {
                $content .= '[homlity_wpb_' . $module . $attributes . '][/homlity_wpb_' . $module . ']';
            }
            return '[vc_row][vc_column]' . $content . '[/vc_column][/vc_row]';
        }
        return $this->wrapBuilderContent($builder, '[homlity_property_detail]');
    }

    private function singleTemplateVersion(string $builder): string
    {
        return $builder === 'wpbakery'
            ? self::WPBAKERY_SINGLE_TEMPLATE_VERSION
            : self::SINGLE_TEMPLATE_VERSION;
    }

    private function builderTemplateVersion(string $builder, string $purpose): string
    {
        if ($purpose === 'single_property') {
            return $this->singleTemplateVersion($builder);
        }
        if ($purpose === 'archive' && $builder === 'wpbakery') {
            return self::WPBAKERY_ARCHIVE_TEMPLATE_VERSION;
        }
        if ($purpose === 'agent_profile') {
            return self::AGENT_PROFILE_TEMPLATE_VERSION;
        }
        if ($purpose === 'technical_sheet') {
            return self::TECHNICAL_SHEET_TEMPLATE_VERSION;
        }
        return '';
    }

    private function legacyArchiveBuilderContent(string $builder, int $archivePageId): string
    {
        if ($builder !== 'wpbakery') {
            return '';
        }
        $title = esc_html__('Resultados de inmuebles', 'homlity-real-estate');
        return '[vc_row][vc_column][vc_column_text]<h1>' . $title . '</h1>[/vc_column_text]'
            . '[homlity_listing view="grid" columns="3" per_page="12" filters="true" sort="true"]'
            . '[/vc_column][/vc_row]';
    }

    /**
     * Contenido con el que se sembraban las plantillas en versiones
     * anteriores. Es la huella que permite distinguir una plantilla sin tocar
     * de una que el dueño del sitio editó, así que describe lo que hay escrito
     * en esas páginas —incluido `property_related`, un módulo que ya no
     * existe— y no lo que se sembraría hoy.
     */
    private function legacySingleBuilderContent(string $builder): string
    {
        if ($builder === 'divi') {
            $modules = [
                'property_breadcrumb', 'property_title', 'property_media_tabs',
                'property_operation_price', 'property_content', 'property_features_primary',
                'property_features_secondary', 'property_share', 'property_map',
                'property_agent', 'property_related',
            ];
            $content = '';
            foreach ($modules as $module) {
                $content .= '[homlity_divi_' . $module . '][/homlity_divi_' . $module . ']';
            }
            return '[et_pb_section][et_pb_row][et_pb_column type="4_4"]' . $content
                . '[/et_pb_column][/et_pb_row][/et_pb_section]';
        }

        return $this->wrapBuilderContent($builder, '[homlity_property_detail]');
    }

    private function wrapBuilderContent(string $builder, string $content): string
    {
        if ($builder === 'divi') {
            return '[et_pb_section][et_pb_row][et_pb_column type="4_4"][et_pb_text]'
                . $content . '[/et_pb_text][/et_pb_column][/et_pb_row][/et_pb_section]';
        }
        if ($builder === 'wpbakery') {
            return '[vc_row][vc_column][vc_column_text]' . $content . '[/vc_column_text][/vc_column][/vc_row]';
        }
        return $content;
    }

    private function saveElementorData(int $postId, array $data, string $templateType, string $purpose): void
    {
        $previousBuilder = (string) get_post_meta($postId, '_homlity_seeded_builder', true);
        if ($previousBuilder !== '' && $previousBuilder !== 'elementor') {
            wp_update_post(['ID' => $postId, 'post_content' => '']);
        }
        delete_post_meta($postId, '_et_pb_use_builder');
        delete_post_meta($postId, '_et_pb_page_layout');
        delete_post_meta($postId, '_et_pb_built_for_post_type');
        delete_post_meta($postId, '_wpb_vc_js_status');
        delete_post_meta($postId, '_vc_post_settings');
        update_post_meta($postId, '_elementor_edit_mode', 'builder');
        update_post_meta($postId, '_elementor_template_type', $templateType);
        update_post_meta($postId, '_elementor_version', defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '3.0.0');
        update_post_meta($postId, '_elementor_data', wp_slash(wp_json_encode($data)));
        update_post_meta($postId, '_homlity_seeded_builder', 'elementor');
        update_post_meta($postId, '_homlity_seeded_purpose', $purpose);
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
