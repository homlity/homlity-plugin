<?php
/**
 * Registers the Property custom post type and related meta handling.
 */

namespace Codwelt\PluginInmobiliario\Services;

use Codwelt\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyPostType implements ServiceInterface
{
    public const POST_TYPE = 'property';

    private array $metaKeys = [
        'price_sale' => '_property_price_sale',
        'currency_sale' => '_property_currency_sale',
        'price_rent' => '_property_price_rent',
        'currency_rent' => '_property_currency_rent',
        'price_admin' => '_property_price_admin',
        'currency_admin' => '_property_currency_admin',
        'area' => '_property_area',
        'area_lot' => '_property_area_lot',
        'area_private' => '_property_area_private',
        'area_built' => '_property_area_built',
        'bedrooms' => '_property_bedrooms',
        'bathrooms' => '_property_bathrooms',
        'parking' => '_property_parking',
        'condition' => '_property_condition',
        'age' => '_property_age',
        'code' => '_property_code',
        'address' => '_property_address',
        'latitude' => '_property_latitude',
        'longitude' => '_property_longitude',
        'admin_included' => '_property_admin_included',
        'gallery' => '_property_gallery',
        'featured' => '_property_featured',
        'agent_id' => '_property_agent_id',
        'agent_phone' => '_property_agent_phone',
        'agent_email' => '_property_agent_email',
    ];

    private array $locationTaxonomies = [
        'country' => \Codwelt\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_COUNTRY,
        'state' => \Codwelt\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_STATE,
        'city' => \Codwelt\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_CITY,
        'neighborhood' => \Codwelt\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
    ];

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'registerMeta']);
        add_action('add_meta_boxes', [$this, 'registerMetaboxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'saveMeta'], 10, 2);
        add_filter('pll_get_post_types', [$this, 'registerWithPolylang'], 10, 2);
        add_filter('wpml_element_types', [$this, 'registerWithWpml']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    public function registerPostType(): void
    {
        $labels = [
            'name' => __('Propiedades', 'plugin-inmobiliario'),
            'singular_name' => __('Propiedad', 'plugin-inmobiliario'),
            'add_new' => __('Añadir nueva', 'plugin-inmobiliario'),
            'add_new_item' => __('Añadir propiedad', 'plugin-inmobiliario'),
            'edit_item' => __('Editar propiedad', 'plugin-inmobiliario'),
            'new_item' => __('Nueva propiedad', 'plugin-inmobiliario'),
            'view_item' => __('Ver propiedad', 'plugin-inmobiliario'),
            'search_items' => __('Buscar propiedades', 'plugin-inmobiliario'),
            'not_found' => __('No se encontraron propiedades', 'plugin-inmobiliario'),
            'menu_name' => __('Propiedades', 'plugin-inmobiliario'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'properties', 'with_front' => false],
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions'],
            'menu_icon' => 'dashicons-building',
            'capability_type' => [self::POST_TYPE, self::POST_TYPE . 's'],
            'map_meta_cap' => true,
            'capabilities' => \Codwelt\PluginInmobiliario\Services\CapabilityService::CAPS,
            'show_in_menu' => false, // Handled by custom admin menu.
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    public function registerMeta(): void
    {
        foreach ($this->metaKeys as $metaKey) {
            $isBoolean = in_array($metaKey, [$this->metaKeys['featured'], $this->metaKeys['admin_included']], true);
            register_post_meta(self::POST_TYPE, $metaKey, [
                'type' => $isBoolean ? 'boolean' : 'string',
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => $isBoolean
                    ? 'rest_sanitize_boolean'
                    : ($metaKey === $this->metaKeys['gallery']
                        ? null
                        : 'sanitize_text_field'),
                'auth_callback' => static function () {
                    return current_user_can('edit_posts');
                },
            ]);
        }
    }

    public function registerMetaboxes(): void
    {
        add_meta_box(
            'property-pricing',
            __('Precio y moneda', 'plugin-inmobiliario'),
            [$this, 'renderPricingMetabox'],
            self::POST_TYPE,
            'side'
        );

        add_meta_box(
            'property-details',
            __('Detalles', 'plugin-inmobiliario'),
            [$this, 'renderDetailsMetabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'property-gallery',
            __('Galería de imágenes', 'plugin-inmobiliario'),
            [$this, 'renderGalleryMetabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'property-operation',
            __('Gestión', 'plugin-inmobiliario'),
            [$this, 'renderOperationMetabox'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'property-type',
            __('Tipo de inmueble', 'plugin-inmobiliario'),
            [$this, 'renderTypeMetabox'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'property-agent',
            __('Asesor comercial', 'plugin-inmobiliario'),
            [$this, 'renderAgentMetabox'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'property-flags',
            __('Opciones', 'plugin-inmobiliario'),
            [$this, 'renderFlagsMetabox'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public function renderPricingMetabox(\WP_Post $post): void
    {
        wp_nonce_field('property_price_nonce', 'property_price_nonce_field');
        $currencyService = new CurrencyService();
        $currencies = $currencyService->supportedCurrencies();

        $fields = [
            'sale' => __('Precio venta', 'plugin-inmobiliario'),
            'rent' => __('Precio arriendo', 'plugin-inmobiliario'),
            'admin' => __('Precio administración', 'plugin-inmobiliario'),
        ];
        ?>
        <p><?php esc_html_e('Admite cualquier moneda, escribe el código si no aparece en la lista.', 'plugin-inmobiliario'); ?></p>
        <?php foreach ($fields as $key => $label): ?>
            <?php
            $price = get_post_meta($post->ID, $this->metaKeys['price_' . $key], true);
            $currency = get_post_meta($post->ID, $this->metaKeys['currency_' . $key], true) ?: $currencyService->baseCurrency();
            ?>
            <div class="property-price-block property-price-<?php echo esc_attr($key); ?>" data-gestion="<?php echo esc_attr($key); ?>">
                <p>
                    <label for="property_price_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                    <input type="number" min="0" step="0.01" id="property_price_<?php echo esc_attr($key); ?>"
                           name="property_price_<?php echo esc_attr($key); ?>"
                           value="<?php echo esc_attr($price); ?>" class="widefat">
                </p>
                <p>
                    <label for="property_currency_<?php echo esc_attr($key); ?>"><?php esc_html_e('Moneda', 'plugin-inmobiliario'); ?></label>
                    <select id="property_currency_<?php echo esc_attr($key); ?>"
                            name="property_currency_<?php echo esc_attr($key); ?>"
                            class="widefat">
                        <?php foreach ($currencies as $code): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($currency, $code); ?>>
                                <?php echo esc_html($code); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (!in_array($currency, $currencies, true)): ?>
                            <option value="<?php echo esc_attr($currency); ?>" selected><?php echo esc_html($currency); ?></option>
                        <?php endif; ?>
                    </select>
                </p>
                <?php if ($key === 'rent'): ?>
                    <p>
                        <label>
                            <input type="checkbox" name="property_admin_included" value="1" <?php checked((bool) get_post_meta($post->ID, $this->metaKeys['admin_included'], true)); ?>>
                            <?php esc_html_e('Administración incluida en el arriendo', 'plugin-inmobiliario'); ?>
                        </label>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php
    }

    public function renderGalleryMetabox(\WP_Post $post): void
    {
        wp_nonce_field('property_gallery_nonce', 'property_gallery_nonce_field');
        $gallery = get_post_meta($post->ID, $this->metaKeys['gallery'], true);
        $ids = array_filter(array_map('absint', explode(',', (string) $gallery)));

        $items = [];
        foreach ($ids as $id) {
            $url = wp_get_attachment_image_url($id, 'thumbnail');
            if ($url) {
                $items[] = ['id' => $id, 'url' => $url];
            }
        }
        ?>
        <div id="property-gallery-wrapper">
            <p>
                <button type="button" class="button" id="property_gallery_add"><?php esc_html_e('Agregar/editar galería', 'plugin-inmobiliario'); ?></button>
                <button type="button" class="button link-button" id="property_gallery_clear"><?php esc_html_e('Limpiar galería', 'plugin-inmobiliario'); ?></button>
            </p>
            <ul id="property_gallery_list" class="property-gallery-list" style="display:flex;gap:8px;flex-wrap:wrap;padding:0;">
                <?php foreach ($items as $item): ?>
                    <li data-id="<?php echo esc_attr($item['id']); ?>" style="list-style:none;cursor:move;">
                        <img src="<?php echo esc_url($item['url']); ?>" style="width:90px;height:90px;object-fit:cover;border:1px solid #ccd0d4;border-radius:3px;">
                    </li>
                <?php endforeach; ?>
            </ul>
            <input type="hidden" id="property_gallery" name="property_gallery" value="<?php echo esc_attr(implode(',', $ids)); ?>">
            <p class="description"><?php esc_html_e('Arrastra para reordenar. La primera imagen será la principal.', 'plugin-inmobiliario'); ?></p>
        </div>
        <?php
    }

    public function renderDetailsMetabox(\WP_Post $post): void
    {
        wp_nonce_field('property_details_nonce', 'property_details_nonce_field');
        $fields = [
            'area' => __('Área total (m²)', 'plugin-inmobiliario'),
            'area_lot' => __('Área de lote (m²)', 'plugin-inmobiliario'),
            'area_private' => __('Área privada (m²)', 'plugin-inmobiliario'),
            'area_built' => __('Área construida (m²)', 'plugin-inmobiliario'),
            'bedrooms' => __('Habitaciones', 'plugin-inmobiliario'),
            'bathrooms' => __('Baños', 'plugin-inmobiliario'),
            'parking' => __('Parqueaderos', 'plugin-inmobiliario'),
            'condition' => __('Estado', 'plugin-inmobiliario'),
            'age' => __('Edad (años)', 'plugin-inmobiliario'),
            'code' => __('Código de la propiedad', 'plugin-inmobiliario'),
            'address' => __('Dirección', 'plugin-inmobiliario'),
            'latitude' => __('Latitud', 'plugin-inmobiliario'),
            'longitude' => __('Longitud', 'plugin-inmobiliario'),
        ];
        ?>
        <table class="form-table">
            <tbody>
            <?php foreach ($fields as $key => $label): ?>
                <tr>
                    <th scope="row"><label for="property_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                    <td>
                        <input class="regular-text" type="text"
                               id="property_<?php echo esc_attr($key); ?>"
                               name="property_<?php echo esc_attr($key); ?>"
                               value="<?php echo esc_attr(get_post_meta($post->ID, $this->metaKeys[$key], true)); ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th scope="row"><?php esc_html_e('Mapa', 'plugin-inmobiliario'); ?></th>
                <td>
                    <div id="property_map_preview" style="width:100%;height:320px;background:#eef1f5;border:1px solid #ccd0d4;border-radius:4px;"></div>
                    <p class="description"><?php esc_html_e('El mapa se actualiza según la dirección y la latitud/longitud.', 'plugin-inmobiliario'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="property_country"><?php esc_html_e('País', 'plugin-inmobiliario'); ?></label></th>
                <td>
                    <select id="property_country" name="property_country" class="property-location-select" data-taxonomy="<?php echo esc_attr($this->locationTaxonomies['country']); ?>">
                        <option value=""><?php esc_html_e('Selecciona país', 'plugin-inmobiliario'); ?></option>
                        <?php echo $this->renderLocationOptions($post->ID, 'country'); ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="property_state"><?php esc_html_e('Departamento / Provincia', 'plugin-inmobiliario'); ?></label></th>
                <td>
                    <select id="property_state" name="property_state" class="property-location-select" data-taxonomy="<?php echo esc_attr($this->locationTaxonomies['state']); ?>" data-parent="country">
                        <option value=""><?php esc_html_e('Selecciona departamento / provincia', 'plugin-inmobiliario'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="property_city"><?php esc_html_e('Ciudad / Municipio', 'plugin-inmobiliario'); ?></label></th>
                <td>
                    <select id="property_city" name="property_city" class="property-location-select" data-taxonomy="<?php echo esc_attr($this->locationTaxonomies['city']); ?>" data-parent="state">
                        <option value=""><?php esc_html_e('Selecciona ciudad / municipio', 'plugin-inmobiliario'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="property_neighborhood"><?php esc_html_e('Barrio', 'plugin-inmobiliario'); ?></label></th>
                <td>
                    <select id="property_neighborhood" name="property_neighborhood" class="property-location-select" data-taxonomy="<?php echo esc_attr($this->locationTaxonomies['neighborhood']); ?>" data-parent="city">
                        <option value=""><?php esc_html_e('Selecciona barrio', 'plugin-inmobiliario'); ?></option>
                    </select>
                </td>
            </tr>
            </tbody>
        </table>
        <?php
    }

    public function renderOperationMetabox(\WP_Post $post): void
    {
        wp_nonce_field('property_operation_nonce', 'property_operation_nonce_field');
        $taxonomy = PropertyTaxonomies::TAXONOMY_OPERATION;
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);
        $assigned = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids']);
        $current = $assigned ? $assigned[0] : 0;
        ?>
        <p>
            <label for="property_operation"><?php esc_html_e('Selecciona la gestión principal', 'plugin-inmobiliario'); ?></label>
            <select id="property_operation" name="property_operation" class="widefat">
                <option value=""><?php esc_html_e('Elige una gestión', 'plugin-inmobiliario'); ?></option>
                <?php foreach ($terms as $term): ?>
                    <option value="<?php echo esc_attr($term->term_id); ?>" data-slug="<?php echo esc_attr($term->slug); ?>" <?php selected($current, $term->term_id); ?>>
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    public function renderTypeMetabox(\WP_Post $post): void
    {
        wp_nonce_field('property_type_nonce', 'property_type_nonce_field');
        $taxonomy = PropertyTaxonomies::TAXONOMY_TYPE;
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);
        $assigned = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids']);
        $current = $assigned ? $assigned[0] : 0;
        ?>
        <p>
            <label for="property_type"><?php esc_html_e('Selecciona el tipo de inmueble', 'plugin-inmobiliario'); ?></label>
            <select id="property_type" name="property_type" class="widefat">
                <option value=""><?php esc_html_e('Elige un tipo', 'plugin-inmobiliario'); ?></option>
                <?php foreach ($terms as $term): ?>
                    <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($current, $term->term_id); ?>>
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    public function enqueueAdminAssets(string $hook): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $postType = $screen->post_type ?? ($_GET['post_type'] ?? '');
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        if ($postType !== self::POST_TYPE) {
            return;
        }

        wp_enqueue_script(
            'plugin-inmobiliario-location',
            PLUGIN_INMOBILIARIO_URL . 'assets/js/location-admin.js',
            [],
            PLUGIN_INMOBILIARIO_VERSION,
            true
        );

        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');

        $gallery = get_post_meta($post->ID, $this->metaKeys['gallery'], true);
        $galleryIds = array_filter(array_map('absint', explode(',', (string) $gallery)));

        $galleryItems = [];
        foreach ($galleryIds as $gid) {
            $url = wp_get_attachment_image_url($gid, 'thumbnail');
            if ($url) {
                $galleryItems[] = ['id' => $gid, 'url' => $url];
            }
        }

        wp_enqueue_style(
            'plugin-inmobiliario-leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            [],
            '1.9.4'
        );

        wp_enqueue_script(
            'plugin-inmobiliario-leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            [],
            '1.9.4',
            true
        );

        wp_enqueue_script(
            'plugin-inmobiliario-location-map',
            PLUGIN_INMOBILIARIO_URL . 'assets/js/location-map.js',
            ['plugin-inmobiliario-leaflet'],
            PLUGIN_INMOBILIARIO_VERSION,
            true
        );

        wp_enqueue_script(
            'plugin-inmobiliario-gallery',
            PLUGIN_INMOBILIARIO_URL . 'assets/js/gallery-admin.js',
            ['jquery', 'jquery-ui-sortable'],
            PLUGIN_INMOBILIARIO_VERSION,
            true
        );

        wp_enqueue_script(
            'plugin-inmobiliario-pricing',
            PLUGIN_INMOBILIARIO_URL . 'assets/js/pricing-admin.js',
            [],
            PLUGIN_INMOBILIARIO_VERSION,
            true
        );

        $selected = [];
        $postId = isset($GLOBALS['post']) && $GLOBALS['post'] instanceof \WP_Post ? $GLOBALS['post']->ID : 0;
        foreach ($this->locationTaxonomies as $key => $taxonomy) {
            $terms = $postId ? wp_get_object_terms($postId, $taxonomy, ['fields' => 'ids']) : [];
            $selected[$key] = $terms ? $terms[0] : 0;
        }

        $settings = get_option('plugin_inmobiliario_settings', []);
        $selected['country'] = $selected['country'] ?: absint($settings['default_country'] ?? 0);
        $selected['state'] = $selected['state'] ?: absint($settings['default_state'] ?? 0);
        $selected['city'] = $selected['city'] ?: absint($settings['default_city'] ?? 0);

        $selected['latitude'] = ($postId ? get_post_meta($postId, $this->metaKeys['latitude'], true) : '') ?: 4.5709;
        $selected['longitude'] = ($postId ? get_post_meta($postId, $this->metaKeys['longitude'], true) : '') ?: -74.2973;

        wp_localize_script('plugin-inmobiliario-location', 'pluginInmobiliarioLocation', [
            'restUrl' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'selected' => $selected,
            'taxonomies' => $this->locationTaxonomies,
        ]);

        wp_localize_script('plugin-inmobiliario-location-map', 'pluginInmobiliarioMap', [
            'defaultLat' => $selected['latitude'] ?? 4.5709,
            'defaultLng' => $selected['longitude'] ?? -74.2973,
        ]);

        wp_localize_script('plugin-inmobiliario-gallery', 'pluginInmobiliarioGallery', [
            'items' => $galleryItems,
        ]);
    }

    public function renderFlagsMetabox(\WP_Post $post): void
    {
        $featured = (bool) get_post_meta($post->ID, $this->metaKeys['featured'], true);
        ?>
        <p>
            <label for="property_featured">
                <input type="checkbox" id="property_featured" name="property_featured" value="1" <?php checked($featured); ?>>
                <?php esc_html_e('Propiedad destacada', 'plugin-inmobiliario'); ?>
            </label>
        </p>
        <?php
    }

    public function renderAgentMetabox(\WP_Post $post): void
    {
        $currentAgent = (int) get_post_meta($post->ID, $this->metaKeys['agent_id'], true);

        $users = get_users([
            'role__in' => ['administrator', 'editor', 'author'],
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name', 'user_email'],
        ]);
        ?>
        <p>
            <label for="property_agent_id"><?php esc_html_e('Usuario/asesor', 'plugin-inmobiliario'); ?></label>
            <select id="property_agent_id" name="property_agent_id" class="widefat">
                <option value=""><?php esc_html_e('Sin asignar', 'plugin-inmobiliario'); ?></option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($currentAgent, $user->ID); ?>>
                        <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description">
            <?php esc_html_e('Se usará la foto, correo y teléfono que tenga el usuario en su perfil.', 'plugin-inmobiliario'); ?>
        </p>
        <?php
    }

    public function saveMeta(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['property_price_nonce_field'], $_POST['property_details_nonce_field'], $_POST['property_gallery_nonce_field'], $_POST['property_operation_nonce_field'], $_POST['property_type_nonce_field'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['property_price_nonce_field'], 'property_price_nonce') ||
            !wp_verify_nonce($_POST['property_details_nonce_field'], 'property_details_nonce') ||
            !wp_verify_nonce($_POST['property_gallery_nonce_field'], 'property_gallery_nonce') ||
            !wp_verify_nonce($_POST['property_operation_nonce_field'], 'property_operation_nonce') ||
            !wp_verify_nonce($_POST['property_type_nonce_field'], 'property_type_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $sanitizers = [
            'price_sale' => static function ($value) {
                return (float) $value;
            },
            'currency_sale' => static function ($value) {
                return strtoupper(sanitize_text_field($value));
            },
            'price_rent' => static function ($value) {
                return (float) $value;
            },
            'currency_rent' => static function ($value) {
                return strtoupper(sanitize_text_field($value));
            },
            'price_admin' => static function ($value) {
                return (float) $value;
            },
            'currency_admin' => static function ($value) {
                return strtoupper(sanitize_text_field($value));
            },
            'area' => static function ($value) {
                return sanitize_text_field($value);
            },
            'bedrooms' => static function ($value) {
                return (int) $value;
            },
            'bathrooms' => static function ($value) {
                return (int) $value;
            },
            'area_lot' => static function ($value) {
                return sanitize_text_field($value);
            },
            'area_private' => static function ($value) {
                return sanitize_text_field($value);
            },
            'area_built' => static function ($value) {
                return sanitize_text_field($value);
            },
            'parking' => static function ($value) {
                return (int) $value;
            },
            'condition' => static function ($value) {
                return sanitize_text_field($value);
            },
            'age' => static function ($value) {
                return sanitize_text_field($value);
            },
            'code' => static function ($value) {
                return sanitize_text_field($value);
            },
            'address' => static function ($value) {
                return sanitize_text_field($value);
            },
            'latitude' => static function ($value) {
                return sanitize_text_field($value);
            },
            'longitude' => static function ($value) {
                return sanitize_text_field($value);
            },
            'admin_included' => static function ($value) {
                return (bool) $value;
            },
            'gallery' => static function ($value) {
                if (is_array($value)) {
                    $ids = array_map('absint', $value);
                } else {
                    $ids = array_map('absint', array_filter(array_map('trim', explode(',', (string) $value))));
                }
                $ids = array_filter($ids);
                return implode(',', $ids);
            },
            'featured' => static function ($value) {
                return (bool) $value;
            },
            'agent_id' => static function ($value) {
                return absint($value);
            },
            'agent_phone' => static function ($value) {
                return sanitize_text_field($value);
            },
            'agent_email' => static function ($value) {
                return sanitize_email($value);
            },
        ];

        foreach ($this->metaKeys as $key => $metaKey) {
            $value = $_POST['property_' . $key] ?? null;
            // For checkboxes/booleans ensure a default value is stored.
            if (in_array($key, ['featured', 'admin_included'], true) && $value === null) {
                $value = 0;
            } elseif ($value === null) {
                continue;
            }
            $value = call_user_func($sanitizers[$key], $value);
            update_post_meta($postId, $metaKey, $value);
        }

        $this->saveLocationTerms($postId);
        $this->saveOperationTerm($postId);
        $this->saveTypeTerm($postId);
    }

    private function saveLocationTerms(int $postId): void
    {
        foreach ($this->locationTaxonomies as $key => $taxonomy) {
            $termId = isset($_POST['property_' . $key]) ? absint($_POST['property_' . $key]) : 0;
            if ($termId) {
                wp_set_object_terms($postId, [$termId], $taxonomy, false);
            } else {
                wp_set_object_terms($postId, [], $taxonomy, false);
            }
        }
    }

    private function saveOperationTerm(int $postId): void
    {
        $taxonomy = PropertyTaxonomies::TAXONOMY_OPERATION;
        $termId = isset($_POST['property_operation']) ? absint($_POST['property_operation']) : 0;
        if ($termId) {
            wp_set_object_terms($postId, [$termId], $taxonomy, false);
        } else {
            wp_set_object_terms($postId, [], $taxonomy, false);
        }
    }

    private function saveTypeTerm(int $postId): void
    {
        $taxonomy = PropertyTaxonomies::TAXONOMY_TYPE;
        $termId = isset($_POST['property_type']) ? absint($_POST['property_type']) : 0;
        if ($termId) {
            wp_set_object_terms($postId, [$termId], $taxonomy, false);
        } else {
            wp_set_object_terms($postId, [], $taxonomy, false);
        }
    }

    private function renderLocationOptions(int $postId, string $key): string
    {
        $taxonomy = $this->locationTaxonomies[$key] ?? '';
        if (!$taxonomy) {
            return '';
        }

        $settings = get_option('plugin_inmobiliario_settings', []);
        $defaultMap = [
            'country' => $settings['default_country'] ?? 0,
            'state' => $settings['default_state'] ?? 0,
            'city' => $settings['default_city'] ?? 0,
        ];

        $selected = wp_get_object_terms($postId, $taxonomy, ['fields' => 'ids']);
        $current = $selected ? $selected[0] : absint($defaultMap[$key] ?? 0);
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        $html = '';
        foreach ($terms as $term) {
            $html .= sprintf(
                '<option value="%d"%s>%s</option>',
                (int) $term->term_id,
                selected($current, $term->term_id, false),
                esc_html($term->name)
            );
        }

        return $html;
    }

    public function metaKeys(): array
    {
        return $this->metaKeys;
    }

    public function registerWithPolylang(array $types, $isSettings): array
    {
        if ($isSettings) {
            $types[] = self::POST_TYPE;
        }
        return $types;
    }

    public function registerWithWpml(array $types): array
    {
        $types[] = 'post_' . self::POST_TYPE;
        return $types;
    }
}
