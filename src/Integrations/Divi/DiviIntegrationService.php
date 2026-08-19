<?php
/**
 * Divi Builder integration for the property listing.
 *
 * Registers a custom Divi module (PropertyListingModule) once the Divi builder
 * framework is ready. The whole service is a no-op when Divi is not active.
 */

namespace Homlity\PluginInmobiliario\Integrations\Divi;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Services\DataSeederService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!defined('ABSPATH')) {
    exit;
}

class DiviIntegrationService implements ServiceInterface
{
    private bool $modulesLoaded = false;

    public function register(): void
    {
        // ET_Builder_Module is defined by the Divi theme / Divi Builder plugin.
        add_action('et_builder_ready', [$this, 'loadModule']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets'], 20);
        add_action('admin_menu', [$this, 'registerTemplateEditorMenu'], 30);
        add_action('admin_bar_menu', [$this, 'addTemplateAdminBarLink'], 1001);
        add_filter('page_row_actions', [$this, 'addTemplateRowAction'], 10, 2);
        add_filter('display_post_states', [$this, 'addTemplatePostState'], 10, 2);
        add_action('save_post', [$this, 'clearDetailTemplateCaches'], 100, 3);
        add_action('et_save_post', [$this, 'clearDetailTemplateCachesAfterDiviSave'], 100, 1);
    }

    public function loadModule(): void
    {
        if (!class_exists('ET_Builder_Module')) {
            return;
        }
        $this->modulesLoaded = true;

        require_once __DIR__ . '/Modules/PropertyListingModule.php';

        require_once __DIR__ . '/Compatibility/DiviWidgetApi.php';
        require_once __DIR__ . '/Modules/WidgetModule.php';

        foreach ($this->widgetClasses() as $widgetClass) {
            try {
                if (class_exists($widgetClass)) {
                    new \Homlity_Divi_Widget_Module($widgetClass);
                }
            } catch (\Throwable $exception) {
                /**
                 * Allow integrations to report a widget that could not be
                 * adapted without preventing Divi or the site from loading.
                 */
                do_action('homlity_divi_widget_registration_error', $widgetClass, $exception);

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        'Homlity Divi: no se pudo registrar %s: %s',
                        $widgetClass,
                        $exception->getMessage()
                    ));
                }
            }
        }

        (new DataSeederService())->seedBuilderTemplates();
    }

    public function enqueueAssets(): void
    {
        if (!$this->modulesLoaded) {
            return;
        }

        // Divi modules can be placed on ordinary pages and in Theme Builder
        // templates, not only on property archives/details. Their structural
        // CSS therefore has to be available before the modules render.
        wp_enqueue_style(
            'homlity-real-estate-front-components',
            HOMLITY_PLUGIN_URL . 'assets/css/front-components.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );
        wp_enqueue_style(
            'homlity-real-estate-listing',
            HOMLITY_PLUGIN_URL . 'assets/css/property-listing.css',
            ['homlity-real-estate-front-components'],
            HOMLITY_PLUGIN_VERSION
        );
        wp_enqueue_style(
            'homlity-property-faq',
            HOMLITY_PLUGIN_URL . 'assets/css/property-faq.css',
            ['homlity-real-estate-front-components'],
            HOMLITY_PLUGIN_VERSION
        );
        wp_enqueue_script(
            'homlity-property-faq',
            HOMLITY_PLUGIN_URL . 'assets/js/property-faq.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );
        // Keep the player available while Divi replaces a module preview via
        // AJAX. Conditional enqueues performed during render are too late for
        // the visual builder when the switch is enabled for the first time.
        wp_enqueue_style(
            'homlity-real-estate-property-content-audio',
            HOMLITY_PLUGIN_URL . 'assets/css/property-content-audio.css',
            ['homlity-real-estate-front-components'],
            HOMLITY_PLUGIN_VERSION
        );
        wp_enqueue_script(
            'homlity-real-estate-property-content-audio',
            HOMLITY_PLUGIN_URL . 'assets/js/property-content-audio.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        $this->enqueueIconFontFaces();
    }

    /**
     * Make Divi's complete icon fonts available under isolated family names.
     *
     * Divi's dynamic scanner does not detect icons rendered by third-party
     * modules reliably. Direct URLs also let us separate Font Awesome regular
     * and brands, which Divi exposes with the same weight (400).
     */
    private function enqueueIconFontFaces(): void
    {
        if (!defined('ET_CORE_URL')) {
            return;
        }

        $fontsUrl = trailingslashit((string) ET_CORE_URL) . 'admin/fonts/';
        $css = sprintf(
            '@font-face{font-family:HomlityDiviIcons;font-display:block;font-style:normal;font-weight:400;src:url("%1$smodules/all/modules.woff") format("woff"),url("%1$smodules/all/modules.ttf") format("truetype");}'
            . '@font-face{font-family:HomlityDiviFontAwesomeRegular;font-display:block;font-style:normal;font-weight:400;src:url("%1$sfontawesome/fa-regular-400.woff2") format("woff2"),url("%1$sfontawesome/fa-regular-400.woff") format("woff");}'
            . '@font-face{font-family:HomlityDiviFontAwesomeSolid;font-display:block;font-style:normal;font-weight:900;src:url("%1$sfontawesome/fa-solid-900.woff2") format("woff2"),url("%1$sfontawesome/fa-solid-900.woff") format("woff");}'
            . '@font-face{font-family:HomlityDiviFontAwesomeBrands;font-display:block;font-style:normal;font-weight:400;src:url("%1$sfontawesome/fa-brands-400.woff2") format("woff2"),url("%1$sfontawesome/fa-brands-400.woff") format("woff");}',
            esc_url($fontsUrl)
        );
        wp_add_inline_style('homlity-real-estate-front-components', $css);
    }

    /** @return list<class-string> */
    private function widgetClasses(): array
    {
        $namespace = 'Homlity\\PluginInmobiliario\\Integrations\\Divi\\Widgets\\';
        return array_map(static fn(string $name): string => $namespace . $name, [
            'PropertyAgentWidget', 'PropertyAgentsAvailableWidget',
            'PropertyBreadcrumbWidget', 'PropertyCardWidget', 'PropertyContentWidget',
            'PropertyDynamicCodeButtonWidget', 'PropertyFeaturedCitiesWidget',
            'PropertyFeaturedNeighborhoodsWidget', 'PropertyFeaturedOperationsWidget',
            'PropertyFeaturedTermsWidget', 'PropertyFeaturedTypesWidget',
            'PropertyFaqWidget',
            'PropertyFeaturesPrimaryWidget', 'PropertyFeaturesSecondaryWidget',
            'PropertyFilterWidget', 'PropertyGalleryWidget', 'PropertyListingWidget',
            'PropertyMapWidget', 'PropertyMediaTabsWidget', 'PropertyOperationPriceWidget',
            'PropertyRelatedWidget', 'PropertyResultsTitleWidget', 'PropertyShareWidget',
            'PropertySummaryWidget', 'PropertyTechnicalSheetButtonWidget',
            'PropertyTechnicalSheetWidget',
            'PropertyTitleWidget', 'PropertyVideoWidget', 'SimulatorWidget',
        ]);
    }

    public function registerTemplateEditorMenu(): void
    {
        $templateId = $this->detailTemplateId();
        if ($templateId <= 0 || !current_user_can('edit_post', $templateId)) {
            return;
        }

        add_submenu_page(
            'homlity-real-estate-settings',
            __('Diseñar detalle con Divi', 'homlity-real-estate'),
            __('Diseñar detalle con Divi', 'homlity-real-estate'),
            'edit_pages',
            'homlity-divi-property-template',
            [$this, 'renderTemplateEditorPage']
        );
    }

    public function renderTemplateEditorPage(): void
    {
        $templateId = $this->detailTemplateId();
        if ($templateId <= 0 || !current_user_can('edit_post', $templateId)) {
            wp_die(esc_html__('No tienes permisos para editar esta plantilla.', 'homlity-real-estate'));
        }

        $previewId = isset($_GET['property_preview'])
            ? absint(wp_unslash($_GET['property_preview']))
            : $this->defaultPreviewPropertyId();
        if (get_post_type($previewId) !== PropertyPostType::POST_TYPE) {
            $previewId = $this->defaultPreviewPropertyId();
        }

        $properties = get_posts([
            'post_type' => PropertyPostType::POST_TYPE,
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => 100,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);
        $visualUrl = $this->visualBuilderUrl($templateId, $previewId);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Diseñar detalle de inmueble con Divi', 'homlity-real-estate'); ?></h1>
            <p class="description" style="max-width:760px;">
                <?php esc_html_e(
                    'Edita la plantilla global que usan todos los inmuebles. El inmueble seleccionado se utiliza solamente como vista previa para mostrar datos reales dentro de los módulos de Divi.',
                    'homlity-real-estate'
                ); ?>
            </p>

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin:24px 0;">
                <input type="hidden" name="page" value="homlity-divi-property-template">
                <label for="homlity-property-preview"><strong><?php esc_html_e('Inmueble para vista previa', 'homlity-real-estate'); ?></strong></label>
                <select id="homlity-property-preview" name="property_preview" style="min-width:360px;max-width:100%;margin:0 8px;">
                    <?php foreach ($properties as $property): ?>
                        <option value="<?php echo esc_attr((string) $property->ID); ?>" <?php selected($previewId, (int) $property->ID); ?>>
                            <?php echo esc_html($property->post_title . ' (#' . $property->ID . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Cambiar vista previa', 'homlity-real-estate'), 'secondary', 'submit', false); ?>
            </form>

            <?php if ($previewId <= 0): ?>
                <div class="notice notice-warning inline"><p>
                    <?php esc_html_e('Crea o publica al menos un inmueble para poder mostrar datos dinámicos en la vista previa.', 'homlity-real-estate'); ?>
                </p></div>
            <?php endif; ?>

            <p style="margin-top:24px;">
                <a class="button button-primary button-hero" href="<?php echo esc_url($visualUrl); ?>">
                    <?php esc_html_e('Editar con el constructor visual de Divi', 'homlity-real-estate'); ?>
                </a>
                <a class="button button-hero" href="<?php echo esc_url(get_edit_post_link($templateId, '')); ?>">
                    <?php esc_html_e('Editar en el administrador', 'homlity-real-estate'); ?>
                </a>
            </p>
            <p class="description">
                <?php esc_html_e('Los cambios se guardan en la plantilla global, no en el inmueble usado para la vista previa.', 'homlity-real-estate'); ?>
            </p>
        </div>
        <?php
    }

    public function addTemplateAdminBarLink(\WP_Admin_Bar $adminBar): void
    {
        if (!is_admin_bar_showing()) {
            return;
        }

        $templateId = $this->detailTemplateId();
        if ($templateId <= 0 || !current_user_can('edit_post', $templateId)) {
            return;
        }

        $previewId = is_singular(PropertyPostType::POST_TYPE)
            ? (int) get_queried_object_id()
            : $this->previewPropertyIdFromRequest();
        if ($previewId <= 0) {
            $previewId = $this->defaultPreviewPropertyId();
        }
        $visualUrl = $this->visualBuilderUrl($templateId, $previewId);

        $adminBar->add_node([
            'id' => 'homlity-edit-divi-property-template',
            'parent' => 'homlity-real-estate-links',
            'title' => __('Editar detalle con Divi', 'homlity-real-estate'),
            'href' => $visualUrl,
            'meta' => [
                'title' => __('Editar la plantilla global de detalle usando este inmueble como vista previa', 'homlity-real-estate'),
            ],
        ]);

        // On a property page Divi's native button would edit that individual
        // property. Point it to Homlity's global detail template instead.
        if (is_singular(PropertyPostType::POST_TYPE)) {
            $adminBar->remove_node('et-use-visual-builder');
            $adminBar->add_node([
                'id' => 'et-use-visual-builder',
                'title' => __('Editar plantilla de detalle con Divi', 'homlity-real-estate'),
                'href' => $visualUrl,
            ]);
        }
    }

    public function addTemplateRowAction(array $actions, \WP_Post $post): array
    {
        $templateId = $this->detailTemplateId();
        if ($post->ID !== $templateId || !current_user_can('edit_post', $templateId)) {
            return $actions;
        }

        $previewId = $this->defaultPreviewPropertyId();
        $actions['homlity_divi_visual_builder'] = sprintf(
            '<a href="%1$s">%2$s</a>',
            esc_url($this->visualBuilderUrl($templateId, $previewId)),
            esc_html__('Editar detalle con Divi', 'homlity-real-estate')
        );
        return $actions;
    }

    public function addTemplatePostState(array $states, \WP_Post $post): array
    {
        if ($post->ID === $this->detailTemplateId()) {
            $states['homlity_property_detail'] = __('Plantilla de detalle Homlity (Divi)', 'homlity-real-estate');
        }
        return $states;
    }

    /**
     * A Divi detail page is reused by every property. Divi normally clears only
     * the CSS resource associated with the edited page, while the front-end
     * resources may have been generated under each property's post ID. Clear
     * those shared resources after saving the global Homlity template so its
     * latest modules and styles are visible immediately on every property.
     */
    public function clearDetailTemplateCaches(int $postId, \WP_Post $post, bool $update): void
    {
        unset($post, $update);

        if ($postId !== $this->detailTemplateId() || wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        $this->purgeDiviTemplateCaches();
    }

    public function clearDetailTemplateCachesAfterDiviSave(int $postId): void
    {
        if ($postId !== $this->detailTemplateId()) {
            return;
        }

        $this->purgeDiviTemplateCaches();
    }

    private function purgeDiviTemplateCaches(): void
    {
        static $purged = false;
        if ($purged) {
            return;
        }
        $purged = true;

        if (class_exists('ET_Core_PageResource')) {
            \ET_Core_PageResource::remove_static_resources('all', 'all');
        } else {
            do_action('et_core_page_resource_auto_clear');
        }

        // Divi's helper covers supported full-page cache integrations. A global
        // purge is intentional because this one layout affects every property.
        if (function_exists('et_core_clear_wp_cache')) {
            et_core_clear_wp_cache();
        }

        clean_post_cache($this->detailTemplateId());
    }

    private function detailTemplateId(): int
    {
        if (
            (string) get_option('homlity_plugin_visual_builder_explicit', '') === '1'
            && sanitize_key((string) get_option('homlity_plugin_visual_builder', '')) !== 'divi'
        ) {
            return 0;
        }

        $templateId = (int) get_option('homlity_plugin_single_template_id', 0);
        if ($templateId <= 0 || !get_post_status($templateId)) {
            return 0;
        }
        if (get_post_meta($templateId, '_elementor_edit_mode', true) === 'builder') {
            return 0;
        }
        if (get_post_meta($templateId, '_et_pb_use_builder', true) !== 'on') {
            return 0;
        }
        return $templateId;
    }

    private function visualBuilderUrl(int $templateId, int $previewId): string
    {
        $permalink = (string) get_permalink($templateId);
        $url = function_exists('et_fb_get_builder_url')
            ? (string) et_fb_get_builder_url($permalink)
            : (string) add_query_arg(['et_fb' => '1', 'PageSpeed' => 'off'], $permalink);

        if ($previewId > 0) {
            $url = (string) add_query_arg('homlity_property_preview', $previewId, $url);
        }
        return $url;
    }

    private function previewPropertyIdFromRequest(): int
    {
        return isset($_GET['homlity_property_preview'])
            ? absint(wp_unslash($_GET['homlity_property_preview']))
            : 0;
    }

    private function defaultPreviewPropertyId(): int
    {
        $ids = get_posts([
            'post_type' => PropertyPostType::POST_TYPE,
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        return $ids ? (int) $ids[0] : 0;
    }
}
