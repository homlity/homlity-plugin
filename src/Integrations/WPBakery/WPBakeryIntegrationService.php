<?php
/**
 * WPBakery integration.
 *
 * Besides the standalone listing shortcode, this service exposes every Homlity
 * widget in WPBakery through an autonomous control contract and renders every
 * component directly with WPBakery attributes and responsive styles.
 */

namespace Homlity\PluginInmobiliario\Integrations\WPBakery;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Controls_Manager;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Compatibility\Widget_Base;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyAgentWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyAgentsAvailableWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyBreadcrumbWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyCardWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyContentWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyDynamicCodeButtonWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyFaqWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyFeaturedCitiesWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyFeaturedNeighborhoodsWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyFeaturedOperationsWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyFeaturedTermsWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyFeaturedTypesWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyFeaturesPrimaryWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyFeaturesSecondaryWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyFilterWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyGalleryWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyListingWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyMapWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyMediaTabsWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyOperationPriceWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyRelatedWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyResultsTitleWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyShareWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertySummaryWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyTechnicalSheetButtonWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyTechnicalSheetWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyTitleWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyVideoWidget;
use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\SimulatorWidget;
use Homlity\PluginInmobiliario\Services\DataSeederService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!defined('ABSPATH')) {
    exit;
}

class WPBakeryIntegrationService implements ServiceInterface
{
    private const CATEGORY = 'Homlity Plugin';

    private bool $mapped = false;
    private bool $listingMapped = false;
    /** @var array<string,string> */
    private array $runtimeCss = [];

    public function register(): void
    {
        // Do not check WPB_VC_VERSION here: some bundled WPBakery distributions
        // define it after plugins_loaded. vc_before_init is the authoritative hook.
        add_action('vc_before_init', [$this, 'enableEditorPostTypes'], 5);
        add_action('vc_before_init', [$this, 'mapElements'], 10);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets'], 20);
        add_action('wp_footer', [$this, 'printRuntimeCss'], 99);
        add_action('admin_menu', [$this, 'registerTemplateEditorMenu'], 30);
        add_action('admin_init', [$this, 'handleActivateTemplates']);
        add_action('admin_init', [$this, 'capturePropertyPreviewContext'], 20);
        add_action('admin_bar_menu', [$this, 'addTemplateAdminBarLinks'], 1001);
        add_filter('page_row_actions', [$this, 'addTemplateRowActions'], 10, 2);
        add_filter('display_post_states', [$this, 'addTemplatePostStates'], 10, 2);
        add_filter('vc_is_valid_post_type_be', [$this, 'allowPageBackendEditor'], 10, 2);
        add_filter('vc_check_post_type_validation', [$this, 'allowTemplatePostType'], 10, 2);
        add_filter('vc_show_button_fe', [$this, 'allowTemplateFrontendButton'], 10, 3);
        add_filter('vc_user_access_with_backend_editor_get_state', [$this, 'allowTemplateEditorAccess']);
        add_filter('vc_user_access_with_frontend_editor_get_state', [$this, 'allowTemplateEditorAccess']);
        add_filter('wpb_vc_js_status_filter', [$this, 'forceTemplateEditorStatus']);
    }

    public function enableEditorPostTypes(): void
    {
        if (!function_exists('vc_set_default_editor_post_types')) {
            return;
        }

        $postTypes = function_exists('vc_default_editor_post_types')
            ? (array) vc_default_editor_post_types()
            : ['page'];
        $postTypes[] = 'page';

        vc_set_default_editor_post_types(array_values(array_unique(array_filter(array_map(
            'sanitize_key',
            $postTypes
        )))));
    }

    public function allowPageBackendEditor(bool $isValid, string $postType = ''): bool
    {
        return $postType === 'page' && $this->isTemplateEditorRequest() ? true : $isValid;
    }

    /**
     * WPBakery validates the post type before its backend editor class applies
     * vc_is_valid_post_type_be. Force only Homlity's two template pages.
     */
    public function allowTemplatePostType(?bool $isValid, string $postType = ''): ?bool
    {
        return $postType === 'page' && $this->isTemplateEditorRequest() ? true : $isValid;
    }

    public function allowTemplateFrontendButton(bool $show, int $postId = 0, string $postType = ''): bool
    {
        return $postType === 'page' && $this->isTemplateId($postId) ? true : $show;
    }

    public function allowTemplateEditorAccess(bool $allowed): bool
    {
        return $this->isTemplateEditorRequest() ? true : $allowed;
    }

    /**
     * WPBakery uses this status to decide whether the visual canvas or the
     * classic textarea is shown for the post currently being edited.
     */
    public function forceTemplateEditorStatus(mixed $status): mixed
    {
        return $this->isTemplateEditorRequest() ? 'true' : $status;
    }

    public function enqueueAssets(): void
    {
        if (!$this->isAvailable()) {
            return;
        }

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
        wp_enqueue_script(
            'homlity-real-estate-filter',
            HOMLITY_PLUGIN_URL . 'assets/js/property-filter.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );
    }

    public function registerTemplateEditorMenu(): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        add_submenu_page(
            'homlity-real-estate-settings',
            __('Plantillas WPBakery', 'homlity-real-estate'),
            __('Plantillas WPBakery', 'homlity-real-estate'),
            'edit_pages',
            'homlity-wpbakery-property-templates',
            [$this, 'renderTemplateEditorPage']
        );
    }

