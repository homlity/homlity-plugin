<?php
/**
 * Registers the Property custom post type and related meta handling.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

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
        'country' => \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_COUNTRY,
        'state' => \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_STATE,
        'city' => \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_CITY,
        'neighborhood' => \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
    ];

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'registerMeta']);
        add_action('add_meta_boxes', [$this, 'registerMetaboxes']);
        add_action('rest_api_init', [$this, 'registerEditorRoutes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'saveMeta'], 10, 2);
        add_filter('pll_get_post_types', [$this, 'registerWithPolylang'], 10, 2);
        add_filter('wpml_element_types', [$this, 'registerWithWpml']);
        add_filter('use_block_editor_for_post_type', [$this, 'useBlockEditorForPostType'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_notices', [$this, 'renderValidationNotice']);
        add_action('add_meta_boxes_' . self::POST_TYPE, [$this, 'removeLegacyMetaboxes'], 999);
    }

    public function useBlockEditorForPostType(bool $useBlockEditor, string $postType): bool
    {
        if ($postType === self::POST_TYPE) {
            return false;
        }

        return $useBlockEditor;
    }

    public function removeLegacyMetaboxes(): void
    {
        // Keep only the publish box; React handles the full property form UI.
        $boxes = [
            'submitdiv',
            'slugdiv',
            'postexcerpt',
            'trackbacksdiv',
            'postcustom',
            'commentstatusdiv',
            'commentsdiv',
            'authordiv',
            'revisionsdiv',
            'formatdiv',
            'pageparentdiv',
            'postimagediv',
            'tagsdiv-property_tag',
            'property_categorydiv',
            'property_featurediv',
            'property_nearbydiv',
            'property_locationdiv',
            'property_countrydiv',
            'property_statediv',
            'property_citydiv',
            'property_neighborhooddiv',
            'property_typediv',
            'property_operationdiv',
            'property-details',
            'property-pricing',
            'property-gallery',
            'property-location',
            'property-flags',
            'property-agent',
            'property-operation',
            'property-type',
        ];

        foreach ($boxes as $id) {
            if ($id === 'submitdiv') {
                continue;
            }
            remove_meta_box($id, self::POST_TYPE, 'normal');
            remove_meta_box($id, self::POST_TYPE, 'side');
            remove_meta_box($id, self::POST_TYPE, 'advanced');
        }
    }

    public function registerPostType(): void
    {
        $labels = [
            'name' => __('Propiedades', 'homlity-plugin'),
            'singular_name' => __('Propiedad', 'homlity-plugin'),
            'add_new' => __('Añadir nueva', 'homlity-plugin'),
            'add_new_item' => __('Añadir propiedad', 'homlity-plugin'),
            'edit_item' => __('Editar propiedad', 'homlity-plugin'),
            'new_item' => __('Nueva propiedad', 'homlity-plugin'),
            'view_item' => __('Ver propiedad', 'homlity-plugin'),
            'search_items' => __('Buscar propiedades', 'homlity-plugin'),
            'not_found' => __('No se encontraron propiedades', 'homlity-plugin'),
            'menu_name' => __('Propiedades', 'homlity-plugin'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_in_admin_bar' => true,
            'show_in_rest' => true,
            'has_archive' => false,
            'rewrite' => ['slug' => 'inmueble', 'with_front' => false],
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions'],
            'menu_icon' => 'dashicons-building',
            'capability_type' => [self::POST_TYPE, self::POST_TYPE . 's'],
            'map_meta_cap' => true,
            'capabilities' => \Homlity\PluginInmobiliario\Services\CapabilityService::CAPS,
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
            __('Precio y moneda', 'homlity-plugin'),
            [$this, 'renderPricingMetabox'],
            self::POST_TYPE,
            'side'
        );

        add_meta_box(
            'property-details',
            __('Detalles', 'homlity-plugin'),
            [$this, 'renderDetailsMetabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'property-gallery',
            __('Galería de imágenes', 'homlity-plugin'),
            [$this, 'renderGalleryMetabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'property-operation',
            __('Gestión', 'homlity-plugin'),
            [$this, 'renderOperationMetabox'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'property-type',
            __('Tipo de inmueble', 'homlity-plugin'),
            [$this, 'renderTypeMetabox'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'property-agent',
            __('Asesor comercial', 'homlity-plugin'),
            [$this, 'renderAgentMetabox'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'property-flags',
            __('Opciones', 'homlity-plugin'),
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
            'sale' => __('Precio venta', 'homlity-plugin'),
            'rent' => __('Precio arriendo', 'homlity-plugin'),
            'admin' => __('Precio administración', 'homlity-plugin'),
        ];
        ?>
        <p><?php esc_html_e('Admite cualquier moneda, escribe el código si no aparece en la lista.', 'homlity-plugin'); ?></p>
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
                    <label for="property_currency_<?php echo esc_attr($key); ?>"><?php esc_html_e('Moneda', 'homlity-plugin'); ?></label>
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
                            <?php esc_html_e('Administración incluida en el arriendo', 'homlity-plugin'); ?>
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
                <button type="button" class="button" id="property_gallery_add"><?php esc_html_e('Agregar/editar galería', 'homlity-plugin'); ?></button>
                <button type="button" class="button link-button" id="property_gallery_clear"><?php esc_html_e('Limpiar galería', 'homlity-plugin'); ?></button>
            </p>
            <ul id="property_gallery_list" class="property-gallery-list" style="display:flex;gap:8px;flex-wrap:wrap;padding:0;">
                <?php foreach ($items as $item): ?>
                    <li data-id="<?php echo esc_attr($item['id']); ?>" style="list-style:none;cursor:move;">
                        <img src="<?php echo esc_url($item['url']); ?>" style="width:90px;height:90px;object-fit:cover;border:1px solid #ccd0d4;border-radius:3px;">
                    </li>
                <?php endforeach; ?>
            </ul>
            <input type="hidden" id="property_gallery" name="property_gallery" value="<?php echo esc_attr(implode(',', $ids)); ?>">
            <p class="description"><?php esc_html_e('Arrastra para reordenar. La primera imagen será la principal.', 'homlity-plugin'); ?></p>
        </div>
        <?php
    }

    public function renderDetailsMetabox(\WP_Post $post): void
    {
        wp_nonce_field('property_details_nonce', 'property_details_nonce_field');
        $fields = [
            'area' => __('Área total (m²)', 'homlity-plugin'),
            'area_lot' => __('Área de lote (m²)', 'homlity-plugin'),
            'area_private' => __('Área privada (m²)', 'homlity-plugin'),
            'area_built' => __('Área construida (m²)', 'homlity-plugin'),
            'bedrooms' => __('Habitaciones', 'homlity-plugin'),
            'bathrooms' => __('Baños', 'homlity-plugin'),
            'parking' => __('Parqueaderos', 'homlity-plugin'),
            'condition' => __('Estado', 'homlity-plugin'),
            'age' => __('Edad (años)', 'homlity-plugin'),
            'code' => __('Código de la propiedad', 'homlity-plugin'),
            'address' => __('Dirección', 'homlity-plugin'),
            'latitude' => __('Latitud', 'homlity-plugin'),
            'longitude' => __('Longitud', 'homlity-plugin'),
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
                <th scope="row"><?php esc_html_e('Mapa', 'homlity-plugin'); ?></th>
                <td>
                    <div id="property_map_preview" style="width:100%;height:320px;background:#eef1f5;border:1px solid #ccd0d4;border-radius:4px;"></div>
                    <p class="description"><?php esc_html_e('El mapa se actualiza según la dirección y la latitud/longitud.', 'homlity-plugin'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="property_country"><?php esc_html_e('País', 'homlity-plugin'); ?></label></th>
                <td>
                    <select id="property_country" name="property_country" class="property-location-select" data-taxonomy="<?php echo esc_attr($this->locationTaxonomies['country']); ?>">
                        <option value=""><?php esc_html_e('Selecciona país', 'homlity-plugin'); ?></option>
                        <?php echo $this->renderLocationOptions($post->ID, 'country'); ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="property_state"><?php esc_html_e('Departamento / Provincia', 'homlity-plugin'); ?></label></th>
                <td>
                    <select id="property_state" name="property_state" class="property-location-select" data-taxonomy="<?php echo esc_attr($this->locationTaxonomies['state']); ?>" data-parent="country">
                        <option value=""><?php esc_html_e('Selecciona departamento / provincia', 'homlity-plugin'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="property_city"><?php esc_html_e('Ciudad / Municipio', 'homlity-plugin'); ?></label></th>
                <td>
                    <select id="property_city" name="property_city" class="property-location-select" data-taxonomy="<?php echo esc_attr($this->locationTaxonomies['city']); ?>" data-parent="state">
                        <option value=""><?php esc_html_e('Selecciona ciudad / municipio', 'homlity-plugin'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="property_neighborhood"><?php esc_html_e('Barrio', 'homlity-plugin'); ?></label></th>
                <td>
                    <select id="property_neighborhood" name="property_neighborhood" class="property-location-select" data-taxonomy="<?php echo esc_attr($this->locationTaxonomies['neighborhood']); ?>" data-parent="city">
                        <option value=""><?php esc_html_e('Selecciona barrio', 'homlity-plugin'); ?></option>
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
            <label for="property_operation"><?php esc_html_e('Selecciona la gestión principal', 'homlity-plugin'); ?></label>
            <select id="property_operation" name="property_operation" class="widefat">
                <option value=""><?php esc_html_e('Elige una gestión', 'homlity-plugin'); ?></option>
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
            <label for="property_type"><?php esc_html_e('Selecciona el tipo de inmueble', 'homlity-plugin'); ?></label>
            <select id="property_type" name="property_type" class="widefat">
                <option value=""><?php esc_html_e('Elige un tipo', 'homlity-plugin'); ?></option>
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
        if (isset($_GET['action']) && sanitize_key((string) $_GET['action']) === 'elementor') {
            return;
        }

        if (isset($_GET['elementor-preview']) || isset($_GET['elementor_library'])) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $postType = $screen->post_type ?? ($_GET['post_type'] ?? '');
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        if ($postType !== self::POST_TYPE) {
            return;
        }

        wp_enqueue_script(
            'homlity-plugin-location',
            HOMLITY_PLUGIN_URL . 'assets/js/location-admin.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');

        $postId = isset($GLOBALS['post']) && $GLOBALS['post'] instanceof \WP_Post ? $GLOBALS['post']->ID : 0;
        $gallery = $postId ? get_post_meta($postId, $this->metaKeys['gallery'], true) : '';
        $galleryIds = array_filter(array_map('absint', explode(',', (string) $gallery)));

        $galleryItems = [];
        foreach ($galleryIds as $gid) {
            $url = wp_get_attachment_image_url($gid, 'thumbnail');
            if ($url) {
                $galleryItems[] = ['id' => $gid, 'url' => $url];
            }
        }

        wp_enqueue_style(
            'homlity-plugin-leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            [],
            '1.9.4'
        );

        wp_enqueue_script(
            'homlity-plugin-leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            [],
            '1.9.4',
            true
        );

        wp_enqueue_script(
            'homlity-plugin-location-map',
            HOMLITY_PLUGIN_URL . 'assets/js/location-map.js',
            ['homlity-plugin-leaflet'],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        wp_enqueue_script(
            'homlity-plugin-gallery',
            HOMLITY_PLUGIN_URL . 'assets/js/gallery-admin.js',
            ['jquery', 'jquery-ui-sortable'],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        wp_enqueue_script(
            'homlity-plugin-pricing',
            HOMLITY_PLUGIN_URL . 'assets/js/pricing-admin.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        $selected = [];
        foreach ($this->locationTaxonomies as $key => $taxonomy) {
            $terms = $postId ? wp_get_object_terms($postId, $taxonomy, ['fields' => 'ids']) : [];
            $selected[$key] = $terms ? $terms[0] : 0;
        }

        $settings = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
        $selected['country'] = $selected['country'] ?: absint($settings['default_country'] ?? 0);
        $selected['state'] = $selected['state'] ?: absint($settings['default_state'] ?? 0);
        $selected['city'] = $selected['city'] ?: absint($settings['default_city'] ?? 0);
        $selected['neighborhood'] = $selected['neighborhood'] ?: absint($settings['default_neighborhood'] ?? 0);

        $selected['latitude'] = ($postId ? get_post_meta($postId, $this->metaKeys['latitude'], true) : '') ?: 4.5709;
        $selected['longitude'] = ($postId ? get_post_meta($postId, $this->metaKeys['longitude'], true) : '') ?: -74.2973;

        wp_localize_script('homlity-plugin-location', 'homlityPluginLocation', [
            'restUrl' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'selected' => $selected,
            'taxonomies' => $this->locationTaxonomies,
        ]);
        wp_add_inline_script('homlity-plugin-location', 'window.pluginInmobiliarioLocation = window.homlityPluginLocation;', 'after');

        wp_localize_script('homlity-plugin-location-map', 'homlityPluginMap', [
            'defaultLat' => $selected['latitude'] ?? 4.5709,
            'defaultLng' => $selected['longitude'] ?? -74.2973,
        ]);
        wp_add_inline_script('homlity-plugin-location-map', 'window.pluginInmobiliarioMap = window.homlityPluginMap;', 'after');

        wp_localize_script('homlity-plugin-gallery', 'homlityPluginGallery', [
            'items' => $galleryItems,
        ]);
        wp_add_inline_script('homlity-plugin-gallery', 'window.pluginInmobiliarioGallery = window.homlityPluginGallery;', 'after');

        $this->enqueueReactEditorApp((int) $postId);
    }

    private function enqueueReactEditorApp(int $postId): void
    {
        wp_enqueue_style(
            'homlity-plugin-property-editor-react',
            HOMLITY_PLUGIN_URL . 'assets/css/property-editor-react.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'homlity-plugin-property-editor-react',
            HOMLITY_PLUGIN_URL . 'assets/js/property-editor-react.js',
            ['wp-element'],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        wp_enqueue_script(
            'homlity-plugin-ckeditor5',
            'https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js',
            [],
            '39.0.1',
            true
        );

        $meta = [];
        foreach ($this->metaKeys as $key => $metaKey) {
            $meta[$key] = get_post_meta($postId, $metaKey, true);
        }

        $operationTerms = wp_get_object_terms($postId, PropertyTaxonomies::TAXONOMY_OPERATION, ['fields' => 'ids']);
        $typeTerms = wp_get_object_terms($postId, PropertyTaxonomies::TAXONOMY_TYPE, ['fields' => 'ids']);
        $featureTerms = wp_get_object_terms($postId, PropertyTaxonomies::TAXONOMY_FEATURE, ['fields' => 'ids']);
        $nearbyTerms = wp_get_object_terms($postId, PropertyTaxonomies::TAXONOMY_NEARBY, ['fields' => 'ids']);

        $location = [];
        foreach ($this->locationTaxonomies as $key => $taxonomy) {
            $terms = wp_get_object_terms($postId, $taxonomy, ['fields' => 'ids']);
            $location[$key] = $terms ? (int) $terms[0] : 0;
        }
        $galleryIds = array_filter(array_map('absint', explode(',', (string) ($meta['gallery'] ?? ''))));
        $galleryItems = [];
        foreach ($galleryIds as $gid) {
            $url = wp_get_attachment_image_url($gid, 'medium');
            if ($url) {
                $galleryItems[] = ['id' => (int) $gid, 'url' => $url];
            }
        }

        $currencies = (new CurrencyService())->supportedCurrencies();
        $users = get_users([
            'role__in' => ['administrator', 'editor', 'author'],
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name', 'user_email'],
        ]);

        wp_localize_script('homlity-plugin-property-editor-react', 'homlityPropertyEditorApp', [
            'postId' => $postId,
            'currentUserId' => get_current_user_id(),
            'reactNonce' => wp_create_nonce('homlity_react_editor'),
            'postStatus' => (string) get_post_status($postId),
            'permalink' => $postId > 0 ? get_permalink($postId) : '',
            'previewLink' => $postId > 0 ? get_preview_post_link($postId) : '',
            'deleteLink' => $postId > 0 ? get_delete_post_link($postId, '', true) : '',
            'meta' => $meta,
            'title' => get_the_title($postId),
            'excerpt' => get_post_field('post_excerpt', $postId),
            'content' => get_post_field('post_content', $postId),
            'selected' => [
                'operation' => $operationTerms ? (int) $operationTerms[0] : 0,
                'type' => $typeTerms ? (int) $typeTerms[0] : 0,
                'country' => $location['country'] ?? 0,
                'state' => $location['state'] ?? 0,
                'city' => $location['city'] ?? 0,
                'neighborhood' => $location['neighborhood'] ?? 0,
                'features' => array_map('intval', is_array($featureTerms) ? $featureTerms : []),
                'nearby' => array_map('intval', is_array($nearbyTerms) ? $nearbyTerms : []),
            ],
            'options' => [
                'operations' => $this->termsToOptions(PropertyTaxonomies::TAXONOMY_OPERATION),
                'types' => $this->termsToOptions(PropertyTaxonomies::TAXONOMY_TYPE),
                'countries' => $this->termsToOptions(PropertyTaxonomies::TAXONOMY_COUNTRY),
                'states' => $this->termsToOptions(PropertyTaxonomies::TAXONOMY_STATE),
                'cities' => $this->termsToOptions(PropertyTaxonomies::TAXONOMY_CITY),
                'neighborhoods' => $this->termsToOptions(PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD),
                'users' => array_map(static function ($user) {
                    return [
                        'id' => (int) $user->ID,
                        'label' => $user->display_name . ' (' . $user->user_email . ')',
                    ];
                }, $users),
                'currencies' => array_values($currencies),
                'gallery_items' => $galleryItems,
                'feature_groups' => $this->featureGroupsOptions(),
                'nearby_options' => $this->termsToOptions(PropertyTaxonomies::TAXONOMY_NEARBY),
            ],
            'restUrl' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'taxonomies' => $this->locationTaxonomies,
            'validation' => [
                'message' => sanitize_text_field(wp_unslash((string) ($_GET['homlity_validation_error'] ?? ''))),
                'fields' => array_values(array_filter(array_map('sanitize_key', explode(',', sanitize_text_field(wp_unslash((string) ($_GET['homlity_validation_fields'] ?? ''))))))),
            ],
        ]);
    }

    public function registerEditorRoutes(): void
    {
        register_rest_route('homlity-plugin/v1', '/property-next-code', [
            'methods' => 'GET',
            'callback' => [$this, 'restNextCode'],
            'permission_callback' => static function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'operation' => ['type' => 'integer', 'required' => false, 'default' => 0],
                'type' => ['type' => 'integer', 'required' => false, 'default' => 0],
                'post_id' => ['type' => 'integer', 'required' => false, 'default' => 0],
            ],
        ]);

        register_rest_route('homlity-plugin/v1', '/property-geocode', [
            'methods' => 'GET',
            'callback' => [$this, 'restGeocode'],
            'permission_callback' => static function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'address' => ['type' => 'string', 'required' => false, 'default' => ''],
                'country' => ['type' => 'string', 'required' => false, 'default' => ''],
                'state' => ['type' => 'string', 'required' => false, 'default' => ''],
                'city' => ['type' => 'string', 'required' => false, 'default' => ''],
                'neighborhood' => ['type' => 'string', 'required' => false, 'default' => ''],
            ],
        ]);
    }

    public function restNextCode(\WP_REST_Request $request): \WP_REST_Response
    {
        $operationTermId = absint($request->get_param('operation'));
        $typeTermId = absint($request->get_param('type'));
        $postId = absint($request->get_param('post_id'));

        return new \WP_REST_Response([
            'code' => $this->generateAutomaticCode($operationTermId, $typeTermId, $postId),
        ], 200);
    }

    public function restGeocode(\WP_REST_Request $request): \WP_REST_Response
    {
        $parts = array_filter([
            sanitize_text_field((string) $request->get_param('address')),
            sanitize_text_field((string) $request->get_param('neighborhood')),
            sanitize_text_field((string) $request->get_param('city')),
            sanitize_text_field((string) $request->get_param('state')),
            sanitize_text_field((string) $request->get_param('country')),
        ]);

        if (!$parts) {
            return new \WP_REST_Response(['lat' => null, 'lng' => null], 200);
        }

        $url = add_query_arg([
            'q' => implode(', ', $parts),
            'format' => 'json',
            'limit' => 1,
        ], 'https://nominatim.openstreetmap.org/search');

        $response = wp_remote_get($url, [
            'timeout' => 12,
            'headers' => [
                'Accept-Language' => 'es',
                'User-Agent' => 'HomlityPlugin/1.0',
            ],
        ]);

        if (is_wp_error($response)) {
            return new \WP_REST_Response(['lat' => null, 'lng' => null], 200);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode((string) $body, true);
        if (!is_array($data) || empty($data[0]['lat']) || empty($data[0]['lon'])) {
            return new \WP_REST_Response(['lat' => null, 'lng' => null], 200);
        }

        return new \WP_REST_Response([
            'lat' => (float) $data[0]['lat'],
            'lng' => (float) $data[0]['lon'],
        ], 200);
    }

    private function termsToOptions(string $taxonomy): array
    {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || !$terms) {
            return [];
        }

        return array_map(static function ($term) {
            return [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        }, $terms);
    }

    public function renderFlagsMetabox(\WP_Post $post): void
    {
        $featured = (bool) get_post_meta($post->ID, $this->metaKeys['featured'], true);
        ?>
        <p>
            <label for="property_featured">
                <input type="checkbox" id="property_featured" name="property_featured" value="1" <?php checked($featured); ?>>
                <?php esc_html_e('Propiedad destacada', 'homlity-plugin'); ?>
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
            <label for="property_agent_id"><?php esc_html_e('Usuario/asesor', 'homlity-plugin'); ?></label>
            <select id="property_agent_id" name="property_agent_id" class="widefat">
                <option value=""><?php esc_html_e('Sin asignar', 'homlity-plugin'); ?></option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($currentAgent, $user->ID); ?>>
                        <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description">
            <?php esc_html_e('Se usará la foto, correo y teléfono que tenga el usuario en su perfil.', 'homlity-plugin'); ?>
        </p>
        <?php
    }

    public function saveMeta(int $postId, \WP_Post $post): void
    {
        $hasLegacyNonces = isset(
            $_POST['property_price_nonce_field'],
            $_POST['property_details_nonce_field'],
            $_POST['property_gallery_nonce_field'],
            $_POST['property_operation_nonce_field'],
            $_POST['property_type_nonce_field']
        );
        $hasReactNonce = isset($_POST['homlity_react_editor_nonce']) &&
            wp_verify_nonce((string) $_POST['homlity_react_editor_nonce'], 'homlity_react_editor');

        if ($hasLegacyNonces) {
            if (!wp_verify_nonce((string) $_POST['property_price_nonce_field'], 'property_price_nonce') ||
                !wp_verify_nonce((string) $_POST['property_details_nonce_field'], 'property_details_nonce') ||
                !wp_verify_nonce((string) $_POST['property_gallery_nonce_field'], 'property_gallery_nonce') ||
                !wp_verify_nonce((string) $_POST['property_operation_nonce_field'], 'property_operation_nonce') ||
                !wp_verify_nonce((string) $_POST['property_type_nonce_field'], 'property_type_nonce')) {
                return;
            }
        } elseif (!$hasReactNonce) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $validationErrors = $this->validateRequiredFieldsFromRequest($postId, $post);

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
            'age' => function ($value) {
                return $this->normalizeYearBuilt($value);
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

        $agentId = absint($_POST['property_agent_id'] ?? 0);
        if ($agentId <= 0) {
            $agentId = get_current_user_id();
            if ($agentId > 0) {
                update_post_meta($postId, $this->metaKeys['agent_id'], $agentId);
            }
        }
        $this->syncAgentContactMeta($postId, $agentId);

        $this->saveGalleryFromRequest($postId);
        $this->saveFeatureTerms($postId);
        $this->saveNearbyTerms($postId);

        $this->saveLocationTerms($postId);
        $this->saveOperationTerm($postId);
        $this->saveTypeTerm($postId);
        $this->saveAutomaticCodeIfNeeded($postId);

        if (!empty($validationErrors)) {
            $this->appendValidationErrorToRedirect($validationErrors);
            $this->forceDraftIfNeeded($postId);
        }
    }

    public function renderValidationNotice(): void
    {
        if (!is_admin() || empty($_GET['homlity_validation_error'])) {
            return;
        }
        $message = sanitize_text_field(wp_unslash((string) $_GET['homlity_validation_error']));
        if ($message === '') {
            return;
        }
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private function appendValidationErrorToRedirect(array $errors): void
    {
        $fieldKeys = array_keys($errors);
        $labels = array_values($errors);
        $message = sprintf(
            __('Faltan campos obligatorios: %s', 'homlity-plugin'),
            implode(', ', $labels)
        );
        add_filter('redirect_post_location', static function ($location) use ($message, $fieldKeys) {
            return add_query_arg([
                'homlity_validation_error' => rawurlencode($message),
                'homlity_validation_fields' => implode(',', $fieldKeys),
            ], $location);
        });
    }

    private function validateRequiredFieldsFromRequest(int $postId, \WP_Post $post): array
    {
        $missing = [];

        $title = trim((string) ($_POST['post_title'] ?? $post->post_title));
        if ($title === '') {
            $missing['title'] = __('Título', 'homlity-plugin');
        }

        $operationTermId = absint($_POST['property_operation'] ?? 0);
        if ($operationTermId <= 0) {
            $missing['operation'] = __('Gestión', 'homlity-plugin');
        }

        $typeTermId = absint($_POST['property_type'] ?? 0);
        if ($typeTermId <= 0) {
            $missing['type'] = __('Tipo de inmueble', 'homlity-plugin');
        }

        if (absint($_POST['property_country'] ?? 0) <= 0) {
            $missing['country'] = __('País', 'homlity-plugin');
        }
        if (absint($_POST['property_state'] ?? 0) <= 0) {
            $missing['state'] = __('Departamento/Provincia', 'homlity-plugin');
        }
        if (absint($_POST['property_city'] ?? 0) <= 0) {
            $missing['city'] = __('Ciudad', 'homlity-plugin');
        }

        if (trim((string) ($_POST['property_address'] ?? '')) === '') {
            $missing['address'] = __('Dirección', 'homlity-plugin');
        }
        if (trim((string) ($_POST['property_latitude'] ?? '')) === '') {
            $missing['latitude'] = __('Latitud', 'homlity-plugin');
        }
        if (trim((string) ($_POST['property_longitude'] ?? '')) === '') {
            $missing['longitude'] = __('Longitud', 'homlity-plugin');
        }

        $operationSlug = '';
        if ($operationTermId > 0) {
            $operationTerm = get_term($operationTermId, PropertyTaxonomies::TAXONOMY_OPERATION);
            if ($operationTerm instanceof \WP_Term) {
                $operationSlug = strtolower((string) $operationTerm->slug);
            }
        }

        $isRent = strpos($operationSlug, 'arr') !== false || strpos($operationSlug, 'alquil') !== false || strpos($operationSlug, 'rent') !== false;
        $isSale = strpos($operationSlug, 'vent') !== false || strpos($operationSlug, 'sale') !== false;

        if ($isRent && trim((string) ($_POST['property_price_rent'] ?? '')) === '') {
            $missing['price_rent'] = __('Precio arriendo', 'homlity-plugin');
        }
        if ($isSale && trim((string) ($_POST['property_price_sale'] ?? '')) === '') {
            $missing['price_sale'] = __('Precio venta', 'homlity-plugin');
        }

        return $missing;
    }

    private function forceDraftIfNeeded(int $postId): void
    {
        static $isUpdating = false;
        if ($isUpdating) {
            return;
        }

        $status = get_post_status($postId);
        if (!in_array($status, ['publish', 'future', 'pending'], true)) {
            return;
        }

        $isUpdating = true;
        remove_action('save_post_' . self::POST_TYPE, [$this, 'saveMeta'], 10);
        wp_update_post([
            'ID' => $postId,
            'post_status' => 'draft',
        ]);
        add_action('save_post_' . self::POST_TYPE, [$this, 'saveMeta'], 10, 2);
        $isUpdating = false;
    }

    private function saveGalleryFromRequest(int $postId): void
    {
        $existingIds = array_filter(array_map('absint', array_map('trim', explode(',', (string) ($_POST['property_gallery_existing'] ?? '')))));
        $orderTokens = array_filter(array_map('sanitize_text_field', array_map('trim', explode(',', (string) ($_POST['property_gallery_order'] ?? '')))));
        $featuredToken = sanitize_text_field((string) ($_POST['property_gallery_featured'] ?? ''));
        $uploadedIds = $this->handleGalleryUploads($postId);

        $finalIds = [];
        $featuredId = 0;
        $existingMap = array_fill_keys($existingIds, true);
        $pendingQueue = $uploadedIds;

        foreach ($orderTokens as $token) {
            if (strpos($token, 'id:') === 0) {
                $id = absint(substr($token, 3));
                if ($id > 0 && isset($existingMap[$id]) && !in_array($id, $finalIds, true)) {
                    $finalIds[] = $id;
                    if ($featuredToken === $token) {
                        $featuredId = $id;
                    }
                }
                continue;
            }
            if (strpos($token, 'new:') === 0 && !empty($pendingQueue)) {
                $newId = (int) array_shift($pendingQueue);
                if ($newId > 0 && !in_array($newId, $finalIds, true)) {
                    $finalIds[] = $newId;
                    if ($featuredToken === $token) {
                        $featuredId = $newId;
                    }
                }
            }
        }

        foreach ($existingIds as $id) {
            if (!in_array($id, $finalIds, true)) {
                $finalIds[] = $id;
            }
        }
        foreach ($pendingQueue as $id) {
            if (!in_array((int) $id, $finalIds, true)) {
                $finalIds[] = (int) $id;
            }
        }

        $finalIds = array_values(array_filter(array_map('absint', $finalIds)));
        update_post_meta($postId, $this->metaKeys['gallery'], implode(',', $finalIds));

        if (!$featuredId && !empty($finalIds)) {
            $featuredId = (int) $finalIds[0];
        }
        if ($featuredId > 0) {
            set_post_thumbnail($postId, $featuredId);
        } else {
            delete_post_thumbnail($postId);
        }
    }

    private function handleGalleryUploads(int $postId): array
    {
        if (empty($_FILES['property_gallery_files']) || !is_array($_FILES['property_gallery_files'])) {
            return [];
        }

        $files = $_FILES['property_gallery_files'];
        $count = is_array($files['name'] ?? null) ? count($files['name']) : 0;
        if ($count === 0) {
            return [];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $uploadedIds = [];
        for ($i = 0; $i < $count; $i++) {
            if (empty($files['name'][$i])) {
                continue;
            }
            if (!empty($files['error'][$i]) && (int) $files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $single = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? 0,
                'size' => $files['size'][$i] ?? 0,
            ];

            $_FILES['homlity_property_gallery_single'] = $single;
            $attachmentId = media_handle_upload('homlity_property_gallery_single', $postId);
            if (!is_wp_error($attachmentId)) {
                $uploadedIds[] = (int) $attachmentId;
            }
            unset($_FILES['homlity_property_gallery_single']);
        }

        return $uploadedIds;
    }

    private function syncAgentContactMeta(int $postId, int $agentId): void
    {
        if ($agentId <= 0) {
            update_post_meta($postId, $this->metaKeys['agent_phone'], '');
            update_post_meta($postId, $this->metaKeys['agent_email'], '');
            return;
        }

        $user = get_user_by('id', $agentId);
        if (!$user instanceof \WP_User) {
            return;
        }

        $phoneCandidates = [
            get_user_meta($agentId, 'phone', true),
            get_user_meta($agentId, 'telefono', true),
            get_user_meta($agentId, 'mobile_phone', true),
            get_user_meta($agentId, 'celular', true),
            get_user_meta($agentId, 'billing_phone', true),
        ];
        $phone = '';
        foreach ($phoneCandidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                $phone = sanitize_text_field($candidate);
                break;
            }
        }

        update_post_meta($postId, $this->metaKeys['agent_phone'], $phone);
        update_post_meta($postId, $this->metaKeys['agent_email'], sanitize_email((string) $user->user_email));
    }

    private function saveFeatureTerms(int $postId): void
    {
        $raw = $_POST['property_features'] ?? [];
        $featureIds = [];
        if (is_array($raw)) {
            $featureIds = array_map('absint', $raw);
        } else {
            $featureIds = array_map('absint', array_filter(array_map('trim', explode(',', (string) $raw))));
        }
        $featureIds = array_values(array_filter($featureIds));
        wp_set_object_terms($postId, $featureIds, PropertyTaxonomies::TAXONOMY_FEATURE, false);
    }

    private function saveNearbyTerms(int $postId): void
    {
        $raw = $_POST['property_nearby'] ?? [];
        $nearbyIds = [];
        if (is_array($raw)) {
            $nearbyIds = array_map('absint', $raw);
        } else {
            $nearbyIds = array_map('absint', array_filter(array_map('trim', explode(',', (string) $raw))));
        }
        $nearbyIds = array_values(array_filter($nearbyIds));
        wp_set_object_terms($postId, $nearbyIds, PropertyTaxonomies::TAXONOMY_NEARBY, false);
    }

    private function featureGroupsOptions(): array
    {
        $terms = get_terms([
            'taxonomy' => PropertyTaxonomies::TAXONOMY_FEATURE,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || !$terms) {
            return [];
        }

        $groups = [];
        $children = [];
        foreach ($terms as $term) {
            $parentId = (int) $term->parent;
            if ($parentId === 0) {
                $groups[(int) $term->term_id] = [
                    'id' => (int) $term->term_id,
                    'name' => $term->name,
                    'items' => [],
                ];
            } else {
                $children[] = $term;
            }
        }

        foreach ($children as $term) {
            $parentId = (int) $term->parent;
            if (!isset($groups[$parentId])) {
                $groups[$parentId] = [
                    'id' => $parentId,
                    'name' => __('Otras características', 'homlity-plugin'),
                    'items' => [],
                ];
            }
            $groups[$parentId]['items'][] = [
                'id' => (int) $term->term_id,
                'name' => $term->name,
            ];
        }

        foreach ($groups as $groupId => $group) {
            if (empty($group['items'])) {
                $groups[$groupId]['items'][] = [
                    'id' => (int) $groupId,
                    'name' => $group['name'],
                ];
            }
        }

        return array_values($groups);
    }

    private function saveAutomaticCodeIfNeeded(int $postId): void
    {
        $current = trim((string) get_post_meta($postId, $this->metaKeys['code'], true));
        if ($current !== '') {
            return;
        }

        $operation = wp_get_object_terms($postId, PropertyTaxonomies::TAXONOMY_OPERATION, ['fields' => 'ids']);
        $type = wp_get_object_terms($postId, PropertyTaxonomies::TAXONOMY_TYPE, ['fields' => 'ids']);
        $operationTermId = $operation ? (int) $operation[0] : 0;
        $typeTermId = $type ? (int) $type[0] : 0;

        update_post_meta($postId, $this->metaKeys['code'], $this->generateAutomaticCode($operationTermId, $typeTermId, $postId));
    }

    private function generateAutomaticCode(int $operationTermId, int $typeTermId, int $postId = 0): string
    {
        $operation = $operationTermId ? get_term($operationTermId, PropertyTaxonomies::TAXONOMY_OPERATION) : null;
        $type = $typeTermId ? get_term($typeTermId, PropertyTaxonomies::TAXONOMY_TYPE) : null;
        $prefixes = $this->resolveCodePrefixes($operation, $type);
        $opPrefix = $prefixes['operation'];
        $typePrefix = $prefixes['type'];
        $separator = (string) \homlity_plugin_apply_filters('homlity_property_code_separator', null, '-', $operation, $type);
        $prefix = $opPrefix . $separator . $typePrefix . $separator;

        $query = new \WP_Query([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'posts_per_page' => -1,
            'post__not_in' => $postId ? [$postId] : [],
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => $this->metaKeys['code'],
                    'value' => '^' . preg_quote($prefix, '/'),
                    'compare' => 'REGEXP',
                ],
            ],
        ]);

        $max = 0;
        foreach ($query->posts as $id) {
            $code = (string) get_post_meta((int) $id, $this->metaKeys['code'], true);
            if (preg_match('/' . preg_quote($prefix, '/') . '(\\d+)/', $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $max) {
                    $max = $num;
                }
            }
        }
        wp_reset_postdata();

        $next = $max + 1;
        $padLength = max(1, (int) \homlity_plugin_apply_filters('homlity_property_code_pad_length', null, 5, $operation, $type));
        $sequence = str_pad((string) $next, $padLength, '0', STR_PAD_LEFT);

        $defaultCode = $prefix . $sequence;
        return (string) \homlity_plugin_apply_filters(
            'homlity_property_code_format',
            null,
            $defaultCode,
            [
                'operation_prefix' => $opPrefix,
                'type_prefix' => $typePrefix,
                'sequence' => $sequence,
                'separator' => $separator,
                'operation_term' => $operation,
                'type_term' => $type,
            ]
        );
    }

    private function buildCodePrefix(string $label): string
    {
        $clean = strtoupper(remove_accents(sanitize_text_field($label)));
        $clean = preg_replace('/[^A-Z0-9]/', '', $clean);
        $clean = substr((string) $clean, 0, 3);
        return str_pad($clean !== '' ? $clean : 'XXX', 3, 'X');
    }

    private function resolveCodePrefixes($operation, $type): array
    {
        $default = [
            'operation' => $this->buildCodePrefix($operation instanceof \WP_Term ? $operation->name : 'NA'),
            'type' => $this->buildCodePrefix($type instanceof \WP_Term ? $type->name : 'INM'),
        ];

        $defaultMap = [
            'operation' => [
                'venta' => 'V',
                'arriendo' => 'A',
            ],
            'type' => [
                'apartamento' => 'APT',
                'casa' => 'CAS',
                'lote' => 'LOT',
                'oficina' => 'OFI',
                'local' => 'LOC',
                'bodega' => 'BOD',
            ],
        ];

        $maps = \homlity_plugin_apply_filters('homlity_property_code_prefixes', null, $defaultMap, $operation, $type);
        $maps = is_array($maps) ? $maps : $defaultMap;

        $operationSlug = $operation instanceof \WP_Term ? sanitize_title($operation->slug) : '';
        $typeSlug = $type instanceof \WP_Term ? sanitize_title($type->slug) : '';

        if ($operationSlug !== '' && !empty($maps['operation'][$operationSlug])) {
            $default['operation'] = $this->buildCodePrefix((string) $maps['operation'][$operationSlug]);
        }
        if ($typeSlug !== '' && !empty($maps['type'][$typeSlug])) {
            $default['type'] = $this->buildCodePrefix((string) $maps['type'][$typeSlug]);
        }

        return $default;
    }

    private function normalizeYearBuilt($value): string
    {
        $currentYear = (int) gmdate('Y');
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $num = (int) preg_replace('/\\D+/', '', $raw);
        if ($num <= 0) {
            return '';
        }

        if ($num < 1900) {
            $year = max(1900, $currentYear - $num);
            return (string) $year;
        }

        if ($num > $currentYear + 1) {
            return (string) ($currentYear + 1);
        }

        return (string) $num;
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

        $settings = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
        $defaultMap = [
            'country' => $settings['default_country'] ?? 0,
            'state' => $settings['default_state'] ?? 0,
            'city' => $settings['default_city'] ?? 0,
            'neighborhood' => $settings['default_neighborhood'] ?? 0,
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
