<?php
// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Integrations\CRM\CrmConfig;
use Homlity\PluginInmobiliario\Integrations\CRM\CrmIntegrationManager;
use Homlity\PluginInmobiliario\Integrations\CRM\CrmSyncLogger;
use Homlity\PluginInmobiliario\Integrations\CRM\CrmSyncQueueService;
use Homlity\PluginInmobiliario\Integrations\CRM\PropertyUpsertService;

if (!defined('ABSPATH')) {
    exit;
}

class CrmAdminService implements ServiceInterface
{
    private CrmIntegrationManager $manager;
    private CrmSyncQueueService $queue;
    private CrmSyncLogger $logger;

    public function __construct()
    {
        $this->manager = new CrmIntegrationManager();
        $this->logger = new CrmSyncLogger();
        $this->queue = new CrmSyncQueueService($this->manager, new PropertyUpsertService(), $this->logger);
    }

    public function register(): void
    {
        add_action('admin_post_homlity_crm_save', [$this, 'handleSave']);
        add_action('admin_post_homlity_crm_enqueue_batch', [$this, 'handleEnqueueBatch']);
        add_action('admin_post_homlity_crm_run_queue', [$this, 'handleRunQueue']);
        add_action('admin_post_homlity_crm_retry_failed', [$this, 'handleRetryFailed']);
        add_action('admin_post_homlity_crm_clear_logs', [$this, 'handleClearLogs']);
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'homlity-real-estate'));
        }

        $configs = get_option(CrmConfig::OPTION_INTEGRATIONS, []);
        if (!is_array($configs)) {
            $configs = [];
        }

        $providers = $this->manager->listProviders();
        $stats = $this->queue->stats();
        $filterProvider = sanitize_key((string) ($_GET['log_provider'] ?? ''));
        $filterDateFrom = sanitize_text_field((string) ($_GET['log_date_from'] ?? ''));
        $filterDateTo = sanitize_text_field((string) ($_GET['log_date_to'] ?? ''));
        $logs = $this->logger->list(80, $filterProvider ?: null, $filterDateFrom ?: null, $filterDateTo ?: null);

        echo '<div class="wrap"><h1>' . esc_html__('Integraciones CRM', 'homlity-real-estate') . '</h1>';

        echo '<h2>' . esc_html__('Configuración por proveedor', 'homlity-real-estate') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('homlity_crm_save');
        echo '<input type="hidden" name="action" value="homlity_crm_save" />';

        foreach ($providers as $provider) {
            $key = (string) ($provider['key'] ?? '');
            $cfg = is_array($configs[$key] ?? null) ? $configs[$key] : [];
            echo '<h3>' . esc_html((string) ($provider['label'] ?? $key)) . ' (' . esc_html($key) . ')</h3>';
            echo '<table class="form-table" role="presentation"><tbody>';
            echo '<tr><th><label>Webhook key</label></th><td><input type="text" class="regular-text" name="crm[' . esc_attr($key) . '][webhook_key]" value="' . esc_attr((string) ($cfg['webhook_key'] ?? '')) . '" /></td></tr>';
            echo '<tr><th><label>Webhook HMAC secret</label></th><td><input type="text" class="regular-text" name="crm[' . esc_attr($key) . '][webhook_hmac_secret]" value="' . esc_attr((string) ($cfg['webhook_hmac_secret'] ?? '')) . '" /><p class="description">Opcional. Usa x-homlity-timestamp y x-homlity-signature.</p></td></tr>';
            echo '<tr><th><label>API base URL</label></th><td><input type="url" class="regular-text" name="crm[' . esc_attr($key) . '][api_base_url]" value="' . esc_attr((string) ($cfg['api_base_url'] ?? '')) . '" /></td></tr>';
            echo '<tr><th><label>API token</label></th><td><input type="text" class="regular-text" name="crm[' . esc_attr($key) . '][api_token]" value="' . esc_attr((string) ($cfg['api_token'] ?? '')) . '" /></td></tr>';
            echo '</tbody></table>';
        }

        $global = is_array($configs['_global'] ?? null) ? $configs['_global'] : [];
        echo '<h3>' . esc_html__('Configuración global de cola', 'homlity-real-estate') . '</h3>';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th><label>Batch size</label></th><td><input type="number" min="1" max="500" name="crm[_global][batch_size]" value="' . esc_attr((string) ($global['batch_size'] ?? 20)) . '" /></td></tr>';
        echo '</tbody></table>';

        submit_button(__('Guardar configuración CRM', 'homlity-real-estate'));
        echo '</form>';

        echo '<hr/><h2>' . esc_html__('Cola de sincronización', 'homlity-real-estate') . '</h2>';
        echo '<p>' . esc_html(sprintf('Pendientes: %d | Procesados: %d | Fallidos: %d | Total: %d', (int) $stats['pending'], (int) $stats['done'], (int) $stats['failed'], (int) $stats['total'])) . '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:12px;">';
        wp_nonce_field('homlity_crm_run_queue');
        echo '<input type="hidden" name="action" value="homlity_crm_run_queue" />';
        submit_button(__('Procesar cola ahora', 'homlity-real-estate'), 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:12px;">';
        wp_nonce_field('homlity_crm_retry_failed');
        echo '<input type="hidden" name="action" value="homlity_crm_retry_failed" />';
        echo '<input type="hidden" name="provider" value="" />';
        submit_button(__('Reintentar solo fallidos', 'homlity-real-estate'), 'secondary', 'submit', false);
        echo '</form>';

        echo '<h3>' . esc_html__('Encolar lote manual (JSON)', 'homlity-real-estate') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('homlity_crm_enqueue_batch');
        echo '<input type="hidden" name="action" value="homlity_crm_enqueue_batch" />';
        echo '<p><label>Proveedor </label><select name="provider">';
        foreach ($providers as $provider) {
            $key = (string) ($provider['key'] ?? '');
            echo '<option value="' . esc_attr($key) . '">' . esc_html((string) ($provider['label'] ?? $key)) . '</option>';
        }
        echo '</select></p>';
        echo '<p><textarea name="batch_payload" rows="10" style="width:100%;" placeholder="[{\"property\":{...}}, {...}]"></textarea></p>';
        submit_button(__('Encolar lote', 'homlity-real-estate'));
        echo '</form>';

        echo '<hr/><h2>' . esc_html__('Logs de sincronización', 'homlity-real-estate') . '</h2>';
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" style="margin-bottom:12px; display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">';
        echo '<input type="hidden" name="page" value="homlity-real-estate-crm" />';
        echo '<p style="margin:0;"><label>Proveedor<br/><select name="log_provider"><option value="">Todos</option>';
        foreach ($providers as $provider) {
            $key = (string) ($provider['key'] ?? '');
            echo '<option value="' . esc_attr($key) . '"' . selected($filterProvider, $key, false) . '>' . esc_html((string) ($provider['label'] ?? $key)) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p style="margin:0;"><label>Desde<br/><input type="date" name="log_date_from" value="' . esc_attr($filterDateFrom) . '" /></label></p>';
        echo '<p style="margin:0;"><label>Hasta<br/><input type="date" name="log_date_to" value="' . esc_attr($filterDateTo) . '" /></label></p>';
        echo '<p style="margin:0;">';
        submit_button(__('Filtrar logs', 'homlity-real-estate'), 'secondary', '', false);
        echo '</p>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:12px;">';
        wp_nonce_field('homlity_crm_clear_logs');
        echo '<input type="hidden" name="action" value="homlity_crm_clear_logs" />';
        submit_button(__('Limpiar logs', 'homlity-real-estate'), 'delete', 'submit', false);
        echo '</form>';

        echo '<table class="widefat striped"><thead><tr><th>Fecha</th><th>Nivel</th><th>Proveedor</th><th>External ID</th><th>Post ID</th><th>Mensaje</th></tr></thead><tbody>';
        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html((string) ($log['timestamp'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($log['level'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($log['provider'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($log['external_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($log['post_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($log['message'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '</div>';
    }

    public function handleSave(): void
    {
        $this->assertAdminAndNonce('homlity_crm_save');

        $incoming = $_POST['crm'] ?? [];
        if (!is_array($incoming)) {
            $incoming = [];
        }

        $sanitized = [];
        foreach ($incoming as $provider => $cfg) {
            $provider = sanitize_key((string) $provider);
            if (!is_array($cfg)) {
                continue;
            }
            $sanitized[$provider] = [
                'webhook_key' => sanitize_text_field((string) ($cfg['webhook_key'] ?? '')),
                'webhook_hmac_secret' => sanitize_text_field((string) ($cfg['webhook_hmac_secret'] ?? '')),
                'api_base_url' => esc_url_raw((string) ($cfg['api_base_url'] ?? '')),
                'api_token' => sanitize_text_field((string) ($cfg['api_token'] ?? '')),
            ];
            if ($provider === '_global') {
                $sanitized[$provider]['batch_size'] = max(1, min(500, (int) ($cfg['batch_size'] ?? 20)));
            }
        }

        update_option(CrmConfig::OPTION_INTEGRATIONS, $sanitized, false);
        wp_safe_redirect(admin_url('admin.php?page=homlity-real-estate-crm&updated=1'));
        exit;
    }

    public function handleEnqueueBatch(): void
    {
        $this->assertAdminAndNonce('homlity_crm_enqueue_batch');

        $provider = sanitize_key((string) ($_POST['provider'] ?? ''));
        // wp_unslash only — sanitize_text_field would corrupt the JSON structure.
        // The value is immediately decoded; individual fields are sanitised by enqueueBatch().
        $raw = wp_unslash((string) ($_POST['batch_payload'] ?? '')); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $decoded = json_decode($raw, true);
        $records = is_array($decoded) ? $decoded : [];

        $count = $this->queue->enqueueBatch($provider, $records);
        wp_safe_redirect(admin_url('admin.php?page=homlity-real-estate-crm&queued=' . $count));
        exit;
    }

    public function handleRunQueue(): void
    {
        $this->assertAdminAndNonce('homlity_crm_run_queue');
        $this->queue->processQueue();
        wp_safe_redirect(admin_url('admin.php?page=homlity-real-estate-crm&processed=1'));
        exit;
    }

    public function handleRetryFailed(): void
    {
        $this->assertAdminAndNonce('homlity_crm_retry_failed');
        $provider = sanitize_key((string) ($_POST['provider'] ?? ''));
        if ($provider === '') {
            $provider = null;
        }
        $retried = $this->queue->retryFailed($provider);
        wp_safe_redirect(admin_url('admin.php?page=homlity-real-estate-crm&retried=' . (int) $retried));
        exit;
    }

    public function handleClearLogs(): void
    {
        $this->assertAdminAndNonce('homlity_crm_clear_logs');
        $this->logger->clear();
        wp_safe_redirect(admin_url('admin.php?page=homlity-real-estate-crm&logs_cleared=1'));
        exit;
    }

    private function assertAdminAndNonce(string $action): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'homlity-real-estate'));
        }

        check_admin_referer($action);
    }
}
