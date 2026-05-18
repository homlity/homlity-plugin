<?php
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
    private const OPTION_ARCHIVE_LAYOUT = 'homlity_plugin_archive_page_layout';
    private const OPTION_SINGLE_LAYOUT  = 'homlity_plugin_single_page_layout';
    private const OPTION_UNAVAILABLE_LAYOUT  = 'homlity_plugin_unavailable_page_layout';
    private const NONCE_ACTION   = 'homlity_elementor_templates_save';
    private const NONCE_FIELD    = 'homlity_elementor_templates_nonce';
    private const PAGE_SLUG      = 'homlity-plugin-elementor-templates';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'handleSave']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'homlity-plugin-settings',
            __('Plantillas Elementor', 'homlity-plugin'),
            __('Plantillas Elementor', 'homlity-plugin'),
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

        $archiveId = isset($_POST['archive_page_id']) ? absint($_POST['archive_page_id']) : 0;
        $singleId  = isset($_POST['single_template_id']) ? absint($_POST['single_template_id']) : 0;
        $unavailableId  = isset($_POST['unavailable_template_id']) ? absint($_POST['unavailable_template_id']) : 0;
        $archiveLayout = isset($_POST['archive_page_layout']) ? sanitize_text_field(wp_unslash($_POST['archive_page_layout'])) : 'default';
        $singleLayout  = isset($_POST['single_page_layout']) ? sanitize_text_field(wp_unslash($_POST['single_page_layout'])) : 'default';
        $unavailableLayout  = isset($_POST['unavailable_page_layout']) ? sanitize_text_field(wp_unslash($_POST['unavailable_page_layout'])) : 'default';

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

        update_option(self::OPTION_ARCHIVE, $archiveId);
        update_option(self::OPTION_SINGLE, $singleId);
        update_option(self::OPTION_UNAVAILABLE, $unavailableId);
        update_option(self::OPTION_ARCHIVE_LAYOUT, $archiveLayout);
        update_option(self::OPTION_SINGLE_LAYOUT, $singleLayout);
        update_option(self::OPTION_UNAVAILABLE_LAYOUT, $unavailableLayout);

        if ($archiveId > 0) {
            $this->applyLayoutToPost($archiveId, $archiveLayout);
        }
        if ($singleId > 0) {
            $this->applyLayoutToPost($singleId, $singleLayout);
        }
        if ($unavailableId > 0) {
            $this->applyLayoutToPost($unavailableId, $unavailableLayout);
        }

        // Regenerar reglas de reescritura porque archive_page_id puede haber cambiado
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

        $archiveId = (int) get_option(self::OPTION_ARCHIVE, 0);
        $singleId  = (int) get_option(self::OPTION_SINGLE, 0);
        $unavailableId  = (int) get_option(self::OPTION_UNAVAILABLE, 0);
        $archiveLayout = (string) get_option(self::OPTION_ARCHIVE_LAYOUT, 'default');
        $singleLayout  = (string) get_option(self::OPTION_SINGLE_LAYOUT, 'default');
        $unavailableLayout  = (string) get_option(self::OPTION_UNAVAILABLE_LAYOUT, 'default');
        $pages     = $this->getElementorPages();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Plantillas Elementor para inmuebles', 'homlity-plugin'); ?></h1>

            <?php if (!empty($_GET['saved'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Configuración guardada correctamente.', 'homlity-plugin'); ?></p>
                </div>
            <?php endif; ?>

            <p class="description" style="max-width:700px;margin-bottom:20px;">
                <?php esc_html_e(
                    'Selecciona qué página construida con Elementor se usará como plantilla para el listado y el detalle de inmuebles. Usa páginas con el layout "Elementor Canvas" (★ Canvas) para tener control total del diseño sin header ni footer del tema.',
                    'homlity-plugin'
                ); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="archive_page_id">
                                <?php esc_html_e('Página de listado de inmuebles', 'homlity-plugin'); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderPageSelect('archive_page_id', $archiveId, $pages); ?>
                            <p class="description">
                                <?php esc_html_e(
                                    'Página de Elementor que se mostrará en /inmuebles/ y en las rutas de búsqueda filtrada (/inmuebles/gestion/venta/, etc.).',
                                    'homlity-plugin'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="archive_page_layout">
                                <?php esc_html_e('Layout página de listado', 'homlity-plugin'); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderLayoutSelect('archive_page_layout', $archiveLayout); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="single_template_id">
                                <?php esc_html_e('Plantilla de detalle de inmueble', 'homlity-plugin'); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderPageSelect('single_template_id', $singleId, $pages); ?>
                            <p class="description">
                                <?php esc_html_e(
                                    'Página de Elementor cuyo layout y widgets se usarán para mostrar cada inmueble. Los widgets de propiedad leerán automáticamente los datos del inmueble actual.',
                                    'homlity-plugin'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="single_page_layout">
                                <?php esc_html_e('Layout página de detalle', 'homlity-plugin'); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderLayoutSelect('single_page_layout', $singleLayout); ?>
                            <p class="description"><?php esc_html_e('Canvas en detalle renderiza solo el contenido Elementor (sin header/footer del tema).', 'homlity-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="unavailable_template_id">
                                <?php esc_html_e('Plantilla inmueble no disponible', 'homlity-plugin'); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderPageSelect('unavailable_template_id', $unavailableId, $pages); ?>
                            <p class="description">
                                <?php esc_html_e(
                                    'Se usa cuando un inmueble deja de estar público. La URL responde 410 (SEO) y muestra esta plantilla Elementor.',
                                    'homlity-plugin'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="unavailable_page_layout">
                                <?php esc_html_e('Layout página no disponible', 'homlity-plugin'); ?>
                            </label>
                        </th>
                        <td>
                            <?php $this->renderLayoutSelect('unavailable_page_layout', $unavailableLayout); ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Guardar cambios', 'homlity-plugin')); ?>
            </form>

            <?php if (!empty($pages)): ?>
                <hr>
                <h2><?php esc_html_e('Páginas con Elementor disponibles', 'homlity-plugin'); ?></h2>
                <table class="widefat striped" style="max-width:750px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Título', 'homlity-plugin'); ?></th>
                            <th><?php esc_html_e('Layout', 'homlity-plugin'); ?></th>
                            <th><?php esc_html_e('Estado', 'homlity-plugin'); ?></th>
                            <th><?php esc_html_e('Acción', 'homlity-plugin'); ?></th>
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
                                        <?php esc_html_e('Editar con Elementor', 'homlity-plugin'); ?> ↗
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif (!class_exists('\Elementor\Plugin')): ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('Elementor no está activo. Activa Elementor para poder seleccionar plantillas.', 'homlity-plugin'); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-info inline">
                    <p><?php esc_html_e('No se encontraron páginas construidas con Elementor. Crea una página nueva y edítala con Elementor.', 'homlity-plugin'); ?></p>
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
        echo '<option value="0">' . esc_html__('— Sin plantilla personalizada —', 'homlity-plugin') . '</option>';

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
            'default' => __('Predeterminado del tema', 'homlity-plugin'),
            'elementor-full-width' => __('Elementor Full Width', 'homlity-plugin'),
            'elementor_canvas' => __('Elementor Canvas (Ancho completo)', 'homlity-plugin'),
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

    /**
     * Genera el badge HTML de color para el tipo de layout.
     */
    private function layoutBadge(string $template): string
    {
        [$label, $color] = match ($template) {
            'elementor_canvas'     => [__('Canvas (Ancho completo)', 'homlity-plugin'), '#0ea5e9'],
            'elementor-full-width' => [__('Full Width', 'homlity-plugin'), '#22c55e'],
            default                => [__('Predeterminado del tema', 'homlity-plugin'), '#94a3b8'],
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
            'publish' => __('Publicada', 'homlity-plugin'),
            'draft'   => __('Borrador', 'homlity-plugin'),
            'private' => __('Privada', 'homlity-plugin'),
        ];

        $pages = [];
        foreach ($posts as $post) {
            $template = get_post_meta($post->ID, '_wp_page_template', true) ?: 'default';
            $pages[]  = [
                'id'           => $post->ID,
                'title'        => $post->post_title ?: __('(sin título)', 'homlity-plugin'),
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
