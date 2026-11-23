<?php

namespace Homlity\PluginInmobiliario\Search;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

/** Admin status and controls for the resumable property-index rebuild. */
final class PropertyIndexAdminPage implements ServiceInterface
{
    public const SLUG = 'homlity-property-index';
    public const USE_OPTION = 'homlity_property_index_use';

    public static function useIndex(): bool
    {
        return (bool) get_option(self::USE_OPTION, false) && PropertyIndexSchema::isReady();
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 40);
        add_action('admin_post_homlity_property_index_save', [$this, 'saveSettings']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'homlity-real-estate-settings',
            __('Índice de búsqueda', 'homlity-real-estate'),
            __('Índice de búsqueda', 'homlity-real-estate'),
            'manage_options',
            self::SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'homlity-real-estate'));
        }
        $status = (new PropertyIndexRebuilder())->status();
        $nonce = wp_create_nonce('homlity_property_index');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Índice de búsqueda', 'homlity-real-estate'); ?></h1>
            <p><?php esc_html_e('La tabla es una copia reconstruible de los inmuebles y todavía no modifica la ruta actual del buscador.', 'homlity-real-estate'); ?></p>
            <table class="widefat striped" style="max-width:720px">
                <tbody>
                    <tr><th><?php esc_html_e('Publicados', 'homlity-real-estate'); ?></th><td><?php echo esc_html((string) $status['published']); ?></td></tr>
                    <tr><th><?php esc_html_e('Filas indexadas', 'homlity-real-estate'); ?></th><td><?php echo esc_html((string) $status['indexed']); ?></td></tr>
                    <tr><th><?php esc_html_e('Diferencia', 'homlity-real-estate'); ?></th><td><?php echo esc_html((string) $status['difference']); ?></td></tr>
                    <tr><th><?php esc_html_e('Última reconstrucción', 'homlity-real-estate'); ?></th><td><?php echo esc_html($status['last_rebuild'] ?: __('Nunca', 'homlity-real-estate')); ?></td></tr>
                </tbody>
            </table>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px">
                <input type="hidden" name="action" value="homlity_property_index_save">
                <?php wp_nonce_field('homlity_property_index_settings'); ?>
                <label><input type="checkbox" name="use_index" value="1" <?php checked((bool) get_option(self::USE_OPTION, false)); ?>> <?php esc_html_e('Permitir que el buscador use el índice cuando esté listo', 'homlity-real-estate'); ?></label>
                <p><button class="button button-primary" type="submit"><?php esc_html_e('Guardar', 'homlity-real-estate'); ?></button>
                <button class="button" type="button" id="homlity-property-index-rebuild"><?php esc_html_e('Reconstruir índice', 'homlity-real-estate'); ?></button></p>
            </form>
            <p id="homlity-property-index-progress" aria-live="polite"></p>
        </div>
        <script>
        (function () {
            var button = document.getElementById('homlity-property-index-rebuild');
            var status = document.getElementById('homlity-property-index-progress');
            if (!button || !window.fetch) return;
            button.addEventListener('click', function () {
                button.disabled = true;
                var body = new URLSearchParams({action: 'homlity_property_index_rebuild', nonce: <?php echo wp_json_encode($nonce); ?>});
                fetch(ajaxurl, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body.toString()})
                    .then(function () {
                        status.textContent = <?php echo wp_json_encode(__('Reconstrucción programada; consultando progreso…', 'homlity-real-estate')); ?>;
                        var poll = function () {
                            var query = new URLSearchParams({action: 'homlity_property_index_rebuild_status', nonce: <?php echo wp_json_encode($nonce); ?>});
                            fetch(ajaxurl, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: query.toString()})
                                .then(function (response) { return response.json(); })
                                .then(function (payload) {
                                    var data = payload && payload.data ? payload.data : {};
                                    status.textContent = (data.indexed || 0) + ' / ' + (data.published || 0) + ' inmuebles indexados';
                                    if (!data.ready) window.setTimeout(poll, 1500);
                                    else button.disabled = false;
                                }).catch(function () { button.disabled = false; });
                        };
                        poll();
                    })
                    .catch(function () { status.textContent = <?php echo wp_json_encode(__('No se pudo iniciar la reconstrucción.', 'homlity-real-estate')); ?>; })
                    .finally(function () { button.disabled = false; });
            });
        }());
        </script>
        <?php
    }

    public function saveSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'homlity-real-estate'));
        }
        check_admin_referer('homlity_property_index_settings');
        update_option(self::USE_OPTION, !empty($_POST['use_index']) ? 1 : 0, false);
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'updated' => 1], admin_url('admin.php')));
        exit;
    }
}
