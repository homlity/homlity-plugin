<?php
/**
 * Adds parent relations for location taxonomies and exposes filtered term lists.
 */

namespace Codwelt\PluginInmobiliario\Services;

use Codwelt\PluginInmobiliario\Core\Contracts\ServiceInterface;

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
        $this->hookTermFields(PropertyTaxonomies::TAXONOMY_STATE, PropertyTaxonomies::TAXONOMY_COUNTRY, __('País', 'plugin-inmobiliario'));
        $this->hookTermFields(PropertyTaxonomies::TAXONOMY_CITY, PropertyTaxonomies::TAXONOMY_STATE, __('Departamento / Provincia', 'plugin-inmobiliario'));
        $this->hookTermFields(PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, PropertyTaxonomies::TAXONOMY_CITY, __('Ciudad / Municipio', 'plugin-inmobiliario'));
    }

    private function hookTermFields(string $taxonomy, string $parentTaxonomy, string $parentLabel): void
    {
        add_action($taxonomy . '_add_form_fields', function () use ($parentTaxonomy, $parentLabel) {
            $this->renderParentField($parentTaxonomy, $parentLabel, 0);
        });

        add_action($taxonomy . '_edit_form_fields', function ($term) use ($parentTaxonomy, $parentLabel, $taxonomy) {
            $parentId = (int) get_term_meta($term->term_id, self::META_KEYS[$taxonomy] ?? '', true);
            ?>
            <tr class="form-field">
                <th scope="row"><label><?php echo esc_html($parentLabel); ?></label></th>
                <td>
                    <?php $this->renderParentSelect($parentTaxonomy, $parentLabel, $parentId); ?>
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
            $value = isset($_POST[$field]) ? absint($_POST[$field]) : 0;
            $this->saveParentMeta($termId, $metaKey, $value, $parentTaxonomy);
        };

        add_action('created_' . $taxonomy, $saveCallback, 10, 1);
        add_action('edited_' . $taxonomy, $saveCallback, 10, 1);
    }

    private function renderParentField(string $parentTaxonomy, string $label, int $selected): void
    {
        ?>
        <div class="form-field">
            <label for="parent_<?php echo esc_attr($parentTaxonomy); ?>"><?php echo esc_html($label); ?></label>
            <?php $this->renderParentSelect($parentTaxonomy, $label, $selected); ?>
        </div>
        <?php
    }

    private function renderParentSelect(string $parentTaxonomy, string $label, int $selected): void
    {
        $terms = get_terms([
            'taxonomy' => $parentTaxonomy,
            'hide_empty' => false,
        ]);
        ?>
        <select name="parent_<?php echo esc_attr($parentTaxonomy); ?>">
            <option value="0"><?php echo esc_html(sprintf(__('Selecciona %s', 'plugin-inmobiliario'), $label)); ?></option>
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
        register_rest_route('plugin-inmobiliario/v1', '/location-terms', [
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