    public function handleActivateTemplates(): void
    {
        if (
            !$this->isAvailable()
            || empty($_POST['homlity_wpbakery_activate'])
            || empty($_POST['homlity_wpbakery_activate_nonce'])
        ) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para cambiar el constructor de las plantillas.', 'homlity-real-estate'));
        }
        if (!wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['homlity_wpbakery_activate_nonce'])),
            'homlity_wpbakery_activate_templates'
        )) {
            wp_die(esc_html__('La solicitud para activar WPBakery no es válida.', 'homlity-real-estate'));
        }

        update_option('homlity_plugin_visual_builder', 'wpbakery');
        update_option('homlity_plugin_visual_builder_explicit', '1');
        (new DataSeederService())->seedBuilderTemplates();
        flush_rewrite_rules();

        wp_safe_redirect(add_query_arg([
            'page' => 'homlity-wpbakery-property-templates',
            'activated' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    public function capturePropertyPreviewContext(): void
    {
        $previewId = isset($_REQUEST['homlity_property_preview'])
            ? absint(wp_unslash($_REQUEST['homlity_property_preview']))
            : 0;
        $editedPostId = isset($_REQUEST['post'])
            ? absint(wp_unslash($_REQUEST['post']))
            : 0;
        $singleTemplateId = (int) get_option('homlity_plugin_single_template_id', 0);

        if (
            $previewId <= 0
            || $editedPostId !== $singleTemplateId
            || get_post_type($previewId) !== PropertyPostType::POST_TYPE
            || !current_user_can('edit_post', $singleTemplateId)
        ) {
            return;
        }

        set_transient(
            'homlity_wpbakery_property_preview_' . get_current_user_id(),
            $previewId,
            HOUR_IN_SECONDS
        );
    }

    public function renderTemplateEditorPage(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('No tienes permisos para editar estas plantillas.', 'homlity-real-estate'));
        }

        $archiveId = $this->templateId('archive');
        $singleId = $this->templateId('single');
        $previewId = isset($_GET['property_preview'])
            ? absint(wp_unslash($_GET['property_preview']))
            : $this->defaultPreviewPropertyId();
        if ($previewId > 0 && get_post_type($previewId) !== PropertyPostType::POST_TYPE) {
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
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Plantillas de inmuebles con WPBakery', 'homlity-real-estate'); ?></h1>
            <p class="description" style="max-width:760px;">
                <?php esc_html_e(
                    'Edita las páginas globales que Homlity usa para los resultados y el detalle de todos los inmuebles.',
                    'homlity-real-estate'
                ); ?>
            </p>

            <?php if (!empty($_GET['activated'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('WPBakery quedó seleccionado como constructor de las plantillas Homlity.', 'homlity-real-estate'); ?></p></div>
            <?php endif; ?>

            <?php if ($archiveId <= 0 && $singleId <= 0 && current_user_can('manage_options')): ?>
                <div class="notice notice-warning inline"><p>
                    <?php esc_html_e('Las plantillas Homlity están asociadas a otro constructor. Convertirlas a WPBakery reemplazará la maquetación actual de las páginas de resultados y detalle; crea una copia si deseas conservarla.', 'homlity-real-estate'); ?>
                </p></div>
                <form method="post" action="">
                    <?php wp_nonce_field('homlity_wpbakery_activate_templates', 'homlity_wpbakery_activate_nonce'); ?>
                    <input type="hidden" name="homlity_wpbakery_activate" value="1">
                    <?php submit_button(__('Usar WPBakery para las plantillas Homlity', 'homlity-real-estate'), 'primary'); ?>
                </form>
            <?php endif; ?>

            <?php if ($singleId > 0): ?>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin:24px 0;">
                    <input type="hidden" name="page" value="homlity-wpbakery-property-templates">
                    <label for="homlity-wpbakery-property-preview"><strong><?php esc_html_e('Inmueble para vista previa del detalle', 'homlity-real-estate'); ?></strong></label>
                    <select id="homlity-wpbakery-property-preview" name="property_preview" style="min-width:360px;max-width:100%;margin:0 8px;">
                        <?php foreach ($properties as $property): ?>
                            <option value="<?php echo esc_attr((string) $property->ID); ?>" <?php selected($previewId, (int) $property->ID); ?>>
                                <?php echo esc_html($property->post_title . ' (#' . $property->ID . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php submit_button(__('Cambiar vista previa', 'homlity-real-estate'), 'secondary', 'submit', false); ?>
                </form>
            <?php endif; ?>

            <table class="widefat striped" style="max-width:900px;">
                <thead><tr><th><?php esc_html_e('Plantilla', 'homlity-real-estate'); ?></th><th><?php esc_html_e('Acciones', 'homlity-real-estate'); ?></th></tr></thead>
                <tbody>
                    <?php $this->renderTemplateEditorRow(__('Resultados de inmuebles', 'homlity-real-estate'), $archiveId, 0); ?>
                    <?php $this->renderTemplateEditorRow(__('Detalle de inmueble', 'homlity-real-estate'), $singleId, $previewId); ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function addTemplateAdminBarLinks(\WP_Admin_Bar $adminBar): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        $archiveId = $this->templateId('archive');
        $singleId = $this->templateId('single');
        if ($archiveId > 0 && current_user_can('edit_post', $archiveId)) {
            $adminBar->add_node([
                'id' => 'homlity-edit-wpbakery-archive-template',
                'parent' => 'homlity-real-estate-links',
                'title' => __('Editar resultados con WPBakery', 'homlity-real-estate'),
                'href' => $this->backendEditorUrl($archiveId),
            ]);
        }
        if ($singleId > 0 && current_user_can('edit_post', $singleId)) {
            $previewId = is_singular(PropertyPostType::POST_TYPE)
                ? (int) get_queried_object_id()
                : $this->defaultPreviewPropertyId();
            $adminBar->add_node([
                'id' => 'homlity-edit-wpbakery-single-template',
                'parent' => 'homlity-real-estate-links',
                'title' => __('Editar detalle con WPBakery', 'homlity-real-estate'),
                'href' => $this->frontendEditorUrl($singleId, $previewId)
                    ?: $this->backendEditorUrl($singleId, $previewId),
            ]);

            // A property renders the shared detail template. Expose that
            // template as a visible top-level action, with this property as
            // its preview context, instead of enabling WPBakery on CRM data.
            if (is_singular(PropertyPostType::POST_TYPE)) {
                $adminBar->add_node([
                    'id' => 'homlity-edit-current-detail-wpbakery',
                    'title' => __('Editar con WPBakery', 'homlity-real-estate'),
                    'href' => $this->frontendEditorUrl($singleId, $previewId)
                        ?: $this->backendEditorUrl($singleId, $previewId),
                    'meta' => [
                        'class' => 'homlity-edit-current-detail-wpbakery',
                        'title' => __('Edita la plantilla global usando este inmueble como vista previa.', 'homlity-real-estate'),
                    ],
                ]);
            }
        }
    }

    public function addTemplateRowActions(array $actions, \WP_Post $post): array
    {
        foreach (['archive', 'single'] as $purpose) {
            $templateId = $this->templateId($purpose);
            if ($post->ID !== $templateId || !current_user_can('edit_post', $templateId)) {
                continue;
            }
            $previewId = $purpose === 'single' ? $this->defaultPreviewPropertyId() : 0;
            $actions['homlity_wpbakery_editor'] = sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url($this->frontendEditorUrl($templateId, $previewId)
                    ?: $this->backendEditorUrl($templateId, $previewId)),
                esc_html__('Editar con WPBakery', 'homlity-real-estate')
            );
            break;
        }
        return $actions;
    }

    public function addTemplatePostStates(array $states, \WP_Post $post): array
    {
        if ($post->ID === $this->templateId('archive')) {
            $states['homlity_wpbakery_archive'] = __('Resultados Homlity (WPBakery)', 'homlity-real-estate');
        } elseif ($post->ID === $this->templateId('single')) {
            $states['homlity_wpbakery_single'] = __('Detalle Homlity (WPBakery)', 'homlity-real-estate');
        }
        return $states;
    }

    private function loadWidgetContract(): void
    {
        if (!class_exists(Widget_Base::class, false)) {
            require_once HOMLITY_PLUGIN_PATH . 'src/Integrations/WPBakery/Compatibility/WPBakeryWidgetApi.php';
        }
    }

    private function isAvailable(): bool
    {
        return defined('WPB_VC_VERSION')
            || function_exists('vc_map')
            || class_exists('Vc_Manager');
    }

    private function templateId(string $purpose): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }
        if (
            (string) get_option('homlity_plugin_visual_builder_explicit', '') === '1'
            && sanitize_key((string) get_option('homlity_plugin_visual_builder', '')) !== 'wpbakery'
        ) {
            return 0;
        }

        $option = $purpose === 'single'
            ? 'homlity_plugin_single_template_id'
            : 'homlity_plugin_archive_page_id';
        $templateId = (int) get_option($option, 0);
        if ($templateId <= 0 || !get_post_status($templateId)) {
            return 0;
        }
        if (
            get_post_meta($templateId, '_wpb_vc_js_status', true) !== 'true'
            && get_post_meta($templateId, '_homlity_seeded_builder', true) !== 'wpbakery'
        ) {
            return 0;
        }
        return $templateId;
    }

    private function backendEditorUrl(int $templateId, int $previewId = 0): string
    {
        $url = (string) get_edit_post_link($templateId, '');
        if ($url !== '' && $previewId > 0) {
            $url = (string) add_query_arg('homlity_property_preview', $previewId, $url);
        }
        return $url;
    }

    private function isTemplateId(int $postId): bool
    {
        if ($postId <= 0) {
            return false;
        }
        return in_array($postId, [
            (int) get_option('homlity_plugin_archive_page_id', 0),
            (int) get_option('homlity_plugin_single_template_id', 0),
        ], true);
    }

    private function isTemplateEditorRequest(): bool
    {
        $postId = 0;
        if (isset($_REQUEST['post'])) {
            $postId = absint(wp_unslash($_REQUEST['post']));
        } elseif (isset($_REQUEST['post_id'])) {
            $postId = absint(wp_unslash($_REQUEST['post_id']));
        }

        if ($this->isTemplateId($postId)) {
            return current_user_can('edit_post', $postId);
        }

        if (!is_admin() && is_singular('page')) {
            $queriedId = (int) get_queried_object_id();
            return $this->isTemplateId($queriedId) && current_user_can('edit_post', $queriedId);
        }

        return false;
    }

    private function frontendEditorUrl(int $templateId, int $previewId = 0): string
    {
        if (!function_exists('vc_get_inline_url')) {
            return '';
        }

        try {
            $url = (string) vc_get_inline_url($templateId);
        } catch (\Throwable) {
            return '';
        }
        if ($url !== '' && $previewId > 0) {
            $url = (string) add_query_arg('homlity_property_preview', $previewId, $url);
        }
        return $url;
    }

    private function renderTemplateEditorRow(string $label, int $templateId, int $previewId): void
    {
        echo '<tr><td><strong>' . esc_html($label) . '</strong>';
        if ($templateId > 0) {
            echo '<br><span class="description">' . esc_html(get_the_title($templateId)) . ' (#' . esc_html((string) $templateId) . ')</span>';
        }
        echo '</td><td>';
        if ($templateId <= 0 || !current_user_can('edit_post', $templateId)) {
            echo '<span class="description">' . esc_html__('La plantilla WPBakery aún no está disponible.', 'homlity-real-estate') . '</span>';
        } else {
            $frontendUrl = $this->frontendEditorUrl($templateId, $previewId);
            if ($frontendUrl !== '') {
                echo '<a class="button button-primary" href="' . esc_url($frontendUrl) . '">'
                    . esc_html__('Editor visual WPBakery', 'homlity-real-estate') . '</a> ';
            }
            echo '<a class="button" href="' . esc_url($this->backendEditorUrl($templateId, $previewId)) . '">'
                . esc_html__('Editor backend WPBakery', 'homlity-real-estate') . '</a>';
        }
        echo '</td></tr>';
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

    public function mapElements(): void
    {
        if ($this->mapped || !function_exists('vc_map')) {
            return;
        }

        $this->loadWidgetContract();

        if (!$this->listingMapped) {
            $this->mapListing();
            $this->listingMapped = true;
        }

        (new DataSeederService())->seedBuilderTemplates();

        if (!class_exists(Widget_Base::class)) {
            return;
        }

        foreach ($this->widgetClasses() as $widgetClass) {
            try {
                $this->mapWidget($widgetClass);
            } catch (\Throwable $exception) {
                do_action('homlity_wpbakery_widget_registration_error', $widgetClass, $exception);

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        'Homlity WPBakery: no se pudo registrar %s: %s',
                        $widgetClass,
                        $exception->getMessage()
                    ));
                }
            }
        }

        $this->mapped = true;
    }

    private function mapListing(): void
    {
        vc_map([
            'name'        => __('Listado de inmuebles', 'homlity-real-estate'),
            'base'        => 'homlity_listing',
            'category'    => __(self::CATEGORY, 'homlity-real-estate'),
            'icon'        => HOMLITY_PLUGIN_URL . 'icono.png',
            'description' => __('Grilla/mapa de propiedades con filtros y orden.', 'homlity-real-estate'),
            'params'      => $this->listingParams(),
        ]);
    }

    /**
     * @param class-string<Widget_Base> $widgetClass
     */
    private function mapWidget(string $widgetClass): void
    {
        if (!class_exists($widgetClass)) {
            return;
        }

        /** @var Widget_Base $widget */
        $widget = new $widgetClass();
        $base = 'homlity_wpb_' . sanitize_key($widget->get_name());

        add_shortcode($base, function ($attributes = []) use ($widgetClass): string {
            return $this->renderWidget($widgetClass, (array) $attributes);
        });

        vc_map([
            'name'        => $widget->get_title(),
            'base'        => $base,
            'category'    => __(self::CATEGORY, 'homlity-real-estate'),
            'icon'        => HOMLITY_PLUGIN_URL . 'icono.png',
            'description' => sprintf(
                __('Elemento Homlity para WPBakery: %s.', 'homlity-real-estate'),
                $widget->get_title()
            ),
            'params'      => $this->controlsToParams($widget),
        ]);
    }

    /**
     * @param class-string<Widget_Base> $widgetClass
     */
    private function renderWidget(string $widgetClass, array $attributes): string
    {
        $this->loadWidgetContract();
        if (!class_exists(Widget_Base::class) || !class_exists($widgetClass)) {
            return '';
        }

        /** @var Widget_Base $widget */
        $widget = new $widgetClass();
        $settings = $this->normalizeSettings($attributes, $widget->get_controls());
        $elementId = substr(md5($widgetClass . wp_json_encode($settings) . wp_rand()), 0, 8);
        $wrapper = '.homlity-wpbakery-' . $elementId;
        $css = $this->buildElementCss($widget->get_controls(), $settings, $wrapper);
        if ($css !== '') {
            $this->runtimeCss[$elementId] = $css;
        }
        $widget->homlitySetSettings($settings);
        $markup = $widget->homlityRender();

        $wrapperClasses = ['homlity-wpbakery-widget', 'homlity-wpbakery-' . $elementId];
        $designCss = (string) ($attributes['css'] ?? '');
        if ($designCss !== '' && function_exists('vc_shortcode_custom_css_class')) {
            $wrapperClasses = array_merge(
                $wrapperClasses,
                preg_split('/\s+/', trim(vc_shortcode_custom_css_class($designCss, ' '))) ?: []
            );
        }
        if (!empty($attributes['el_class'])) {
            $wrapperClasses = array_merge(
                $wrapperClasses,
                preg_split('/\s+/', trim((string) $attributes['el_class'])) ?: []
            );
        }
        $wrapperClasses = array_values(array_unique(array_filter(array_map(
            'sanitize_html_class',
            $wrapperClasses
        ))));
        $wrapperId = !empty($attributes['el_id'])
            ? sanitize_title((string) $attributes['el_id'])
            : '';

        $output = '<div class="' . esc_attr(implode(' ', $wrapperClasses)) . '"'
            . ($wrapperId !== '' ? ' id="' . esc_attr($wrapperId) . '"' : '')
            . '>';
        if ($css !== '') {
            $output .= '<style type="text/css" data-homlity-wpbakery-css="' . esc_attr($elementId) . '">'
                . wp_strip_all_tags($css)
                . '</style>';
        }
        $output .= $markup . '</div>';

        return $output;
    }

    /**
     * WPBakery can refresh a shortcode independently in its frontend editor.
     * The local style above covers that response; this consolidated copy also
     * protects normal pages from content filters that relocate inline styles.
     */
    public function printRuntimeCss(): void
    {
        if ($this->runtimeCss === []) {
            return;
        }

        echo '<style type="text/css" id="homlity-wpbakery-runtime-css">'
            . wp_strip_all_tags(implode('', $this->runtimeCss))
            . '</style>';
    }

    private function normalizeSettings(array $attributes, array $controls): array
    {
        $settings = [];

        foreach ($attributes as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $controlKey = $key;
            if (!isset($controls[$controlKey])) {
                $controlKey = (string) preg_replace('/_(tablet|phone)$/', '', $key);
            }
            $control = (array) ($controls[$controlKey] ?? []);
            $type = (string) ($control['type'] ?? '');

            if ($type === Controls_Manager::SELECT2 && !empty($control['multiple'])) {
                if (is_array($value)) {
                    $settings[$key] = array_values(array_filter(array_map('strval', $value)));
                } else {
                    $raw = trim((string) $value);
                    $decoded = $raw !== '' && $raw[0] === '[' ? json_decode($raw, true) : null;
                    $settings[$key] = is_array($decoded)
                        ? array_values(array_filter(array_map('strval', $decoded)))
                        : array_values(array_filter(array_map('trim', explode(',', $raw))));
                }
                continue;
            }

            if ($type === Controls_Manager::SWITCHER) {
                $settings[$key] = in_array($value, ['true', 'yes', 'on', '1', 1, true], true) ? 'yes' : '';
                continue;
            }

            if ($type === Controls_Manager::REPEATER) {
                $rows = is_array($value)
                    ? $value
                    : (function_exists('vc_param_group_parse_atts') ? vc_param_group_parse_atts((string) $value) : []);
                $fields = (array) ($control['fields'] ?? []);
                $settings[$key] = array_map(function (array $row) use ($fields): array {
                    return $this->normalizeSettings($row, $fields);
                }, array_values(array_filter((array) $rows, 'is_array')));
                continue;
            }

            if (in_array($type, ['media', 'image'], true) && is_numeric($value)) {
                $attachmentId = (int) $value;
                $settings[$key] = [
                    'id' => $attachmentId,
                    'url' => (string) wp_get_attachment_url($attachmentId),
                ];
                continue;
            }

            if ($type === 'gallery' && is_string($value)) {
                $settings[$key] = array_map(static function ($id): array {
                    $attachmentId = (int) $id;
                    return ['id' => $attachmentId, 'url' => (string) wp_get_attachment_url($attachmentId)];
                }, array_filter(array_map('trim', explode(',', $value))));
                continue;
            }

            if ($type === 'url' && is_string($value) && function_exists('vc_build_link')) {
                $link = vc_build_link($value);
                $settings[$key] = [
                    'url' => (string) ($link['url'] ?? ''),
                    'is_external' => (($link['target'] ?? '') === '_blank') ? 'on' : '',
                    'nofollow' => str_contains((string) ($link['rel'] ?? ''), 'nofollow') ? 'on' : '',
                ];
                continue;
            }

            if ($type === Controls_Manager::ICONS && is_string($value)) {
                $decoded = json_decode($value, true);
                $settings[$key] = is_array($decoded)
                    ? $decoded
                    : ['value' => sanitize_text_field($value), 'library' => ''];
                continue;
            }

            if ($type === Controls_Manager::SLIDER && is_string($value)) {
                $settings[$key] = $this->normalizeSlider($value, $control);
                continue;
            }

            if ($type === Controls_Manager::DIMENSIONS && is_string($value)) {
                $settings[$key] = $this->normalizeDimensions($value, $control);
                continue;
            }

            if (is_string($value) && in_array(substr(trim($value), 0, 1), ['{', '['], true)) {
                $decoded = json_decode($value, true);
                $settings[$key] = is_array($decoded) ? $decoded : $value;
                continue;
            }

            $settings[$key] = $value;
        }

        return $settings;
    }

    private function normalizeSlider(string $value, array $control): array
    {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $unit = (string) (($control['size_units'][0] ?? null) ?: 'px');
        return ['size' => is_numeric($value) ? (float) $value : $value, 'unit' => $unit];
    }

    private function normalizeDimensions(string $value, array $control): array
    {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $parts = preg_split('/\s+/', trim($value)) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
        if ($parts === []) {
            return [];
        }
        if (count($parts) === 1) {
            $parts = [$parts[0], $parts[0], $parts[0], $parts[0]];
        } elseif (count($parts) === 2) {
            $parts = [$parts[0], $parts[1], $parts[0], $parts[1]];
        } elseif (count($parts) === 3) {
            $parts = [$parts[0], $parts[1], $parts[2], $parts[1]];
        } else {
            $parts = array_slice($parts, 0, 4);
        }

        $unit = (string) (($control['size_units'][0] ?? null) ?: 'px');
        foreach ($parts as &$part) {
            if (preg_match('/^(-?(?:\d+\.?\d*|\.\d+))([a-z%]+)?$/i', $part, $matches)) {
                $part = $matches[1];
                if (!empty($matches[2])) {
                    $unit = $matches[2];
                }
            }
        }
        unset($part);

        return [
            'top' => $parts[0],
            'right' => $parts[1],
            'bottom' => $parts[2],
            'left' => $parts[3],
            'unit' => $unit,
            'isLinked' => false,
        ];
    }

    private function buildElementCss(array $controls, array $settings, string $wrapper): string
    {
        $rules = [];
        $responsiveRules = ['tablet' => [], 'phone' => []];

        foreach ($controls as $name => $control) {
            if (($control['type'] ?? '') === 'homlity_group') {
                $this->appendGroupCss($rules, (string) $name, (array) $control, $settings, $wrapper);
                continue;
            }

            $selectors = (array) ($control['selectors'] ?? []);
            if ($selectors === []) {
                continue;
            }

            $value = $settings[$name] ?? ($control['default'] ?? null);
            if (($control['type'] ?? '') === Controls_Manager::HIDDEN
                && $this->conditionsMatch((array) ($control['condition'] ?? []), $settings)) {
                $value = '1';
            }
            if (!$this->hasCssValue($value, (array) $control)) {
                continue;
            }

            foreach ($selectors as $selector => $declaration) {
                $selector = str_replace('{{WRAPPER}}', $wrapper, (string) $selector);
                $declaration = $this->selectorDeclaration((string) $declaration, $value, (array) $control, $settings);
                if ($declaration !== '' && !str_contains($declaration, '{{')) {
                    $rules[$selector][] = $declaration;
                }
            }

            if (empty($control['responsive'])) {
                continue;
            }
            foreach (['tablet', 'phone'] as $device) {
                $responsiveValue = $this->responsiveValue($settings, (string) $name, $device, (array) $control);
                if (!$this->hasCssValue($responsiveValue, (array) $control)) {
                    continue;
                }
                foreach ($selectors as $selector => $declaration) {
                    $selector = str_replace('{{WRAPPER}}', $wrapper, (string) $selector);
                    $declaration = $this->selectorDeclaration((string) $declaration, $responsiveValue, (array) $control, $settings);
                    if ($declaration !== '' && !str_contains($declaration, '{{')) {
                        $responsiveRules[$device][$selector][] = $declaration;
                    }
                }
            }
        }

        foreach (['tablet', 'phone'] as $device) {
            foreach ((array) ($rules['@' . $device] ?? []) as $selector => $declarations) {
                $responsiveRules[$device][$selector] = array_merge(
                    $responsiveRules[$device][$selector] ?? [],
                    (array) $declarations
                );
            }
            unset($rules['@' . $device]);
        }

        $css = $this->compileRules($rules);
        $tabletCss = $this->compileRules($responsiveRules['tablet']);
        $phoneCss = $this->compileRules($responsiveRules['phone']);
        $css .= $tabletCss !== '' ? '@media(max-width:980px){' . $tabletCss . '}' : '';
        $css .= $phoneCss !== '' ? '@media(max-width:767px){' . $phoneCss . '}' : '';
        return $css;
    }

    private function compileRules(array $rules): string
    {
        $css = '';
        foreach ($rules as $selector => $declarations) {
            $declarations = array_map(
                [$this, 'prioritizeDeclaration'],
                array_unique((array) $declarations)
            );
            $css .= $selector . '{' . implode('', $declarations) . '}';
        }
        return $css;
    }

    /**
     * User-selected builder styles must override Homlity's baseline rules,
     * some of which intentionally use !important for theme compatibility.
     */
    private function prioritizeDeclaration(string $declaration): string
    {
        return (string) preg_replace_callback(
            '/(^|;)\s*([A-Za-z-]+)\s*:\s*([^;{}]+)(?=;|$)/',
            static function (array $matches): string {
                $value = rtrim((string) $matches[3]);
                if (!preg_match('/!important\s*$/i', $value)) {
                    $value .= ' !important';
                }
                return $matches[1] . $matches[2] . ':' . $value;
            },
            $declaration
        );
    }

    private function responsiveValue(array $settings, string $name, string $device, array $control): mixed
    {
        $key = $name . '_' . $device;
        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }
        $defaultKey = $device === 'phone' ? 'mobile_default' : 'tablet_default';
        return $control[$defaultKey] ?? null;
    }

    private function hasCssValue(mixed $value, array $control): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_array($value)) {
            foreach (['value', 'size', 'top', 'right', 'bottom', 'left'] as $key) {
                if (array_key_exists($key, $value) && trim((string) $value[$key]) !== '') {
                    return true;
                }
            }
            return false;
        }
        return trim((string) $value) !== ''
            || array_key_exists('', (array) ($control['selectors_dictionary'] ?? []));
    }

    private function appendGroupCss(array &$rules, string $name, array $control, array $settings, string $wrapper): void
    {
        $selector = str_replace('{{WRAPPER}}', $wrapper, (string) ($control['selector'] ?? ''));
        if ($selector === '') {
            return;
        }

        $group = strtolower((string) ($control['group_type'] ?? ''));
        $map = str_contains($group, 'typography') ? [
            'font_family' => 'font-family', 'font_size' => 'font-size', 'font_weight' => 'font-weight',
            'text_transform' => 'text-transform', 'font_style' => 'font-style',
            'text_decoration' => 'text-decoration', 'line_height' => 'line-height',
            'letter_spacing' => 'letter-spacing',
        ] : (str_contains($group, 'border') ? [
            'border_type' => 'border-style', 'border_width' => 'border-width',
            'border_color' => 'border-color', 'border_radius' => 'border-radius',
        ] : (str_contains($group, 'background') ? [
            'background_color' => 'background-color', 'background_image' => 'background-image',
            'background_position' => 'background-position', 'background_repeat' => 'background-repeat',
            'background_size' => 'background-size',
        ] : []));

        foreach ($map as $suffix => $property) {
            $value = trim((string) ($settings[$name . '_' . $suffix] ?? ''));
            if ($value !== '') {
                $rules[$selector][] = $property . ':' . $this->groupCssValue($suffix, $value) . ';';
            }
            foreach (['tablet', 'phone'] as $device) {
                $responsive = trim((string) ($settings[$name . '_' . $suffix . '_' . $device] ?? ''));
                if ($responsive !== '') {
                    $rules['@' . $device][$selector][] = $property . ':' . $this->groupCssValue($suffix, $responsive) . ';';
                }
            }
        }

        if (str_contains($group, 'shadow')) {
            $value = trim((string) ($settings[$name . '_shadow'] ?? ''));
            if ($value === '') {
                $color = trim((string) ($settings[$name . '_shadow_color'] ?? 'rgba(0,0,0,.25)'));
                $horizontal = trim((string) ($settings[$name . '_shadow_horizontal'] ?? ''));
                $vertical = trim((string) ($settings[$name . '_shadow_vertical'] ?? ''));
                $blur = trim((string) ($settings[$name . '_shadow_blur'] ?? ''));
                $spread = trim((string) ($settings[$name . '_shadow_spread'] ?? ''));
                if ($horizontal !== '' || $vertical !== '' || $blur !== '' || $spread !== '') {
                    $parts = [
                        $this->cssLength($horizontal !== '' ? $horizontal : '0'),
                        $this->cssLength($vertical !== '' ? $vertical : '0'),
                        $this->cssLength($blur !== '' ? $blur : '0'),
                    ];
                    if (!str_contains($group, 'text')) {
                        $parts[] = $this->cssLength($spread !== '' ? $spread : '0');
                        if (($settings[$name . '_shadow_position'] ?? '') === 'inset') {
                            array_unshift($parts, 'inset');
                        }
                    }
                    $parts[] = $color;
                    $value = implode(' ', $parts);
                }
            }
            if ($value !== '') {
                $rules[$selector][] = (str_contains($group, 'text') ? 'text-shadow:' : 'box-shadow:') . $value . ';';
            }
        }

        if (str_contains($group, 'background')) {
            $first = trim((string) ($settings[$name . '_background_gradient_color'] ?? ''));
            $second = trim((string) ($settings[$name . '_background_gradient_color_b'] ?? ''));
            if ($first !== '' && $second !== '') {
                $gradientType = ($settings[$name . '_background_gradient_type'] ?? 'linear') === 'radial'
                    ? 'radial'
                    : 'linear';
                if ($gradientType === 'radial') {
                    $position = trim((string) ($settings[$name . '_background_gradient_position'] ?? 'center center'));
                    $rules[$selector][] = 'background-image:radial-gradient(at ' . $position . ',' . $first . ',' . $second . ');';
                } else {
                    $angle = trim((string) ($settings[$name . '_background_gradient_angle'] ?? '180'));
                    $rules[$selector][] = 'background-image:linear-gradient(' . $this->cssAngle($angle) . ',' . $first . ',' . $second . ');';
                }
            }
        }
    }

    private function groupCssValue(string $suffix, string $value): string
    {
        if ($suffix === 'background_image') {
            return str_starts_with($value, 'url(') ? $value : 'url("' . esc_url_raw($value) . '")';
        }
        return in_array($suffix, ['font_size', 'line_height', 'letter_spacing', 'border_width', 'border_radius'], true)
            ? $this->cssLength($value)
            : $value;
    }

    private function selectorDeclaration(string $declaration, mixed $value, array $control, array $settings): string
    {
        $dictionary = (array) ($control['selectors_dictionary'] ?? []);
        $lookup = is_array($value)
            ? (string) ($value['value'] ?? $value['size'] ?? '')
            : (string) $value;
        if (array_key_exists($lookup, $dictionary)) {
            $mapped = (string) $dictionary[$lookup];
            if (!str_contains($declaration, '{{VALUE}}') && str_contains($mapped, ':')) {
                return $mapped;
            }
            $value = is_array($value)
                ? array_replace($value, ['value' => $mapped, 'size' => $mapped])
                : $mapped;
        }
        return $this->replaceCssTokens($declaration, $value, $settings);
    }

    /**
     * Resolve the source control tokens using WPBakery's normalized values.
     *
     * @param mixed $value
     */
    private function replaceCssTokens(string $css, $value, array $settings): string
    {
        $scalar = is_array($value) ? (string) ($value['size'] ?? $value['value'] ?? '') : (string) $value;
        $unit = is_array($value) ? (string) ($value['unit'] ?? '') : '';

        $css = str_replace(
            ['{{VALUE}}', '{{SIZE}}', '{{UNIT}}', '{{TOP}}', '{{RIGHT}}', '{{BOTTOM}}', '{{LEFT}}'],
            [
                $scalar,
                $scalar,
                $unit,
                is_array($value) ? (string) ($value['top'] ?? '') : '',
                is_array($value) ? (string) ($value['right'] ?? '') : '',
                is_array($value) ? (string) ($value['bottom'] ?? '') : '',
                is_array($value) ? (string) ($value['left'] ?? '') : '',
            ],
            $css
        );

        return (string) preg_replace_callback(
            '/\{\{([A-Za-z0-9_]+)\.(VALUE|SIZE|UNIT)\}\}/',
            static function (array $matches) use ($settings): string {
                $setting = $settings[$matches[1]] ?? '';
                if (!is_array($setting)) {
                    return $matches[2] === 'UNIT' ? '' : (string) $setting;
                }

                $key = $matches[2] === 'UNIT' ? 'unit' : ($matches[2] === 'SIZE' ? 'size' : 'value');
                return (string) ($setting[$key] ?? ($key === 'value' ? ($setting['size'] ?? '') : ''));
            },
            $css
        );
    }

    private function conditionsMatch(array $conditions, array $settings): bool
    {
        foreach ($conditions as $key => $expected) {
            $negated = str_ends_with((string) $key, '!');
            $setting = $settings[rtrim((string) $key, '!')] ?? null;
            $matches = is_array($expected)
                ? in_array($setting, $expected, true)
                : (string) $setting === (string) $expected;
            if ($negated ? $matches : !$matches) {
                return false;
            }
        }
        return true;
    }

    private function cssLength(string $value): string
    {
        return is_numeric($value) ? $value . 'px' : $value;
    }

    private function cssAngle(string $value): string
    {
        return is_numeric($value) ? $value . 'deg' : $value;
    }

    private function controlsToParams(Widget_Base $widget): array
    {
        $params = [];
        $group = __('Contenido', 'homlity-real-estate');

        foreach ($widget->get_controls() as $name => $control) {
            $type = (string) ($control['type'] ?? '');
            $controlGroup = trim((string) ($control['section_label'] ?? ''));
            if ($controlGroup !== '') {
                $group = $controlGroup;
            }

            if ($type === 'homlity_group') {
                array_push($params, ...$this->groupControlParams((string) $name, (array) $control, $group));
                continue;
            }

            $param = $this->controlToParam((string) $name, (array) $control, $group);
            if ($param !== null) {
                $params[] = $param;
                if (!empty($control['responsive'])) {
                    foreach (['tablet' => __('Tableta', 'homlity-real-estate'), 'phone' => __('Móvil', 'homlity-real-estate')] as $device => $label) {
                        $responsiveParam = $param;
                        $responsiveParam['param_name'] = $name . '_' . $device;
                        $responsiveParam['heading'] = $param['heading'] . ' — ' . $label;
                        $defaultKey = $device === 'phone' ? 'mobile_default' : 'tablet_default';
                        $default = $control[$defaultKey] ?? '';
                        unset($responsiveParam['std']);
                        $normalizedDefault = is_array($default) ? wp_json_encode($default) : (string) $default;
                        if (in_array($responsiveParam['type'], ['dropdown', 'checkbox'], true)) {
                            $responsiveParam['std'] = $normalizedDefault;
                        } else {
                            $responsiveParam['value'] = $normalizedDefault;
                            $responsiveParam['std'] = $normalizedDefault;
                        }
                        $params[] = $responsiveParam;
                    }
                }
            }
        }

        if ($params === []) {
            $params[] = [
                'type' => 'textfield',
                'heading' => __('Sin opciones adicionales', 'homlity-real-estate'),
                'param_name' => '_homlity_placeholder',
                'group' => $group,
                'edit_field_class' => 'vc_col-sm-12 vc_hidden',
            ];
        }

        $params[] = [
            'type' => 'css_editor',
            'heading' => __('Opciones de diseño', 'homlity-real-estate'),
            'param_name' => 'css',
            'group' => __('Diseño WPBakery', 'homlity-real-estate'),
        ];
        $params[] = [
            'type' => 'textfield',
            'heading' => __('ID del elemento', 'homlity-real-estate'),
            'param_name' => 'el_id',
            'group' => __('Diseño WPBakery', 'homlity-real-estate'),
        ];
        $params[] = [
            'type' => 'textfield',
            'heading' => __('Clase CSS adicional', 'homlity-real-estate'),
            'param_name' => 'el_class',
            'group' => __('Diseño WPBakery', 'homlity-real-estate'),
        ];

        return $params;
    }

    private function controlToParam(string $name, array $control, string $group): ?array
    {
        $type = (string) ($control['type'] ?? '');
        $ignored = ['hidden', 'heading', 'homlity_group', 'raw_html', 'divider', 'popover_toggle', 'tab'];
        if (in_array($type, $ignored, true) || $name === '') {
            return null;
        }

        $param = [
            'type'        => 'textfield',
            'heading'     => (string) ($control['label'] ?? ucwords(str_replace('_', ' ', $name))),
            'param_name'  => $name,
            'description' => wp_strip_all_tags((string) ($control['description'] ?? '')),
            'group'       => $group,
        ];

        $default = $control['default'] ?? '';
        if (is_array($default)) {
            $param['value'] = wp_json_encode($default);
        } elseif ($default !== '') {
            $param['value'] = (string) $default;
            $param['std'] = (string) $default;
        }

        if ($type === Controls_Manager::SELECT2 && !empty($control['multiple'])) {
            $param['type'] = 'checkbox';
            $param['value'] = $this->flipOptions((array) ($control['options'] ?? []));
            $param['std'] = is_array($default) ? implode(',', array_map('strval', $default)) : (string) $default;
        } elseif (in_array($type, ['select', 'select2', 'choose'], true)) {
            $param['type'] = 'dropdown';
            $param['value'] = $this->flipOptions((array) ($control['options'] ?? []));
        } elseif (in_array($type, ['switcher', 'checkbox'], true)) {
            $param['type'] = 'checkbox';
            $onValue = (string) ($control['return_value'] ?? 'yes');
            $param['value'] = [
                (string) ($control['label_on'] ?? __('Sí', 'homlity-real-estate')) => $onValue,
            ];
            $param['std'] = (string) $default;
        } elseif ($type === 'color') {
            $param['type'] = 'colorpicker';
        } elseif ($type === Controls_Manager::REPEATER) {
            $param['type'] = 'param_group';
            $param['params'] = [];
            foreach ((array) ($control['fields'] ?? []) as $fieldName => $fieldControl) {
                $field = $this->controlToParam((string) $fieldName, (array) $fieldControl, $group);
                if ($field !== null) {
                    unset($field['group']);
                    $param['params'][] = $field;
                }
            }
            $param['value'] = '';
        } elseif (in_array($type, ['textarea', 'wysiwyg', 'code'], true)) {
            $param['type'] = 'textarea';
        } elseif (in_array($type, ['media', 'image'], true)) {
            $param['type'] = 'attach_image';
            $param['value'] = is_array($default) ? (string) ($default['id'] ?? '') : '';
        } elseif ($type === 'gallery') {
            $param['type'] = 'attach_images';
        } elseif ($type === 'url') {
            $param['type'] = 'vc_link';
            $param['value'] = '';
        } elseif (in_array($type, ['icon', 'icons'], true)) {
            $param['type'] = 'iconpicker';
            $param['value'] = is_array($default) ? (string) ($default['value'] ?? '') : (string) $default;
        } elseif (in_array($type, ['number', 'slider', 'dimensions'], true)) {
            $param['type'] = 'textfield';
        }

        $dependency = $this->dependency((array) ($control['condition'] ?? []));
        if ($dependency !== null) {
            $param['dependency'] = $dependency;
        }

        return $param;
    }

    private function groupControlParams(string $name, array $control, string $section): array
    {
        $group = strtolower((string) ($control['group_type'] ?? ''));
        $definitions = str_contains($group, 'typography') ? [
            'font_family' => [__('Fuente', 'homlity-real-estate'), 'textfield', false],
            'font_size' => [__('Tamaño de fuente', 'homlity-real-estate'), 'textfield', true],
            'font_weight' => [__('Peso de fuente', 'homlity-real-estate'), 'dropdown', false, [__('Predeterminado', 'homlity-real-estate') => '', '100' => '100', '200' => '200', '300' => '300', '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800', '900' => '900']],
            'text_transform' => [__('Transformación', 'homlity-real-estate'), 'dropdown', false, [__('Predeterminada', 'homlity-real-estate') => '', __('Mayúsculas', 'homlity-real-estate') => 'uppercase', __('Minúsculas', 'homlity-real-estate') => 'lowercase', __('Capitalizar', 'homlity-real-estate') => 'capitalize', __('Ninguna', 'homlity-real-estate') => 'none']],
            'font_style' => [__('Estilo', 'homlity-real-estate'), 'dropdown', false, [__('Predeterminado', 'homlity-real-estate') => '', __('Normal', 'homlity-real-estate') => 'normal', __('Cursiva', 'homlity-real-estate') => 'italic']],
            'text_decoration' => [__('Decoración', 'homlity-real-estate'), 'dropdown', false, [__('Predeterminada', 'homlity-real-estate') => '', __('Ninguna', 'homlity-real-estate') => 'none', __('Subrayado', 'homlity-real-estate') => 'underline', __('Tachado', 'homlity-real-estate') => 'line-through']],
            'line_height' => [__('Altura de línea', 'homlity-real-estate'), 'textfield', true],
            'letter_spacing' => [__('Espaciado entre letras', 'homlity-real-estate'), 'textfield', true],
        ] : (str_contains($group, 'border') ? [
            'border_type' => [__('Tipo de borde', 'homlity-real-estate'), 'dropdown', false, [__('Ninguno', 'homlity-real-estate') => '', __('Sólido', 'homlity-real-estate') => 'solid', __('Doble', 'homlity-real-estate') => 'double', __('Punteado', 'homlity-real-estate') => 'dotted', __('Discontinuo', 'homlity-real-estate') => 'dashed']],
            'border_width' => [__('Ancho de borde', 'homlity-real-estate'), 'textfield', true],
            'border_color' => [__('Color de borde', 'homlity-real-estate'), 'colorpicker', false],
            'border_radius' => [__('Radio del borde', 'homlity-real-estate'), 'textfield', true],
        ] : (str_contains($group, 'background') ? [
            'background_color' => [__('Color de fondo', 'homlity-real-estate'), 'colorpicker', false],
            'background_image' => [__('Imagen de fondo (URL)', 'homlity-real-estate'), 'textfield', false],
            'background_position' => [__('Posición del fondo', 'homlity-real-estate'), 'textfield', false],
            'background_repeat' => [__('Repetición del fondo', 'homlity-real-estate'), 'dropdown', false, [__('Predeterminada', 'homlity-real-estate') => '', __('No repetir', 'homlity-real-estate') => 'no-repeat', __('Repetir', 'homlity-real-estate') => 'repeat', __('Horizontal', 'homlity-real-estate') => 'repeat-x', __('Vertical', 'homlity-real-estate') => 'repeat-y']],
            'background_size' => [__('Tamaño del fondo', 'homlity-real-estate'), 'dropdown', false, [__('Predeterminado', 'homlity-real-estate') => '', __('Cubrir', 'homlity-real-estate') => 'cover', __('Contener', 'homlity-real-estate') => 'contain', __('Automático', 'homlity-real-estate') => 'auto']],
            'background_gradient_color' => [__('Gradiente: color inicial', 'homlity-real-estate'), 'colorpicker', false],
            'background_gradient_color_b' => [__('Gradiente: color final', 'homlity-real-estate'), 'colorpicker', false],
            'background_gradient_type' => [__('Gradiente: tipo', 'homlity-real-estate'), 'dropdown', false, [__('Lineal', 'homlity-real-estate') => 'linear', __('Radial', 'homlity-real-estate') => 'radial']],
            'background_gradient_angle' => [__('Gradiente: ángulo', 'homlity-real-estate'), 'textfield', false],
            'background_gradient_position' => [__('Gradiente: posición', 'homlity-real-estate'), 'textfield', false],
        ] : (str_contains($group, 'shadow') ? [
            'shadow_color' => [__('Color de sombra', 'homlity-real-estate'), 'colorpicker', false],
            'shadow_horizontal' => [__('Desplazamiento horizontal', 'homlity-real-estate'), 'textfield', false],
            'shadow_vertical' => [__('Desplazamiento vertical', 'homlity-real-estate'), 'textfield', false],
            'shadow_blur' => [__('Desenfoque', 'homlity-real-estate'), 'textfield', false],
            'shadow_spread' => [__('Extensión', 'homlity-real-estate'), 'textfield', false],
            'shadow_position' => [__('Posición', 'homlity-real-estate'), 'dropdown', false, [__('Exterior', 'homlity-real-estate') => '', __('Interior', 'homlity-real-estate') => 'inset']],
        ] : [])));

        if (str_contains($group, 'text_shadow')) {
            unset($definitions['shadow_spread'], $definitions['shadow_position']);
        }

        $params = [];
        foreach ($definitions as $suffix => $definition) {
            [$label, $type, $responsive] = $definition;
            $param = [
                'type' => $type,
                'heading' => $label,
                'param_name' => $name . '_' . $suffix,
                'group' => $section,
                'value' => $definition[3] ?? '',
                'dependency' => $this->dependency((array) ($control['condition'] ?? [])),
            ];
            if ($param['dependency'] === null) {
                unset($param['dependency']);
            }
            $params[] = $param;
            if ($responsive) {
                foreach (['tablet' => __('Tableta', 'homlity-real-estate'), 'phone' => __('Móvil', 'homlity-real-estate')] as $device => $deviceLabel) {
                    $copy = $param;
                    $copy['heading'] .= ' — ' . $deviceLabel;
                    $copy['param_name'] .= '_' . $device;
                    $params[] = $copy;
                }
            }
        }
        return $params;
    }

    private function flipOptions(array $options): array
    {
        $values = [];
        foreach ($options as $value => $label) {
            if (is_array($label)) {
                $label = $label['title'] ?? $label['label'] ?? $value;
            }
            $values[wp_strip_all_tags((string) $label)] = (string) $value;
        }
        return $values;
    }

    private function dependency(array $condition): ?array
    {
        if (count($condition) !== 1) {
            return null;
        }

        $field = (string) array_key_first($condition);
        $value = $condition[$field];
        $not = str_ends_with($field, '!');
        $field = rtrim($field, '!');

        if (is_array($value)) {
            return ['element' => $field, 'value' => array_map('strval', $value)];
        }

        return [
            'element' => $field,
            $not ? 'value_not_equal_to' : 'value' => (string) $value,
        ];
    }

    /**
     * @return array<class-string<Widget_Base>>
     */
    private function widgetClasses(): array
    {
        return [
            PropertyFilterWidget::class,
            PropertyListingWidget::class,
            PropertyResultsTitleWidget::class,
            PropertyTitleWidget::class,
            PropertyOperationPriceWidget::class,
            PropertyContentWidget::class,
            PropertySummaryWidget::class,
            PropertyGalleryWidget::class,
            PropertyBreadcrumbWidget::class,
            PropertyMediaTabsWidget::class,
            PropertyVideoWidget::class,
            PropertyTechnicalSheetButtonWidget::class,
            PropertyTechnicalSheetWidget::class,
            PropertyDynamicCodeButtonWidget::class,
            PropertyFaqWidget::class,
            PropertyFeaturedCitiesWidget::class,
            PropertyFeaturedNeighborhoodsWidget::class,
            PropertyFeaturedOperationsWidget::class,
            PropertyFeaturedTypesWidget::class,
            PropertyFeaturedTermsWidget::class,
            PropertyAgentsAvailableWidget::class,
            PropertyFeaturesPrimaryWidget::class,
            PropertyFeaturesSecondaryWidget::class,
            PropertyMapWidget::class,
            PropertyAgentWidget::class,
            PropertyShareWidget::class,
            PropertyRelatedWidget::class,
            PropertyCardWidget::class,
            SimulatorWidget::class,
        ];
    }

    private function listingParams(): array
    {
        return [
            ['type' => 'dropdown', 'heading' => __('Diseño de plantilla', 'homlity-real-estate'), 'param_name' => 'template', 'value' => [__('Predeterminado (CSS propio)', 'homlity-real-estate') => 'default', __('Bootstrap 5', 'homlity-real-estate') => 'bootstrap'], 'std' => 'default', 'group' => __('Presentación', 'homlity-real-estate')],
            ['type' => 'dropdown', 'heading' => __('Vista por defecto', 'homlity-real-estate'), 'param_name' => 'view', 'value' => [__('Grilla / Cards', 'homlity-real-estate') => 'grid', __('Mapa', 'homlity-real-estate') => 'map'], 'std' => 'grid', 'group' => __('Presentación', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Botón para cambiar de vista', 'homlity-real-estate'), 'param_name' => 'view_toggle', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Presentación', 'homlity-real-estate')],
            ['type' => 'dropdown', 'heading' => __('Columnas en grilla', 'homlity-real-estate'), 'param_name' => 'columns', 'value' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'], 'std' => '3', 'group' => __('Presentación', 'homlity-real-estate')],
            ['type' => 'textfield', 'heading' => __('Inmuebles por página', 'homlity-real-estate'), 'param_name' => 'per_page', 'value' => '12', 'description' => __('Número entre 1 y 100.', 'homlity-real-estate'), 'group' => __('Consulta', 'homlity-real-estate')],
            ['type' => 'dropdown', 'heading' => __('Orden por defecto', 'homlity-real-estate'), 'param_name' => 'orderby', 'value' => [__('Más recientes', 'homlity-real-estate') => 'date', __('Precio: menor a mayor', 'homlity-real-estate') => 'price_asc', __('Precio: mayor a menor', 'homlity-real-estate') => 'price_desc', __('Nombre A–Z', 'homlity-real-estate') => 'title'], 'std' => 'date', 'group' => __('Consulta', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Solo destacados', 'homlity-real-estate'), 'param_name' => 'featured', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'group' => __('Consulta', 'homlity-real-estate')],
            ['type' => 'textfield', 'heading' => __('ID de término: Gestión fija', 'homlity-real-estate'), 'param_name' => 'operation', 'value' => '0', 'group' => __('Consulta', 'homlity-real-estate')],
            ['type' => 'textfield', 'heading' => __('ID de término: Tipo fijo', 'homlity-real-estate'), 'param_name' => 'type', 'value' => '0', 'group' => __('Consulta', 'homlity-real-estate')],
            ['type' => 'textfield', 'heading' => __('ID de localidad fija', 'homlity-real-estate'), 'param_name' => 'locality', 'value' => '0', 'group' => __('Consulta', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Mostrar panel de filtros', 'homlity-real-estate'), 'param_name' => 'filters', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Filtro: Gestión', 'homlity-real-estate'), 'param_name' => 'filter_operation', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Filtro: Tipo de inmueble', 'homlity-real-estate'), 'param_name' => 'filter_type', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Filtro: Ciudad', 'homlity-real-estate'), 'param_name' => 'filter_city', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Filtro: Rango de precio', 'homlity-real-estate'), 'param_name' => 'filter_price', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Filtro: Habitaciones', 'homlity-real-estate'), 'param_name' => 'filter_bedrooms', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Mostrar selector de orden', 'homlity-real-estate'), 'param_name' => 'sort', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'textfield', 'heading' => __('Altura del mapa (px)', 'homlity-real-estate'), 'param_name' => 'map_height', 'value' => '500', 'group' => __('Mapa', 'homlity-real-estate')],
            ['type' => 'textfield', 'heading' => __('Zoom inicial del mapa', 'homlity-real-estate'), 'param_name' => 'map_zoom', 'value' => '12', 'group' => __('Mapa', 'homlity-real-estate')],
        ];
    }
}
