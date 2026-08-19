<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
/**
 * Admin settings page for selecting Elementor templates for property pages.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class ElementorTemplateSettingsService implements ServiceInterface
{
    private const OPTION_ARCHIVE = 'homlity_plugin_archive_page_id';
    private const OPTION_SINGLE  = 'homlity_plugin_single_template_id';
    private const OPTION_UNAVAILABLE = 'homlity_plugin_unavailable_template_id';
    private const OPTION_AGENT_PROFILE = 'homlity_plugin_agent_profile_page_id';
    private const OPTION_SHEET_PAGE = 'homlity_plugin_sheet_page_id';
    private const OPTION_ARCHIVE_LAYOUT = 'homlity_plugin_archive_page_layout';
    private const OPTION_SINGLE_LAYOUT  = 'homlity_plugin_single_page_layout';
    private const OPTION_UNAVAILABLE_LAYOUT  = 'homlity_plugin_unavailable_page_layout';

    // ── Recovery settings ─────────────────────────────────────────────────────
    private const OPTION_RECOVERY_ENABLED        = 'homlity_recovery_enabled';
    private const OPTION_RECOVERY_MIN_RESULTS    = 'homlity_recovery_min_results';
    private const OPTION_RECOVERY_MAX_RESULTS    = 'homlity_recovery_max_results';
    private const OPTION_RECOVERY_PRICE_TOL      = 'homlity_recovery_price_tolerance';
    private const OPTION_RECOVERY_AREA_TOL       = 'homlity_recovery_area_tolerance';
    private const OPTION_RECOVERY_NO_RESULTS_ACT = 'homlity_recovery_no_results_action';

    private const NONCE_ACTION   = 'homlity_elementor_templates_save';
    private const NONCE_FIELD    = 'homlity_elementor_templates_nonce';
    private const PAGE_SLUG      = 'homlity-real-estate-elementor-templates';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'handleSave']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'homlity-real-estate-settings',
            __('Plantillas Elementor', 'homlity-real-estate'),
            __('Plantillas Elementor', 'homlity-real-estate'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function handleSave(): void
    {
        if (empty($_POST[self::NONCE_FIELD])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $archiveId         = isset($_POST['archive_page_id'])        ? absint($_POST['archive_page_id'])        : 0;
        $singleId          = isset($_POST['single_template_id'])     ? absint($_POST['single_template_id'])     : 0;
        $unavailableId     = isset($_POST['unavailable_template_id']) ? absint($_POST['unavailable_template_id']) : 0;
        $archiveLayout     = isset($_POST['archive_page_layout'])     ? sanitize_text_field(wp_unslash($_POST['archive_page_layout']))    : 'default';
        $singleLayout      = isset($_POST['single_page_layout'])      ? sanitize_text_field(wp_unslash($_POST['single_page_layout']))     : 'default';
        $unavailableLayout = isset($_POST['unavailable_page_layout']) ? sanitize_text_field(wp_unslash($_POST['unavailable_page_layout'])) : 'default';

        $allowedLayouts = ['default', 'elementor-full-width', 'elementor_canvas'];
        if (!in_array($archiveLayout, $allowedLayouts, true)) {
            $archiveLayout = 'default';
        }
        if (!in_array($singleLayout, $allowedLayouts, true)) {
            $singleLayout = 'default';
        }
        if (!in_array($unavailableLayout, $allowedLayouts, true)) {
            $unavailableLayout = 'default';
        }

        $archiveId = $this->isElementorDocument($archiveId) ? $archiveId : 0;
        $singleId = $this->isElementorDocument($singleId) ? $singleId : 0;
        $unavailableId = $this->isElementorDocument($unavailableId) ? $unavailableId : 0;

        // Builder-agnostic: the advisor profile page may be built with
        // Elementor, Divi or WPBakery, so it is not validated as an Elementor
        // document. An empty value falls back to the plugin template.
        $agentProfileId = isset($_POST['agent_profile_page_id']) ? absint($_POST['agent_profile_page_id']) : 0;
        if ($agentProfileId > 0 && get_post_type($agentProfileId) !== 'page') {
            $agentProfileId = 0;
        }
        update_option(self::OPTION_AGENT_PROFILE, $agentProfileId);

        // Same rule for the technical sheet page: any builder may own it.
        $sheetPageId = isset($_POST['sheet_page_id']) ? absint($_POST['sheet_page_id']) : 0;
        if ($sheetPageId > 0 && get_post_type($sheetPageId) !== 'page') {
            $sheetPageId = 0;
        }
        update_option(self::OPTION_SHEET_PAGE, $sheetPageId);

        update_option(self::OPTION_ARCHIVE, $archiveId);
        update_option(self::OPTION_SINGLE, $singleId);
        update_option(self::OPTION_UNAVAILABLE, $unavailableId);
        update_option(self::OPTION_ARCHIVE_LAYOUT, $archiveLayout);
        update_option(self::OPTION_SINGLE_LAYOUT, $singleLayout);
        update_option(self::OPTION_UNAVAILABLE_LAYOUT, $unavailableLayout);

        if ($archiveId > 0 || $singleId > 0 || $unavailableId > 0) {
            update_option('homlity_plugin_visual_builder', 'elementor');
            update_option('homlity_plugin_visual_builder_explicit', '1');
        }

        // ── Recovery settings ─────────────────────────────────────────────────
        $recoveryEnabled = !empty($_POST['recovery_enabled']) ? '1' : '0';
        update_option(self::OPTION_RECOVERY_ENABLED, $recoveryEnabled);

        $recoveryMin = isset($_POST['recovery_min_results']) ? max(1, absint($_POST['recovery_min_results'])) : 3;
        update_option(self::OPTION_RECOVERY_MIN_RESULTS, $recoveryMin);

        $recoveryMax = isset($_POST['recovery_max_results']) ? max(1, absint($_POST['recovery_max_results'])) : 12;
        update_option(self::OPTION_RECOVERY_MAX_RESULTS, $recoveryMax);

        // Admin form sends 0-100 %; RetiredPropertyRecoveryService expects 0.0-1.0.
        $recoveryPriceTol = isset($_POST['recovery_price_tolerance'])
            ? min(1.0, max(0.0, (float) $_POST['recovery_price_tolerance'] / 100))
            : 0.20;
        update_option(self::OPTION_RECOVERY_PRICE_TOL, $recoveryPriceTol);

        $recoveryAreaTol = isset($_POST['recovery_area_tolerance'])
            ? min(1.0, max(0.0, (float) $_POST['recovery_area_tolerance'] / 100))
            : 0.20;
        update_option(self::OPTION_RECOVERY_AREA_TOL, $recoveryAreaTol);

        $allowedNoResultsActions = ['noindex', '404', '410'];
        $recoveryNoResultsAct = isset($_POST['recovery_no_results_action'])
            ? sanitize_key(wp_unslash((string) $_POST['recovery_no_results_action']))
            : 'noindex';
        if (!in_array($recoveryNoResultsAct, $allowedNoResultsActions, true)) {
            $recoveryNoResultsAct = 'noindex';
        }
        update_option(self::OPTION_RECOVERY_NO_RESULTS_ACT, $recoveryNoResultsAct);

        if ($archiveId > 0) {
            $this->prepareElementorDocument($archiveId);
            $this->applyLayoutToPost($archiveId, $archiveLayout);
        }
        if ($singleId > 0) {
            $this->prepareElementorDocument($singleId);
            $this->applyLayoutToPost($singleId, $singleLayout);
        }
        if ($unavailableId > 0) {
            $this->prepareElementorDocument($unavailableId);
            $this->applyLayoutToPost($unavailableId, $unavailableLayout);
        }

        // Regenerar reglas de reescritura porque archive_page_id, la página de
        // perfil del asesor o la de ficha técnica pueden haber cambiado. Se vuelven a registrar antes
        // del flush: las reglas añadidas en `init` todavía llevan los IDs
        // anteriores y quedarían grabadas tal cual.
        (new TemplateService())->addRewriteRules();
        flush_rewrite_rules();

        wp_safe_redirect(add_query_arg([
            'page'  => self::PAGE_SLUG,
            'saved' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $archiveId         = (int)    get_option(self::OPTION_ARCHIVE, 0);
        $singleId          = (int)    get_option(self::OPTION_SINGLE, 0);
        $unavailableId     = (int)    get_option(self::OPTION_UNAVAILABLE, 0);
        $agentProfileId    = (int)    get_option(self::OPTION_AGENT_PROFILE, 0);
        $sheetPageId       = (int)    get_option(self::OPTION_SHEET_PAGE, 0);
        $archiveLayout     = (string) get_option(self::OPTION_ARCHIVE_LAYOUT, 'default');
        $singleLayout      = (string) get_option(self::OPTION_SINGLE_LAYOUT, 'default');
        $unavailableLayout = (string) get_option(self::OPTION_UNAVAILABLE_LAYOUT, 'default');
        $pages             = $this->getElementorPages();

        // Recovery settings
        $recoveryEnabled      = (string) get_option(self::OPTION_RECOVERY_ENABLED, '1');
        $recoveryMin          = (int)    get_option(self::OPTION_RECOVERY_MIN_RESULTS, 3);
        $recoveryMax          = (int)    get_option(self::OPTION_RECOVERY_MAX_RESULTS, 12);
        $recoveryPriceTol     = (float)  get_option(self::OPTION_RECOVERY_PRICE_TOL, 0.20);
        $recoveryAreaTol      = (float)  get_option(self::OPTION_RECOVERY_AREA_TOL, 0.20);
        $recoveryNoResultsAct = (string) get_option(self::OPTION_RECOVERY_NO_RESULTS_ACT, 'noindex');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Plantillas Elementor para inmuebles', 'homlity-real-estate'); ?></h1>

            <?php if (!empty($_GET['saved'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Configuración guardada correctamente.', 'homlity-real-estate'); ?></p>
                </div>
            <?php endif; ?>

            <p class="description" style="max-width:700px;margin-bottom:20px;">
                <?php esc_html_e(
                    'Selecciona qué página construida con Elementor se usará como plantilla para el listado y el detalle de inmuebles. Usa páginas con el layout "Elementor Canvas" (★ Canvas) para tener control total del diseño sin header ni footer del tema.',
                    'homlity-real-estate'
                ); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="archive_page_id">
                                <?php esc_html_e('Página de listado de inmuebles', 'homlity-real-estate'); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderPageSelect('archive_page_id', $archiveId, $pages); ?>
                            <p class="description">
                                <?php esc_html_e(
                                    'Página de Elementor que se mostrará en /inmuebles/ y en las rutas de búsqueda filtrada (/inmuebles/gestion/venta/, etc.).',
                                    'homlity-real-estate'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="archive_page_layout">
                                <?php esc_html_e('Layout página de listado', 'homlity-real-estate'); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderLayoutSelect('archive_page_layout', $archiveLayout); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="single_template_id">
                                <?php esc_html_e('Plantilla de detalle de inmueble', 'homlity-real-estate'); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderPageSelect('single_template_id', $singleId, $pages); ?>
                            <p class="description">
                                <?php esc_html_e(
                                    'Página de Elementor cuyo layout y widgets se usarán para mostrar cada inmueble. Los widgets de propiedad leerán automáticamente los datos del inmueble actual.',
                                    'homlity-real-estate'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="single_page_layout">
                                <?php esc_html_e('Layout página de detalle', 'homlity-real-estate'); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderLayoutSelect('single_page_layout', $singleLayout); ?>
                            <p class="description"><?php esc_html_e('Canvas en detalle renderiza solo el contenido Elementor (sin header/footer del tema).', 'homlity-real-estate'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="unavailable_template_id">
                                <?php esc_html_e('Plantilla inmueble no disponible', 'homlity-real-estate'); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderPageSelect('unavailable_template_id', $unavailableId, $pages); ?>
                            <p class="description">
                                <?php esc_html_e(
                                    'Se usa cuando un inmueble deja de estar público. La URL responde 410 (SEO) y muestra esta plantilla Elementor.',
                                    'homlity-real-estate'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="unavailable_page_layout">
                                <?php esc_html_e('Layout página no disponible', 'homlity-real-estate'); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderLayoutSelect('unavailable_page_layout', $unavailableLayout); ?>
                            <p class="description">
                                <?php esc_html_e(
                                    'Dentro de la página puedes usar los shortcodes: [homlity_unavailable_notice], [homlity_unavailable_similar_properties] y [homlity_unavailable_search_context].',
                                    'homlity-real-estate'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="agent_profile_page_id">
                                <?php esc_html_e('Página de perfil del asesor', 'homlity-real-estate'); ?>
                            </label>
                        </th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'id' => 'agent_profile_page_id',
                                'name' => 'agent_profile_page_id',
                                'selected' => $agentProfileId,
                                'show_option_none' => __('— Usar plantilla del plugin —', 'homlity-real-estate'),
                                'option_none_value' => '0',
                                'post_status' => ['publish', 'draft', 'pending'],
                            ]);
                            ?>
                            <p class="description">
                                <?php esc_html_e(
                                    'Diseña esta página con Elementor, Divi o WPBakery y se usará para cada asesor en /property-agent/{asesor}/. Coloca el widget "Asesor del inmueble" en modo "Asesor de la página" y el widget de listado con "Inmuebles del asesor de la página" activado.',
                                    'homlity-real-estate'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sheet_page_id">
                                <?php esc_html_e('Página de ficha técnica', 'homlity-real-estate'); ?>
                            </label>
                        </th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'id' => 'sheet_page_id',
                                'name' => 'sheet_page_id',
                                'selected' => $sheetPageId,
                                'show_option_none' => __('— Usar plantilla del plugin —', 'homlity-real-estate'),
                                'option_none_value' => '0',
                                'post_status' => ['publish', 'draft', 'pending'],
                            ]);
                            ?>
                            <p class="description">
                                <?php esc_html_e(
                                    'Diseña esta página con Elementor, Divi o WPBakery y se usará para cada inmueble en /ficha-tecnica/{inmueble}/. Coloca el widget "Ficha técnica del inmueble": desde ahí controlas espacios, márgenes, colores y qué secciones se muestran.',
                                    'homlity-real-estate'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <hr style="margin:2rem 0;">
                <h2><?php esc_html_e('SEO y recuperación de inmuebles retirados', 'homlity-real-estate'); ?></h2>
                <p class="description" style="max-width:700px;margin-bottom:20px;">
                    <?php esc_html_e(
                        'Cuando la recuperación está activa, la URL de un inmueble retirado devuelve una landing con propiedades similares en lugar de un 404/410 vacío, conservando la intención comercial y el tráfico SEO.',
                        'homlity-real-estate'
                    ); ?>
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Activar recuperación', 'homlity-real-estate'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="recovery_enabled" value="1"
                                    <?php checked($recoveryEnabled, '1'); ?>>
                                <?php esc_html_e('Mostrar página de recuperación para inmuebles retirados', 'homlity-real-estate'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Si está desactivado, los inmuebles retirados devuelven HTTP 410 y muestran la plantilla "Inmueble no disponible".', 'homlity-real-estate'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="recovery_min_results">
                                <?php esc_html_e('Mínimo de resultados para indexar', 'homlity-real-estate'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" id="recovery_min_results" name="recovery_min_results"
                                   value="<?php echo esc_attr($recoveryMin); ?>"
                                   min="1" max="50" style="width:80px;">
                            <p class="description">
                                <?php esc_html_e('Si hay menos propiedades similares que este valor, se aplica la acción "Sin resultados" (ver abajo).', 'homlity-real-estate'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="recovery_max_results">
                                <?php esc_html_e('Máximo de resultados similares', 'homlity-real-estate'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" id="recovery_max_results" name="recovery_max_results"
                                   value="<?php echo esc_attr($recoveryMax); ?>"
                                   min="1" max="100" style="width:80px;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="recovery_price_tolerance">
                                <?php esc_html_e('Tolerancia de precio (%)', 'homlity-real-estate'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" id="recovery_price_tolerance" name="recovery_price_tolerance"
                                   value="<?php echo esc_attr(round($recoveryPriceTol * 100, 0)); ?>"
                                   min="0" max="100" step="5" style="width:80px;">
                            <span class="description">%</span>
                            <p class="description">
                                <?php esc_html_e('Rango de precio para considerar un inmueble como similar. Ejemplo: 20 = ±20% del precio original.', 'homlity-real-estate'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="recovery_area_tolerance">
                                <?php esc_html_e('Tolerancia de área (%)', 'homlity-real-estate'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" id="recovery_area_tolerance" name="recovery_area_tolerance"
                                   value="<?php echo esc_attr(round($recoveryAreaTol * 100, 0)); ?>"
                                   min="0" max="100" step="5" style="width:80px;">
                            <span class="description">%</span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="recovery_no_results_action">
                                <?php esc_html_e('Acción si no hay resultados similares', 'homlity-real-estate'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="recovery_no_results_action" name="recovery_no_results_action">
                                <option value="noindex" <?php selected($recoveryNoResultsAct, 'noindex'); ?>>
                                    <?php esc_html_e('noindex, follow (mantiene la URL pero la excluye de Google)', 'homlity-real-estate'); ?>
                                </option>
                                <option value="404" <?php selected($recoveryNoResultsAct, '404'); ?>>
                                    <?php esc_html_e('HTTP 404 (página no encontrada)', 'homlity-real-estate'); ?>
                                </option>
                                <option value="410" <?php selected($recoveryNoResultsAct, '410'); ?>>
                                    <?php esc_html_e('HTTP 410 (eliminado permanentemente)', 'homlity-real-estate'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
            </form>

            <?php if (!empty($pages)): ?>
                <hr>
                <h2><?php esc_html_e('Páginas con Elementor disponibles', 'homlity-real-estate'); ?></h2>
                <table class="widefat striped" style="max-width:750px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Título', 'homlity-real-estate'); ?></th>
                            <th><?php esc_html_e('Layout', 'homlity-real-estate'); ?></th>
                            <th><?php esc_html_e('Estado', 'homlity-real-estate'); ?></th>
                            <th><?php esc_html_e('Acción', 'homlity-real-estate'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td><?php echo esc_html($page['title']); ?></td>
                                <td><?php echo wp_kses_post( $this->layoutBadge($page['template']) ); ?></td>
                                <td><?php echo esc_html($page['status_label']); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $page['id'] . '&action=elementor')); ?>" target="_blank">
                                        <?php esc_html_e('Editar con Elementor', 'homlity-real-estate'); ?> ↗
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif (!class_exists('\Elementor\Plugin')): ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('Elementor no está activo. Activa Elementor para poder seleccionar plantillas.', 'homlity-real-estate'); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-info inline">
                    <p><?php esc_html_e('No se encontraron páginas construidas con Elementor. Crea una página nueva y edítala con Elementor.', 'homlity-real-estate'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderiza el <select> con las páginas de Elementor disponibles.
     *
     * @param array<int, array<string, mixed>> $pages
     */
    private function renderPageSelect(string $name, int $selected, array $pages): void
    {
        echo '<select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" style="min-width:380px;">';
        echo '<option value="0">' . esc_html__('— Sin plantilla personalizada —', 'homlity-real-estate') . '</option>';

        foreach ($pages as $page) {
            $badge = match ($page['template']) {
                'elementor_canvas'     => ' ★ Canvas',
                'elementor-full-width' => ' ★ Full Width',
                default                => '',
            };

            printf(
                '<option value="%d"%s>%s%s (%s)</option>',
                (int) $page['id'],
                selected($selected, $page['id'], false),
                esc_html($page['title']),
                esc_html($badge),
                esc_html($page['status_label'])
            );
        }

        echo '</select>';
    }

    private function renderLayoutSelect(string $name, string $selected): void
    {
        $options = [
            'default' => __('Predeterminado del tema', 'homlity-real-estate'),
            'elementor-full-width' => __('Elementor Full Width', 'homlity-real-estate'),
            'elementor_canvas' => __('Elementor Canvas (Ancho completo)', 'homlity-real-estate'),
        ];

        echo '<select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" style="min-width:280px;">';
        foreach ($options as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($selected, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    private function applyLayoutToPost(int $postId, string $layout): void
    {
        $template = match ($layout) {
            'elementor_canvas' => 'elementor_canvas',
            'elementor-full-width' => 'elementor-full-width',
            default => 'default',
        };

        update_post_meta($postId, '_wp_page_template', $template);
    }

    private function isElementorDocument(int $postId): bool
    {
        if ($postId <= 0 || !get_post_status($postId)) {
            return false;
        }

        if (!in_array(get_post_type($postId), ['page', 'elementor_library'], true)) {
            return false;
        }

        return get_post_meta($postId, '_elementor_edit_mode', true) === 'builder';
    }

    /**
     * Remove stale ownership flags left by a previous page builder. Keeping
     * both Elementor and Divi flags makes both builders claim the same page,
     * which breaks the editor links and can make the wrong renderer win.
     */
    private function prepareElementorDocument(int $postId): void
    {
        foreach ([
            '_et_pb_use_builder',
            '_et_pb_page_layout',
            '_et_pb_built_for_post_type',
            '_wpb_vc_js_status',
            '_vc_post_settings',
        ] as $metaKey) {
            delete_post_meta($postId, $metaKey);
        }

        if (get_post_meta($postId, '_homlity_seeded_builder', true) !== '') {
            update_post_meta($postId, '_homlity_seeded_builder', 'elementor');
        }

        clean_post_cache($postId);
    }

    /**
     * Genera el badge HTML de color para el tipo de layout.
     */
    private function layoutBadge(string $template): string
    {
        [$label, $color] = match ($template) {
            'elementor_canvas'     => [__('Canvas (Ancho completo)', 'homlity-real-estate'), '#0ea5e9'],
            'elementor-full-width' => [__('Full Width', 'homlity-real-estate'), '#22c55e'],
            default                => [__('Predeterminado del tema', 'homlity-real-estate'), '#94a3b8'],
        };

        return sprintf(
            '<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:%s;color:#fff;font-size:11px;">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }

    /**
     * Devuelve todas las páginas/posts construidos con Elementor, ordenadas por layout.
     *
     * @return array<int, array{id:int, title:string, template:string, status_label:string}>
     */
    private function getElementorPages(): array
    {
        global $wpdb;

        $postIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_elementor_edit_mode' AND meta_value = %s
                 LIMIT 300",
                'builder'
            )
        );

        if (empty($postIds)) {
            return [];
        }

        $posts = get_posts([
            'post__in'       => array_map('intval', $postIds),
            'post_type'      => ['page', 'elementor_library'],
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 300,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $statusLabels = [
            'publish' => __('Publicada', 'homlity-real-estate'),
            'draft'   => __('Borrador', 'homlity-real-estate'),
            'private' => __('Privada', 'homlity-real-estate'),
        ];

        $pages = [];
        foreach ($posts as $post) {
            $template = get_post_meta($post->ID, '_wp_page_template', true) ?: 'default';
            $pages[]  = [
                'id'           => $post->ID,
                'title'        => $post->post_title ?: __('(sin título)', 'homlity-real-estate'),
                'template'     => $template,
                'status_label' => $statusLabels[$post->post_status] ?? $post->post_status,
            ];
        }

        // Canvas primero, Full Width segundo, resto alfabético
        usort($pages, static function (array $a, array $b): int {
            $priority = ['elementor_canvas' => 0, 'elementor-full-width' => 1];
            $pa = $priority[$a['template']] ?? 2;
            $pb = $priority[$b['template']] ?? 2;

            return $pa !== $pb ? $pa - $pb : strcmp($a['title'], $b['title']);
        });

        return $pages;
    }
}
