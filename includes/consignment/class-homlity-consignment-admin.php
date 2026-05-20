<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
/**
 * Homlity Consignment Admin — settings page under Homlity > Consignación.
 *
 * Follows the same pattern as SeoGeoSettingsService (PHP form + admin-post.php action).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Consignment_Admin
{
    // ── Menu registration ─────────────────────────────────────────────────

    public static function registerMenu(string $parent_slug): void
    {
        add_submenu_page(
            $parent_slug,
            __('Consignación de Inmuebles', 'homlity-real-estate'),
            __('Consignación', 'homlity-real-estate'),
            'manage_options',
            'homlity-consignment',
            [self::class, 'renderPage']
        );
    }

    // ── Page render ───────────────────────────────────────────────────────

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'homlity-real-estate'));
        }

        $opts    = Homlity_Consignment_Manager::options();
        $updated = isset($_GET['updated']) && $_GET['updated'] === '1';
        $nonce_action = 'homlity_consignment_save';
        ?>
        <div class="wrap" style="max-width:900px;">
            <h1><?php esc_html_e('Consignación de Inmuebles', 'homlity-real-estate'); ?></h1>
            <p><?php esc_html_e('Configura el formulario público de consignación de inmuebles.', 'homlity-real-estate'); ?></p>

            <?php if ($updated): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Ajustes guardados.', 'homlity-real-estate'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field($nonce_action); ?>
                <input type="hidden" name="action" value="homlity_consignment_save">

                <!-- ── General ──────────────────────────────────────────── -->
                <h2 class="title"><?php esc_html_e('General', 'homlity-real-estate'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::checkbox('enabled', $opts, __('Activar formulario público', 'homlity-real-estate'));
                    self::select('default_status', $opts, __('Estado por defecto del inmueble', 'homlity-real-estate'), [
                        'pending' => __('Pendiente de revisión (recomendado)', 'homlity-real-estate'),
                        'draft'   => __('Borrador', 'homlity-real-estate'),
                    ]);
                    self::text('provider', $opts, __('Provider / Source', 'homlity-real-estate'), __('Identificador del proveedor. Valor por defecto: public-consignment', 'homlity-real-estate'));
                    self::text('redirect_url', $opts, __('URL de redirección tras envío', 'homlity-real-estate'), __('Déjalo en blanco para mostrar el mensaje de éxito en el mismo formulario.', 'homlity-real-estate'));
                    self::text('notification_email', $opts, __('Correo de notificaciones', 'homlity-real-estate'), __('Separar múltiples correos con coma.', 'homlity-real-estate'));
                    self::text('default_currency', $opts, __('Moneda por defecto', 'homlity-real-estate'), 'COP, USD, EUR…');
                    self::text('default_country', $opts, __('País por defecto', 'homlity-real-estate'));
                    self::text('default_city', $opts, __('Ciudad por defecto', 'homlity-real-estate'), __('Opcional.', 'homlity-real-estate'));
                    ?>
                </table>

                <!-- ── Validaciones ─────────────────────────────────────── -->
                <h2 class="title"><?php esc_html_e('Validaciones', 'homlity-real-estate'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::checkbox('require_coordinates', $opts, __('Requerir coordenadas (latitud y longitud)', 'homlity-real-estate'));
                    self::checkbox('require_image', $opts, __('Requerir al menos una imagen', 'homlity-real-estate'));
                    self::checkbox('require_gallery', $opts, __('Requerir galería (múltiples imágenes)', 'homlity-real-estate'));
                    self::checkbox('allow_advisors', $opts, __('Permitir asesores independientes', 'homlity-real-estate'));
                    self::checkbox('allow_agencies', $opts, __('Permitir inmobiliarias externas', 'homlity-real-estate'));
                    self::checkbox('allow_owners', $opts, __('Permitir propietarios', 'homlity-real-estate'));
                    ?>
                </table>

                <!-- ── Archivos ──────────────────────────────────────────── -->
                <h2 class="title"><?php esc_html_e('Límites de archivos', 'homlity-real-estate'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::number('max_images', $opts, __('Máximo de imágenes', 'homlity-real-estate'), 1, 100);
                    self::number('max_image_size', $opts, __('Tamaño máximo por imagen (MB)', 'homlity-real-estate'), 1, 50);
                    self::number('max_brochure_size', $opts, __('Tamaño máximo del brochure (MB)', 'homlity-real-estate'), 1, 50);
                    ?>
                </table>

                <!-- ── Seguridad ─────────────────────────────────────────── -->
                <h2 class="title"><?php esc_html_e('Seguridad', 'homlity-real-estate'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::checkbox('enable_honeypot', $opts, __('Activar campo honeypot anti-spam', 'homlity-real-estate'));
                    self::checkbox('enable_rate_limit', $opts, __('Activar rate limiting por IP', 'homlity-real-estate'));
                    self::number('rate_limit_per_hour', $opts, __('Envíos máximos por IP por hora', 'homlity-real-estate'), 1, 100);
                    self::checkbox('enable_logs', $opts, __('Activar logs de consignaciones', 'homlity-real-estate'));
                    ?>
                </table>

                <!-- ── Notificaciones ────────────────────────────────────── -->
                <h2 class="title"><?php esc_html_e('Notificaciones', 'homlity-real-estate'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::checkbox('notify_admin', $opts, __('Notificar al administrador al recibir una consignación', 'homlity-real-estate'));
                    self::checkbox('notify_consignant', $opts, __('Enviar confirmación al consignante', 'homlity-real-estate'));
                    ?>
                </table>

                <!-- ── Textos UI ─────────────────────────────────────────── -->
                <h2 class="title"><?php esc_html_e('Textos del formulario', 'homlity-real-estate'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::text('form_title', $opts, __('Título del formulario', 'homlity-real-estate'));
                    self::text('form_subtitle', $opts, __('Subtítulo', 'homlity-real-estate'));
                    self::textarea('form_welcome_text', $opts, __('Texto de bienvenida', 'homlity-real-estate'), 3);
                    self::text('btn_next_text', $opts, __('Texto botón Siguiente', 'homlity-real-estate'));
                    self::text('btn_prev_text', $opts, __('Texto botón Anterior', 'homlity-real-estate'));
                    self::text('btn_submit_text', $opts, __('Texto botón Enviar', 'homlity-real-estate'));
                    self::textarea('success_message', $opts, __('Mensaje de éxito', 'homlity-real-estate'), 3);
                    self::textarea('error_message', $opts, __('Mensaje de error', 'homlity-real-estate'), 2);
                    self::textarea('data_policy_text', $opts, __('Texto de política de datos', 'homlity-real-estate'), 4);
                    self::textarea('authorization_text', $opts, __('Texto de autorización', 'homlity-real-estate'), 3);
                    ?>
                </table>

                <!-- ── Estilos ───────────────────────────────────────────── -->
                <h2 class="title"><?php esc_html_e('Estilos básicos', 'homlity-real-estate'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    self::color('primary_color', $opts, __('Color principal', 'homlity-real-estate'));
                    self::color('secondary_color', $opts, __('Color secundario', 'homlity-real-estate'));
                    self::color('bg_color', $opts, __('Color de fondo', 'homlity-real-estate'));
                    self::color('text_color', $opts, __('Color de texto', 'homlity-real-estate'));
                    self::number('border_radius', $opts, __('Radio de bordes (px)', 'homlity-real-estate'), 0, 32);
                    ?>
                </table>

                <!-- ── Shortcode info ────────────────────────────────────── -->
                <h2 class="title"><?php esc_html_e('Uso', 'homlity-real-estate'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e('Shortcode', 'homlity-real-estate'); ?></th>
                        <td>
                            <code>[homlity_consignment_form]</code><br>
                            <code>[homlity_consignment_form provider="public-consignment" theme="light"]</code><br>
                            <code>[homlity_consignment_form redirect_url="/gracias/"]</code>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
            </form>

            <!-- ── Logs ─────────────────────────────────────────────────── -->
            <?php if ($opts['enable_logs']): ?>
            <h2><?php esc_html_e('Log de consignaciones (últimas 20)', 'homlity-real-estate'); ?></h2>
            <?php
            $logs = array_reverse((array) get_option('homlity_consignment_logs', []));
            $logs = array_slice($logs, 0, 20);
            if (empty($logs)):
            ?>
                <p><?php esc_html_e('No hay registros aún.', 'homlity-real-estate'); ?></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead><tr>
                        <th><?php esc_html_e('Fecha', 'homlity-real-estate'); ?></th>
                        <th><?php esc_html_e('Post ID', 'homlity-real-estate'); ?></th>
                        <th><?php esc_html_e('Tipo', 'homlity-real-estate'); ?></th>
                        <th><?php esc_html_e('Correo', 'homlity-real-estate'); ?></th>
                        <th><?php esc_html_e('Resultado', 'homlity-real-estate'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['date'] ?? '—'); ?></td>
                            <td>
                                <?php if (!empty($log['post_id'])): ?>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . (int) $log['post_id'] . '&action=edit')); ?>">#<?php echo (int) $log['post_id']; ?></a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?php echo esc_html($log['consignant_type'] ?? '—'); ?></td>
                            <td><?php echo esc_html($log['email'] ?? '—'); ?></td>
                            <td><?php echo esc_html($log['result'] ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Save handler ──────────────────────────────────────────────────────

    public static function handleSave(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('homlity_consignment_save')) {
            wp_die(esc_html__('No autorizado.', 'homlity-real-estate'));
        }

        $raw  = (array) ($_POST['homlity_consignment'] ?? []);
        $opts = Homlity_Consignment_Manager::defaults();
        $save = [];

        // Booleans
        $bool_keys = ['enabled', 'require_coordinates', 'require_image', 'require_gallery',
                      'allow_advisors', 'allow_agencies', 'allow_owners', 'enable_honeypot',
                      'enable_rate_limit', 'enable_logs', 'notify_admin', 'notify_consignant'];
        foreach ($bool_keys as $k) {
            $save[$k] = !empty($raw[$k]);
        }

        // Text fields
        $text_keys = ['provider', 'redirect_url', 'notification_email', 'default_currency',
                      'default_country', 'default_city', 'form_title', 'form_subtitle',
                      'btn_next_text', 'btn_prev_text', 'btn_submit_text'];
        foreach ($text_keys as $k) {
            $save[$k] = sanitize_text_field($raw[$k] ?? $opts[$k]);
        }

        // Textarea fields
        $textarea_keys = ['form_welcome_text', 'success_message', 'error_message',
                          'data_policy_text', 'authorization_text'];
        foreach ($textarea_keys as $k) {
            $save[$k] = sanitize_textarea_field($raw[$k] ?? $opts[$k]);
        }

        // Select fields
        $save['default_status'] = in_array($raw['default_status'] ?? '', ['draft', 'pending'], true)
            ? $raw['default_status']
            : 'pending';

        // Integer fields
        $int_keys = ['max_images', 'max_image_size', 'max_brochure_size', 'rate_limit_per_hour', 'border_radius'];
        foreach ($int_keys as $k) {
            $save[$k] = max(1, (int) ($raw[$k] ?? $opts[$k]));
        }

        // Color fields
        $color_keys = ['primary_color', 'secondary_color', 'bg_color', 'text_color'];
        foreach ($color_keys as $k) {
            $color = sanitize_hex_color($raw[$k] ?? $opts[$k]);
            $save[$k] = $color ?: $opts[$k];
        }

        update_option('homlity_consignment_settings', $save);
        Homlity_Consignment_Manager::flushOptionsCache();

        wp_safe_redirect(add_query_arg([
            'page'    => 'homlity-consignment',
            'updated' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    // ── Field helpers ─────────────────────────────────────────────────────

    private static function text(string $key, array $opts, string $label, string $desc = ''): void
    {
        $val = esc_attr($opts[$key] ?? '');
        echo '<tr><th scope="row"><label for="hc_' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input type="text" id="hc_' . esc_attr($key) . '" name="homlity_consignment[' . esc_attr($key) . ']" value="' . $val . '" class="regular-text">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if ($desc) {
            echo '<p class="description">' . esc_html($desc) . '</p>';
        }
        echo '</td></tr>';
    }

    private static function textarea(string $key, array $opts, string $label, int $rows = 3): void
    {
        $val = esc_textarea($opts[$key] ?? '');
        $rows_attr = esc_attr((string) $rows);
        echo '<tr><th scope="row"><label for="hc_' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
        echo '<td><textarea id="hc_' . esc_attr($key) . '" name="homlity_consignment[' . esc_attr($key) . ']" rows="' . $rows_attr . '" class="large-text">' . $val . '</textarea></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function checkbox(string $key, array $opts, string $label): void
    {
        $checked = !empty($opts[$key]) ? ' checked="checked"' : '';
        echo '<tr><th scope="row">' . esc_html($label) . '</th>';
        echo '<td><label><input type="checkbox" name="homlity_consignment[' . esc_attr($key) . ']" value="1"' . $checked . '> ' . esc_html__('Activar', 'homlity-real-estate') . '</label></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function number(string $key, array $opts, string $label, int $min = 0, int $max = 9999): void
    {
        $val = (int) ($opts[$key] ?? 0);
        $val_attr = esc_attr((string) $val);
        $min_attr = esc_attr((string) $min);
        $max_attr = esc_attr((string) $max);
        echo '<tr><th scope="row"><label for="hc_' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input type="number" id="hc_' . esc_attr($key) . '" name="homlity_consignment[' . esc_attr($key) . ']" value="' . $val_attr . '" min="' . $min_attr . '" max="' . $max_attr . '" class="small-text"></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function select(string $key, array $opts, string $label, array $choices): void
    {
        $current = $opts[$key] ?? '';
        echo '<tr><th scope="row"><label for="hc_' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
        echo '<select id="hc_' . esc_attr($key) . '" name="homlity_consignment[' . esc_attr($key) . ']">';
        foreach ($choices as $val => $lbl) {
            echo '<option value="' . esc_attr($val) . '"' . selected($current, $val, false) . '>' . esc_html($lbl) . '</option>';
        }
        echo '</select></td></tr>';
    }

    private static function color(string $key, array $opts, string $label): void
    {
        $val = esc_attr($opts[$key] ?? '#000000');
        echo '<tr><th scope="row"><label for="hc_' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input type="color" id="hc_' . esc_attr($key) . '" name="homlity_consignment[' . esc_attr($key) . ']" value="' . $val . '"></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
