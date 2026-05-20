<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
/**
 * Admin template editor for property listing and detail pages.
 *
 * Adds a "Plantillas" submenu under Configuración with a CodeMirror-based
 * editor that lets administrators edit plugin template files directly from
 * the WordPress admin without leaving the browser.
 *
 * Security:
 *   - Requires manage_options capability.
 *   - All file paths are validated against the plugin's templates/ directory
 *     to prevent path traversal.
 *   - Nonce verification on every AJAX request.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateEditorService implements ServiceInterface
{
    private const MENU_SLUG  = 'homlity-template-editor';
    private const AJAX_LOAD  = 'homlity_load_template';
    private const AJAX_SAVE  = 'homlity_save_template';
    private const NONCE_LOAD = 'homlity_tpl_load';
    private const NONCE_SAVE = 'homlity_tpl_save';

    public function register(): void
    {
        add_action('homlity_plugin_register_integration_submenus', [$this, 'registerSubmenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_' . self::AJAX_LOAD, [$this, 'handleLoad']);
        add_action('wp_ajax_' . self::AJAX_SAVE, [$this, 'handleSave']);
    }

    // -------------------------------------------------------------------------
    // Menu
    // -------------------------------------------------------------------------

    public function registerSubmenu(string $parentSlug): void
    {
        add_submenu_page(
            $parentSlug,
            __('Plantillas', 'homlity-real-estate'),
            __('Plantillas', 'homlity-real-estate'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public function enqueueAssets(string $hookSuffix): void
    {
        if (strpos($hookSuffix, self::MENU_SLUG) === false) {
            return;
        }

        $editorSettings = wp_enqueue_code_editor(['type' => 'application/x-httpd-php']);

        wp_enqueue_style('wp-codemirror');
        wp_enqueue_script('wp-theme-plugin-editor');

        // Pass runtime config to the inline JS rendered in renderPage().
        // We store it on window so the inline <script> block can pick it up.
        wp_add_inline_script(
            'wp-theme-plugin-editor',
            sprintf(
                'window.homlityTemplateEditor = %s;',
                wp_json_encode([
                    'ajaxUrl'        => admin_url('admin-ajax.php'),
                    'loadNonce'      => wp_create_nonce(self::NONCE_LOAD),
                    'saveNonce'      => wp_create_nonce(self::NONCE_SAVE),
                    'loadAction'     => self::AJAX_LOAD,
                    'saveAction'     => self::AJAX_SAVE,
                    'editorSettings' => $editorSettings !== false ? $editorSettings : new \stdClass(),
                    'i18n'           => [
                        'unsavedChanges' => __('Hay cambios sin guardar. ¿Deseas continuar?', 'homlity-real-estate'),
                        'saving'         => __('Guardando…', 'homlity-real-estate'),
                        'saveBtn'        => __('Guardar cambios', 'homlity-real-estate'),
                        'saved'          => __('✓ Guardado correctamente', 'homlity-real-estate'),
                        'errorSaving'    => __('Error al guardar el archivo.', 'homlity-real-estate'),
                        'errorLoading'   => __('Error al cargar el archivo.', 'homlity-real-estate'),
                        'connError'      => __('Error de conexión.', 'homlity-real-estate'),
                    ],
                ])
            ),
            'before'
        );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public function handleLoad(): void
    {
        check_ajax_referer(self::NONCE_LOAD, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        $relative = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : '';
        $path     = $this->validatePath($relative);

        if (!$path) {
            wp_send_json_error(['message' => 'Archivo no permitido.']);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            wp_send_json_error(['message' => 'No se pudo leer el archivo.']);
        }

        wp_send_json_success(['content' => $content, 'file' => $relative]);
    }

    public function handleSave(): void
    {
        check_ajax_referer(self::NONCE_SAVE, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        $relative = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : '';
        $content  = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
        $path     = $this->validatePath($relative);

        if (!$path) {
            wp_send_json_error(['message' => 'Archivo no permitido.']);
        }

        $result = file_put_contents($path, $content, LOCK_EX);
        if ($result === false) {
            wp_send_json_error(['message' => 'No se pudo guardar el archivo.']);
        }

        wp_send_json_success(['message' => 'Guardado correctamente.', 'bytes' => $result]);
    }

    // -------------------------------------------------------------------------
    // Page render
    // -------------------------------------------------------------------------

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $groups = $this->getTemplateGroups();
        ?>
        <div class="wrap" id="homlity-template-editor-wrap">
            <h1><?php esc_html_e('Editor de Plantillas', 'homlity-real-estate'); ?></h1>
            <p class="description">
                <?php esc_html_e(
                    'Edita las plantillas PHP del plugin directamente desde el admin. Usa Ctrl+S (o ⌘S en Mac) para guardar. Los cambios son inmediatos.',
                    'homlity-real-estate'
                ); ?>
            </p>

            <div id="homlity-tpl-notices"></div>

            <div id="homlity-tpl-layout">

                <!-- Sidebar: file tree -->
                <div id="homlity-tpl-sidebar">
                    <div id="homlity-tpl-sidebar-inner">
                        <?php foreach ($groups as $groupLabel => $files) : ?>
                            <div class="homlity-tpl-group">
                                <div class="homlity-tpl-group-label"><?php echo esc_html($groupLabel); ?></div>
                                <ul>
                                    <?php foreach ($files as $relative) : ?>
                                        <?php
                                        $absPath  = $this->validatePath($relative);
                                        $basename = basename($relative);
                                        $exists   = $absPath !== null;
                                        ?>
                                        <li>
                                            <?php if ($exists) : ?>
                                                <a href="#"
                                                   class="homlity-file-link"
                                                   data-file="<?php echo esc_attr($relative); ?>"
                                                   title="<?php echo esc_attr($relative); ?>">
                                                    <?php echo esc_html($basename); ?>
                                                </a>
                                            <?php else : ?>
                                                <span class="homlity-file-missing" title="<?php echo esc_attr($relative); ?>">
                                                    <?php echo esc_html($basename); ?>
                                                </span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Main: editor -->
                <div id="homlity-tpl-main">
                    <div id="homlity-tpl-toolbar">
                        <span id="homlity-tpl-filename"><?php esc_html_e('← Selecciona un archivo', 'homlity-real-estate'); ?></span>
                        <button id="homlity-tpl-save" class="button button-primary" disabled>
                            <?php esc_html_e('Guardar cambios', 'homlity-real-estate'); ?>
                        </button>
                    </div>

                    <div id="homlity-tpl-placeholder">
                        <p><?php esc_html_e('Selecciona un archivo de la lista para comenzar a editar.', 'homlity-real-estate'); ?></p>
                    </div>

                    <div id="homlity-tpl-editor-area" style="display:none">
                        <textarea id="homlity-tpl-content" style="width:100%;height:600px"></textarea>
                    </div>
                </div>

            </div><!-- #homlity-tpl-layout -->
        </div><!-- .wrap -->

        <style>
        #homlity-template-editor-wrap .description { margin-bottom: 16px; }
        #homlity-tpl-layout { display: flex; gap: 16px; margin-top: 16px; }
        #homlity-tpl-sidebar { width: 240px; flex-shrink: 0; }
        #homlity-tpl-sidebar-inner { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; max-height: 680px; overflow-y: auto; }
        .homlity-tpl-group { border-bottom: 1px solid #f0f0f1; }
        .homlity-tpl-group:last-child { border-bottom: none; }
        .homlity-tpl-group-label { font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #50575e; padding: 8px 12px 4px; background: #f6f7f7; }
        .homlity-tpl-group ul { margin: 0; padding: 4px 0; list-style: none; }
        .homlity-tpl-group li { margin: 0; }
        .homlity-file-link { display: block; padding: 4px 12px; font-size: 13px; text-decoration: none; color: #2271b1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .homlity-file-link:hover, .homlity-file-link.active { background: #f0f6fc; color: #135e96; }
        .homlity-file-link.active { font-weight: 600; }
        .homlity-file-missing { display: block; padding: 4px 12px; font-size: 13px; color: #999; text-decoration: line-through; }
        #homlity-tpl-main { flex: 1; min-width: 0; }
        #homlity-tpl-toolbar { display: flex; align-items: center; gap: 12px; padding: 8px 12px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px 4px 0 0; border-bottom: none; }
        #homlity-tpl-filename { flex: 1; font-size: 13px; font-family: monospace; color: #50575e; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #homlity-tpl-placeholder { background: #fff; border: 1px solid #c3c4c7; border-radius: 0 0 4px 4px; padding: 60px; text-align: center; color: #888; }
        #homlity-tpl-editor-area .CodeMirror { border: 1px solid #c3c4c7; border-radius: 0 0 4px 4px; height: 640px; font-size: 13px; }
        #homlity-tpl-notices .notice { margin: 0 0 12px; }
        </style>

        <script>
        (function ($) {
            var cfg     = window.homlityTemplateEditor || {};
            var editor  = null;
            var current = null;
            var dirty   = false;

            function showNotice(type, msg) {
                var $n = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + msg + '</p></div>');
                $('#homlity-tpl-notices').html($n);
                if (type === 'success') {
                    setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 3000);
                }
            }

            function setDirty(val) {
                dirty = val;
                if (current) {
                    $('#homlity-tpl-filename').text(dirty ? '* ' + current : current);
                }
            }

            function loadFile(relative) {
                $.post(cfg.ajaxUrl, {
                    action : cfg.loadAction,
                    nonce  : cfg.loadNonce,
                    file   : relative
                }, function (res) {
                    if (!res.success) {
                        showNotice('error', res.data ? res.data.message : cfg.i18n.errorLoading);
                        return;
                    }

                    var content = res.data.content;

                    if (!editor) {
                        $('#homlity-tpl-placeholder').hide();
                        $('#homlity-tpl-editor-area').show();
                        editor = wp.codeEditor.initialize(
                            document.getElementById('homlity-tpl-content'),
                            cfg.editorSettings
                        );
                        editor.codemirror.on('change', function () { setDirty(true); });
                    }

                    editor.codemirror.setValue(content);
                    editor.codemirror.clearHistory();
                    editor.codemirror.refresh();

                    current = relative;
                    setDirty(false);

                    $('#homlity-tpl-filename').text(relative);
                    $('#homlity-tpl-save').prop('disabled', false);
                    $('.homlity-file-link').removeClass('active');
                    $('[data-file="' + CSS.escape(relative) + '"]').addClass('active');
                }).fail(function () {
                    showNotice('error', cfg.i18n.connError);
                });
            }

            function saveFile() {
                if (!current || !editor) { return; }

                var $btn    = $('#homlity-tpl-save');
                var content = editor.codemirror.getValue();

                $btn.prop('disabled', true).text(cfg.i18n.saving);

                $.post(cfg.ajaxUrl, {
                    action  : cfg.saveAction,
                    nonce   : cfg.saveNonce,
                    file    : current,
                    content : content
                }, function (res) {
                    if (res.success) {
                        setDirty(false);
                        showNotice('success', cfg.i18n.saved);
                    } else {
                        showNotice('error', res.data ? res.data.message : cfg.i18n.errorSaving);
                    }
                }).fail(function () {
                    showNotice('error', cfg.i18n.connError);
                }).always(function () {
                    $btn.prop('disabled', false).text(cfg.i18n.saveBtn);
                });
            }

            // Events
            $(document).on('click', '.homlity-file-link', function (e) {
                e.preventDefault();
                var file = $(this).data('file');
                if (file === current) { return; }
                if (dirty && !confirm(cfg.i18n.unsavedChanges)) { return; }
                loadFile(file);
            });

            $('#homlity-tpl-save').on('click', saveFile);

            $(document).on('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    saveFile();
                }
            });

            window.addEventListener('beforeunload', function (e) {
                if (dirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

        }(jQuery));
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Template groups (sidebar structure)
    // -------------------------------------------------------------------------

    private function getTemplateGroups(): array
    {
        return [
            __('Páginas de entrada', 'homlity-real-estate') => [
                'single-property.php',
                'archive-property.php',
                'property-agent.php',
                'taxonomy-property.php',
            ],
            __('Detalle del inmueble', 'homlity-real-estate') => [
                'bootstrap/inmuebles/detalleInmueble.php',
                'bootstrap/inmuebles/detalleInmueble-noexiste.php',
                'bootstrap/inmuebles/detalleInmueble-retirado.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/titulo.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/sliders.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/caracteristicasPrincipales.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/caracteristicas.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/descripcion.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/perfilAsesor.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/formularios-leads.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/ubicacion.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/ubicacion-header.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/breadcrumb.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/gestion.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/tags.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/redesSociales.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/propiedadesSimilares.php',
                'bootstrap/inmuebles/componentes/detalleInmueble/password-form.php',
            ],
            __('Resultados / Búsqueda', 'homlity-real-estate') => [
                'bootstrap/inmuebles/search.php',
                'bootstrap/inmuebles/componentes/search/search-form.php',
                'bootstrap/inmuebles/componentes/search/search-form-sidebar.php',
                'bootstrap/inmuebles/componentes/search/search-header.php',
                'bootstrap/inmuebles/componentes/search/cards.php',
                'bootstrap/inmuebles/componentes/search/mapa.php',
                'bootstrap/inmuebles/componentes/search/paginator-form.php',
                'bootstrap/inmuebles/componentes/search/search-widgets.php',
                'bootstrap/inmuebles/componentes/filtrer-inmuebles-html.php',
            ],
            __('Tarjetas de inmueble', 'homlity-real-estate') => [
                'bootstrap/inmuebles/componentes/card.php',
                'bootstrap/inmuebles/componentes/card-default.php',
                'bootstrap/inmuebles/componentes/card-noclick.php',
                'bootstrap/inmuebles/componentes/card-password.php',
                'bootstrap/inmuebles/componentes/card/cover-image.php',
                'bootstrap/inmuebles/componentes/card/features.php',
                'bootstrap/inmuebles/componentes/card/advisor.php',
            ],
            __('Asesores', 'homlity-real-estate') => [
                'bootstrap/asesores/detalle-asesor.php',
                'bootstrap/asesores/componentes/asesores/detalle.php',
                'bootstrap/asesores/componentes/asesores/foto.php',
                'bootstrap/asesores/componentes/asesores/info.php',
            ],
            __('Widgets', 'homlity-real-estate') => [
                'bootstrap/widgets/inmueblesDestacados.php',
                'bootstrap/widgets/inmueblesEnVenta.php',
                'bootstrap/widgets/inmueblesEnArriendo.php',
                'bootstrap/widgets/inmueblesDelujo.php',
                'bootstrap/widgets/inmueblesLocalidad.php',
                'bootstrap/widgets/ciudadesDestacadas.php',
                'bootstrap/widgets/barriosDestacados.php',
                'bootstrap/widgets/search-inmuebles.php',
                'bootstrap/widgets/asesores.php',
                'bootstrap/widgets/propertysByTag.php',
            ],
            __('Parts', 'homlity-real-estate') => [
                'parts/property-card.php',
                'parts/property-card-bootstrap.php',
                'parts/property-listing.php',
                'parts/property-listing-bootstrap.php',
                'parts/property-content.php',
                'parts/property-gallery.php',
                'parts/property-filter.php',
                'parts/property-features-primary.php',
                'parts/property-features-secondary.php',
                'parts/property-map.php',
                'parts/property-summary.php',
                'parts/property-title.php',
                'parts/property-related.php',
                'parts/property-agent-info.php',
                'parts/property-share.php',
                'parts/property-operation-price.php',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Security helper
    // -------------------------------------------------------------------------

    /**
     * Validates that $relative is an existing file inside the templates/ directory.
     * Returns the absolute real path or null on any violation.
     */
    private function validatePath(string $relative): ?string
    {
        $relative = ltrim(str_replace(['..', "\0"], '', $relative), '/\\');

        if ($relative === '') {
            return null;
        }

        $base = realpath(HOMLITY_PLUGIN_PATH . 'templates');
        if (!$base) {
            return null;
        }

        $candidate = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $real      = realpath($candidate);

        if (!$real || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }

        if (!is_file($real)) {
            return null;
        }

        return $real;
    }
}
