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
        $this->seedUnavailableElementorPage();
    }

    public function seedBuilderTemplates(): void
    {
        $builder = $this->preferredBuilder();
        if ($builder === 'elementor') {
            $this->seedElementorTemplates();
            return;
        }

        $this->seedBuilderPage(
            (int) get_option('homlity_plugin_archive_page_id', 0),
            'archive',
            $builder,
            $this->archiveBuilderContent($builder)
        );
        $agentPageId = $this->ensurePage('homlity_plugin_agent_profile_page_id', 'perfil-asesor', __('Perfil del asesor', 'homlity-real-estate'));
        $unavailablePageId = $this->ensurePage('homlity_plugin_unavailable_template_id', 'inmueble-no-disponible', __('Inmueble no disponible', 'homlity-real-estate'));
        $singlePageId = $this->ensurePage('homlity_plugin_single_template_id', 'plantilla-detalle-inmueble', __('Detalle de inmueble', 'homlity-real-estate'));
        $this->seedBuilderPage(
            $agentPageId,
            'agent_profile',
            $builder,
            $this->wrapBuilderContent($builder, '[homlity_agent_profile]')
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

    private function preferredBuilder(): string
    {
        $archiveId = (int) get_option('homlity_plugin_archive_page_id', 0);
        if ($archiveId > 0) {
            if (get_post_meta($archiveId, '_elementor_edit_mode', true) === 'builder' && $this->elementorActive()) {
                return 'elementor';
            }
            if (get_post_meta($archiveId, '_et_pb_use_builder', true) === 'on' && $this->diviActive()) {
                return 'divi';
            }
            if (get_post_meta($archiveId, '_wpb_vc_js_status', true) === 'true' && $this->wpBakeryActive()) {
                return 'wpbakery';
            }
        }

        if ($this->diviActive()) {
            return 'divi';
        }
        if ($this->elementorActive()) {
            return 'elementor';
        }
        if ($this->wpBakeryActive()) {
            return 'wpbakery';
        }
        return 'native';
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
        $template = strtolower((string) get_template());
        $stylesheet = strtolower((string) get_stylesheet());
        return in_array($template, ['divi', 'extra'], true)
            || in_array($stylesheet, ['divi', 'extra'], true)
            || $this->pluginActive('divi-builder/divi-builder.php');
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
                $this->elementorWidget('heading', [
                    'title' => __('Resultados de inmuebles', 'homlity-real-estate'),
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

        $this->saveElementorData($pageId, $data, 'wp-page');
        update_option('homlity_plugin_archive_elementor_page_id', $pageId);
    }

    private function seedSingleElementorTemplate(): void
    {
        $optionKey  = 'homlity_plugin_single_template_id';
        $templateId = (int) get_option($optionKey, 0);

        // Fast path: option exists and post is live.
        if ($templateId > 0 && in_array(get_post_status($templateId), ['publish', 'draft', 'pending'], true)) {
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
            update_option($optionKey, $templateId);
            $this->removeSingleTemplateDuplicates($templateId);
            return;
        }

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
            get_post_meta((int) $pageId, '_elementor_edit_mode', true) === 'builder'
        ) {
            return;
        }

        $data = [
            $this->elementorSection([
                $this->elementorWidget('shortcode', [
                    'shortcode' => '[homlity_agent_profile]',
                ]),
            ]),
        ];

        $this->saveElementorData((int) $pageId, $data, 'wp-page');
        update_option('homlity_plugin_agent_profile_elementor_page_id', (int) $pageId);
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

        $this->saveElementorData((int) $pageId, $data, 'wp-page');
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
        if ($seededBy === $builder && (string) get_post_meta($pageId, '_homlity_seeded_purpose', true) === $purpose) {
            return;
        }

        wp_update_post(['ID' => $pageId, 'post_content' => wp_slash($content)]);
        update_post_meta($pageId, '_homlity_seeded_builder', $builder);
        update_post_meta($pageId, '_homlity_seeded_purpose', $purpose);

        if ($builder === 'divi') {
            update_post_meta($pageId, '_et_pb_use_builder', 'on');
            update_post_meta($pageId, '_et_pb_page_layout', 'et_full_width_page');
            update_post_meta($pageId, '_et_pb_built_for_post_type', 'page');
        } elseif ($builder === 'wpbakery') {
            update_post_meta($pageId, '_wpb_vc_js_status', 'true');
            update_post_meta($pageId, '_vc_post_settings', ['vc_grid_id' => []]);
        }
    }

    private function archiveBuilderContent(string $builder): string
    {
        $title = esc_html__('Resultados de inmuebles', 'homlity-real-estate');
        $listing = '[homlity_listing view="grid" columns="3" per_page="12" filters="true" sort="true"]';
        if ($builder === 'divi') {
            return '[et_pb_section][et_pb_row][et_pb_column type="4_4"]'
                . '[et_pb_text]<h1>' . $title . '</h1>[/et_pb_text]'
                . '[homlity_listing_divi view="grid" columns="3" per_page="12" filters="on" sort="on"][/homlity_listing_divi]'
                . '[/et_pb_column][/et_pb_row][/et_pb_section]';
        }
        if ($builder === 'wpbakery') {
            return '[vc_row][vc_column][vc_column_text]<h1>' . $title . '</h1>[/vc_column_text]'
                . $listing . '[/vc_column][/vc_row]';
        }
        return '<h1>' . $title . '</h1>' . $listing;
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
                'property_summary', 'property_gallery', 'property_features_primary',
                'property_features_secondary', 'property_map', 'property_agent', 'property_related',
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
