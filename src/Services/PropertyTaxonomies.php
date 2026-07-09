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
    private const FEATURE_VISIBILITY_META_KEY = '_homlity_feature_visible';

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
    public const TAXONOMY_CONDITION = 'property_condition';

    public function register(): void
    {
        add_action('init', [$this, 'registerTaxonomies']);
        add_action('init', [$this, 'ensureDefaultTerms'], 20);
        add_action('init', [$this, 'registerFeatureTermControls'], 12);
        add_filter('pll_get_taxonomies', [$this, 'registerWithPolylang'], 10, 2);
    }

    public function registerFeatureTermControls(): void
    {
        $taxonomy = self::TAXONOMY_FEATURE;

        add_action($taxonomy . '_add_form_fields', [$this, 'renderFeatureVisibilityAddField']);
        add_action($taxonomy . '_edit_form_fields', [$this, 'renderFeatureVisibilityEditField']);
        add_action('created_' . $taxonomy, [$this, 'saveFeatureVisibility'], 10, 1);
        add_action('edited_' . $taxonomy, [$this, 'saveFeatureVisibility'], 10, 1);
        add_filter('manage_edit-' . $taxonomy . '_columns', [$this, 'registerFeatureColumns']);
        add_filter('manage_' . $taxonomy . '_custom_column', [$this, 'renderFeatureColumn'], 10, 3);
    }

    public function registerTaxonomies(): void
    {
        register_taxonomy(
            self::TAXONOMY_TYPE,
            [PropertyPostType::POST_TYPE],
            [
                'label' => __('Tipo de propiedad', 'homlity-real-estate'),
                'labels' => [
                    'name' => __('Tipos de propiedad', 'homlity-real-estate'),
                    'singular_name' => __('Tipo de propiedad', 'homlity-real-estate'),
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
                'label' => __('Gestión', 'homlity-real-estate'),
                'labels' => [
                    'name' => __('Gestiones', 'homlity-real-estate'),
                    'singular_name' => __('Gestión', 'homlity-real-estate'),
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
                'label' => __('Ubicación', 'homlity-real-estate'),
                'labels' => [
                    'name' => __('Ubicaciones', 'homlity-real-estate'),
                    'singular_name' => __('Ubicación', 'homlity-real-estate'),
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
                'label' => __('Categoría', 'homlity-real-estate'),
                'labels' => [
                    'name' => __('Categorías', 'homlity-real-estate'),
                    'singular_name' => __('Categoría', 'homlity-real-estate'),
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
                'label' => __('Etiquetas', 'homlity-real-estate'),
                'labels' => [
                    'name' => __('Etiquetas', 'homlity-real-estate'),
                    'singular_name' => __('Etiqueta', 'homlity-real-estate'),
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
                'label' => __('Características', 'homlity-real-estate'),
                'labels' => [
                    'name' => __('Características', 'homlity-real-estate'),
                    'singular_name' => __('Característica', 'homlity-real-estate'),
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
                'label' => __('País', 'homlity-real-estate'),
                'labels' => [
                    'name' => __('Países', 'homlity-real-estate'),
                    'singular_name' => __('País', 'homlity-real-estate'),
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
                'label' => __('Departamento / Provincia', 'homlity-real-estate'),
                'labels' => [
                    'name' => __('Departamentos / Provincias', 'homlity-real-estate'),
                    'singular_name' => __('Departamento / Provincia', 'homlity-real-estate'),
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
                'label' => __('Ciudad / Municipio', 'homlity-real-estate'),
                'labels' => [
                    'name' => __('Ciudades / Municipios', 'homlity-real-estate'),
                    'singular_name' => __('Ciudad / Municipio', 'homlity-real-estate'),
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
                'label' => __('Barrio', 'homlity-real-estate'),
                'labels' => [
                    'name' => __('Barrios', 'homlity-real-estate'),
                    'singular_name' => __('Barrio', 'homlity-real-estate'),
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
                'label' => __('Lugares cercanos', 'homlity-real-estate'),
                'labels' => [
                    'name' => __('Lugares cercanos', 'homlity-real-estate'),
                    'singular_name' => __('Lugar cercano', 'homlity-real-estate'),
                ],
                'hierarchical' => false,
                'show_in_rest' => true,
                'show_admin_column' => true,
                'rewrite' => ['slug' => 'property-nearby'],
            ]
        );

        register_taxonomy(
            self::TAXONOMY_CONDITION,
            [PropertyPostType::POST_TYPE],
            [
                'label' => __('Estado del inmueble', 'homlity-real-estate'),
                'labels' => [
                    'name' => __('Estados', 'homlity-real-estate'),
                    'singular_name' => __('Estado', 'homlity-real-estate'),
                ],
                'hierarchical' => false,
                'public' => false,
                'show_ui' => false,
                'show_in_rest' => true,
                'meta_box_cb' => false,
                'rewrite' => false,
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
            $taxonomies[] = self::TAXONOMY_CONDITION;
        }
        return $taxonomies;
    }

    public function ensureDefaultTerms(): void
    {
        $this->ensureDefaultTag('destacado');
        $this->ensureDefaultConditionTerms();
    }

    private function ensureDefaultConditionTerms(): void
    {
        if (!taxonomy_exists(self::TAXONOMY_CONDITION)) {
            return;
        }

        $defaults = [
            'nuevo'           => 'Nuevo',
            'sobre-planos'    => 'Sobre planos',
            'en-construccion' => 'En construcción',
            'para-estrenar'   => 'Para estrenar',
            'usado'           => 'Usado',
            'reformado'       => 'Reformado',
            'para-reformar'   => 'Para reformar',
        ];

        foreach ($defaults as $slug => $name) {
            if (!term_exists($slug, self::TAXONOMY_CONDITION)) {
                wp_insert_term($name, self::TAXONOMY_CONDITION, ['slug' => $slug]);
            }
        }
    }

    private function ensureDefaultTag(string $name): void
    {
        if (!taxonomy_exists(self::TAXONOMY_TAG)) {
            return;
        }

        $term = term_exists($name, self::TAXONOMY_TAG);
        if ($term) {
            return;
        }

        wp_insert_term($name, self::TAXONOMY_TAG, [
            'slug' => sanitize_title($name),
        ]);
    }

    public function renderFeatureVisibilityAddField(): void
    {
        wp_nonce_field('homlity_feature_visibility', '_homlity_feature_visibility_nonce');
        ?>
        <div class="form-field">
            <label for="homlity-feature-visible"><?php esc_html_e('Visible en frontend', 'homlity-real-estate'); ?></label>
            <label>
                <input type="checkbox" id="homlity-feature-visible" name="homlity_feature_visible" value="1" checked>
                <?php esc_html_e('Mostrar esta característica en el sitio público', 'homlity-real-estate'); ?>
            </label>
        </div>
        <?php
    }

    public function renderFeatureVisibilityEditField(\WP_Term $term): void
    {
        wp_nonce_field('homlity_feature_visibility', '_homlity_feature_visibility_nonce');
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="homlity-feature-visible"><?php esc_html_e('Visible en frontend', 'homlity-real-estate'); ?></label>
            </th>
            <td>
                <label>
                    <input
                        type="checkbox"
                        id="homlity-feature-visible"
                        name="homlity_feature_visible"
                        value="1"
                        <?php checked(self::isFeatureTermVisible($term)); ?>
                    >
                    <?php esc_html_e('Mostrar esta característica en el sitio público', 'homlity-real-estate'); ?>
                </label>
            </td>
        </tr>
        <?php
    }

    public function saveFeatureVisibility(int $termId): void
    {
        if (
            !isset($_POST['_homlity_feature_visibility_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_homlity_feature_visibility_nonce'])), 'homlity_feature_visibility')
        ) {
            return;
        }

        if (!current_user_can('manage_categories')) {
            return;
        }

        $visible = isset($_POST['homlity_feature_visible']) ? '1' : '0';
        update_term_meta($termId, self::FEATURE_VISIBILITY_META_KEY, $visible);
    }

    public function registerFeatureColumns(array $columns): array
    {
        $columns['homlity_feature_visible'] = __('Visible', 'homlity-real-estate');
        return $columns;
    }

    public function renderFeatureColumn(string $content, string $columnName, int $termId): string
    {
        if ($columnName !== 'homlity_feature_visible') {
            return $content;
        }

        return self::isFeatureTermVisible($termId)
            ? esc_html__('Sí', 'homlity-real-estate')
            : esc_html__('No', 'homlity-real-estate');
    }

    public static function isFeatureTermVisible($term): bool
    {
        $termId = $term instanceof \WP_Term ? (int) $term->term_id : absint($term);
        if ($termId <= 0) {
            return true;
        }

        $stored = get_term_meta($termId, self::FEATURE_VISIBILITY_META_KEY, true);
        return $stored === '' || $stored === '1';
    }

    /**
     * @param \WP_Term[]|\WP_Error|false $terms
     * @return \WP_Term[]
     */
    public static function filterVisibleFeatureTerms($terms): array
    {
        if (is_wp_error($terms) || empty($terms) || !is_array($terms)) {
            return [];
        }

        return array_values(array_filter($terms, static function ($term): bool {
            return $term instanceof \WP_Term && self::isFeatureTermVisible($term);
        }));
    }

    /**
     * @return \WP_Term[]
     */
    public static function getVisibleFeatureTermsForPost(int $postId): array
    {
        return self::filterVisibleFeatureTerms(get_the_terms($postId, self::TAXONOMY_FEATURE));
    }

    /**
     * @param int[] $termIds
     * @return int[]
     */
    public static function expandOperationTermIds(array $termIds): array
    {
        $termIds = array_values(array_unique(array_filter(array_map('absint', $termIds))));
        if (empty($termIds)) {
            return [];
        }

        $selectedTerms = [];
        foreach ($termIds as $termId) {
            $term = get_term($termId, self::TAXONOMY_OPERATION);
            if ($term instanceof \WP_Term) {
                $selectedTerms[] = $term;
            }
        }

        if (empty($selectedTerms)) {
            return $termIds;
        }

        $allTerms = get_terms([
            'taxonomy' => self::TAXONOMY_OPERATION,
            'hide_empty' => false,
        ]);
        if (is_wp_error($allTerms) || empty($allTerms)) {
            return $termIds;
        }

        $expanded = $termIds;
        foreach ($selectedTerms as $selectedTerm) {
            $selectedFamilies = self::operationFamiliesForTerm($selectedTerm);
            if (empty($selectedFamilies)) {
                continue;
            }

            foreach ($allTerms as $candidateTerm) {
                if (!$candidateTerm instanceof \WP_Term) {
                    continue;
                }

                $candidateFamilies = self::operationFamiliesForTerm($candidateTerm);
                if (empty($candidateFamilies)) {
                    continue;
                }

                if (!array_diff($selectedFamilies, $candidateFamilies)) {
                    $expanded[] = (int) $candidateTerm->term_id;
                }
            }
        }

        return array_values(array_unique(array_filter(array_map('absint', $expanded))));
    }

    /**
     * @param string[] $slugs
     * @return string[]
     */
    public static function expandOperationTermSlugs(array $slugs): array
    {
        $slugs = array_values(array_unique(array_filter(array_map('sanitize_title', $slugs))));
        if (empty($slugs)) {
            return [];
        }

        $termIds = [];
        foreach ($slugs as $slug) {
            $term = get_term_by('slug', $slug, self::TAXONOMY_OPERATION);
            if ($term instanceof \WP_Term) {
                $termIds[] = (int) $term->term_id;
            }
        }

        $expandedIds = self::expandOperationTermIds($termIds);
        if (empty($expandedIds)) {
            return $slugs;
        }

        $expandedSlugs = [];
        foreach ($expandedIds as $termId) {
            $term = get_term($termId, self::TAXONOMY_OPERATION);
            if ($term instanceof \WP_Term) {
                $expandedSlugs[] = $term->slug;
            }
        }

        return array_values(array_unique(array_filter(array_map('sanitize_title', $expandedSlugs))));
    }

    /**
     * @return string[]
     */
    private static function operationFamiliesForTerm(\WP_Term $term): array
    {
        return self::operationFamiliesFromText($term->slug . ' ' . $term->name);
    }

    /**
     * @return string[]
     */
    private static function operationFamiliesFromText(string $text): array
    {
        $text = strtolower(remove_accents($text));
        $families = [];

        if (
            strpos($text, 'arriend') !== false
            || strpos($text, 'alquil') !== false
            || strpos($text, 'rent') !== false
            || strpos($text, 'renta') !== false
        ) {
            $families[] = 'rent';
        }

        if (
            strpos($text, 'vent') !== false
            || strpos($text, 'sale') !== false
            || strpos($text, 'vend') !== false
        ) {
            $families[] = 'sale';
        }

        return array_values(array_unique($families));
    }
}
