<?php
// Un plugin inmobiliario consulta inevitablemente por postmeta y term meta: el
// precio, el área o la localidad de un inmueble viven ahí, no en columnas
// propias. Los avisos de «consulta lenta» describen ese diseño, no un defecto.
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
/**
 * Administrative CRUD for localities/communes between cities and neighborhoods.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class LocalityPostType implements ServiceInterface
{
    public const POST_TYPE = 'property_locality';
    public const META_CITY_ID = '_homlity_locality_city_id';
    public const META_NORMALIZED_NAME = '_homlity_locality_normalized_name';
    public const META_VALIDATION_ERROR = '_homlity_locality_validation_error';
    public const TERM_META_LOCALITY_ID = '_parent_locality';

    private const NONCE_ACTION = 'homlity_save_locality';
    private const NONCE_FIELD = '_homlity_locality_nonce';
    private const FIELD_CITY = 'homlity_locality_city_id';
    private const FIELD_NEIGHBORHOODS = 'homlity_locality_neighborhood_ids';

    private bool $updatingPost = false;

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('add_meta_boxes_' . self::POST_TYPE, [$this, 'registerMetaBoxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'savePost'], 10, 3);
        add_action('before_delete_post', [$this, 'clearNeighborhoodRelations']);
        add_action('wp_trash_post', [$this, 'clearNeighborhoodRelations']);
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_filter('enter_title_here', [$this, 'titlePlaceholder'], 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'renderColumn'], 10, 2);
        add_filter('post_row_actions', [$this, 'removeQuickEdit'], 10, 2);
        add_action('admin_notices', [$this, 'renderValidationNotice']);
    }

    public function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Localidades', 'homlity-real-estate'),
                'singular_name' => __('Localidad', 'homlity-real-estate'),
                'add_new' => __('Añadir localidad', 'homlity-real-estate'),
                'add_new_item' => __('Añadir localidad', 'homlity-real-estate'),
                'edit_item' => __('Editar localidad', 'homlity-real-estate'),
                'new_item' => __('Nueva localidad', 'homlity-real-estate'),
                'view_item' => __('Ver localidad', 'homlity-real-estate'),
                'search_items' => __('Buscar localidades', 'homlity-real-estate'),
                'not_found' => __('No se encontraron localidades.', 'homlity-real-estate'),
                'not_found_in_trash' => __('No hay localidades en la papelera.', 'homlity-real-estate'),
                'all_items' => __('Todas las localidades', 'homlity-real-estate'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            // CRUD is handled by the validated admin screen. A dedicated,
            // read-only endpoint exposes the relationship to internal clients.
            'show_in_rest' => false,
            'supports' => ['title'],
            'map_meta_cap' => true,
            'capability_type' => 'post',
            'menu_icon' => 'dashicons-location-alt',
        ]);

    }

    public function registerMetaBoxes(): void
    {
        add_meta_box(
            'homlity-locality-geo-relations',
            __('Relaciones geográficas', 'homlity-real-estate'),
            [$this, 'renderRelationsMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function renderRelationsMetaBox(\WP_Post $post): void
    {
        $cityId = self::cityId((int) $post->ID);
        $selectedNeighborhoodIds = self::neighborhoodIds((int) $post->ID);
        $cities = get_terms([
            'taxonomy' => PropertyTaxonomies::TAXONOMY_CITY,
            'hide_empty' => false,
        ]);
        $neighborhoods = get_terms([
            'taxonomy' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            'hide_empty' => false,
        ]);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        ?>
        <p>
            <label for="<?php echo esc_attr(self::FIELD_CITY); ?>"><strong><?php esc_html_e('Ciudad / Municipio', 'homlity-real-estate'); ?></strong></label><br>
            <select id="<?php echo esc_attr(self::FIELD_CITY); ?>" name="<?php echo esc_attr(self::FIELD_CITY); ?>" required style="min-width:320px;max-width:100%;">
                <option value="0"><?php esc_html_e('Selecciona una ciudad', 'homlity-real-estate'); ?></option>
                <?php foreach ((array) $cities as $city): ?>
                    <option value="<?php echo esc_attr((string) $city->term_id); ?>" <?php selected($cityId, (int) $city->term_id); ?>><?php echo esc_html((string) $city->name); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr(self::FIELD_NEIGHBORHOODS); ?>"><strong><?php esc_html_e('Barrios / Conjuntos', 'homlity-real-estate'); ?></strong></label><br>
            <select id="<?php echo esc_attr(self::FIELD_NEIGHBORHOODS); ?>" name="<?php echo esc_attr(self::FIELD_NEIGHBORHOODS); ?>[]" multiple size="12" style="width:100%;max-width:720px;">
                <?php foreach ((array) $neighborhoods as $neighborhood):
                    $neighborhoodCityId = (int) get_term_meta((int) $neighborhood->term_id, '_parent_city', true);
                    $hidden = $cityId > 0 && $neighborhoodCityId > 0 && $neighborhoodCityId !== $cityId;
                    ?>
                    <option
                        value="<?php echo esc_attr((string) $neighborhood->term_id); ?>"
                        data-city-id="<?php echo esc_attr((string) $neighborhoodCityId); ?>"
                        <?php selected(in_array((int) $neighborhood->term_id, $selectedNeighborhoodIds, true)); ?>
                        <?php echo $hidden ? 'hidden' : ''; ?>
                    ><?php echo esc_html((string) $neighborhood->name); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description"><?php esc_html_e('La localidad pertenece a una ciudad y puede contener varios barrios o conjuntos. La relación es opcional para barrios existentes.', 'homlity-real-estate'); ?></p>
        <script>
        (() => {
            const city = document.getElementById(<?php echo wp_json_encode(self::FIELD_CITY); ?>);
            const neighborhoods = document.getElementById(<?php echo wp_json_encode(self::FIELD_NEIGHBORHOODS); ?>);
            if (!city || !neighborhoods) return;
            const filter = () => {
                const cityId = String(city.value || '0');
                [...neighborhoods.options].forEach((option) => {
                    const parent = String(option.dataset.cityId || '0');
                    const visible = cityId === '0' || parent === '0' || parent === cityId;
                    option.hidden = !visible;
                    if (!visible) option.selected = false;
                });
            };
            city.addEventListener('change', filter);
            filter();
        })();
        </script>
        <?php
    }

    public function savePost(int $postId, \WP_Post $post, bool $update): void
    {
        if ($this->updatingPost || wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        if (!isset($_POST[self::NONCE_FIELD])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
            || !current_user_can('edit_post', $postId)
        ) {
            return;
        }

        $name = trim(wp_strip_all_tags((string) $post->post_title));
        $normalizedName = self::normalizeName($name);
        $cityId = isset($_POST[self::FIELD_CITY]) ? absint($_POST[self::FIELD_CITY]) : 0;

        $error = '';
        if ($normalizedName === '') {
            $error = __('La localidad debe tener un nombre.', 'homlity-real-estate');
        } elseif ($cityId <= 0 || !term_exists($cityId, PropertyTaxonomies::TAXONOMY_CITY)) {
            $error = __('La localidad debe estar relacionada con una ciudad válida.', 'homlity-real-estate');
        } elseif ($this->findDuplicateId($normalizedName, $postId) > 0) {
            $error = __('Ya existe una localidad con ese nombre. No se permiten localidades repetidas.', 'homlity-real-estate');
        }

        if ($error !== '') {
            update_post_meta($postId, self::META_VALIDATION_ERROR, $error);
            $this->setPostDraft($postId);
            $this->clearNeighborhoodRelations($postId);
            set_transient('homlity_locality_error_' . get_current_user_id(), $error, 60);
            return;
        }

        delete_post_meta($postId, self::META_VALIDATION_ERROR);
        update_post_meta($postId, self::META_CITY_ID, $cityId);
        update_post_meta($postId, self::META_NORMALIZED_NAME, $normalizedName);

        $requestedIds = isset($_POST[self::FIELD_NEIGHBORHOODS])
            ? array_values(array_unique(array_filter(array_map('absint', (array) wp_unslash($_POST[self::FIELD_NEIGHBORHOODS])))))
            : [];
        $validNeighborhoodIds = [];
        foreach ($requestedIds as $termId) {
            if (!term_exists($termId, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD)) {
                continue;
            }
            $parentCityId = (int) get_term_meta($termId, '_parent_city', true);
            if ($parentCityId > 0 && $parentCityId !== $cityId) {
                continue;
            }
            if ($parentCityId === 0) {
                update_term_meta($termId, '_parent_city', $cityId);
            }
            $validNeighborhoodIds[] = $termId;
        }

        $currentIds = self::neighborhoodIds($postId);
        foreach (array_diff($currentIds, $validNeighborhoodIds) as $termId) {
            delete_term_meta((int) $termId, self::TERM_META_LOCALITY_ID);
        }
        foreach ($validNeighborhoodIds as $termId) {
            update_term_meta($termId, self::TERM_META_LOCALITY_ID, $postId);
        }
    }

    public function clearNeighborhoodRelations(int $postId): void
    {
        if (get_post_type($postId) !== self::POST_TYPE) {
            return;
        }
        foreach (self::neighborhoodIds($postId) as $termId) {
            delete_term_meta($termId, self::TERM_META_LOCALITY_ID);
        }
    }

    public function registerRoutes(): void
    {
        foreach (['homlity-real-estate/v1', 'plugin-inmobiliario/v1'] as $namespace) {
            register_rest_route($namespace, '/localities', [
                'methods' => 'GET',
                'callback' => [$this, 'restLocalities'],
                'permission_callback' => static fn(): bool => current_user_can('edit_posts'),
                'args' => [
                    'city' => ['type' => 'integer', 'required' => false, 'default' => 0],
                ],
            ]);
        }
    }

    public function restLocalities(\WP_REST_Request $request): \WP_REST_Response
    {
        $cityId = absint($request->get_param('city'));
        $args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        if ($cityId > 0) {
            $args['meta_query'] = [[
                'key' => self::META_CITY_ID,
                'value' => $cityId,
                'compare' => '=',
                'type' => 'NUMERIC',
            ]];
        }
        $data = array_map(static fn(\WP_Post $locality): array => [
            'id' => (int) $locality->ID,
            'name' => (string) $locality->post_title,
            'city_id' => self::cityId((int) $locality->ID),
            'neighborhood_ids' => self::neighborhoodIds((int) $locality->ID),
        ], get_posts($args));

        return new \WP_REST_Response($data, 200);
    }

    public function titlePlaceholder(string $title, \WP_Post $post): string
    {
        return $post->post_type === self::POST_TYPE
            ? __('Nombre de la localidad', 'homlity-real-estate')
            : $title;
    }

    public function columns(array $columns): array
    {
        return [
            'cb' => $columns['cb'] ?? '<input type="checkbox">',
            'title' => __('Localidad', 'homlity-real-estate'),
            'homlity_locality_city' => __('Ciudad', 'homlity-real-estate'),
            'homlity_locality_neighborhoods' => __('Barrios / Conjuntos', 'homlity-real-estate'),
            'date' => $columns['date'] ?? __('Fecha', 'homlity-real-estate'),
        ];
    }

    public function renderColumn(string $column, int $postId): void
    {
        if ($column === 'homlity_locality_city') {
            $city = get_term(self::cityId($postId), PropertyTaxonomies::TAXONOMY_CITY);
            echo $city && !is_wp_error($city) ? esc_html((string) $city->name) : '—';
        } elseif ($column === 'homlity_locality_neighborhoods') {
            echo esc_html((string) count(self::neighborhoodIds($postId)));
        }
    }

    public function removeQuickEdit(array $actions, \WP_Post $post): array
    {
        if ($post->post_type === self::POST_TYPE) {
            unset($actions['inline hide-if-no-js']);
        }
        return $actions;
    }

    public function renderValidationNotice(): void
    {
        $key = 'homlity_locality_error_' . get_current_user_id();
        $message = (string) get_transient($key);
        if ($message === '') {
            return;
        }
        delete_transient($key);
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    public static function cityId(int $localityId): int
    {
        return (int) get_post_meta($localityId, self::META_CITY_ID, true);
    }

    /**
     * Resolve published locality IDs from IDs, slugs or comma-separated values.
     *
     * @param mixed $raw
     * @return int[]
     */
    public static function resolvePublishedIds($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $values = is_array($raw) ? $raw : explode(',', (string) $raw);
        $ids = [];
        foreach ($values as $value) {
            $value = sanitize_text_field(trim((string) $value));
            if ($value === '') {
                continue;
            }

            $post = is_numeric($value)
                ? get_post(absint($value))
                : get_page_by_path(sanitize_title($value), OBJECT, self::POST_TYPE);
            if ($post instanceof \WP_Post
                && $post->post_type === self::POST_TYPE
                && $post->post_status === 'publish'
            ) {
                $ids[] = (int) $post->ID;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return array<string,string> */
    public static function publishedOptions(): array
    {
        static $options = null;

        if (is_array($options)) {
            return $options;
        }

        $options = [];
        global $wpdb;

        $localities = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title
             FROM {$wpdb->posts}
             WHERE post_type = %s AND post_status = 'publish'
             ORDER BY post_title ASC",
            self::POST_TYPE
        ), ARRAY_A);

        foreach ((array) $localities as $locality) {
            $options[(string) $locality['ID']] = (string) $locality['post_title'];
        }

        return $options;
    }

    /** @return int[] */
    public static function neighborhoodIds(int $localityId): array
    {
        $terms = get_terms([
            'taxonomy' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            'hide_empty' => false,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => self::TERM_META_LOCALITY_ID,
                'value' => $localityId,
                'compare' => '=',
                'type' => 'NUMERIC',
            ]],
        ]);
        return is_wp_error($terms) ? [] : array_values(array_map('intval', (array) $terms));
    }

    public static function normalizeName(string $name): string
    {
        return sanitize_title(remove_accents(trim(wp_strip_all_tags($name))));
    }

    private function findDuplicateId(string $normalizedName, int $excludePostId): int
    {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'post__not_in' => [$excludePostId],
            'meta_key' => self::META_NORMALIZED_NAME,
            'meta_value' => $normalizedName,
            'no_found_rows' => true,
        ]);
        if ($ids !== []) {
            return (int) $ids[0];
        }

        // Compatibility for localities created before normalized-name metadata.
        $candidates = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'post__not_in' => [$excludePostId],
            'no_found_rows' => true,
        ]);
        foreach ($candidates as $candidate) {
            if (self::normalizeName((string) $candidate->post_title) === $normalizedName) {
                return (int) $candidate->ID;
            }
        }
        return 0;
    }

    private function setPostDraft(int $postId): void
    {
        if (get_post_status($postId) === 'draft') {
            return;
        }
        $this->updatingPost = true;
        wp_update_post(['ID' => $postId, 'post_status' => 'draft']);
        $this->updatingPost = false;
    }
}
