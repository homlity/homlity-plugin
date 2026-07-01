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
        add_action('admin_init', [self::class, 'maybeRedirectLegacyPage']);
    }

    // ── Page render ───────────────────────────────────────────────────────

    public static function renderPage(): void
    {
        self::renderContent(false);
    }

    public static function renderEmbeddedPage(): void
    {
        self::renderContent(true);
    }

    public static function redirectToSettingsTab(): void
    {
        wp_safe_redirect(self::settingsPageUrl());
        exit;
    }

    public static function maybeRedirectLegacyPage(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        if ($page !== 'homlity-consignment') {
            return;
        }

        self::redirectToSettingsTab();
    }

    private static function renderContent(bool $embedded = false): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'homlity-real-estate'));
        }

        $opts         = Homlity_Consignment_Manager::options();
        $updated      = isset($_GET['updated'])      && $_GET['updated']      === '1';
        $page_created = isset($_GET['page_created']) && $_GET['page_created'] === '1';
        $page_deleted = isset($_GET['page_deleted']) && $_GET['page_deleted'] === '1';
        $nonce_action = 'homlity_consignment_save';
        if (!$embedded) {
            ?>
            <div class="wrap" style="max-width:1100px;">
            <?php
        }
        ?>
            <div class="homlity-consignment-admin<?php echo $embedded ? ' homlity-consignment-admin--embedded' : ''; ?>">
            <div class="homlity-consignment-admin__intro">
                <div>
                    <h2><?php esc_html_e('Consignación de Inmuebles', 'homlity-real-estate'); ?></h2>
                    <p><?php esc_html_e('Configura el formulario público de consignación de inmuebles.', 'homlity-real-estate'); ?></p>
                </div>
                <div class="homlity-consignment-admin__badges">
                    <span class="homlity-consignment-admin__badge<?php echo !empty($opts['enabled']) ? ' is-active' : ''; ?>">
                        <?php echo !empty($opts['enabled']) ? esc_html__('Formulario activo', 'homlity-real-estate') : esc_html__('Formulario inactivo', 'homlity-real-estate'); ?>
                    </span>
                    <span class="homlity-consignment-admin__badge">
                        <?php printf(esc_html__('Máx. %d imágenes', 'homlity-real-estate'), (int) ($opts['max_images'] ?? 0)); ?>
                    </span>
                    <span class="homlity-consignment-admin__badge">
                        <?php echo !empty($opts['enable_logs']) ? esc_html__('Logs activos', 'homlity-real-estate') : esc_html__('Logs desactivados', 'homlity-real-estate'); ?>
                    </span>
                </div>
            </div>

            <?php if ($updated): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Ajustes guardados.', 'homlity-real-estate'); ?></p></div>
            <?php endif; ?>
            <?php if ($page_created): ?>
                <?php $pid = Homlity_Consignment_Manager::getConsignmentPageId(); ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e('✅ Página de consignación creada correctamente.', 'homlity-real-estate'); ?>
                    <?php if ($pid > 0): ?>
                        &nbsp;<a href="<?php echo esc_url(get_permalink($pid)); ?>" target="_blank"><?php esc_html_e('Ver página', 'homlity-real-estate'); ?></a>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $pid . '&action=edit')); ?>"><?php esc_html_e('Editar', 'homlity-real-estate'); ?></a>
                    <?php endif; ?>
                </p></div>
            <?php endif; ?>
            <?php if ($page_deleted): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Página eliminada.', 'homlity-real-estate'); ?></p></div>
            <?php endif; ?>

            <div class="homlity-consignment-admin__layout">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="homlity-consignment-admin__main">
                <?php wp_nonce_field($nonce_action); ?>
                <input type="hidden" name="action" value="homlity_consignment_save">

                <div class="homlity-consignment-admin__card">
                    <h3 class="title"><?php esc_html_e('General', 'homlity-real-estate'); ?></h3>
                    <p class="description"><?php esc_html_e('Define el estado inicial del inmueble, origen del formulario y datos base de operación.', 'homlity-real-estate'); ?></p>
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
                </div>

                <div class="homlity-consignment-admin__card">
                    <h3 class="title"><?php esc_html_e('Validaciones y permisos', 'homlity-real-estate'); ?></h3>
                    <p class="description"><?php esc_html_e('Controla qué datos son obligatorios y quiénes pueden consignar inmuebles.', 'homlity-real-estate'); ?></p>
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
                </div>

                <div class="homlity-consignment-admin__card">
                    <h3 class="title"><?php esc_html_e('Archivos y seguridad', 'homlity-real-estate'); ?></h3>
                    <p class="description"><?php esc_html_e('Configura límites de carga y protecciones básicas para el formulario.', 'homlity-real-estate'); ?></p>
                    <table class="form-table" role="presentation">
                        <?php
                        self::number('max_images', $opts, __('Máximo de imágenes', 'homlity-real-estate'), 1, 100);
                        self::number('max_image_size', $opts, __('Tamaño máximo por imagen (MB)', 'homlity-real-estate'), 1, 50);
                        self::number('max_brochure_size', $opts, __('Tamaño máximo del brochure (MB)', 'homlity-real-estate'), 1, 50);
                        self::checkbox('enable_honeypot', $opts, __('Activar campo honeypot anti-spam', 'homlity-real-estate'));
                        self::checkbox('enable_rate_limit', $opts, __('Activar rate limiting por IP', 'homlity-real-estate'));
                        self::number('rate_limit_per_hour', $opts, __('Envíos máximos por IP por hora', 'homlity-real-estate'), 1, 100);
                        self::checkbox('enable_logs', $opts, __('Activar logs de consignaciones', 'homlity-real-estate'));
                        ?>
                    </table>
                </div>

                <div class="homlity-consignment-admin__card">
                    <h3 class="title"><?php esc_html_e('Notificaciones y textos', 'homlity-real-estate'); ?></h3>
                    <p class="description"><?php esc_html_e('Ajusta los mensajes visibles y los correos asociados al flujo de consignación.', 'homlity-real-estate'); ?></p>
                    <table class="form-table" role="presentation">
                        <?php
                        self::checkbox('notify_admin', $opts, __('Notificar al administrador al recibir una consignación', 'homlity-real-estate'));
                        self::checkbox('notify_consignant', $opts, __('Enviar confirmación al consignante', 'homlity-real-estate'));
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
                </div>

                <div class="homlity-consignment-admin__card">
                    <h3 class="title"><?php esc_html_e('Estilos básicos', 'homlity-real-estate'); ?></h3>
                    <p class="description"><?php esc_html_e('Define colores y bordes para el render público del formulario.', 'homlity-real-estate'); ?></p>
                    <table class="form-table" role="presentation">
                        <?php
                        self::color('primary_color', $opts, __('Color principal', 'homlity-real-estate'));
                        self::color('secondary_color', $opts, __('Color secundario', 'homlity-real-estate'));
                        self::color('bg_color', $opts, __('Color de fondo', 'homlity-real-estate'));
                        self::color('text_color', $opts, __('Color de texto', 'homlity-real-estate'));
                        self::number('border_radius', $opts, __('Radio de bordes (px)', 'homlity-real-estate'), 0, 32);
                        ?>
                    </table>
                </div>

                <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
            </form>

            <aside class="homlity-consignment-admin__side">
                <div class="homlity-consignment-admin__card">
                    <h3 class="title"><?php esc_html_e('Uso manual', 'homlity-real-estate'); ?></h3>
                    <p class="description"><?php esc_html_e('Usa el shortcode del plugin. Internamente se mantiene conectado a la primera versión del consignador.', 'homlity-real-estate'); ?></p>
                    <p><code>[homlity_consignment_form]</code></p>
                </div>

                <div class="homlity-consignment-admin__card">
                    <?php self::renderPageSection(); ?>
                </div>

                <?php if ($opts['enable_logs']): ?>
                <div class="homlity-consignment-admin__card">
                <h3 class="title"><?php esc_html_e('Log de consignaciones (últimas 20)', 'homlity-real-estate'); ?></h3>
                <?php
                $logs = array_reverse((array) get_option('homlity_consignment_logs', []));
                $logs = array_slice($logs, 0, 20);
                if (empty($logs)):
                ?>
                    <p><?php esc_html_e('No hay registros aún.', 'homlity-real-estate'); ?></p>
                <?php else: ?>
                    <div class="homlity-consignment-admin__logs">
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
                    </div>
                <?php endif; ?>
                </div>
                <?php endif; ?>
            </aside>
            </div>
            </div>
        <?php
        if (!$embedded) {
            echo '</div>';
        }
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
            'page'    => 'homlity-real-estate-settings',
            'tab'     => 'consignment',
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

    // ── Consignment page section ──────────────────────────────────────────

    public static function renderPageSection(): void
    {
        $page_id = Homlity_Consignment_Manager::getConsignmentPageId();
        $create_url    = wp_nonce_url(
            admin_url('admin-post.php?action=homlity_consignment_create_page'),
            'homlity_consignment_create_page'
        );
        $recreate_url  = wp_nonce_url(
            admin_url('admin-post.php?action=homlity_consignment_create_page&force=1'),
            'homlity_consignment_create_page'
        );
        ?>
        <hr style="margin: 30px 0;">
        <h2><?php esc_html_e('Página de Consignación', 'homlity-real-estate'); ?></h2>
        <p class="description"><?php esc_html_e('El plugin puede crear automáticamente una página pública con el formulario de consignación ya montado.', 'homlity-real-estate'); ?></p>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Estado', 'homlity-real-estate'); ?></th>
                <td>
                <?php if ($page_id > 0):
                    $page    = get_post($page_id);
                    $status  = get_post_status($page_id);
                    $status_label = [
                        'publish' => '<span style="color:#00a32a">● ' . esc_html__('Publicada', 'homlity-real-estate') . '</span>',
                        'draft'   => '<span style="color:#dba617">● ' . esc_html__('Borrador', 'homlity-real-estate') . '</span>',
                        'pending' => '<span style="color:#dba617">● ' . esc_html__('Pendiente', 'homlity-real-estate') . '</span>',
                    ][$status] ?? '<span style="color:#d63638">● ' . esc_html(ucfirst($status)) . '</span>';
                ?>
                    <?php echo $status_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    &nbsp;&mdash;&nbsp;
                    <strong><?php echo esc_html($page->post_title ?? ''); ?></strong>
                    &nbsp;
                    <a href="<?php echo esc_url(get_permalink($page_id)); ?>" target="_blank" class="button button-small">
                        <?php esc_html_e('Ver página', 'homlity-real-estate'); ?> ↗
                    </a>
                    &nbsp;
                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $page_id . '&action=edit')); ?>" class="button button-small">
                        <?php esc_html_e('Editar', 'homlity-real-estate'); ?>
                    </a>
                    <br><br>
                    <strong><?php esc_html_e('URL:', 'homlity-real-estate'); ?></strong>
                    <code><?php echo esc_url(get_permalink($page_id)); ?></code>
                    <br>
                    <strong><?php esc_html_e('Shortcode en la página:', 'homlity-real-estate'); ?></strong>
                    <code>[homlity_consignment_form]</code>
                    <br><br>
                    <a href="<?php echo esc_url($recreate_url); ?>"
                       class="button"
                       onclick="return confirm('<?php esc_attr_e('¿Recrear la página? Se creará una nueva página con el formulario.', 'homlity-real-estate'); ?>')">
                        <?php esc_html_e('Recrear página', 'homlity-real-estate'); ?>
                    </a>
                <?php else: ?>
                    <span style="color:#d63638">● <?php esc_html_e('No existe ninguna página de consignación.', 'homlity-real-estate'); ?></span>
                    <br><br>
                    <a href="<?php echo esc_url($create_url); ?>" class="button button-primary">
                        <?php esc_html_e('✚ Crear página de consignación', 'homlity-real-estate'); ?>
                    </a>
                    <p class="description"><?php esc_html_e('Se creará una página publicada con título "Consigna tu Inmueble" y el formulario ya integrado.', 'homlity-real-estate'); ?></p>
                <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    // ── Create/recreate page handler ──────────────────────────────────────

    public static function handleCreatePage(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('homlity_consignment_create_page')) {
            wp_die(esc_html__('No autorizado.', 'homlity-real-estate'));
        }

        $force = !empty($_GET['force']);

        $page_id = $force
            ? Homlity_Consignment_Manager::recreateConsignmentPage()
            : Homlity_Consignment_Manager::createConsignmentPage();

        wp_safe_redirect(add_query_arg([
            'page'         => 'homlity-real-estate-settings',
            'tab'          => 'consignment',
            'page_created' => $page_id > 0 ? '1' : '0',
        ], admin_url('admin.php')));
        exit;
    }

    private static function settingsPageUrl(): string
    {
        return add_query_arg([
            'page' => 'homlity-real-estate-settings',
            'tab' => 'consignment',
        ], admin_url('admin.php'));
    }
}
