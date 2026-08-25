<?php
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
/**
 * Adds parent relations for location taxonomies and exposes filtered term lists.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class LocationMetaService implements ServiceInterface
{
    private const META_KEYS = [
        PropertyTaxonomies::TAXONOMY_STATE => '_parent_country',
        PropertyTaxonomies::TAXONOMY_CITY => '_parent_state',
        PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => '_parent_city',
    ];

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('init', [$this, 'registerTermFields'], 12);
        add_filter('manage_edit-' . PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD . '_columns', [$this, 'neighborhoodColumns']);
        add_filter('manage_' . PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD . '_custom_column', [$this, 'renderNeighborhoodColumn'], 10, 3);
    }

    public function registerTermFields(): void
    {
        $this->hookTermFields(PropertyTaxonomies::TAXONOMY_STATE, PropertyTaxonomies::TAXONOMY_COUNTRY, __('País', 'homlity-real-estate'));
        $this->hookTermFields(PropertyTaxonomies::TAXONOMY_CITY, PropertyTaxonomies::TAXONOMY_STATE, __('Departamento / Provincia', 'homlity-real-estate'));
        $this->hookTermFields(PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, PropertyTaxonomies::TAXONOMY_CITY, __('Ciudad / Municipio', 'homlity-real-estate'));
        $this->hookNeighborhoodLocalityField();
    }

    private function hookNeighborhoodLocalityField(): void
    {
        $taxonomy = PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD;
        $nonceAction = 'homlrees_neighborhood_locality';
        $nonceName = '_homlrees_neighborhood_locality_nonce';

        add_action($taxonomy . '_add_form_fields', function () use ($nonceAction, $nonceName): void {
            $this->renderLocalityField(0, false);
            wp_nonce_field($nonceAction, $nonceName);
        }, 20);

        add_action($taxonomy . '_edit_form_fields', function ($term) use ($nonceAction, $nonceName): void {
            $selected = (int) get_term_meta((int) $term->term_id, LocalityPostType::TERM_META_LOCALITY_ID, true);
            $this->renderLocalityField($selected, true);
            wp_nonce_field($nonceAction, $nonceName);
        }, 20);

        $save = function ($termId) use ($nonceAction, $nonceName): void {
            if (!isset($_POST[$nonceName])
                || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonceName])), $nonceAction)
                || !current_user_can('manage_categories')
            ) {
                return;
            }

            $localityId = isset($_POST['parent_locality']) ? absint($_POST['parent_locality']) : 0;
            if ($localityId === 0) {
                delete_term_meta((int) $termId, LocalityPostType::TERM_META_LOCALITY_ID);
                return;
            }
            if (get_post_type($localityId) !== LocalityPostType::POST_TYPE || get_post_status($localityId) !== 'publish') {
                return;
            }

            $cityId = (int) get_term_meta((int) $termId, '_parent_city', true);
            if ($cityId <= 0 || LocalityPostType::cityId($localityId) !== $cityId) {
                return;
            }
            update_term_meta((int) $termId, LocalityPostType::TERM_META_LOCALITY_ID, $localityId);
        };

        add_action('created_' . $taxonomy, $save, 20, 1);
        add_action('edited_' . $taxonomy, $save, 20, 1);
    }

    private function renderLocalityField(int $selected, bool $tableRow): void
    {
        $localities = get_posts([
            'post_type' => LocalityPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        ob_start();
        ?>
        <select id="parent_locality" name="parent_locality">
            <option value="0"><?php esc_html_e('Sin localidad (relación directa con la ciudad)', 'homlity-real-estate'); ?></option>
            <?php foreach ($localities as $locality): ?>
                <option
                    value="<?php echo esc_attr((string) $locality->ID); ?>"
                    data-city-id="<?php echo esc_attr((string) LocalityPostType::cityId((int) $locality->ID)); ?>"
                    <?php selected($selected, (int) $locality->ID); ?>
                ><?php echo esc_html((string) $locality->post_title); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Opcional. Solo se muestran localidades pertenecientes a la ciudad seleccionada.', 'homlity-real-estate'); ?></p>
        <script>
        (() => {
            const city = document.getElementById('parent_property_neighborhood');
            const locality = document.getElementById('parent_locality');
            if (!city || !locality) return;
            const filter = () => {
                const cityId = String(city.value || '0');
                [...locality.options].forEach((option) => {
                    if (option.value === '0') return;
                    const visible = cityId !== '0' && String(option.dataset.cityId || '0') === cityId;
                    option.hidden = !visible;
                    if (!visible && option.selected) locality.value = '0';
                });
            };
            city.addEventListener('change', filter);
            filter();
        })();
        </script>
        <?php
        $field = (string) ob_get_clean();

        // $field es el buffer del <select> y el <script> que este mismo método
        // acaba de imprimir arriba, ya escapados pieza a pieza.
        if ($tableRow) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<tr class="form-field"><th scope="row"><label for="parent_locality">'
                . esc_html__('Localidad', 'homlity-real-estate')
                . '</label></th><td>' . $field . '</td></tr>';
            return;
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="form-field"><label for="parent_locality">'
            . esc_html__('Localidad', 'homlity-real-estate')
            . '</label>' . $field . '</div>';
    }

    private function hookTermFields(string $taxonomy, string $parentTaxonomy, string $parentLabel): void
    {
        $nonceAction = 'homlrees_location_parent_' . $taxonomy;
        $nonceName   = '_homlrees_location_nonce_' . $taxonomy;

        add_action($taxonomy . '_add_form_fields', function () use ($parentTaxonomy, $parentLabel, $taxonomy, $nonceAction, $nonceName) {
            $this->renderParentField('parent_' . $taxonomy, $parentTaxonomy, $parentLabel, 0);
            wp_nonce_field($nonceAction, $nonceName);
        });

        add_action($taxonomy . '_edit_form_fields', function ($term) use ($parentTaxonomy, $parentLabel, $taxonomy, $nonceAction, $nonceName) {
            $parentId = (int) get_term_meta($term->term_id, self::META_KEYS[$taxonomy] ?? '', true);
            ?>
            <tr class="form-field">
                <th scope="row"><label><?php echo esc_html($parentLabel); ?></label></th>
                <td>
                    <?php
                    $this->renderParentSelect('parent_' . $taxonomy, $parentTaxonomy, $parentLabel, $parentId);
                    wp_nonce_field($nonceAction, $nonceName);
                    ?>
                </td>
            </tr>
            <?php
        });

        $saveCallback = function ($termId) use ($taxonomy, $parentTaxonomy, $nonceAction, $nonceName) {
            if (!isset($_POST[$nonceName]) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonceName])), $nonceAction)
            ) {
                return;
            }

            if (!current_user_can('manage_categories')) {
                return;
            }

            $metaKey = self::META_KEYS[$taxonomy] ?? '';
            if (!$metaKey) {
                return;
            }
            $field      = 'parent_' . $taxonomy;
            $legacyField = 'parent_' . $parentTaxonomy;
            $value = isset($_POST[$field])
                ? absint($_POST[$field])
                : (isset($_POST[$legacyField]) ? absint($_POST[$legacyField]) : 0);
            $this->saveParentMeta($termId, $metaKey, $value, $parentTaxonomy);
        };

        add_action('created_' . $taxonomy, $saveCallback, 10, 1);
        add_action('edited_' . $taxonomy, $saveCallback, 10, 1);
    }

    private function renderParentField(string $fieldName, string $parentTaxonomy, string $label, int $selected): void
    {
        ?>
        <div class="form-field">
            <label for="<?php echo esc_attr($fieldName); ?>"><?php echo esc_html($label); ?></label>
            <?php $this->renderParentSelect($fieldName, $parentTaxonomy, $label, $selected); ?>
        </div>
        <?php
    }

    private function renderParentSelect(string $fieldName, string $parentTaxonomy, string $label, int $selected): void
    {
        $terms = get_terms([
            'taxonomy' => $parentTaxonomy,
            'hide_empty' => false,
        ]);
        ?>
        <select id="<?php echo esc_attr($fieldName); ?>" name="<?php echo esc_attr($fieldName); ?>">
            <option value="0"><?php
                /* translators: %s: parent taxonomy label */
                echo esc_html(sprintf(__('Selecciona %s', 'homlity-real-estate'), $label));
            ?></option>
            <?php foreach ($terms as $term): ?>
                <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($selected, $term->term_id); ?>>
                    <?php echo esc_html($term->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    private function saveParentMeta(int $termId, string $metaKey, int $parentId, string $parentTaxonomy): void
    {
        if ($parentId && !term_exists($parentId, $parentTaxonomy)) {
            return;
        }
        update_term_meta($termId, $metaKey, $parentId);
    }

    public function registerRoutes(): void
    {
        foreach (['homlity-real-estate/v1', 'plugin-inmobiliario/v1'] as $namespace) {
            register_rest_route($namespace, '/location-terms', [
                'methods' => 'GET',
                'callback' => [$this, 'restLocationTerms'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'args' => [
                    'taxonomy' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                    'parent' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 0,
                    ],
                ],
            ]);
        }
    }

    public function restLocationTerms(\WP_REST_Request $request): \WP_REST_Response
    {
        $taxonomy = $request->get_param('taxonomy');
        $parentId = (int) $request->get_param('parent');

        if (!taxonomy_exists($taxonomy)) {
            return new \WP_REST_Response([], 200);
        }

        $metaKey = self::META_KEYS[$taxonomy] ?? null;
        $metaQuery = [];
        if ($metaKey) {
            $metaQuery[] = [
                'key' => $metaKey,
                'value' => $parentId,
                'compare' => '=',
            ];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'meta_query' => $metaQuery,
        ]);

        $data = array_map(static function ($term) use ($taxonomy) {
            $item = [
                'id' => $term->term_id,
                'name' => $term->name,
            ];
            if ($taxonomy === PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD) {
                $item['city_id'] = (int) get_term_meta((int) $term->term_id, '_parent_city', true);
                $item['locality_id'] = (int) get_term_meta((int) $term->term_id, LocalityPostType::TERM_META_LOCALITY_ID, true);
            }
            return $item;
        }, $terms);

        return new \WP_REST_Response($data, 200);
    }

    public function neighborhoodColumns(array $columns): array
    {
        $result = [];
        foreach ($columns as $key => $label) {
            $result[$key] = $label;
            if ($key === 'name') {
                $result['homlity_locality'] = __('Localidad', 'homlity-real-estate');
            }
        }
        return $result;
    }

    public function renderNeighborhoodColumn(string $content, string $column, int $termId): string
    {
        if ($column !== 'homlity_locality') {
            return $content;
        }

        $localityId = (int) get_term_meta($termId, LocalityPostType::TERM_META_LOCALITY_ID, true);
        if ($localityId <= 0 || get_post_status($localityId) !== 'publish') {
            return '—';
        }

        $title = get_the_title($localityId);
        return $title !== '' ? esc_html($title) : '—';
    }
}
