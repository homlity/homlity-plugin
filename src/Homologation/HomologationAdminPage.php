<?php

namespace Homlity\PluginInmobiliario\Homologation;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin page for managing homologation mappings.
 *
 * Registers itself as a submenu under "Configuración" via the
 * homlity_plugin_register_integration_submenus action hook.
 *
 * URL: wp-admin/admin.php?page=homlity-homologation
 */
class HomologationAdminPage implements ServiceInterface
{
    public function __construct(
        private readonly HomologationService $service = new HomologationService(),
    ) {}

    public function register(): void
    {
        add_action('homlity_plugin_register_integration_submenus', [$this, 'registerSubmenu']);
        add_action('admin_post_homlity_homologation_add', [$this, 'handleAddMapping']);
    }

    public function registerSubmenu(string $parentSlug): void
    {
        add_submenu_page(
            $parentSlug,
            __('Homologación', 'homlity-plugin'),
            __('Homologación', 'homlity-plugin'),
            'manage_options',
            'homlity-homologation',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Acceso denegado.', 'homlity-plugin'));
        }

        $stats        = $this->service->getStats();
        $sources      = $this->service->getSources();
        $entityFilter = isset($_GET['entity_type']) ? sanitize_text_field(wp_unslash($_GET['entity_type'])) : '';
        $sourceFilter = isset($_GET['source']) ? sanitize_text_field(wp_unslash($_GET['source'])) : '';
        $currentPage  = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;
        $perPage      = 50;

        $mappings   = $this->service->getMappings(
            $entityFilter !== '' ? $entityFilter : null,
            $sourceFilter !== '' ? $sourceFilter : null,
            $currentPage,
            $perPage,
        );
        $total      = $this->service->getTotal(
            $entityFilter !== '' ? $entityFilter : null,
            $sourceFilter !== '' ? $sourceFilter : null,
        );
        $totalPages = (int) ceil($total / $perPage);

        $nonce    = wp_create_nonce('wp_rest');
        $apiBase  = esc_url(rest_url('homlity/v1/homologation'));
        $adminUrl = admin_url('admin.php?page=homlity-homologation');

        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:10px;">
                <?php echo esc_html__('Homologación de datos', 'homlity-plugin'); ?>
                <span style="font-size:13px;font-weight:400;color:#646970;background:#f0f6fc;padding:3px 10px;border-radius:3px;">
                    <?php echo esc_html__('Centro de mapeo canónico', 'homlity-plugin'); ?>
                </span>
            </h1>
            <p style="max-width:780px;">
                <?php echo esc_html__('Este módulo homologa los datos de todas las integraciones (CRMs, sincronizaciones) hacia los términos canónicos del plugin. Cuando un CRM envía un término, se registra aquí y queda vinculado al término de WordPress, garantizando que todo luzca igual sin importar la fuente.', 'homlity-plugin'); ?>
            </p>

            <?php $this->renderStatsCards($stats); ?>

            <hr style="margin:24px 0;">

            <?php $this->renderAddMappingForm($sources, $nonce, $apiBase); ?>

            <hr style="margin:24px 0;">

            <?php $this->renderFilters($entityFilter, $sourceFilter, $sources, $adminUrl); ?>

            <?php $this->renderTable($mappings, $total, $totalPages, $currentPage, $entityFilter, $sourceFilter, $adminUrl); ?>
        </div>

        <?php $this->renderScript($nonce, $adminUrl); ?>
        <?php
    }

    /**
     * POST handler for the server-side add-mapping form.
     * Redirects back after processing.
     */
    public function handleAddMapping(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Acceso denegado.', 'homlity-plugin'));
        }

        check_admin_referer('homlity_add_mapping');

