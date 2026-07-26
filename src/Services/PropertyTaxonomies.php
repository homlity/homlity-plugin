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
    private const OPERATION_BASE_KEY_META = '_homlity_base_operation_key';
    private const OPERATION_BASE_ID_META = '_homlity_base_operation_id';
    private const PROPERTY_TYPE_BASE_KEY_META = '_homlity_base_property_type_key';
    private const PROPERTY_TYPE_BASE_ID_META = '_homlity_base_property_type_id';

    private bool $updatingBasePropertyTypes = false;

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
        add_action('pre_delete_term', [$this, 'preventBaseOperationDeletion'], 10, 2);
        add_action('pre_delete_term', [$this, 'preventBasePropertyTypeDeletion'], 10, 2);
        add_action('edit_terms', [$this, 'preventBasePropertyTypeUpdate'], 10, 3);
        add_filter('wp_update_term_data', [$this, 'protectBaseOperationIdentity'], 10, 4);
        add_filter('wp_update_term_data', [$this, 'protectBasePropertyTypeIdentity'], 10, 4);
        add_filter(self::TAXONOMY_OPERATION . '_row_actions', [$this, 'filterBaseOperationRowActions'], 10, 2);
        add_filter(self::TAXONOMY_TYPE . '_row_actions', [$this, 'filterBasePropertyTypeRowActions'], 10, 2);
        add_filter('manage_edit-' . self::TAXONOMY_OPERATION . '_columns', [$this, 'registerOperationColumns']);
        add_filter('manage_' . self::TAXONOMY_OPERATION . '_custom_column', [$this, 'renderOperationColumn'], 10, 3);
        add_filter('manage_edit-' . self::TAXONOMY_TYPE . '_columns', [$this, 'registerPropertyTypeColumns']);
        add_filter('manage_' . self::TAXONOMY_TYPE . '_custom_column', [$this, 'renderPropertyTypeColumn'], 10, 3);
        add_action(self::TAXONOMY_OPERATION . '_edit_form_fields', [$this, 'renderBaseOperationIdentityField']);
        add_action(self::TAXONOMY_TYPE . '_edit_form_fields', [$this, 'renderBasePropertyTypeIdentityField']);
        add_action('admin_notices', [$this, 'renderBaseOperationNotice']);
        add_action('admin_notices', [$this, 'renderBasePropertyTypeNotice']);
        add_action('admin_footer', [$this, 'lockBaseOperationAdminControls']);
        add_action('admin_footer', [$this, 'lockBasePropertyTypeAdminControls']);
        add_filter('pll_get_taxonomies', [$this, 'registerWithPolylang'], 10, 2);
    }

    /**
     * Stable business identities for the built-in management types.
     * Names remain editable, while these IDs and technical slugs do not.
     *
     * @return array<int,array{key:string,name:string,slug:string,aliases:string[]}>
     */
    public static function baseOperations(): array
    {
        return [
            1 => [
                'key' => 'rent',
                'name' => __('Arriendo', 'homlity-real-estate'),
                'slug' => 'arriendo',
                'aliases' => ['alquiler', 'renta'],
            ],
            2 => [
                'key' => 'sale',
                'name' => __('Venta', 'homlity-real-estate'),
                'slug' => 'venta',
                'aliases' => [],
            ],
            3 => [
                'key' => 'rent_sale',
                'name' => __('Arriendo/Venta', 'homlity-real-estate'),
                'slug' => 'arriendo-venta',
                'aliases' => ['arriendo-y-venta', 'venta-arriendo', 'ambos'],
            ],
            4 => [
                'key' => 'swap',
                'name' => __('Permuta', 'homlity-real-estate'),
                'slug' => 'permuta',
                'aliases' => ['intercambio'],
            ],
        ];
    }

    /**
     * Immutable built-in property types.
     *
     * @return array<int,array{key:string,name:string,slug:string,aliases:string[]}>
     */
    public static function basePropertyTypes(): array
    {
        return [
            1 => ['key' => 'apartment', 'name' => __('Apartamento', 'homlity-real-estate'), 'slug' => 'apartamento', 'aliases' => ['apartamentos']],
            2 => ['key' => 'house', 'name' => __('Casa', 'homlity-real-estate'), 'slug' => 'casa', 'aliases' => ['casas']],
            3 => ['key' => 'lot', 'name' => __('Lote', 'homlity-real-estate'), 'slug' => 'lote', 'aliases' => ['lote-terreno', 'terreno']],
            4 => ['key' => 'farm', 'name' => __('Finca', 'homlity-real-estate'), 'slug' => 'finca', 'aliases' => []],
            5 => ['key' => 'studio', 'name' => __('Apartaestudio', 'homlity-real-estate'), 'slug' => 'apartaestudio', 'aliases' => ['apartaestudios']],
            6 => ['key' => 'penthouse', 'name' => __('Penthouse', 'homlity-real-estate'), 'slug' => 'penthouse', 'aliases' => []],
            7 => ['key' => 'commercial_unit', 'name' => __('Local', 'homlity-real-estate'), 'slug' => 'local', 'aliases' => ['local-comercial']],
            8 => ['key' => 'commercial_house', 'name' => __('Casa Comercial', 'homlity-real-estate'), 'slug' => 'casa-comercial', 'aliases' => []],
            9 => ['key' => 'parking', 'name' => __('Parqueadero', 'homlity-real-estate'), 'slug' => 'parqueadero', 'aliases' => ['estacionamiento', 'garaje']],
            10 => ['key' => 'building', 'name' => __('Edificio', 'homlity-real-estate'), 'slug' => 'edificio', 'aliases' => []],
        ];
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
        $this->ensureBaseOperationTerms();
        $this->ensureBasePropertyTypes();
        $this->ensureDefaultTag('destacado');
        $this->ensureDefaultConditionTerms();
    }

    public function ensureBaseOperationTerms(): void
    {
        if (!taxonomy_exists(self::TAXONOMY_OPERATION)) {
            return;
        }

        foreach (self::baseOperations() as $baseId => $definition) {
            $term = self::findBaseOperationTerm($baseId, $definition);

            if (!$term instanceof \WP_Term) {
                $created = wp_insert_term($definition['name'], self::TAXONOMY_OPERATION, [
                    'slug' => $definition['slug'],
                ]);
                if (is_wp_error($created)) {
                    continue;
                }
                $term = get_term((int) ($created['term_id'] ?? 0), self::TAXONOMY_OPERATION);
            }

            if (!$term instanceof \WP_Term) {
                continue;
            }

            if ($term->slug !== $definition['slug']) {
                $updated = wp_update_term((int) $term->term_id, self::TAXONOMY_OPERATION, [
                    'slug' => $definition['slug'],
                ]);
                if (!is_wp_error($updated)) {
                    $term = get_term((int) $term->term_id, self::TAXONOMY_OPERATION);
                }
            }

            if (get_term_meta((int) $term->term_id, self::OPERATION_BASE_KEY_META, true) !== $definition['key']) {
                update_term_meta((int) $term->term_id, self::OPERATION_BASE_KEY_META, $definition['key']);
            }
            if ((int) get_term_meta((int) $term->term_id, self::OPERATION_BASE_ID_META, true) !== $baseId) {
                update_term_meta((int) $term->term_id, self::OPERATION_BASE_ID_META, $baseId);
            }
        }
    }

    /**
     * Returns the immutable business ID (1–4), or 0 for a custom operation.
     *
     * @param \WP_Term|int $term
     */
    public static function baseOperationIdForTerm($term): int
    {
        $term = $term instanceof \WP_Term
            ? $term
            : get_term(absint($term), self::TAXONOMY_OPERATION);

        if (!$term instanceof \WP_Term || $term->taxonomy !== self::TAXONOMY_OPERATION) {
            return 0;
        }

        $definitions = self::baseOperations();
        $storedId = (int) get_term_meta((int) $term->term_id, self::OPERATION_BASE_ID_META, true);
        $storedKey = (string) get_term_meta((int) $term->term_id, self::OPERATION_BASE_KEY_META, true);

        if (isset($definitions[$storedId]) && $definitions[$storedId]['key'] === $storedKey) {
            return $storedId;
        }

        foreach ($definitions as $baseId => $definition) {
            if ($storedKey === $definition['key']) {
                return $baseId;
            }
            if ($term->slug === $definition['slug']) {
                return $baseId;
            }
        }

        return 0;
    }

    public static function baseOperationTermById(int $baseId): ?\WP_Term
    {
        $definitions = self::baseOperations();
        if (!isset($definitions[$baseId]) || !taxonomy_exists(self::TAXONOMY_OPERATION)) {
            return null;
        }

        $terms = get_terms([
            'taxonomy' => self::TAXONOMY_OPERATION,
            'hide_empty' => false,
            'number' => 1,
            'meta_key' => self::OPERATION_BASE_ID_META,
            'meta_value' => (string) $baseId,
        ]);
        if (!is_wp_error($terms) && isset($terms[0]) && $terms[0] instanceof \WP_Term) {
            return $terms[0];
        }

        return self::findBaseOperationTerm($baseId, $definitions[$baseId]);
    }

    /**
     * @param array{key:string,name:string,slug:string,aliases:string[]} $definition
     */
    private static function findBaseOperationTerm(int $baseId, array $definition): ?\WP_Term
    {
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY_OPERATION,
            'hide_empty' => false,
            'number' => 1,
            'meta_key' => self::OPERATION_BASE_KEY_META,
            'meta_value' => $definition['key'],
        ]);
        if (!is_wp_error($terms) && isset($terms[0]) && $terms[0] instanceof \WP_Term) {
            return $terms[0];
        }

        $terms = get_terms([
            'taxonomy' => self::TAXONOMY_OPERATION,
            'hide_empty' => false,
            'number' => 1,
            'meta_key' => self::OPERATION_BASE_ID_META,
            'meta_value' => (string) $baseId,
        ]);
        if (!is_wp_error($terms) && isset($terms[0]) && $terms[0] instanceof \WP_Term) {
            return $terms[0];
        }

        foreach (array_merge([$definition['slug']], $definition['aliases']) as $slug) {
            $term = get_term_by('slug', $slug, self::TAXONOMY_OPERATION);
            if ($term instanceof \WP_Term) {
                return $term;
            }
        }

        $term = get_term_by('name', $definition['name'], self::TAXONOMY_OPERATION);
        return $term instanceof \WP_Term ? $term : null;
    }

    public function preventBaseOperationDeletion(int $termId, string $taxonomy): void
    {
        if ($taxonomy !== self::TAXONOMY_OPERATION || self::baseOperationIdForTerm($termId) === 0) {
            return;
        }

        $message = __('Este tipo de gestión es un registro base de Homlity. Puedes cambiar su nombre, pero no eliminarlo ni cambiar su identidad.', 'homlity-real-estate');

        if (wp_doing_ajax()) {
            wp_send_json_error(['message' => $message], 409);
        }

        wp_die(
            esc_html($message),
            esc_html__('Tipo de gestión protegido', 'homlity-real-estate'),
            ['response' => 409, 'back_link' => true]
        );
    }

    public function protectBaseOperationIdentity(array $data, int $termId, string $taxonomy, array $args): array
    {
        if ($taxonomy !== self::TAXONOMY_OPERATION) {
            return $data;
        }

        $baseId = self::baseOperationIdForTerm($termId);
        $definitions = self::baseOperations();
        if ($baseId > 0 && isset($definitions[$baseId])) {
            $data['slug'] = $definitions[$baseId]['slug'];
        }

        return $data;
    }

    public function filterBaseOperationRowActions(array $actions, \WP_Term $term): array
    {
        if (self::baseOperationIdForTerm($term) > 0) {
            unset($actions['delete'], $actions['inline hide']);
        }
        return $actions;
    }

    public function registerOperationColumns(array $columns): array
    {
        $columns['homlity_base_operation_id'] = __('ID base', 'homlity-real-estate');
        $columns['homlity_base_operation_protected'] = __('Protegido', 'homlity-real-estate');
        return $columns;
    }

    public function renderOperationColumn(string $content, string $columnName, int $termId): string
    {
        $baseId = self::baseOperationIdForTerm($termId);
        if ($columnName === 'homlity_base_operation_id') {
            return $baseId > 0 ? (string) $baseId : '—';
        }
        if ($columnName === 'homlity_base_operation_protected') {
            return $baseId > 0 ? esc_html__('Sí', 'homlity-real-estate') : esc_html__('No', 'homlity-real-estate');
        }
        return $content;
    }

    public function renderBaseOperationIdentityField(\WP_Term $term): void
    {
        $baseId = self::baseOperationIdForTerm($term);
        if ($baseId === 0) {
            return;
        }
        ?>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e('Identidad base', 'homlity-real-estate'); ?></th>
            <td>
                <code><?php echo esc_html((string) $baseId); ?></code>
                <p class="description">
                    <?php esc_html_e('El nombre visible es editable. El ID base, el ID interno y el slug técnico están protegidos.', 'homlity-real-estate'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    public function renderBaseOperationNotice(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->taxonomy !== self::TAXONOMY_OPERATION) {
            return;
        }
        ?>
        <div class="notice notice-info">
            <p><?php esc_html_e('Arriendo, Venta, Arriendo/Venta y Permuta son tipos de gestión base. Puedes cambiar sus nombres, pero sus IDs y slugs técnicos permanecen protegidos y no se pueden eliminar.', 'homlity-real-estate'); ?></p>
        </div>
        <?php
    }

    public function lockBaseOperationAdminControls(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->taxonomy !== self::TAXONOMY_OPERATION) {
            return;
        }

        $protectedIds = [];
        foreach (array_keys(self::baseOperations()) as $baseId) {
            $term = self::baseOperationTermById((int) $baseId);
            if ($term instanceof \WP_Term) {
                $protectedIds[] = (int) $term->term_id;
            }
        }
        if (empty($protectedIds)) {
            return;
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var protectedIds = <?php echo wp_json_encode(array_values(array_unique($protectedIds))); ?>;
            protectedIds.forEach(function (termId) {
                var checkbox = document.querySelector('#tag-' + termId + ' .check-column input[type="checkbox"]');
                if (checkbox) {
                    checkbox.disabled = true;
                    checkbox.title = <?php echo wp_json_encode(__('Este tipo de gestión base no se puede eliminar.', 'homlity-real-estate')); ?>;
                }
            });

            var currentTermId = parseInt(new URLSearchParams(window.location.search).get('tag_ID') || '0', 10);
            if (protectedIds.indexOf(currentTermId) !== -1) {
                var slugInput = document.getElementById('slug');
                if (slugInput) {
                    slugInput.readOnly = true;
                    slugInput.setAttribute('aria-describedby', 'homlity-protected-operation-slug');
                    var note = document.createElement('p');
                    note.id = 'homlity-protected-operation-slug';
                    note.className = 'description';
                    note.textContent = <?php echo wp_json_encode(__('El slug técnico está protegido para conservar la identidad de esta gestión.', 'homlity-real-estate')); ?>;
                    slugInput.insertAdjacentElement('afterend', note);
                }
            }
        });
        </script>
        <?php
    }

    public function ensureBasePropertyTypes(): void
    {
        if (!taxonomy_exists(self::TAXONOMY_TYPE)) {
            return;
        }

        foreach (self::basePropertyTypes() as $baseId => $definition) {
            $term = self::findBasePropertyTypeTerm($baseId, $definition);

            if (!$term instanceof \WP_Term) {
                $created = wp_insert_term($definition['name'], self::TAXONOMY_TYPE, [
                    'slug' => $definition['slug'],
                ]);
                if (is_wp_error($created)) {
                    continue;
                }
                $term = get_term((int) ($created['term_id'] ?? 0), self::TAXONOMY_TYPE);
            }

            if (!$term instanceof \WP_Term) {
                continue;
            }

            if ($term->name !== $definition['name'] || $term->slug !== $definition['slug'] || (int) $term->parent !== 0) {
                $this->updatingBasePropertyTypes = true;
                $updated = wp_update_term((int) $term->term_id, self::TAXONOMY_TYPE, [
                    'name' => $definition['name'],
                    'slug' => $definition['slug'],
                    'parent' => 0,
                ]);
                $this->updatingBasePropertyTypes = false;
                if (!is_wp_error($updated)) {
                    $term = get_term((int) $term->term_id, self::TAXONOMY_TYPE);
                }
            }

            if (!$term instanceof \WP_Term) {
                continue;
            }

            if (get_term_meta((int) $term->term_id, self::PROPERTY_TYPE_BASE_KEY_META, true) !== $definition['key']) {
                update_term_meta((int) $term->term_id, self::PROPERTY_TYPE_BASE_KEY_META, $definition['key']);
            }
            if ((int) get_term_meta((int) $term->term_id, self::PROPERTY_TYPE_BASE_ID_META, true) !== $baseId) {
                update_term_meta((int) $term->term_id, self::PROPERTY_TYPE_BASE_ID_META, $baseId);
            }
        }
    }

    /**
     * @param \WP_Term|int $term
     */
    public static function basePropertyTypeIdForTerm($term): int
    {
        $term = $term instanceof \WP_Term
            ? $term
            : get_term(absint($term), self::TAXONOMY_TYPE);

        if (!$term instanceof \WP_Term || $term->taxonomy !== self::TAXONOMY_TYPE) {
            return 0;
        }

        $definitions = self::basePropertyTypes();
        $storedId = (int) get_term_meta((int) $term->term_id, self::PROPERTY_TYPE_BASE_ID_META, true);
        $storedKey = (string) get_term_meta((int) $term->term_id, self::PROPERTY_TYPE_BASE_KEY_META, true);

        if (isset($definitions[$storedId]) && $definitions[$storedId]['key'] === $storedKey) {
            return $storedId;
        }

        foreach ($definitions as $baseId => $definition) {
            if ($storedKey === $definition['key'] || $term->slug === $definition['slug']) {
                return $baseId;
            }
        }

        return 0;
    }

    public static function basePropertyTypeTermById(int $baseId): ?\WP_Term
    {
        $definitions = self::basePropertyTypes();
        if (!isset($definitions[$baseId]) || !taxonomy_exists(self::TAXONOMY_TYPE)) {
            return null;
        }

        $terms = get_terms([
            'taxonomy' => self::TAXONOMY_TYPE,
            'hide_empty' => false,
            'number' => 1,
            'meta_key' => self::PROPERTY_TYPE_BASE_ID_META,
            'meta_value' => (string) $baseId,
        ]);
        if (!is_wp_error($terms) && isset($terms[0]) && $terms[0] instanceof \WP_Term) {
            return $terms[0];
        }

        return self::findBasePropertyTypeTerm($baseId, $definitions[$baseId]);
    }

    /**
     * @param array{key:string,name:string,slug:string,aliases:string[]} $definition
     */
    private static function findBasePropertyTypeTerm(int $baseId, array $definition): ?\WP_Term
    {
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY_TYPE,
            'hide_empty' => false,
            'number' => 1,
            'meta_key' => self::PROPERTY_TYPE_BASE_KEY_META,
            'meta_value' => $definition['key'],
        ]);
        if (!is_wp_error($terms) && isset($terms[0]) && $terms[0] instanceof \WP_Term) {
            return $terms[0];
        }

        $terms = get_terms([
            'taxonomy' => self::TAXONOMY_TYPE,
            'hide_empty' => false,
            'number' => 1,
            'meta_key' => self::PROPERTY_TYPE_BASE_ID_META,
            'meta_value' => (string) $baseId,
        ]);
        if (!is_wp_error($terms) && isset($terms[0]) && $terms[0] instanceof \WP_Term) {
            return $terms[0];
        }

        foreach (array_merge([$definition['slug']], $definition['aliases']) as $slug) {
            $term = get_term_by('slug', $slug, self::TAXONOMY_TYPE);
            if ($term instanceof \WP_Term) {
                return $term;
            }
        }

        $term = get_term_by('name', $definition['name'], self::TAXONOMY_TYPE);
        return $term instanceof \WP_Term ? $term : null;
    }

    private static function hasStoredBasePropertyTypeIdentity(int $termId): bool
    {
        $storedId = (int) get_term_meta($termId, self::PROPERTY_TYPE_BASE_ID_META, true);
        $storedKey = (string) get_term_meta($termId, self::PROPERTY_TYPE_BASE_KEY_META, true);
        $definitions = self::basePropertyTypes();

        return isset($definitions[$storedId]) && $definitions[$storedId]['key'] === $storedKey;
    }

    public function preventBasePropertyTypeDeletion(int $termId, string $taxonomy): void
    {
        if ($taxonomy !== self::TAXONOMY_TYPE || self::basePropertyTypeIdForTerm($termId) === 0) {
            return;
        }

        $message = __('Este tipo de inmueble es un registro base de Homlity y no se puede modificar ni eliminar.', 'homlity-real-estate');
        if (wp_doing_ajax()) {
            wp_send_json_error(['message' => $message], 409);
        }

        wp_die(
            esc_html($message),
            esc_html__('Tipo de inmueble protegido', 'homlity-real-estate'),
            ['response' => 409, 'back_link' => true]
        );
    }

    public function preventBasePropertyTypeUpdate(int $termId, string $taxonomy, array $args = []): void
    {
        if (
            $this->updatingBasePropertyTypes
            || $taxonomy !== self::TAXONOMY_TYPE
            || !self::hasStoredBasePropertyTypeIdentity($termId)
        ) {
            return;
        }

        $message = __('Este tipo de inmueble base está protegido y no se puede modificar.', 'homlity-real-estate');
        if (wp_doing_ajax()) {
            wp_send_json_error(['message' => $message], 409);
        }

        wp_die(
            esc_html($message),
            esc_html__('Tipo de inmueble protegido', 'homlity-real-estate'),
            ['response' => 409, 'back_link' => true]
        );
    }

    public function protectBasePropertyTypeIdentity(array $data, int $termId, string $taxonomy, array $args): array
    {
        if ($taxonomy !== self::TAXONOMY_TYPE) {
            return $data;
        }

        $baseId = self::basePropertyTypeIdForTerm($termId);
        $definitions = self::basePropertyTypes();
        if ($baseId > 0 && isset($definitions[$baseId])) {
            $data['name'] = $definitions[$baseId]['name'];
            $data['slug'] = $definitions[$baseId]['slug'];
        }

        return $data;
    }

    public function filterBasePropertyTypeRowActions(array $actions, \WP_Term $term): array
    {
        if (self::basePropertyTypeIdForTerm($term) > 0) {
            unset($actions['edit'], $actions['delete'], $actions['inline hide']);
        }
        return $actions;
    }

    public function registerPropertyTypeColumns(array $columns): array
    {
        $columns['homlity_base_property_type_id'] = __('ID base', 'homlity-real-estate');
        $columns['homlity_base_property_type_protected'] = __('Protegido', 'homlity-real-estate');
        return $columns;
    }

    public function renderPropertyTypeColumn(string $content, string $columnName, int $termId): string
    {
        $baseId = self::basePropertyTypeIdForTerm($termId);
        if ($columnName === 'homlity_base_property_type_id') {
            return $baseId > 0 ? (string) $baseId : '—';
        }
        if ($columnName === 'homlity_base_property_type_protected') {
            return $baseId > 0 ? esc_html__('Sí', 'homlity-real-estate') : esc_html__('No', 'homlity-real-estate');
        }
        return $content;
    }

    public function renderBasePropertyTypeIdentityField(\WP_Term $term): void
    {
        $baseId = self::basePropertyTypeIdForTerm($term);
        if ($baseId === 0) {
            return;
        }
        ?>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e('Identidad base', 'homlity-real-estate'); ?></th>
            <td>
                <code><?php echo esc_html((string) $baseId); ?></code>
                <p class="description"><?php esc_html_e('El nombre, ID interno, ID base, slug y jerarquía de este tipo están protegidos.', 'homlity-real-estate'); ?></p>
            </td>
        </tr>
        <?php
    }

    public function renderBasePropertyTypeNotice(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->taxonomy !== self::TAXONOMY_TYPE) {
            return;
        }
        ?>
        <div class="notice notice-info">
            <p><?php esc_html_e('Apartamento, Casa, Lote, Finca, Apartaestudio, Penthouse, Local, Casa Comercial, Parqueadero y Edificio son tipos de inmueble base protegidos. No se pueden modificar ni eliminar.', 'homlity-real-estate'); ?></p>
        </div>
        <?php
    }

    public function lockBasePropertyTypeAdminControls(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->taxonomy !== self::TAXONOMY_TYPE) {
            return;
        }

        $protectedIds = [];
        foreach (array_keys(self::basePropertyTypes()) as $baseId) {
            $term = self::basePropertyTypeTermById((int) $baseId);
            if ($term instanceof \WP_Term) {
                $protectedIds[] = (int) $term->term_id;
            }
        }
        if (empty($protectedIds)) {
            return;
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var protectedIds = <?php echo wp_json_encode(array_values(array_unique($protectedIds))); ?>;
            protectedIds.forEach(function (termId) {
                var row = document.getElementById('tag-' + termId);
                if (!row) {
                    return;
                }
                var checkbox = row.querySelector('.check-column input[type="checkbox"]');
                if (checkbox) {
                    checkbox.disabled = true;
                    checkbox.title = <?php echo wp_json_encode(__('Este tipo de inmueble base no se puede eliminar.', 'homlity-real-estate')); ?>;
                }
                var titleLink = row.querySelector('.row-title');
                if (titleLink) {
                    titleLink.removeAttribute('href');
                    titleLink.style.cursor = 'default';
                }
            });

            var currentTermId = parseInt(new URLSearchParams(window.location.search).get('tag_ID') || '0', 10);
            if (protectedIds.indexOf(currentTermId) !== -1) {
                ['name', 'slug', 'description'].forEach(function (fieldId) {
                    var field = document.getElementById(fieldId);
                    if (field) {
                        field.readOnly = true;
                    }
                });
                var parent = document.getElementById('parent');
                if (parent) {
                    parent.disabled = true;
                }
                var submit = document.querySelector('#edittag input[type="submit"]');
                if (submit) {
                    submit.disabled = true;
                }
            }
        });
        </script>
        <?php
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
