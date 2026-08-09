<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended
/**
 * Contact Form 7 integration – registers the [homlity_property_code] and
 * [homlity_property_code_display] form tags.
 *
 * Usage in a CF7 form:
 *   [homlity_property_code property_code]
 *   [homlity_property_code_display property_code_display]
 *
 * On single property pages the tag auto-detects the current property.
 * Outside property pages, pass an explicit post_id option:
 *   [homlity_property_code property_code post_id:456]
 *
 * The field renders as a hidden input pre-filled with the property code
 * (_property_code meta) so it is captured automatically on form submission.
 */

namespace Homlity\PluginInmobiliario\Integrations\CF7;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!defined('ABSPATH')) {
    exit;
}

class CF7IntegrationService implements ServiceInterface
{
    public function register(): void
    {
        add_action('wpcf7_init', [$this, 'registerFormTags'], 10);
        add_action('wpcf7_admin_init', [$this, 'registerTagGenerator'], 20);
        add_action('wpcf7_before_send_mail', [$this, 'captureSubmission'], 20, 3);
    }

    /**
     * Emits the same normalized event used by the Elementor integration.
     *
     * @param mixed $contactForm WPCF7_ContactForm instance.
     * @param mixed $abort       Contact Form 7 abort flag.
     * @param mixed $submission  WPCF7_Submission instance when provided by CF7.
     */
    public function captureSubmission($contactForm, $abort = null, $submission = null): void
    {
        if (!is_object($contactForm)) {
            return;
        }

        if (!is_object($submission) && class_exists('WPCF7_Submission')) {
            $submission = \WPCF7_Submission::get_instance();
        }
        if (!is_object($submission) || !method_exists($submission, 'get_posted_data')) {
            return;
        }

        $posted = $submission->get_posted_data();
        if (!is_array($posted)) {
            return;
        }

        $fields = [];
        $labels = [];
        foreach ($posted as $key => $value) {
            $id = sanitize_key((string) $key);
            if ($id === '' || str_starts_with($id, '_wpcf7') || $this->isSensitiveField($id)) {
                continue;
            }

            $fields[$id] = $this->sanitizeSubmissionValue($value);
            $labels[$id] = sanitize_text_field(ucwords(str_replace(['_', '-'], ' ', $id)));
        }

        $sourceUrl = method_exists($submission, 'get_meta')
            ? (string) $submission->get_meta('url')
            : '';
        $sourceUrl = $sourceUrl !== '' ? $sourceUrl : (wp_get_referer() ?: home_url('/'));
        $formId = method_exists($contactForm, 'id') ? (string) $contactForm->id() : '';
        $formName = method_exists($contactForm, 'title')
            ? (string) $contactForm->title()
            : __('Formulario Contact Form 7', 'homlity-real-estate');

        $normalized = [
            'source'       => 'contact-form-7',
            'form_id'      => sanitize_text_field($formId),
            'form_name'    => sanitize_text_field($formName),
            'fields'       => $fields,
            'field_labels' => $labels,
            'field_types'  => [],
            'source_url'   => esc_url_raw($sourceUrl),
            'submitted_at' => current_time('mysql'),
        ];

        do_action('homlity_cf7_form_submitted', $normalized, $contactForm, $submission);
        do_action('homlity_form_submitted', $normalized, [
            'contact_form' => $contactForm,
            'submission'   => $submission,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tag registration
    // -------------------------------------------------------------------------

    public function registerFormTags(): void
    {
        if (!function_exists('wpcf7_add_form_tag')) {
            return;
        }

        wpcf7_add_form_tag(
            'homlity_property_code',
            [$this, 'renderTag'],
            ['name-attr' => true]
        );

        wpcf7_add_form_tag(
            'homlity_property_code_display',
            [$this, 'renderDisplayTag'],
            ['name-attr' => true]
        );
    }

    /**
     * Renders the hidden input that carries the property code.
     *
     * @param \WPCF7_FormTag $tag
     * @return string
     */
    public function renderTag($tag): string
    {
        if (class_exists('WPCF7_FormTag') && !($tag instanceof \WPCF7_FormTag)) {
            $tag = new \WPCF7_FormTag($tag);
        }

        $name = $tag->name ?? '';
        if (empty($name)) {
            return '';
        }

        $post_id = $this->resolvePostId($tag);
        $code    = $post_id ? (string) get_post_meta($post_id, '_property_code', true) : '';

        return sprintf(
            '<span class="wpcf7-form-control-wrap" data-name="%1$s">'
            . '<input type="hidden" name="%1$s" value="%2$s"'
            . ' class="wpcf7-form-control wpcf7-homlity-property-code" />'
            . '</span>',
            esc_attr($name),
            esc_attr($code)
        );
    }

    /**
     * Renders a visible property code plus a hidden input for submission.
     *
     * Usage:
     *   [homlity_property_code_display property_code_display]
     *   [homlity_property_code_display property_code_display label:"Código del inmueble"]
     *   [homlity_property_code_display property_code_display post_id:456]
     *
     * @param \WPCF7_FormTag $tag
     * @return string
     */
    public function renderDisplayTag($tag): string
    {
        if (class_exists('WPCF7_FormTag') && !($tag instanceof \WPCF7_FormTag)) {
            $tag = new \WPCF7_FormTag($tag);
        }

        $name = $tag->name ?? '';
        if (empty($name)) {
            return '';
        }

        $postId = $this->resolvePostId($tag);
        $code = $postId ? (string) get_post_meta($postId, '_property_code', true) : '';
        $label = $this->resolveDisplayLabel($tag);

        return sprintf(
            '<span class="wpcf7-form-control-wrap" data-name="%1$s">'
            . '<span class="wpcf7-form-control wpcf7-homlity-property-code-display">'
            . '<span class="wpcf7-homlity-property-code-display__label">%2$s</span> '
            . '<strong class="wpcf7-homlity-property-code-display__value">%3$s</strong>'
            . '</span>'
            . '<input type="hidden" name="%1$s" value="%4$s" class="wpcf7-form-control wpcf7-homlity-property-code" />'
            . '</span>',
            esc_attr($name),
            esc_html($label),
            esc_html($code),
            esc_attr($code)
        );
    }

    // -------------------------------------------------------------------------
    // Tag generator (CF7 form editor panel)
    // -------------------------------------------------------------------------

    public function registerTagGenerator(): void
    {
        if (!class_exists('WPCF7_TagGenerator')) {
            return;
        }

        \WPCF7_TagGenerator::get_instance()->add(
            'homlity_property_code',
            __('Código de Inmueble', 'homlity-real-estate'),
            [$this, 'renderTagGeneratorPanel'],
            ['version' => 2]
        );

        \WPCF7_TagGenerator::get_instance()->add(
            'homlity_property_code_display',
            __('Código de Inmueble Visible', 'homlity-real-estate'),
            [$this, 'renderDisplayTagGeneratorPanel'],
            ['version' => 2]
        );
    }

    public function renderTagGeneratorPanel($contact_form, $options): void
    {
        $options  = (array) $options;
        $panel_id = $options['id'] ?? 'homlity_property_code';
        ?>
        <header class="description-box">
            <h3><?php esc_html_e('Código de Inmueble (Homlity)', 'homlity-real-estate'); ?></h3>
            <p>
                <?php esc_html_e(
                    'Campo oculto que captura automáticamente el código del inmueble (_property_code). En páginas de propiedad individual se detecta solo; en otras páginas usa la opción post_id.',
                    'homlity-real-estate'
                ); ?>
            </p>
        </header>

        <div class="control-box">
            <fieldset>
                <legend><?php esc_html_e('Nombre del campo', 'homlity-real-estate'); ?></legend>
                <label>
                    <input type="text"
                           name="name"
                           class="tg-name oneline"
                           id="<?php echo esc_attr($panel_id); ?>-name"
                           value="property_code" />
                </label>
            </fieldset>

            <fieldset>
                <legend><?php esc_html_e('ID de propiedad (opcional)', 'homlity-real-estate'); ?></legend>
                <label>
                    <input type="number"
                           name="values"
                           class="tg-value oneline option"
                           data-tag-part="option"
                           data-option-name="post_id"
                           placeholder="<?php esc_attr_e('Dejar vacío para detectar automáticamente', 'homlity-real-estate'); ?>"
                    />
                </label>
                <p class="description">
                    <?php esc_html_e('Solo necesario si el formulario está fuera de la página del inmueble.', 'homlity-real-estate'); ?>
                </p>
            </fieldset>
        </div>

        <div class="insert-box">
            <input type="text"
                   name="<?php echo esc_attr($panel_id); ?>"
                   class="tag code"
                   readonly="readonly"
                   onfocus="this.select()" />
            <div class="submitbox">
                <input type="button"
                       class="button button-primary insert-tag"
                       value="<?php esc_attr_e('Insertar tag', 'homlity-real-estate'); ?>" />
            </div>
            <br class="clear" />
            <p class="description mail-tag">
                <?php
                printf(
                    /* translators: %s: mail tag placeholder */
                    esc_html__('Para usar en el correo: %s', 'homlity-real-estate'),
                    '<strong>[<span class="mail-tag"></span>]</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    public function renderDisplayTagGeneratorPanel($contact_form, $options): void
    {
        $options  = (array) $options;
        $panel_id = $options['id'] ?? 'homlity_property_code_display';
        ?>
        <header class="description-box">
            <h3><?php esc_html_e('Código de Inmueble Visible (Homlity)', 'homlity-real-estate'); ?></h3>
            <p>
                <?php esc_html_e(
                    'Muestra el código del inmueble dentro del formulario y además lo envía como campo oculto. En páginas de propiedad individual se detecta solo; en otras páginas usa la opción post_id.',
                    'homlity-real-estate'
                ); ?>
            </p>
        </header>

        <div class="control-box">
            <fieldset>
                <legend><?php esc_html_e('Nombre del campo', 'homlity-real-estate'); ?></legend>
                <label>
                    <input type="text"
                           name="name"
                           class="tg-name oneline"
                           id="<?php echo esc_attr($panel_id); ?>-name"
                           value="property_code_display" />
                </label>
            </fieldset>

            <fieldset>
                <legend><?php esc_html_e('Etiqueta visible', 'homlity-real-estate'); ?></legend>
                <label>
                    <input type="text"
                           name="values"
                           class="tg-value oneline option"
                           data-tag-part="option"
                           data-option-name="label"
                           placeholder="<?php esc_attr_e('Código del inmueble:', 'homlity-real-estate'); ?>" />
                </label>
            </fieldset>

            <fieldset>
                <legend><?php esc_html_e('ID de propiedad (opcional)', 'homlity-real-estate'); ?></legend>
                <label>
                    <input type="number"
                           name="values"
                           class="tg-value oneline option"
                           data-tag-part="option"
                           data-option-name="post_id"
                           placeholder="<?php esc_attr_e('Dejar vacío para detectar automáticamente', 'homlity-real-estate'); ?>" />
                </label>
            </fieldset>
        </div>

        <div class="insert-box">
            <input type="text"
                   name="<?php echo esc_attr($panel_id); ?>"
                   class="tag code"
                   readonly="readonly"
                   onfocus="this.select()" />
            <div class="submitbox">
                <input type="button"
                       class="button button-primary insert-tag"
                       value="<?php esc_attr_e('Insertar tag', 'homlity-real-estate'); ?>" />
            </div>
            <br class="clear" />
            <p class="description mail-tag">
                <?php
                printf(
                    esc_html__('Para usar en el correo: %s', 'homlity-real-estate'),
                    '<strong>[<span class="mail-tag"></span>]</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolves the property post ID from the tag options or page context.
     *
     * Priority:
     *   1. Explicit post_id option in the tag  →  [homlity_property_code field post_id:123]
     *   2. Current single-property page        →  is_singular('property')
     *   3. GET parameter                       →  ?property_id=123 or ?inmueble_id=123
     */
    private function resolvePostId($tag): int
    {
        // 1. Explicit option
        if (method_exists($tag, 'get_option')) {
            $explicit = (int) $tag->get_option('post_id', 'int', true);
            if ($explicit > 0 && get_post_type($explicit) === PropertyPostType::POST_TYPE) {
                return $explicit;
            }
        }

        // 2. Single property page
        if (is_singular(PropertyPostType::POST_TYPE)) {
            $queriedId = (int) get_queried_object_id();
            if ($queriedId > 0 && get_post_type($queriedId) === PropertyPostType::POST_TYPE) {
                return $queriedId;
            }

            $loopId = (int) get_the_ID();
            if ($loopId > 0 && get_post_type($loopId) === PropertyPostType::POST_TYPE) {
                return $loopId;
            }
        }

        // 3. Global post fallback (Elementor / shortcodes outside the main loop)
        $globalPostId = $this->resolveGlobalPostId();
        if ($globalPostId > 0) {
            return $globalPostId;
        }

        // 4. GET fallback by post ID (sanitized)
        foreach (['property_id', 'inmueble_id'] as $param) {
            if (!empty($_GET[$param])) {
                $id = (int) $_GET[$param];
                if ($id > 0 && get_post_type($id) === PropertyPostType::POST_TYPE) {
                    return $id;
                }
            }
        }

        // 5. GET fallback by property code (sanitized)
        foreach (['property_code', 'codigo', 'code'] as $param) {
            if (empty($_GET[$param])) {
                continue;
            }

            $postId = $this->findPostIdByPropertyCode((string) wp_unslash($_GET[$param]));
            if ($postId > 0) {
                return $postId;
            }
        }

        return 0;
    }

    private function resolveGlobalPostId(): int
    {
        global $post;

        if ($post instanceof \WP_Post && $post->post_type === PropertyPostType::POST_TYPE) {
            return (int) $post->ID;
        }

        return 0;
    }

    private function resolveDisplayLabel($tag): string
    {
        $default = __('Código del inmueble:', 'homlity-real-estate');

        if (!method_exists($tag, 'get_option')) {
            return $default;
        }

        $label = (string) $tag->get_option('label', '', true);
        $label = trim($label);

        return $label !== '' ? $label : $default;
    }

    private function findPostIdByPropertyCode(string $code): int
    {
        $code = sanitize_text_field($code);
        if ($code === '') {
            return 0;
        }

        $posts = get_posts([
            'post_type' => PropertyPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => '_property_code',
                    'value' => $code,
                    'compare' => '=',
                ],
            ],
        ]);

        return isset($posts[0]) ? (int) $posts[0] : 0;
    }

    private function isSensitiveField(string $id): bool
    {
        return (bool) preg_match('/(?:password|passwd|pass|token|captcha|honeypot|nonce|credit|card|cvv)/i', $id);
    }

    private function sanitizeSubmissionValue(mixed $value): string|array
    {
        if (is_array($value)) {
            return array_values(array_map(
                static fn($item): string => sanitize_text_field((string) $item),
                $value
            ));
        }

        return sanitize_textarea_field((string) $value);
    }
}
