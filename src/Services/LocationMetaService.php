<?php
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

        // Add/select parent field when creating/editing terms.
        $this->hookTermFields(PropertyTaxonomies::TAXONOMY_STATE, PropertyTaxonomies::TAXONOMY_COUNTRY, __('País', 'homlity-plugin'));
        $this->hookTermFields(PropertyTaxonomies::TAXONOMY_CITY, PropertyTaxonomies::TAXONOMY_STATE, __('Departamento / Provincia', 'homlity-plugin'));
        $this->hookTermFields(PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, PropertyTaxonomies::TAXONOMY_CITY, __('Ciudad / Municipio', 'homlity-plugin'));
    }

    private function hookTermFields(string $taxonomy, string $parentTaxonomy, string $parentLabel): void
    {
        add_action($taxonomy . '_add_form_fields', function () use ($parentTaxonomy, $parentLabel) {
            $this->renderParentField('parent_' . $taxonomy, $parentTaxonomy, $parentLabel, 0);
        });

        add_action($taxonomy . '_edit_form_fields', function ($term) use ($parentTaxonomy, $parentLabel, $taxonomy) {
            $parentId = (int) get_term_meta($term->term_id, self::META_KEYS[$taxonomy] ?? '', true);
            ?>
            <tr class="form-field">
                <th scope="row"><label><?php echo esc_html($parentLabel); ?></label></th>
                <td>
                    <?php $this->renderParentSelect('parent_' . $taxonomy, $parentTaxonomy, $parentLabel, $parentId); ?>
                </td>
            </tr>
            <?php
        });

        $saveCallback = function ($termId) use ($taxonomy, $parentTaxonomy) {
            $metaKey = self::META_KEYS[$taxonomy] ?? '';
            if (!$metaKey) {
                return;
            }
            $field = 'parent_' . $taxonomy;
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
            <option value="0"><?php echo esc_html(sprintf(__('Selecciona %s', 'homlity-plugin'), $label)); ?></option>
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
        foreach (['homlity-plugin/v1', 'plugin-inmobiliario/v1'] as $namespace) {
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

        $data = array_map(static function ($term) {
            return [
                'id' => $term->term_id,
                'name' => $term->name,
            ];
        }, $terms);

        return new \WP_REST_Response($data, 200);
    }
}
