<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended
/**
 * Contact Form 7 integration – registers the [homlity_property_code] form tag.
 *
 * Usage in a CF7 form:
 *   [homlity_property_code property_code]
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

if (!defined('ABSPATH')) {
    exit;
}

class CF7IntegrationService implements ServiceInterface
{
    public function register(): void
    {
        add_action('wpcf7_init', [$this, 'registerFormTags'], 10);
        add_action('wpcf7_admin_init', [$this, 'registerTagGenerator'], 20);
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
            [$this, 'renderTagGeneratorPanel']
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
            if ($explicit > 0) {
                return $explicit;
            }
        }

        // 2. Single property page
        if (is_singular('property')) {
            return (int) get_the_ID();
        }

        // 3. GET fallback (sanitized)
        foreach (['property_id', 'inmueble_id'] as $param) {
            if (!empty($_GET[$param])) {
                $id = (int) $_GET[$param];
                if ($id > 0 && get_post_type($id) === 'property') {
                    return $id;
                }
            }
        }

        return 0;
    }
}