        $entityType      = isset($_POST['entity_type']) ? sanitize_text_field(wp_unslash($_POST['entity_type'])) : '';
        $source          = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '';
        $sourceId        = isset($_POST['source_id']) ? sanitize_text_field(wp_unslash($_POST['source_id'])) : '';
        $sourceName      = isset($_POST['source_name']) ? sanitize_text_field(wp_unslash($_POST['source_name'])) : '';
        $canonicalTermId = isset($_POST['canonical_term_id']) ? absint(wp_unslash($_POST['canonical_term_id'])) : 0;

        $redirect = admin_url('admin.php?page=homlity-homologation');

        if (!EntityType::isValid($entityType) || empty($source) || empty($sourceId) || $canonicalTermId <= 0) {
            wp_safe_redirect(add_query_arg('homlity_error', '1', $redirect));
            exit;
        }

        $this->service->register($entityType, $source, $sourceId, $sourceName, $canonicalTermId);

        wp_safe_redirect(add_query_arg('homlity_saved', '1', $redirect));
        exit;
    }

    // ─── Private render helpers ───────────────────────────────────────────────

    private function renderStatsCards(array $stats): void
    {
        ?>
        <div style="display:flex;gap:14px;flex-wrap:wrap;margin:20px 0;">
            <?php foreach (EntityType::LABELS as $type => $label): ?>
                <?php
                $typeTotal   = 0;
                $sourceParts = [];

                foreach ($stats[$type] ?? [] as $src => $count) {
                    $typeTotal   += $count;
                    $sourceParts[] = '<strong>' . esc_html($src) . '</strong>: ' . (int) $count;
                }
                ?>
                <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:14px 18px;min-width:170px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
                    <div style="font-size:12px;font-weight:600;color:#50575e;text-transform:uppercase;letter-spacing:.5px;"><?php echo esc_html($label) ?></div>
                    <div style="font-size:30px;font-weight:700;color:<?php echo $typeTotal > 0 ? '#2271b1' : '#c3c4c7' ?>;margin:4px 0 2px;"><?php echo (int) $typeTotal ?></div>
                    <?php if (!empty($sourceParts)): ?>
                    <div style="font-size:11px;color:#646970;line-height:1.5;"><?php echo wp_kses_post( implode('<br>', $sourceParts) ); ?></div>
                    <?php else: ?>
                        <div style="font-size:11px;color:#c3c4c7;"><?php echo esc_html__('Sin mapeos', 'homlity-plugin') ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function renderAddMappingForm(array $sources, string $nonce, string $apiBase): void
    {
        $savedMsg = isset($_GET['homlity_saved']) ? __('Mapeo guardado correctamente.', 'homlity-plugin') : '';
        $errorMsg = isset($_GET['homlity_error']) ? __('Datos incompletos. Verifica todos los campos.', 'homlity-plugin') : '';
        ?>
        <h2 style="font-size:14px;font-weight:600;margin-bottom:12px;"><?php echo esc_html__('Agregar mapeo manual', 'homlity-plugin') ?></h2>
        <?php if ($savedMsg): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($savedMsg) ?></p></div><?php endif; ?>
        <?php if ($errorMsg): ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html($errorMsg) ?></p></div><?php endif; ?>

        <form id="homlity-add-mapping-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 18px;">
            <input type="hidden" name="action" value="homlity_homologation_add">
            <?php wp_nonce_field('homlity_add_mapping', '_wpnonce', true, true); ?>

            <label style="flex:1;min-width:140px;">
                <span style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;"><?php echo esc_html__('Tipo de entidad', 'homlity-plugin') ?></span>
                <select name="entity_type" required style="width:100%;">
                    <option value=""><?php echo esc_html__('Seleccionar...', 'homlity-plugin') ?></option>
                    <?php foreach (EntityType::LABELS as $type => $label): ?>
                        <option value="<?php echo esc_attr($type) ?>"><?php echo esc_html($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label style="flex:1;min-width:130px;">
                <span style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;"><?php echo esc_html__('Origen (source)', 'homlity-plugin') ?></span>
                <input type="text" name="source" list="homlity-sources-list" required placeholder="homlity_web" style="width:100%;">
                <datalist id="homlity-sources-list">
                    <?php foreach ($sources as $src): ?>
                        <option value="<?php echo esc_attr($src) ?>">
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label style="flex:1;min-width:120px;">
                <span style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;"><?php echo esc_html__('ID en origen', 'homlity-plugin') ?></span>
                <input type="text" name="source_id" required placeholder="123" style="width:100%;">
            </label>

            <label style="flex:1;min-width:140px;">
                <span style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;"><?php echo esc_html__('Nombre en origen', 'homlity-plugin') ?></span>
                <input type="text" name="source_name" placeholder="Apartamento" style="width:100%;">
            </label>

            <label style="flex:1;min-width:140px;">
                <span style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;"><?php echo esc_html__('Term ID canónico (WP)', 'homlity-plugin') ?></span>
                <input type="number" name="canonical_term_id" required min="1" placeholder="42" style="width:100%;">
            </label>

            <div>
                <button type="submit" class="button button-primary"><?php echo esc_html__('Guardar mapeo', 'homlity-plugin') ?></button>
            </div>
        </form>
        <p style="margin-top:8px;color:#646970;font-size:12px;">
            <?php echo esc_html__('Tip: Para ver los Term IDs canónicos disponibles, usa el endpoint REST', 'homlity-plugin') ?>
            <code><?php echo esc_html(rest_url('homlity/v1/homologation/canonical/{entity_type}')) ?></code>
        </p>
        <?php
    }

    private function renderFilters(string $entityFilter, string $sourceFilter, array $sources, string $adminUrl): void
    {
        ?>
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
            <input type="hidden" name="page" value="homlity-homologation">
            <select name="entity_type">
                <option value=""><?php echo esc_html__('Todos los tipos', 'homlity-plugin') ?></option>
                <?php foreach (EntityType::LABELS as $type => $label): ?>
                    <option value="<?php echo esc_attr($type) ?>" <?php echo selected($entityFilter, $type, false) ?>><?php echo esc_html($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="source">
                <option value=""><?php echo esc_html__('Todos los orígenes', 'homlity-plugin') ?></option>
                <?php foreach ($sources as $src): ?>
                    <option value="<?php echo esc_attr($src) ?>" <?php echo selected($sourceFilter, $src, false) ?>><?php echo esc_html($src) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button"><?php echo esc_html__('Filtrar', 'homlity-plugin') ?></button>
            <?php if ($entityFilter !== '' || $sourceFilter !== ''): ?>
                <a href="<?php echo esc_url($adminUrl) ?>" class="button"><?php echo esc_html__('Ver todos', 'homlity-plugin') ?></a>
            <?php endif; ?>
        </form>
        <?php
    }

    private function renderTable(
        array  $mappings,
        int    $total,
        int    $totalPages,
        int    $currentPage,
        string $entityFilter,
        string $sourceFilter,
        string $adminUrl,
    ): void {
        if (empty($mappings)): ?>
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:24px;text-align:center;color:#646970;">
                <?php echo esc_html__('No hay mapeos registrados aún.', 'homlity-plugin') ?>
                <?php if ($entityFilter !== '' || $sourceFilter !== ''): ?>
                    <a href="<?php echo esc_url($adminUrl) ?>"><?php echo esc_html__('Ver todos', 'homlity-plugin') ?></a>
                <?php else: ?>
                    <br><?php echo esc_html__('Los mapeos se crean automáticamente cuando los datos son importados desde integraciones.', 'homlity-plugin') ?>
                <?php endif; ?>
            </div>
        <?php return; endif; ?>

        <p style="color:#646970;margin-bottom:8px;">
            <?php
            /* translators: %d: total number of mappings */
            echo esc_html(sprintf(_n('%d mapeo encontrado', '%d mapeos encontrados', $total, 'homlity-plugin'), $total));
            ?>
        </p>

        <table class="wp-list-table widefat fixed striped" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th style="width:160px;"><?php echo esc_html__('Tipo', 'homlity-plugin') ?></th>
                    <th style="width:130px;"><?php echo esc_html__('Origen', 'homlity-plugin') ?></th>
                    <th><?php echo esc_html__('ID en origen', 'homlity-plugin') ?></th>
                    <th><?php echo esc_html__('Nombre en origen', 'homlity-plugin') ?></th>
                    <th style="width:100px;"><?php echo esc_html__('Term ID WP', 'homlity-plugin') ?></th>
                    <th><?php echo esc_html__('Nombre canónico', 'homlity-plugin') ?></th>
                    <th style="width:90px;"><?php echo esc_html__('Actualizado', 'homlity-plugin') ?></th>
                    <th style="width:70px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mappings as $row): ?>
                    <tr data-mapping-id="<?php echo (int) $row->id ?>">
                        <td><?php echo (int) $row->id ?></td>
                        <td>
                            <span style="background:#f0f6fc;color:#0969da;padding:2px 6px;border-radius:3px;font-size:11px;white-space:nowrap;">
                                <?php echo esc_html(EntityType::label($row->entity_type)) ?>
                            </span>
                        </td>
                        <td><code style="font-size:11px;"><?php echo esc_html($row->source) ?></code></td>
                        <td><code style="font-size:11px;"><?php echo esc_html($row->source_id) ?></code></td>
                        <td><?php echo esc_html($row->source_name) ?></td>
                        <td style="text-align:center;font-weight:600;"><?php echo (int) $row->canonical_term_id ?></td>
                        <td><?php echo esc_html($row->canonical_name) ?></td>
                        <td style="font-size:11px;color:#646970;"><?php echo esc_html(substr($row->updated_at ?? '', 0, 10)) ?></td>
                        <td>
                            <button type="button"
                                class="button button-link-delete homlity-delete-mapping"
                                data-id="<?php echo (int) $row->id ?>"
                                data-confirm="<?php echo esc_attr(__('¿Eliminar este mapeo?', 'homlity-plugin')) ?>"
                                style="color:#d63638;font-size:12px;">
                                <?php echo esc_html__('Eliminar', 'homlity-plugin') ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <div style="margin-top:14px;display:flex;gap:4px;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php
                    $pageUrl = add_query_arg([
                        'page'        => 'homlity-homologation',
                        'entity_type' => $entityFilter,
                        'source'      => $sourceFilter,
                        'paged'       => $i,
                    ], admin_url('admin.php'));
                    ?>
                    <a href="<?php echo esc_url($pageUrl) ?>"
                       class="button <?php echo $i === $currentPage ? 'button-primary' : '' ?>">
                        <?php echo (int) $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
        <?php
    }

    private function renderScript(string $nonce, string $adminUrl): void
    {
        $deleteEndpoint = rest_url('homlity/v1/homologation/mappings/');
        $errMsg         = __('Error al eliminar el mapeo.', 'homlity-plugin');
        ?>
        <script>
        (function () {
            document.querySelectorAll('.homlity-delete-mapping').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id      = this.getAttribute('data-id');
                    var confirm = this.getAttribute('data-confirm') || '¿Eliminar?';
                    var row     = this.closest('tr');

                    if (!window.confirm(confirm)) return;

                    fetch('<?php echo esc_js($deleteEndpoint); ?>' + id, {
                        method:  'DELETE',
                        headers: { 'X-WP-Nonce': '<?php echo esc_js($nonce) ?>' }
                    }).then(function (r) {
                        if (r.ok) {
                            row.style.opacity = '0.4';
                            setTimeout(function () { row.remove(); }, 300);
                        } else {
                            alert('<?php echo esc_js($errMsg); ?>');
                        }
                    }).catch(function () {
                        alert('<?php echo esc_js($errMsg); ?>');
                    });
                });
            });
        })();
        </script>
        <?php
    }
}
