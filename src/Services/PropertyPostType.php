<?php
// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
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
        'address_dane' => '_property_address_dane',
        'address_complement' => '_property_address_complement',
        'location_reference' => '_property_location_reference',
        'maps_url' => '_property_maps_url',
        'latitude' => '_property_latitude',
        'longitude' => '_property_longitude',
        'show_exact_address' => '_property_show_exact_address',
        'admin_included' => '_property_admin_included',
        'negotiable' => '_property_negotiable',
        'commercial_note' => '_property_commercial_note',
        'gallery' => '_property_gallery',
        'videos' => '_property_videos',
        'photos_360' => '_property_photos_360',
        'tour_360' => '_property_tour_360',
        'brochure' => '_property_brochure',
        'photo_note' => '_property_photo_note',
        'featured' => '_property_featured',
        'stratum' => '_property_stratum',
        'floor' => '_property_floor',
        'levels' => '_property_levels',
        'elevators' => '_property_elevators',
        'identification' => '_property_identification',
        'contact_name' => '_property_contact_name',
        'contact_email' => '_property_contact_email',
        'contact_phone' => '_property_contact_phone',
        'contact_whatsapp' => '_property_contact_whatsapp',
        'consignant_type' => '_property_consignant_type',
        'agent_id' => '_property_agent_id',
        'agent_name' => '_property_agent_name',
        'agent_phone' => '_property_agent_phone',
        'agent_email' => '_property_agent_email',
        'agent_external_id' => '_property_agent_external_id',
        'agent_role' => '_property_agent_role',
        'agent_photo' => '_property_agent_photo',
        'consignment_data_consent' => '_consignment_data_consent',
        'consignment_authorization_consent' => '_consignment_authorization_consent',
        'consignment_truth_declaration' => '_consignment_truth_declaration',
        'consignment_contact_consent' => '_consignment_contact_consent',
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
        add_action('added_post_meta', [$this, 'syncConditionTermFromMeta'], 10, 4);
        add_action('updated_post_meta', [$this, 'syncConditionTermFromMeta'], 10, 4);
        add_action('add_meta_boxes_' . self::POST_TYPE, [$this, 'removeLegacyMetaboxes'], 999);
        add_filter('manage_edit-' . self::POST_TYPE . '_columns', [$this, 'registerAdminColumns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'renderAdminColumn'], 10, 2);
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
            'name' => __('Propiedades', 'homlity-real-estate'),
            'singular_name' => __('Propiedad', 'homlity-real-estate'),
            'add_new' => __('Añadir nueva', 'homlity-real-estate'),
            'add_new_item' => __('Añadir propiedad', 'homlity-real-estate'),
            'edit_item' => __('Editar propiedad', 'homlity-real-estate'),
            'new_item' => __('Nueva propiedad', 'homlity-real-estate'),
            'view_item' => __('Ver propiedad', 'homlity-real-estate'),
            'search_items' => __('Buscar propiedades', 'homlity-real-estate'),
            'not_found' => __('No se encontraron propiedades', 'homlity-real-estate'),
            'menu_name' => __('Propiedades', 'homlity-real-estate'),
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
        // Meta keys stored as arrays of URLs — sanitize_text_field() strips arrays to ''
        // so these must use a null sanitize_callback (sanitization happens in saveMeta()).
        $arrayMetaKeys = [
            $this->metaKeys['videos'],
            $this->metaKeys['photos_360'],
            $this->metaKeys['tour_360'],
        ];

        foreach ($this->metaKeys as $metaKey) {
            $isBoolean  = in_array($metaKey, [
                $this->metaKeys['featured'],
                $this->metaKeys['admin_included'],
                $this->metaKeys['show_exact_address'],
                $this->metaKeys['negotiable'],
                $this->metaKeys['consignment_data_consent'],
                $this->metaKeys['consignment_authorization_consent'],
                $this->metaKeys['consignment_truth_declaration'],
                $this->metaKeys['consignment_contact_consent'],
            ], true);
            $isArrayMeta = in_array($metaKey, $arrayMetaKeys, true);

            register_post_meta(self::POST_TYPE, $metaKey, [
                'type'   => $isBoolean ? 'boolean' : 'string',
                'single' => true,
                'show_in_rest' => true,
                // Array-typed meta: do NOT use sanitize_text_field — it converts arrays to ''.
                // Sanitization for these fields is handled in saveMeta() via sanitizeUrlList().
                'sanitize_callback' => $isBoolean
                    ? 'rest_sanitize_boolean'
                    : ($isArrayMeta || $metaKey === $this->metaKeys['gallery']
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
            __('Precio y moneda', 'homlity-real-estate'),
            [$this, 'renderPricingMetabox'],
            self::POST_TYPE,
            'side'
        );

        add_meta_box(
            'property-details',
            __('Detalles', 'homlity-real-estate'),
            [$this, 'renderDetailsMetabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'property-gallery',
            __('Galería de imágenes', 'homlity-real-estate'),
            [$this, 'renderGalleryMetabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'property-operation',
            __('Gestión', 'homlity-real-estate'),
            [$this, 'renderOperationMetabox'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'property-type',
            __('Tipo de inmueble', 'homlity-real-estate'),
            [$this, 'renderTypeMetabox'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'property-agent',
            __('Asesor comercial', 'homlity-real-estate'),
            [$this, 'renderAgentMetabox'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'property-flags',
            __('Opciones', 'homlity-real-estate'),
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
            'sale' => __('Precio venta', 'homlity-real-estate'),
            'rent' => __('Precio arriendo', 'homlity-real-estate'),
            'admin' => __('Precio administración', 'homlity-real-estate'),
        ];
        ?>
        <p><?php esc_html_e('Admite cualquier moneda, escribe el código si no aparece en la lista.', 'homlity-real-estate'); ?></p>
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
                    <label for="property_currency_<?php echo esc_attr($key); ?>"><?php esc_html_e('Moneda', 'homlity-real-estate'); ?></label>
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
                            <?php esc_html_e('Administración incluida en el arriendo', 'homlity-real-estate'); ?>
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
                <button type="button" class="button" id="property_gallery_add"><?php esc_html_e('Agregar/editar galería', 'homlity-real-estate'); ?></button>
                <button type="button" class="button link-button" id="property_gallery_clear"><?php esc_html_e('Limpiar galería', 'homlity-real-estate'); ?></button>
            </p>
            <ul id="property_gallery_list" class="property-gallery-list" style="display:flex;gap:8px;flex-wrap:wrap;padding:0;">
                <?php foreach ($items as $item): ?>
                    <li data-id="<?php echo esc_attr($item['id']); ?>" style="list-style:none;cursor:move;">
                        <img src="<?php echo esc_url($item['url']); ?>" style="width:90px;height:90px;object-fit:cover;border:1px solid #ccd0d4;border-radius:3px;">
                    </li>
                <?php endforeach; ?>
            </ul>
            <input type="hidden" id="property_gallery" name="property_gallery" value="<?php echo esc_attr(implode(',', $ids)); ?>">
            <p class="description"><?php esc_html_e('Arrastra para reordenar. La primera imagen será la principal.', 'homlity-real-estate'); ?></p>
        </div>
        <?php
    }

    public function renderDetailsMetabox(\WP_Post $post): void
    {
        wp_nonce_field('property_details_nonce', 'property_details_nonce_field');
        $fields = [
            'area' => __('Área total (m²)', 'homlity-real-estate'),
            'area_lot' => __('Área de lote (m²)', 'homlity-real-estate'),
            'area_private' => __('Área privada (m²)', 'homlity-real-estate'),
            'area_built' => __('Área construida (m²)', 'homlity-real-estate'),
            'bedrooms' => __('Habitaciones', 'homlity-real-estate'),
            'bathrooms' => __('Baños', 'homlity-real-estate'),
            'parking' => __('Parqueaderos', 'homlity-real-estate'),
            'age' => __('Edad (años)', 'homlity-real-estate'),
            'code' => __('Código de la propiedad', 'homlity-real-estate'),
            'address' => __('Dirección', 'homlity-real-estate'),
            'latitude' => __('Latitud', 'homlity-real-estate'),
            'longitude' => __('Longitud', 'homlity-real-estate'),
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
                <th scope="row"><label for="property_condition"><?php esc_html_e('Estado', 'homlity-real-estate'); ?></label></th>
                <td>
                    <?php
                    $conditionAllTerms  = get_terms(['taxonomy' => PropertyTaxonomies::TAXONOMY_CONDITION, 'hide_empty' => false]);
                    $conditionCurrentSlug = (string) get_post_meta($post->ID, $this->metaKeys['condition'], true);
                    ?>
                    <select id="property_condition" name="property_condition" class="regular-text">
                        <option value=""><?php esc_html_e('Sin especificar', 'homlity-real-estate'); ?></option>
                        <?php if (!is_wp_error($conditionAllTerms)): ?>
                            <?php foreach ($conditionAllTerms as $conditionTerm): ?>
                                <option value="<?php echo esc_attr($conditionTerm->slug); ?>" <?php selected($conditionCurrentSlug, $conditionTerm->slug); ?>>
                                    <?php echo esc_html($conditionTerm->name); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Mapa', 'homlity-real-estate'); ?></th>
                <td>
                    <div id="property_map_preview" style="width:100%;height:320px;background:#eef1f5;border:1px solid #ccd0d4;border-radius:4px;"></div>
                    <p class="description"><?php esc_html_e('El mapa se actualiza según la dirección y la latitud/longitud.', 'homlity-real-estate'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="property_country"><?php esc_html_e('País', 'homlity-real-estate'); ?></label></th>
                <td>
                    <select id="property_country" name="property_country" class="property-location-select" data-taxonomy="<?php echo esc_attr($this->locationTaxonomies['country']); ?>">
                        <option value=""><?php esc_html_e('Selecciona país', 'homlity-real-estate'); ?></option>
                        <?php echo $this->renderLocationOptions($post->ID, 'country'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="property_state"><?php esc_html_e('Departamento / Provincia', 'homlity-real-estate'); ?></label></th>
                <td>
                    <select id="property_state" name="property_state" class="property-location-select" data-taxonomy="<?php echo esc_attr($this->locationTaxonomies['state']); ?>" data-parent="country">
                        <option value=""><?php esc_html_e('Selecciona departamento / provincia', 'homlity-real-estate'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="property_city"><?php esc_html_e('Ciudad / Municipio', 'homlity-real-estate'); ?></label></th>
                <td>
                    <select id="property_city" name="property_city" class="property-location-select" data-taxonomy="<?php echo esc_attr($this->locationTaxonomies['city']); ?>" data-parent="state">
                        <option value=""><?php esc_html_e('Selecciona ciudad / municipio', 'homlity-real-estate'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="property_neighborhood"><?php esc_html_e('Barrio', 'homlity-real-estate'); ?></label></th>
                <td>
                    <select id="property_neighborhood" name="property_neighborhood" class="property-location-select" data-taxonomy="<?php echo esc_attr($this->locationTaxonomies['neighborhood']); ?>" data-parent="city">
                        <option value=""><?php esc_html_e('Selecciona barrio', 'homlity-real-estate'); ?></option>
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
            <label for="property_operation"><?php esc_html_e('Selecciona la gestión principal', 'homlity-real-estate'); ?></label>
            <select id="property_operation" name="property_operation" class="widefat">
                <option value=""><?php esc_html_e('Elige una gestión', 'homlity-real-estate'); ?></option>
                <?php foreach ($terms as $term): ?>
                    <option
                        value="<?php echo esc_attr($term->term_id); ?>"
                        data-slug="<?php echo esc_attr($term->slug); ?>"
                        data-base-id="<?php echo esc_attr((string) PropertyTaxonomies::baseOperationIdForTerm($term)); ?>"
                        <?php selected($current, $term->term_id); ?>
                    >
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
            <label for="property_type"><?php esc_html_e('Selecciona el tipo de inmueble', 'homlity-real-estate'); ?></label>
            <select id="property_type" name="property_type" class="widefat">
                <option value=""><?php esc_html_e('Elige un tipo', 'homlity-real-estate'); ?></option>
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

        // Some admin scripts (core/third-party) call $.fn.pointer on post edit.
        // Ensure pointer assets are present to avoid runtime errors that can
        // interrupt the React property editor boot.
        wp_enqueue_style('wp-pointer');
        wp_enqueue_script('wp-pointer');

        wp_enqueue_script(
            'homlity-real-estate-location',
            HOMLITY_PLUGIN_URL . 'assets/js/location-admin.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');

        $postId = isset($GLOBALS['post']) && $GLOBALS['post'] instanceof \WP_Post ? $GLOBALS['post']->ID : 0;
        $gallery = $postId ? get_post_meta($postId, $this->metaKeys['gallery'], true) : '';
        $galleryRaw = is_array($gallery) ? $gallery : explode(',', (string) $gallery);
        $galleryIds = array_filter(array_map('absint', $galleryRaw));

        $galleryItems = [];
        foreach ($galleryIds as $gid) {
            $url = wp_get_attachment_image_url($gid, 'thumbnail');
            if ($url) {
                $galleryItems[] = ['id' => $gid, 'url' => $url];
            }
        }

        // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent
        wp_enqueue_style(
            'homlity-real-estate-leaflet',
            HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.min.css',
            [],
            '1.9.4'
        );

        // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent
        wp_enqueue_script(
            'homlity-real-estate-leaflet',
            HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.min.js',
            [],
            '1.9.4',
            true
        );

        wp_enqueue_script(
            'homlity-real-estate-location-map',
            HOMLITY_PLUGIN_URL . 'assets/js/location-map.js',
            ['homlity-real-estate-leaflet'],
            HOMLITY_PLUGIN_VERSION,
            true
        );
        wp_localize_script('homlity-real-estate-location-map', 'homlityLeafletAssets', [
            'iconUrl' => file_exists(HOMLITY_PLUGIN_PATH . 'assets/vendor/leaflet/images/marker-icon.png')
                ? HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/images/marker-icon.png'
                : '',
            'iconRetinaUrl' => file_exists(HOMLITY_PLUGIN_PATH . 'assets/vendor/leaflet/images/marker-icon-2x.png')
                ? HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/images/marker-icon-2x.png'
                : '',
            'shadowUrl' => file_exists(HOMLITY_PLUGIN_PATH . 'assets/vendor/leaflet/images/marker-shadow.png')
                ? HOMLITY_PLUGIN_URL . 'assets/vendor/leaflet/images/marker-shadow.png'
                : '',
        ]);

        wp_enqueue_script(
            'homlity-real-estate-gallery',
            HOMLITY_PLUGIN_URL . 'assets/js/gallery-admin.js',
            ['jquery', 'jquery-ui-sortable'],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        wp_enqueue_script(
            'homlity-real-estate-pricing',
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

        wp_localize_script('homlity-real-estate-location', 'homlityPluginLocation', [
            'restUrl' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'selected' => $selected,
            'taxonomies' => $this->locationTaxonomies,
        ]);
        wp_add_inline_script('homlity-real-estate-location', 'window.pluginInmobiliarioLocation = window.homlityPluginLocation;', 'after');

        wp_localize_script('homlity-real-estate-location-map', 'homlityPluginMap', [
            'defaultLat' => $selected['latitude'] ?? 4.5709,
            'defaultLng' => $selected['longitude'] ?? -74.2973,
        ]);
        wp_add_inline_script('homlity-real-estate-location-map', 'window.pluginInmobiliarioMap = window.homlityPluginMap;', 'after');

        wp_localize_script('homlity-real-estate-gallery', 'homlityPluginGallery', [
            'items' => $galleryItems,
        ]);
        wp_add_inline_script('homlity-real-estate-gallery', 'window.pluginInmobiliarioGallery = window.homlityPluginGallery;', 'after');

        $this->enqueueReactEditorApp((int) $postId);
    }

    private function enqueueReactEditorApp(int $postId): void
    {
        wp_enqueue_style(
            'homlity-real-estate-property-editor-react',
            HOMLITY_PLUGIN_URL . 'assets/css/property-editor-react.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'homlity-real-estate-property-editor-react',
            HOMLITY_PLUGIN_URL . 'assets/js/property-editor-react.js',
            ['wp-element'],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        wp_enqueue_script(
            'homlity-real-estate-ckeditor5',
            'https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js',
            [],
            '39.0.1',
            true
        );

        $meta = [];
        foreach ($this->metaKeys as $key => $metaKey) {
            $meta[$key] = get_post_meta($postId, $metaKey, true);
        }
        $isSyncedProperty = $this->isSyncedProperty($postId);
        $meta = $this->normalizeMediaMetaForEditor($postId, $meta);

        $operationTerms  = wp_get_object_terms($postId, PropertyTaxonomies::TAXONOMY_OPERATION, ['fields' => 'ids']);
        $typeTerms       = wp_get_object_terms($postId, PropertyTaxonomies::TAXONOMY_TYPE, ['fields' => 'ids']);
        $featureTerms    = wp_get_object_terms($postId, PropertyTaxonomies::TAXONOMY_FEATURE, ['fields' => 'ids']);
        $nearbyTerms     = wp_get_object_terms($postId, PropertyTaxonomies::TAXONOMY_NEARBY, ['fields' => 'ids']);
        $tagTerms        = wp_get_object_terms($postId, PropertyTaxonomies::TAXONOMY_TAG, ['fields' => 'ids']);
        $conditionTerms  = wp_get_object_terms($postId, PropertyTaxonomies::TAXONOMY_CONDITION, ['fields' => 'slugs']);

        $location = [];
        foreach ($this->locationTaxonomies as $key => $taxonomy) {
            $terms = wp_get_object_terms($postId, $taxonomy, ['fields' => 'ids']);
            $location[$key] = $terms ? (int) $terms[0] : 0;
        }
        $galleryItems = [];
        $rawGallery = $meta['gallery'] ?? '';

        if (is_array($rawGallery)) {
            foreach ($rawGallery as $entry) {
                if (is_numeric($entry)) {
                    $gid = absint($entry);
                    if ($gid <= 0) {
                        continue;
                    }
                    $url = wp_get_attachment_image_url($gid, 'medium');
                    if ($url) {
                        $galleryItems[] = ['id' => $gid, 'url' => $url];
                    }
                    continue;
                }

                $url = esc_url_raw((string) $entry);
                if ($url !== '') {
                    $galleryItems[] = ['id' => 0, 'url' => $url];
                }
            }
        } else {
            $galleryIds = array_filter(array_map('absint', explode(',', (string) $rawGallery)));
            foreach ($galleryIds as $gid) {
                $url = wp_get_attachment_image_url($gid, 'medium');
                if ($url) {
                    $galleryItems[] = ['id' => (int) $gid, 'url' => $url];
                }
            }
        }

        $currencies = (new CurrencyService())->supportedCurrencies();
        $users = get_users([
            'role__in' => [
                CapabilityService::ROLE_ASSESSOR,
                CapabilityService::LEGACY_ROLE_ASSESSOR,
                'administrator',
            ],
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name', 'user_email'],
        ]);

        wp_localize_script('homlity-real-estate-property-editor-react', 'homlityPropertyEditorApp', [
            'postId' => $postId,
            'currentUserId' => get_current_user_id(),
            'reactNonce' => wp_create_nonce('homlity_react_editor'),
            'postStatus' => (string) get_post_status($postId),
            'permalink' => $postId > 0 ? get_permalink($postId) : '',
            'previewLink' => $postId > 0 ? get_preview_post_link($postId) : '',
            'deleteLink' => $postId > 0 ? get_delete_post_link($postId, '', true) : '',
            'bannerUrl' => HOMLITY_PLUGIN_URL . 'assets/img/homlity.png',
            'registrationUrl' => 'https://homi.homlity.com/registro-inmobiliaria-gratis',
            'directoryBannerUrl' => HOMLITY_PLUGIN_URL . 'assets/img/directorio-inmobiliario.png',
            'directoryRegistrationUrl' => 'https://homi.homlity.com/register',
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
                'tags' => array_map('intval', is_array($tagTerms) ? $tagTerms : []),
                'condition' => !is_wp_error($conditionTerms) && !empty($conditionTerms) ? (string) $conditionTerms[0] : '',
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
                'tag_options' => $this->termsToOptions(PropertyTaxonomies::TAXONOMY_TAG),
                'condition_options' => array_values(array_map(static function ($term) {
                    return ['id' => $term->slug, 'label' => $term->name];
                }, array_filter(
                    get_terms(['taxonomy' => PropertyTaxonomies::TAXONOMY_CONDITION, 'hide_empty' => false]) ?: [],
                    static fn ($t) => $t instanceof \WP_Term
                ))),
            ],
            'restUrl' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'taxonomies' => $this->locationTaxonomies,
            'manageTagsUrl' => admin_url('edit-tags.php?taxonomy=' . PropertyTaxonomies::TAXONOMY_TAG . '&post_type=' . self::POST_TYPE),
            'validation' => [
                'message' => sanitize_text_field(wp_unslash((string) ($_GET['homlity_validation_error'] ?? ''))),
                'fields' => array_values(array_filter(array_map('sanitize_key', explode(',', sanitize_text_field(wp_unslash((string) ($_GET['homlity_validation_fields'] ?? ''))))))),
            ],
            'visitStats' => class_exists(\Homlity\PluginInmobiliario\Services\PropertyVisitTrackingService::class)
                ? \Homlity\PluginInmobiliario\Services\PropertyVisitTrackingService::getReportForProperty($postId)
                : [
                    'totals' => ['all' => 0, 'today' => 0, 'last7' => 0, 'last30' => 0, 'unique_visitors' => 0],
                    'daily' => [],
                    'recent' => [],
                ],
            'sync' => [
                'isSynced' => $isSyncedProperty,
                'syncId' => $syncId,
                'source' => $syncSource,
            ],
        ]);
    }

    public function registerEditorRoutes(): void
    {
        register_rest_route('homlity-real-estate/v1', '/property-next-code', [
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

        register_rest_route('homlity-real-estate/v1', '/property-geocode', [
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

        register_rest_route('homlity-real-estate/v1', '/property-media-sync', [
            'methods' => 'GET',
            'callback' => [$this, 'restPropertyMediaSync'],
            'permission_callback' => '__return_true',
            'args' => [
                'post_id' => ['type' => 'integer', 'required' => true],
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

    public function restPropertyMediaSync(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = absint($request->get_param('post_id'));
        if ($postId <= 0 || get_post_type($postId) !== self::POST_TYPE) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'invalid_post'], 400);
        }

        $meta = [];
        foreach ($this->metaKeys as $key => $metaKey) {
            $meta[$key] = get_post_meta($postId, $metaKey, true);
        }
        $meta = $this->normalizeMediaMetaForEditor($postId, $meta);

        return new \WP_REST_Response([
            'ok' => true,
            'videos' => $meta['videos'] ?? [],
            'photos_360' => $meta['photos_360'] ?? [],
            'tour_360' => $meta['tour_360'] ?? [],
            'brochure' => $meta['brochure'] ?? '',
        ], 200);
    }

    /**
     * Normaliza multimedia para que el editor React siempre reciba formato consistente.
     *
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function normalizeMediaMetaForEditor(int $postId, array $meta): array
    {
        // Raw values from get_post_meta():
        //   ''  (empty string) → meta key never existed in DB → eligible for sync-payload fallback
        //   []  (empty array)  → user explicitly cleared the field → respect it, skip fallback
        //   [urls…]            → has a saved value → use it, skip fallback
        $videosRaw    = $meta['videos']     ?? '';
        $photos360Raw = $meta['photos_360'] ?? '';
        $tour360Raw   = $meta['tour_360']   ?? '';
        $brochureRaw  = $meta['brochure']   ?? '';

        $videos    = $this->extractUrlList($videosRaw);
        $photos360 = $this->extractUrlList($photos360Raw);
        $tour360   = $this->extractUrlList($tour360Raw);
        $brochure  = $this->extractSingleUrl($brochureRaw);

        // Optimization: if all four fields already have data from primary meta, no fallback needed.
        $needsFallback = empty($videos) || empty($photos360) || empty($tour360) || $brochure === '';
        if (!$needsFallback) {
            $meta['videos']     = $videos;
            $meta['photos_360'] = $photos360;
            $meta['tour_360']   = $tour360;
            $meta['brochure']   = $brochure;
            return $meta;
        }

        // Determine which empty fields are eligible for sync-payload fallback.
        //
        // For array-typed fields (videos, photos_360, tour_360):
        //   get_post_meta returns '' when the DB row never existed, and [] when it was saved empty.
        //   '' vs [] is unambiguous — we can skip fallback whenever the raw value is not ''.
        //
        // For brochure (stored as a plain string):
        //   get_post_meta returns '' both when the row is absent AND when it was saved as ''.
        //   '' alone is therefore ambiguous, so we call metadata_exists() to check the DB row.
        $videosFallbackOk    = $videosRaw === '' && empty($videos);
        $photos360FallbackOk = $photos360Raw === '' && empty($photos360);
        $tour360FallbackOk   = $tour360Raw === '' && empty($tour360);
        $brochureFallbackOk  = $brochure === ''
            && !metadata_exists('post', $postId, '_property_brochure');

        if (!$videosFallbackOk && !$photos360FallbackOk && !$tour360FallbackOk && !$brochureFallbackOk) {
            // All empty fields were explicitly cleared — no payload lookup needed.
            $meta['videos']     = $videos;
            $meta['photos_360'] = $photos360;
            $meta['tour_360']   = $tour360;
            $meta['brochure']   = $brochure;
            return $meta;
        }

        // One or more fields were never saved — check sync payload and dedicated sync meta keys.
        $rawPayload = get_post_meta($postId, '_property_sync_payload', true);
        if (!is_string($rawPayload) || trim($rawPayload) === '') {
            $rawPayload = get_post_meta($postId, '_homlity_sync_payload', true);
        }

        $payload = [];
        if (is_string($rawPayload) && trim($rawPayload) !== '') {
            $decoded = json_decode($rawPayload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $property = is_array($payload['property'] ?? null) ? $payload['property'] : $payload;
        $media    = is_array($property['media']   ?? null) ? $property['media']   : [];

        if ($videosFallbackOk) {
            $videos = $this->extractUrlList($media['videos'] ?? ($property['videos'] ?? null));
        }
        if ($photos360FallbackOk) {
            $photos360 = $this->extractUrlList($media['photos_360'] ?? null);
        }
        if ($tour360FallbackOk) {
            $tour360 = $this->extractUrlList($media['tour_360'] ?? null);
        }
        if ($brochureFallbackOk) {
            $brochure = $this->extractSingleUrl($media['brochure'] ?? ($property['brochure'] ?? null));
        }

        // Direct fallback from plugin-homlity-sync dedicated media metas.
        if ($videosFallbackOk && empty($videos)) {
            $videos = $this->extractUrlList(get_post_meta($postId, '_homlity_sync_media_videos', true));
        }
        if ($photos360FallbackOk && empty($photos360)) {
            $photos360 = $this->extractUrlList(get_post_meta($postId, '_homlity_sync_media_photos_360', true));
        }
        if ($tour360FallbackOk && empty($tour360)) {
            $tour360 = $this->extractUrlList(get_post_meta($postId, '_homlity_sync_media_tour_360', true));
        }
        if ($brochureFallbackOk && $brochure === '') {
            $brochure = $this->extractSingleUrl(get_post_meta($postId, '_homlity_sync_media_brochure', true));
        }

        // Final fallback: query homlity-sync API when all eligible fields are still empty.
        $anyStillMissing = ($videosFallbackOk && empty($videos))
            || ($photos360FallbackOk && empty($photos360))
            || ($tour360FallbackOk && empty($tour360))
            || ($brochureFallbackOk && $brochure === '');

        if ($anyStillMissing) {
            $externalId = sanitize_text_field((string) get_post_meta($postId, '_homlity_sync_id', true));
            if ($externalId !== '') {
                $remoteMedia = $this->fetchMediaFromHomlitySync($externalId);
                if ($videosFallbackOk && empty($videos) && !empty($remoteMedia['videos'])) {
                    $videos = $this->extractUrlList($remoteMedia['videos']);
                }
                if ($photos360FallbackOk && empty($photos360) && !empty($remoteMedia['photos_360'])) {
                    $photos360 = $this->extractUrlList($remoteMedia['photos_360']);
                }
                if ($tour360FallbackOk && empty($tour360) && !empty($remoteMedia['tour_360'])) {
                    $tour360 = $this->extractUrlList($remoteMedia['tour_360']);
                }
                if ($brochureFallbackOk && $brochure === '' && !empty($remoteMedia['brochure'])) {
                    $brochure = $this->extractSingleUrl($remoteMedia['brochure']);
                }
            }
        }

        $meta['videos']     = $videos;
        $meta['photos_360'] = $photos360;
        $meta['tour_360']   = $tour360;
        $meta['brochure']   = $brochure;

        return $meta;
    }

    /**
     * @return array<int,string>
     */
    private function extractUrlList(mixed $value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            if ($trimmed[0] === '[' || $trimmed[0] === '{') {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return $this->extractUrlList($decoded);
                }
            }

            $parts = preg_split('/[\r\n,;|]+/', $trimmed) ?: [];
            $urls = [];
            foreach ($parts as $part) {
                $url = esc_url_raw(trim((string) $part));
                if ($url !== '') {
                    $urls[] = $url;
                }
            }
            return array_values(array_unique($urls));
        }

        if (is_array($value)) {
            $urls = [];
            foreach ($value as $item) {
                if (is_string($item)) {
                    $url = esc_url_raw(trim($item));
                    if ($url !== '') {
                        $urls[] = $url;
                    }
                    continue;
                }
                if (is_array($item)) {
                    $url = esc_url_raw(trim((string) ($item['url'] ?? '')));
                    if ($url !== '') {
                        $urls[] = $url;
                    }
                }
            }
            return array_values(array_unique($urls));
        }

        return [];
    }

    private function extractSingleUrl(mixed $value): string
    {
        if (is_array($value)) {
            if (isset($value['url']) && is_string($value['url'])) {
                return esc_url_raw(trim($value['url']));
            }
            $urls = $this->extractUrlList($value);
            return $urls[0] ?? '';
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return '';
            }
            if ($trimmed[0] === '{' || $trimmed[0] === '[') {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return $this->extractSingleUrl($decoded);
                }
            }
            $clean = esc_url_raw($trimmed);
            if ($clean !== '') {
                return $clean;
            }
            preg_match('/https?:\/\/[^\s"]+/i', $trimmed, $matches);
            return esc_url_raw($matches[0] ?? '');
        }

        return '';
    }

    /**
     * @return array{videos?: mixed, photos_360?: mixed, tour_360?: mixed, brochure?: mixed}
     */
    private function fetchMediaFromHomlitySync(string $externalId): array
    {
        if (!class_exists(\HomlitySync\Api\HomlityApiClient::class)) {
            return [];
        }

        try {
            $client = new \HomlitySync\Api\HomlityApiClient();
            $response = $client->getProperty($externalId);
            if (!($response->success ?? false)) {
                return [];
            }

            $data = is_array($response->data ?? null) ? $response->data : [];
            $property = $data['data']['data'] ?? $data['data'] ?? $data['item'] ?? $data;
            if (!is_array($property)) {
                return [];
            }

            $media = is_array($property['media'] ?? null) ? $property['media'] : [];
            $videos = $media['videos'] ?? ($property['videos'] ?? []);
            $photos360 = $media['photos_360'] ?? [];
            $tour360 = $media['tour_360'] ?? [];
            $brochure = $media['brochure'] ?? ($property['brochure'] ?? '');

            return [
                'videos' => $videos,
                'photos_360' => $photos360,
                'tour_360' => $tour360,
                'brochure' => $brochure,
            ];
        } catch (\Throwable $e) {
            return [];
        }
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
                'base_id' => $term->taxonomy === PropertyTaxonomies::TAXONOMY_OPERATION
                    ? PropertyTaxonomies::baseOperationIdForTerm($term)
                    : 0,
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
                <?php esc_html_e('Propiedad destacada', 'homlity-real-estate'); ?>
            </label>
        </p>
        <?php
    }

    public function renderAgentMetabox(\WP_Post $post): void
    {
        $currentAgent = (int) get_post_meta($post->ID, $this->metaKeys['agent_id'], true);

        $users = get_users([
            'role__in' => [
                CapabilityService::ROLE_ASSESSOR,
                CapabilityService::LEGACY_ROLE_ASSESSOR,
                'administrator',
            ],
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name', 'user_email'],
        ]);
        ?>
        <p>
            <label for="property_agent_id"><?php esc_html_e('Usuario/asesor', 'homlity-real-estate'); ?></label>
            <select id="property_agent_id" name="property_agent_id" class="widefat">
                <option value=""><?php esc_html_e('Sin asignar', 'homlity-real-estate'); ?></option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($currentAgent, $user->ID); ?>>
                        <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description">
            <?php esc_html_e('Se usará la foto, correo y teléfono que tenga el usuario en su perfil.', 'homlity-real-estate'); ?>
        </p>
        <?php
    }

    public function saveMeta(int $postId, \WP_Post $post): void
    {
        // React editor path: verify nonce first, fail fast if missing or invalid.
        if (isset($_POST['homlity_react_editor_nonce'])) {
            if (!wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['homlity_react_editor_nonce'])),
                'homlity_react_editor'
            )) {
                return;
            }
        } elseif (
            isset(
                $_POST['property_price_nonce_field'],
                $_POST['property_details_nonce_field'],
                $_POST['property_gallery_nonce_field'],
                $_POST['property_operation_nonce_field'],
                $_POST['property_type_nonce_field']
            )
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['property_price_nonce_field'])), 'property_price_nonce')
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['property_details_nonce_field'])), 'property_details_nonce')
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['property_gallery_nonce_field'])), 'property_gallery_nonce')
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['property_operation_nonce_field'])), 'property_operation_nonce')
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['property_type_nonce_field'])), 'property_type_nonce')
        ) {
            // Legacy nonces verified — continue below.
        } else {
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
            'videos' => function ($value) {
                return $this->sanitizeSingleVideoUrlList($value);
            },
            'photos_360' => function ($value) {
                return $this->sanitizeUrlList($value);
            },
            'tour_360' => function ($value) {
                return $this->sanitizeUrlList($value);
            },
            'brochure' => static function ($value) {
                return esc_url_raw((string) $value);
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

        $isSyncedProperty = $this->isSyncedProperty($postId);

        foreach ($this->metaKeys as $key => $metaKey) {
            // wp_unslash() is required because WordPress applies addslashes() (wp_magic_quotes)
            // to all $_POST values. Without it, JSON strings sent from JavaScript are corrupted:
            // '["https://..."]' becomes '[\"https://...\"]', which json_decode() cannot parse.
            $rawPost = $_POST['property_' . $key] ?? null;
            $value   = $rawPost !== null ? wp_unslash($rawPost) : null;
            // For checkboxes/booleans ensure a default value is stored.
            if (in_array($key, ['featured', 'admin_included'], true) && $value === null) {
                $value = 0;
            } elseif ($value === null) {
                continue;
            }
            if ($key === 'code' && $isSyncedProperty) {
                continue;
            }
            $value = call_user_func($sanitizers[$key], $value);
            update_post_meta($postId, $metaKey, $value);
        }

        $agentId = absint($_POST['property_agent_id'] ?? 0);
        $agent = $agentId > 0 ? get_user_by('id', $agentId) : false;
        $allowedAgentRoles = [
            CapabilityService::ROLE_ASSESSOR,
            CapabilityService::LEGACY_ROLE_ASSESSOR,
            'administrator',
        ];
        if (
            !$agent instanceof \WP_User
            || !array_intersect($allowedAgentRoles, $agent->roles)
        ) {
            $agentId = 0;
        }

        if ($agentId > 0) {
            update_post_meta($postId, $this->metaKeys['agent_id'], $agentId);
        } else {
            delete_post_meta($postId, $this->metaKeys['agent_id']);
        }
        $this->syncAgentContactMeta($postId, $agentId);

        $this->saveGalleryFromRequest($postId);
        $this->saveFeatureTerms($postId);
        $this->saveNearbyTerms($postId);
        $this->saveTagTerms($postId);

        $this->saveLocationTerms($postId);
        $this->saveOperationTerm($postId);
        $this->saveTypeTerm($postId);
        $this->saveAutomaticCodeIfNeeded($postId);
        $this->maybeNormalizePublishedSlug($postId);

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
            /* translators: %s: comma-separated list of missing required field labels */
            __('Faltan campos obligatorios: %s', 'homlity-real-estate'),
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

        $title = trim(sanitize_text_field(wp_unslash((string) ($_POST['post_title'] ?? $post->post_title))));
        if ($title === '') {
            $missing['title'] = __('Título', 'homlity-real-estate');
        }

        $operationTermId = absint($_POST['property_operation'] ?? 0);
        if ($operationTermId <= 0) {
            $missing['operation'] = __('Gestión', 'homlity-real-estate');
        }

        $typeTermId = absint($_POST['property_type'] ?? 0);
        if ($typeTermId <= 0) {
            $missing['type'] = __('Tipo de inmueble', 'homlity-real-estate');
        }

        if (absint($_POST['property_country'] ?? 0) <= 0) {
            $missing['country'] = __('País', 'homlity-real-estate');
        }
        if (absint($_POST['property_state'] ?? 0) <= 0) {
            $missing['state'] = __('Departamento/Provincia', 'homlity-real-estate');
        }
        if (absint($_POST['property_city'] ?? 0) <= 0) {
            $missing['city'] = __('Ciudad', 'homlity-real-estate');
        }

        if (trim((string) ($_POST['property_address'] ?? '')) === '') {
            $missing['address'] = __('Dirección', 'homlity-real-estate');
        }
        if (trim((string) ($_POST['property_latitude'] ?? '')) === '') {
            $missing['latitude'] = __('Latitud', 'homlity-real-estate');
        }
        if (trim((string) ($_POST['property_longitude'] ?? '')) === '') {
            $missing['longitude'] = __('Longitud', 'homlity-real-estate');
        }

        $operationSlug = '';
        if ($operationTermId > 0) {
            $operationTerm = get_term($operationTermId, PropertyTaxonomies::TAXONOMY_OPERATION);
            if ($operationTerm instanceof \WP_Term) {
                $operationSlug = strtolower((string) $operationTerm->slug);
            }
        }

        $baseOperationId = isset($operationTerm) && $operationTerm instanceof \WP_Term
            ? PropertyTaxonomies::baseOperationIdForTerm($operationTerm)
            : 0;
        $isRent = in_array($baseOperationId, [1, 3], true)
            || strpos($operationSlug, 'arr') !== false
            || strpos($operationSlug, 'alquil') !== false
            || strpos($operationSlug, 'rent') !== false;
        $isSale = in_array($baseOperationId, [2, 3], true)
            || strpos($operationSlug, 'vent') !== false
            || strpos($operationSlug, 'sale') !== false;

        if ($isRent && trim((string) ($_POST['property_price_rent'] ?? '')) === '') {
            $missing['price_rent'] = __('Precio arriendo', 'homlity-real-estate');
        }
        if ($isSale && trim((string) ($_POST['property_price_sale'] ?? '')) === '') {
            $missing['price_sale'] = __('Precio venta', 'homlity-real-estate');
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

    private function saveTagTerms(int $postId): void
    {
        $raw = $_POST['property_tags'] ?? [];
        $tagIds = [];
        if (is_array($raw)) {
            $tagIds = array_map('absint', $raw);
        } else {
            $tagIds = array_map('absint', array_filter(array_map('trim', explode(',', (string) $raw))));
        }

        $tagIds = array_values(array_filter($tagIds));
        wp_set_object_terms($postId, $tagIds, PropertyTaxonomies::TAXONOMY_TAG, false);
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function sanitizeUrlList($value): array
    {
        $items = [];
        if (is_array($value)) {
            $items = $value;
        } else {
            // wp_unslash() strips the addslashes() that WordPress applies via wp_magic_quotes()
            // before every request. Without this, JSON sent from JavaScript is corrupted and
            // json_decode() returns null, causing URLs to be silently dropped.
            $raw = wp_unslash((string) $value);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = preg_split('/[\r\n,]+/', $raw) ?: [];
            }
        }

        $urls = [];
        foreach ($items as $item) {
            $url = esc_url_raw(trim((string) $item));
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function sanitizeSingleVideoUrlList($value): array
    {
        $urls = $this->sanitizeUrlList($value);
        if (empty($urls)) {
            return [];
        }

        return [reset($urls)];
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
                    'name' => __('Otras características', 'homlity-real-estate'),
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
        // Never auto-generate the code for CRM/webhook-synced properties.
        // For synced records, the code must come from the integration payload.
        if ($this->isSyncedProperty($postId)) {
            return;
        }

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

    private function maybeNormalizePublishedSlug(int $postId): void
    {
        $post = get_post($postId);
        if (!$post instanceof \WP_Post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        $title = trim((string) $post->post_title);
        if ($title === '') {
            return;
        }

        $currentSlug = sanitize_title((string) $post->post_name);
        if (!$this->shouldReplacePlaceholderSlug($currentSlug)) {
            return;
        }

        $desiredSlug = sanitize_title($title);
        if ($desiredSlug === '') {
            return;
        }

        $uniqueSlug = wp_unique_post_slug(
            $desiredSlug,
            $postId,
            $post->post_status,
            $post->post_type,
            (int) $post->post_parent
        );

        if ($uniqueSlug === '' || $uniqueSlug === $post->post_name) {
            return;
        }

        remove_action('save_post_' . self::POST_TYPE, [$this, 'saveMeta'], 10);
        wp_update_post([
            'ID' => $postId,
            'post_name' => $uniqueSlug,
        ]);
        add_action('save_post_' . self::POST_TYPE, [$this, 'saveMeta'], 10, 2);
    }

    private function shouldReplacePlaceholderSlug(string $slug): bool
    {
        if ($slug === '') {
            return true;
        }

        $placeholders = [
            'auto-draft',
            'borrador-automatico',
        ];

        if (in_array($slug, $placeholders, true)) {
            return true;
        }

        return (bool) preg_match('/^(auto-draft|borrador-automatico)(-\d+)?$/', $slug);
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

    public function syncConditionTermFromMeta(int $metaId, int $postId, string $metaKey, $metaValue): void
    {
        if ($metaKey !== $this->metaKeys['condition']) {
            return;
        }
        if (get_post_type($postId) !== self::POST_TYPE) {
            return;
        }

        $value = sanitize_text_field((string) $metaValue);
        if ($value === '') {
            wp_set_object_terms($postId, [], PropertyTaxonomies::TAXONOMY_CONDITION, false);
            return;
        }

        $slug = sanitize_title($value);
        $term = term_exists($slug, PropertyTaxonomies::TAXONOMY_CONDITION);
        if (!$term) {
            $term = wp_insert_term($value, PropertyTaxonomies::TAXONOMY_CONDITION, ['slug' => $slug]);
        }
        if (!is_wp_error($term)) {
            $termId = is_array($term) ? (int) $term['term_id'] : (int) $term;
            wp_set_object_terms($postId, [$termId], PropertyTaxonomies::TAXONOMY_CONDITION, false);
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

    public function registerAdminColumns(array $columns): array
    {
        return [
            'cb' => $columns['cb'] ?? '<input type="checkbox" />',
            'title' => __('Nombre', 'homlity-real-estate'),
            'property_code' => __('Código', 'homlity-real-estate'),
            'property_operation' => __('Gestión', 'homlity-real-estate'),
            'property_type' => __('Tipo de inmueble', 'homlity-real-estate'),
            'property_location' => __('Ubicación', 'homlity-real-estate'),
            'property_operation_value' => __('Valor gestión', 'homlity-real-estate'),
            'property_last_sync' => __('Última sincronización', 'homlity-real-estate'),
            'date' => $columns['date'] ?? __('Fecha', 'homlity-real-estate'),
        ];
    }

    public function renderAdminColumn(string $column, int $postId): void
    {
        if ($column === 'property_code') {
            $code = trim((string) get_post_meta($postId, $this->metaKeys['code'], true));
            echo $code !== '' ? esc_html($code) : '—';
            return;
        }

        if ($column === 'property_operation') {
            $terms = wp_get_post_terms($postId, PropertyTaxonomies::TAXONOMY_OPERATION);
            echo !is_wp_error($terms) && !empty($terms) ? esc_html($terms[0]->name) : '—';
            return;
        }

        if ($column === 'property_type') {
            $terms = wp_get_post_terms($postId, PropertyTaxonomies::TAXONOMY_TYPE);
            echo !is_wp_error($terms) && !empty($terms) ? esc_html($terms[0]->name) : '—';
            return;
        }

        if ($column === 'property_location') {
            $parts = [];
            $taxes = [
                PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
                PropertyTaxonomies::TAXONOMY_CITY,
                PropertyTaxonomies::TAXONOMY_STATE,
                PropertyTaxonomies::TAXONOMY_COUNTRY,
            ];

            foreach ($taxes as $taxonomy) {
                $terms = wp_get_post_terms($postId, $taxonomy);
                if (!is_wp_error($terms) && !empty($terms)) {
                    $parts[] = $terms[0]->name;
                }
            }

            echo !empty($parts) ? esc_html(implode(', ', $parts)) : '—';
            return;
        }

        if ($column === 'property_operation_value') {
            $operation = '';
            $operationTerms = wp_get_post_terms($postId, PropertyTaxonomies::TAXONOMY_OPERATION);
            if (!is_wp_error($operationTerms) && !empty($operationTerms)) {
                $operation = strtolower(sanitize_title($operationTerms[0]->name));
            }

            $currencyService = new CurrencyService();
            $currency = (string) get_post_meta($postId, '_property_currency_sale', true);
            if ($currency === '') {
                $currency = (string) get_post_meta($postId, '_property_currency_rent', true);
            }
            if ($currency === '') {
                $currency = (string) $currencyService->baseCurrency();
            }

            $priceSale = (string) get_post_meta($postId, '_property_price_sale', true);
            $priceRent = (string) get_post_meta($postId, '_property_price_rent', true);
            $priceAdmin = (string) get_post_meta($postId, '_property_price_admin', true);

            $value = '';
            if (strpos($operation, 'arriendo') !== false || strpos($operation, 'rent') !== false) {
                $value = $priceRent !== '' ? $priceRent : $priceSale;
            } else {
                $value = $priceSale !== '' ? $priceSale : $priceRent;
            }

            if ($value === '' && $priceAdmin !== '') {
                $value = $priceAdmin;
            }

            if ($value === '') {
                echo '—';
                return;
            }

            $formatted = \homlity_plugin_apply_filters('homlity_plugin_format_price', null, $value, $currency);
            echo esc_html((string) ($formatted !== null ? $formatted : $value));
            return;
        }

        if ($column === 'property_last_sync') {
            $syncedAt = (string) get_post_meta($postId, '_homlity_sync_updated_at', true);
            if ($syncedAt === '') {
                echo '—';
                return;
            }

            $timestamp = strtotime($syncedAt);
            if ($timestamp === false) {
                echo esc_html($syncedAt);
                return;
            }

            echo esc_html(wp_date('Y-m-d H:i', $timestamp));
        }
    }

    private function isSyncedProperty(int $postId): bool
    {
        if ($postId <= 0) {
            return false;
        }

        $metaPairs = [
            ['_homlity_sync_id', '_homlity_sync_source'],
            ['_simi_sync_id', '_simi_sync_source'],
            ['_wasi_sync_id', '_wasi_sync_source'],
        ];

        foreach ($metaPairs as [$idKey, $sourceKey]) {
            $syncId = trim((string) get_post_meta($postId, $idKey, true));
            $syncSource = trim((string) get_post_meta($postId, $sourceKey, true));

            if ($syncId !== '' || $syncSource !== '') {
                return true;
            }
        }

        return false;
    }
}
