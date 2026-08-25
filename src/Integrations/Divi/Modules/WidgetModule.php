<?php
// Los superglobales que se leen en este archivo sirven sólo para saber en qué
// contexto del maquetador se está pintando (vista previa, pestaña activa,
// petición AJAX del editor). No procesan formularios: van saneados con
// absint()/sanitize_key() y toda rama que cambia estado exige current_user_can(),
// así que un nonce no aplica.
// phpcs:disable WordPress.Security.NonceVerification.Recommended

declare(strict_types=1);

use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Autonomous Divi adapter for Homlity's canonical widget definitions.
 * Uses Homlity Divi widget definitions and its builder-neutral rendering contract.
 * declarative/runtime contract used by Homlity widgets.
 */
#[\AllowDynamicProperties]
class Homlity_Divi_Widget_Module extends ET_Builder_Module
{
    public $vb_support = 'partial';

    // ET_Builder_Element assigns these legacy extension points during its
    // constructor. Declaring them prevents PHP 8.2 dynamic-property notices.
    public $text_shadow = null;
    public $margin_padding = null;
    public $_additional_fields_options = [];

    private string $widgetClass;
    private ?\Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Widget_Base $prototype = null;

    public function __construct(string $widgetClass)
    {
        $this->widgetClass = $widgetClass;
        $widget = $this->widget();
        $this->name = $widget->get_title();
        $this->slug = 'homlity_divi_' . sanitize_key($widget->get_name());
        parent::__construct();
    }

    public function init(): void
    {
        $this->name = $this->widget()->get_title();
    }

    public function get_fields(): array
    {
        $fields = [];
        foreach ($this->widget()->get_controls() as $name => $control) {
            if (($control['type'] ?? '') === 'homlity_group') {
                $fields += $this->groupControlFields((string) $name, (array) $control);
                continue;
            }
            $field = $this->controlToField((string) $name, (array) $control);
            if ($field !== null) {
                $fields[$name] = $field;
            }
        }
        return $fields;
    }

    public function get_settings_modal_toggles(): array
    {
        $toggles = ['general' => ['toggles' => []], 'advanced' => ['toggles' => []]];
        foreach ($this->widget()->get_controls() as $control) {
            $tab = (($control['tab'] ?? '') === Controls_Manager::TAB_STYLE) ? 'advanced' : 'general';
            $section = sanitize_key((string) ($control['section'] ?? 'main_content')) ?: 'main_content';
            $label = trim((string) ($control['section_label'] ?? ''));
            $toggles[$tab]['toggles'][$section] = $label !== ''
                ? esc_html($label)
                : ucwords(str_replace('_', ' ', $section));
        }
        return $toggles;
    }

    public function render($attrs, $content = null, $function_name = ''): string
    {
        $widget = new $this->widgetClass();
        $settings = $this->normalizeSettings((array) $this->props, $widget->get_controls());
        $instance = substr(md5($this->slug . wp_json_encode($settings) . wp_rand()), 0, 10);
        $css = $this->buildCss($widget->get_controls(), $settings, '.homlity-divi-' . $instance);

        $widget->homlitySetSettings($settings);
        $markup = $widget->homlityRender();

        // Dynamic widgets can legitimately return no markup when the selected
        // preview property has no matching data. Keep the module visible and
        // selectable in Divi without leaking editor-only content to visitors.
        if (trim($markup) === '' && $this->isVisualBuilderPreview()) {
            $markup = $this->emptyPreviewMarkup($widget);
            $css .= $this->emptyPreviewCss('.homlity-divi-' . $instance);
        }

        $output = ($css !== '' ? '<style>' . wp_strip_all_tags($css) . '</style>' : '')
            . '<div class="homlity-divi-widget homlity-divi-' . esc_attr($instance) . '">'
            . $markup
            . '</div>';

        // Divi only adds its native module wrapper automatically when
        // vb_support is "on". These widgets intentionally use "partial" so
        // their PHP preview remains available, therefore the wrapper has to be
        // rendered here. Its order class is the target used by all native
        // Design controls (spacing, background, border, sizing, animation...).
        $renderSlug = is_string($function_name) && $function_name !== ''
            ? $function_name
            : $this->slug;

        return $this->_render_module_wrapper($output, $renderSlug);
    }

    private function isVisualBuilderPreview(): bool
    {
        if (function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled()) {
            return true;
        }

        if (function_exists('et_fb_is_enabled') && et_fb_is_enabled()) {
            return true;
        }

        if (!function_exists('wp_doing_ajax') || !wp_doing_ajax()) {
            return false;
        }

        // Divi's server-side partial preview is rendered through admin-ajax,
        // where et_core_is_fb_enabled() intentionally returns false.
        $action = isset($_REQUEST['action'])
            ? sanitize_key(wp_unslash((string) $_REQUEST['action']))
            : '';

        return str_starts_with($action, 'et_fb_') || str_starts_with($action, 'et_builder_');
    }

    private function emptyPreviewMarkup(
        \Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Widget_Base $widget
    ): string {
        return '<div class="homlity-divi-empty-preview" data-homlity-divi-empty-preview="true">'
            . '<strong class="homlity-divi-empty-preview__title">'
            . esc_html($widget->get_title())
            . '</strong>'
            . '<span class="homlity-divi-empty-preview__message">'
            . esc_html__(
                'No hay datos disponibles para este inmueble de vista previa. El widget está activo y se mostrará cuando exista contenido.',
                'homlity-real-estate'
            )
            . '</span>'
            . '</div>';
    }

    private function emptyPreviewCss(string $wrapper): string
    {
        return $wrapper . ' .homlity-divi-empty-preview{'
            . 'box-sizing:border-box;display:flex;flex-direction:column;gap:5px;justify-content:center;'
            . 'min-height:88px;padding:16px;border:1px dashed #8d96a0;border-radius:6px;'
            . 'background:rgba(255,255,255,.92);color:#3c434a;text-align:center;}'
            . $wrapper . ' .homlity-divi-empty-preview__title{font-size:14px;line-height:1.4;}'
            . $wrapper . ' .homlity-divi-empty-preview__message{font-size:12px;line-height:1.5;}';
    }

    private function widget(): \Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Widget_Base
    {
        if ($this->prototype === null) {
            $class = $this->widgetClass;
            $this->prototype = new $class();
        }
        return $this->prototype;
    }

    private function controlToField(string $name, array $control): ?array
    {
        $type = (string) ($control['type'] ?? Controls_Manager::TEXT);
        if (in_array($type, [Controls_Manager::HEADING, Controls_Manager::HIDDEN], true)) {
            return null;
        }

        $field = [
            'label' => esc_html((string) ($control['label'] ?? ucwords(str_replace('_', ' ', $name)))),
            'type' => 'text',
            'default' => $this->encodeDefault($control['default'] ?? ''),
            'tab_slug' => (($control['tab'] ?? '') === Controls_Manager::TAB_STYLE) ? 'advanced' : 'general',
            'toggle_slug' => sanitize_key((string) ($control['section'] ?? 'main_content')) ?: 'main_content',
            'description' => esc_html((string) ($control['description'] ?? '')),
        ];
        if (!empty($control['responsive'])) {
            $field['mobile_options'] = true;
        }
        $this->applyConditions($field, (array) ($control['condition'] ?? []));

        if ($type === Controls_Manager::SWITCHER) {
            $field['type'] = 'yes_no_button';
            $field['options'] = ['on' => esc_html__('Sí', 'homlity-real-estate'), 'off' => esc_html__('No', 'homlity-real-estate')];
            $field['default'] = ($control['default'] ?? '') === 'yes' ? 'on' : 'off';
        } elseif (in_array($type, [Controls_Manager::SELECT, Controls_Manager::SELECT2, Controls_Manager::CHOOSE], true)) {
            // Divi does not expose a native multi-select field. Preserve those
            // values as JSON so the canonical widget still receives an array.
            if ($type === Controls_Manager::SELECT2 && !empty($control['multiple'])) {
                $field['type'] = 'textarea';
                $field['description'] = trim($field['description'] . ' ' . esc_html__('Ingrese los valores seleccionados como un arreglo JSON.', 'homlity-real-estate'));
                return $field;
            }
            $field['type'] = 'select';
            $field['options'] = $this->optionLabels((array) ($control['options'] ?? []));
        } elseif ($type === Controls_Manager::TEXTAREA) {
            $field['type'] = 'textarea';
        } elseif ($type === Controls_Manager::COLOR) {
            $field['type'] = 'color-alpha';
        } elseif ($type === Controls_Manager::SLIDER) {
            $field['type'] = 'range';
            $range = $this->sliderRange($control);
            if ($range !== []) {
                $field['range_settings'] = $range;
            }
            $field['default'] = $this->sliderDefault($control['default'] ?? '');
        } elseif ($type === Controls_Manager::DIMENSIONS) {
            // Divi has no native four-value dimensions field.
            // dimensions control. A CSS shorthand keeps the UI usable and is
            // normalized to the Divi widget top/right/bottom/left structure.
            $field['type'] = 'text';
            $field['default'] = $this->dimensionsDefault($control['default'] ?? '');
            $field['description'] = trim(
                $field['description'] . ' '
                . esc_html__('Use una medida CSS (ej. 16px) o cuatro valores (ej. 16px 20px 16px 20px).', 'homlity-real-estate')
            );
        } elseif ($type === Controls_Manager::ICONS) {
            $field['type'] = 'select_icon';
            $field['default'] = $this->iconDefault($control['default'] ?? '');
            $field['class'] = ['et-pb-font-icon'];
        } elseif ($type === Controls_Manager::URL) {
            $field['type'] = 'text';
            $field['default'] = is_array($control['default'] ?? null)
                ? (string) (($control['default']['url'] ?? ''))
                : (string) ($control['default'] ?? '');
        } elseif ($type === Controls_Manager::MEDIA) {
            $field['type'] = 'upload';
            $field['upload_button_text'] = esc_html__('Seleccionar archivo', 'homlity-real-estate');
            $field['choose_text'] = esc_html__('Elegir archivo', 'homlity-real-estate');
            $field['update_text'] = esc_html__('Usar archivo', 'homlity-real-estate');
        }

        return $field;
    }

    private function groupControlFields(string $name, array $control): array
    {
        $group = strtolower((string) ($control['group_type'] ?? ''));
        // Las etiquetas se traducen aquí, en la propia tabla. Antes se guardaban
        // como texto plano y se envolvían con esc_html__($label) más abajo: el
        // extractor de traducciones no lee variables, así que ninguna de estas
        // cadenas llegaba a los .po.
        $definitions = str_contains($group, 'typography') ? [
            'font_family' => [esc_html__('Fuente', 'homlity-real-estate'), 'text'],
            'font_size' => [esc_html__('Tamaño', 'homlity-real-estate'), 'text'],
            'font_weight' => [esc_html__('Peso', 'homlity-real-estate'), 'text'],
            'text_transform' => [esc_html__('Transformación', 'homlity-real-estate'), 'text'],
            'font_style' => [esc_html__('Estilo', 'homlity-real-estate'), 'text'],
            'text_decoration' => [esc_html__('Decoración', 'homlity-real-estate'), 'text'],
            'line_height' => [esc_html__('Altura de línea', 'homlity-real-estate'), 'text'],
            'letter_spacing' => [esc_html__('Espaciado', 'homlity-real-estate'), 'text'],
        ] : (str_contains($group, 'border') ? [
            'border_type' => [esc_html__('Tipo de borde', 'homlity-real-estate'), 'text'],
            'border_width' => [esc_html__('Ancho de borde', 'homlity-real-estate'), 'text'],
            'border_color' => [esc_html__('Color de borde', 'homlity-real-estate'), 'color-alpha'],
            'border_radius' => [esc_html__('Radio', 'homlity-real-estate'), 'text'],
        ] : (str_contains($group, 'background') ? [
            'background_color' => [esc_html__('Color de fondo', 'homlity-real-estate'), 'color-alpha'],
        ] : (str_contains($group, 'shadow') ? [
            'shadow' => [esc_html__('Sombra CSS', 'homlity-real-estate'), 'text'],
        ] : [])));

        $fields = [];
        $tab = (($control['tab'] ?? '') === Controls_Manager::TAB_STYLE) ? 'advanced' : 'general';
        $toggle = sanitize_key((string) ($control['section'] ?? 'main_content')) ?: 'main_content';
        foreach ($definitions as $suffix => [$label, $type]) {
            $fields[$name . '_' . $suffix] = [
                'label' => $label,
                'type' => $type,
                'default' => '',
                'tab_slug' => $tab,
                'toggle_slug' => $toggle,
            ];
            if (in_array($suffix, ['font_size', 'line_height', 'letter_spacing', 'border_width', 'border_radius'], true)) {
                $fields[$name . '_' . $suffix]['mobile_options'] = true;
            }
            $this->applyConditions($fields[$name . '_' . $suffix], (array) ($control['condition'] ?? []));
        }
        return $fields;
    }

    private function normalizeSettings(array $props, array $controls): array
    {
        $settings = [];
        foreach ($controls as $name => $control) {
            if (($control['type'] ?? '') === 'homlity_group') {
                continue;
            }
            $value = array_key_exists($name, $props) ? $props[$name] : ($control['default'] ?? '');
            $type = (string) ($control['type'] ?? '');
            if ($type === Controls_Manager::SWITCHER) {
                $settings[$name] = in_array($value, ['on', 'yes', 'true', true, 1, '1'], true) ? 'yes' : '';
                continue;
            }
            if ($type === Controls_Manager::ICONS) {
                $settings[$name] = $this->normalizeIconValue($value);
                continue;
            }
            if ($type === Controls_Manager::URL) {
                $settings[$name] = $this->normalizeUrlValue($value);
                continue;
            }
            if (is_string($value) && in_array(substr(trim($value), 0, 1), ['{', '['], true)) {
                $decoded = json_decode($value, true);
                $settings[$name] = is_array($decoded) ? $decoded : $value;
                continue;
            }
            if ($type === Controls_Manager::MEDIA && is_string($value)) {
                $settings[$name] = ['id' => attachment_url_to_postid($value), 'url' => esc_url_raw($value)];
                continue;
            }
            if ($type === Controls_Manager::SLIDER) {
                $settings[$name] = $this->normalizeSliderValue($value, $control);
                continue;
            }
            if ($type === Controls_Manager::DIMENSIONS) {
                $settings[$name] = $this->normalizeDimensionsValue($value, $control);
                continue;
            }
            $settings[$name] = $value;
        }
        foreach ($props as $name => $value) {
            if (is_string($name) && !array_key_exists($name, $settings)) {
                $settings[$name] = $value;
            }
        }
        return $settings;
    }

    private function buildCss(array $controls, array $settings, string $wrapper): string
    {
        $rules = [];
        $responsiveRules = ['tablet' => [], 'phone' => []];
        foreach ($controls as $name => $control) {
            if (($control['type'] ?? '') === 'homlity_group') {
                $this->appendGroupCss($rules, (string) $name, (array) $control, $settings, $wrapper);
                continue;
            }
            $value = $settings[$name] ?? ($control['default'] ?? null);
            if (($control['type'] ?? '') === Controls_Manager::HIDDEN
                && $this->conditionsMatch((array) ($control['condition'] ?? []), $settings)) {
                $value = '1';
            }
            if (!$this->hasCssValue($value, $control)) {
                continue;
            }
            foreach ((array) ($control['selectors'] ?? []) as $selector => $declaration) {
                $selector = str_replace('{{WRAPPER}}', $wrapper, (string) $selector);
                $declaration = $this->selectorDeclaration((string) $declaration, $value, $control);
                if ($selector !== '' && $declaration !== '' && !str_contains($declaration, '{{')) {
                    $rules[$selector][] = $declaration;
                }
            }

            if (empty($control['responsive'])) {
                continue;
            }
            foreach (['tablet', 'phone'] as $device) {
                $responsiveValue = $this->responsiveValue($settings, (string) $name, $device, $control);
                if (!$this->hasCssValue($responsiveValue, $control)) {
                    continue;
                }
                foreach ((array) ($control['selectors'] ?? []) as $selector => $declaration) {
                    $selector = str_replace('{{WRAPPER}}', $wrapper, (string) $selector);
                    $declaration = $this->selectorDeclaration((string) $declaration, $responsiveValue, $control);
                    if ($selector !== '' && $declaration !== '' && !str_contains($declaration, '{{')) {
                        $responsiveRules[$device][$selector][] = $declaration;
                    }
                }
            }
        }
        foreach (['tablet', 'phone'] as $device) {
            if (!empty($rules['@' . $device]) && is_array($rules['@' . $device])) {
                foreach ($rules['@' . $device] as $selector => $declarations) {
                    $responsiveRules[$device][$selector] = array_merge(
                        $responsiveRules[$device][$selector] ?? [],
                        (array) $declarations
                    );
                }
            }
            unset($rules['@' . $device]);
        }

        $css = $this->compileRules($rules);
        $tabletCss = $this->compileRules($responsiveRules['tablet']);
        $phoneCss = $this->compileRules($responsiveRules['phone']);
        $css .= $tabletCss !== '' ? '@media(max-width:980px){' . $tabletCss . '}' : '';
        $css .= $phoneCss !== '' ? '@media(max-width:767px){' . $phoneCss . '}' : '';
        return $css;
    }

    private function compileRules(array $rules): string
    {
        $css = '';
        foreach ($rules as $selector => $declarations) {
            $css .= $selector . '{' . implode('', array_unique($declarations)) . '}';
        }
        return $css;
    }

    private function responsiveValue(array $settings, string $name, string $device, array $control): mixed
    {
        foreach ([$name . '_' . $device, $name . '__' . $device] as $key) {
            if (!array_key_exists($key, $settings)) {
                continue;
            }
            $type = (string) ($control['type'] ?? '');
            if ($type === Controls_Manager::SWITCHER) {
                return in_array($settings[$key], ['on', 'yes', 'true', true, 1, '1'], true)
                    ? 'yes'
                    : '';
            }
            if ($type === Controls_Manager::SLIDER) {
                return $this->normalizeSliderValue($settings[$key], $control);
            }
            if ($type === Controls_Manager::DIMENSIONS) {
                return $this->normalizeDimensionsValue($settings[$key], $control);
            }
            return $settings[$key];
        }

        $defaultKey = $device === 'phone' ? 'mobile_default' : 'tablet_default';
        if (array_key_exists($defaultKey, $control)) {
            return $control[$defaultKey];
        }

        return null;
    }

    private function hasCssValue(mixed $value, array $control): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_array($value)) {
            foreach (['value', 'size', 'top', 'right', 'bottom', 'left'] as $key) {
                if (array_key_exists($key, $value) && trim((string) $value[$key]) !== '') {
                    return true;
                }
            }
            return false;
        }

        if (trim((string) $value) !== '') {
            return true;
        }

        return array_key_exists('', (array) ($control['selectors_dictionary'] ?? []));
    }

    private function appendGroupCss(array &$rules, string $name, array $control, array $settings, string $wrapper): void
    {
        $selector = str_replace('{{WRAPPER}}', $wrapper, (string) ($control['selector'] ?? ''));
        if ($selector === '') {
            return;
        }
        $group = strtolower((string) ($control['group_type'] ?? ''));
        $map = str_contains($group, 'typography') ? [
            'font_family' => 'font-family', 'font_size' => 'font-size', 'font_weight' => 'font-weight',
            'text_transform' => 'text-transform', 'font_style' => 'font-style',
            'text_decoration' => 'text-decoration', 'line_height' => 'line-height',
            'letter_spacing' => 'letter-spacing',
        ] : (str_contains($group, 'border') ? [
            'border_type' => 'border-style', 'border_width' => 'border-width',
            'border_color' => 'border-color', 'border_radius' => 'border-radius',
        ] : (str_contains($group, 'background') ? ['background_color' => 'background-color'] : []));

        foreach ($map as $suffix => $property) {
            $value = trim((string) ($settings[$name . '_' . $suffix] ?? ''));
            if ($value !== '') {
                if (in_array($suffix, ['font_size', 'letter_spacing', 'border_width', 'border_radius'], true)) {
                    $value = $this->cssLength($value);
                }
                $rules[$selector][] = $property . ':' . $value . ';';
            }

            foreach (['tablet', 'phone'] as $device) {
                $responsiveValue = trim((string) (
                    $settings[$name . '_' . $suffix . '_' . $device]
                    ?? $settings[$name . '_' . $suffix . '__' . $device]
                    ?? ''
                ));
                if ($responsiveValue === '') {
                    continue;
                }
                if (in_array($suffix, ['font_size', 'letter_spacing', 'border_width', 'border_radius'], true)) {
                    $responsiveValue = $this->cssLength($responsiveValue);
                }
                $rules['@' . $device][$selector][] = $property . ':' . $responsiveValue . ';';
            }
        }
        if (str_contains($group, 'shadow')) {
            $value = trim((string) ($settings[$name . '_shadow'] ?? ''));
            if ($value !== '') {
                $property = str_contains($group, 'text') ? 'text-shadow' : 'box-shadow';
                $rules[$selector][] = $property . ':' . $value . ';';
            }
        }
    }

    private function replaceTokens(string $css, mixed $value): string
    {
        $data = is_array($value) ? $value : ['value' => $value, 'size' => $value, 'unit' => ''];
        $replacements = [
            '{{VALUE}}' => (string) ($data['value'] ?? $data['size'] ?? ''),
            '{{SIZE}}' => (string) ($data['size'] ?? $data['value'] ?? ''),
            '{{UNIT}}' => (string) ($data['unit'] ?? ''),
        ];
        foreach (['TOP' => 'top', 'RIGHT' => 'right', 'BOTTOM' => 'bottom', 'LEFT' => 'left'] as $token => $key) {
            $replacements['{{' . $token . '}}'] = (string) ($data[$key] ?? '');
        }
        return strtr($css, $replacements);
    }

    private function selectorDeclaration(string $declaration, mixed $value, array $control): string
    {
        $dictionary = (array) ($control['selectors_dictionary'] ?? []);
        $lookup = is_array($value)
            ? (string) ($value['value'] ?? $value['size'] ?? '')
            : (string) $value;
        if (array_key_exists($lookup, $dictionary)) {
            $mapped = (string) $dictionary[$lookup];
            if (!str_contains($declaration, '{{VALUE}}') && str_contains($mapped, ':')) {
                return $mapped;
            }
            $value = is_array($value)
                ? array_replace($value, ['value' => $mapped, 'size' => $mapped])
                : $mapped;
        }
        return $this->replaceTokens($declaration, $value);
    }

    private function sliderRange(array $control): array
    {
        $ranges = (array) ($control['range'] ?? []);
        $unit = $this->controlUnit($control);
        $range = (array) ($ranges[$unit] ?? reset($ranges) ?: []);
        $result = [];
        foreach (['min', 'max', 'step'] as $key) {
            if (isset($range[$key]) && is_numeric($range[$key])) {
                $result[$key] = (float) $range[$key];
            }
        }
        return $result;
    }

    private function sliderDefault(mixed $value): string
    {
        if (is_array($value)) {
            return (string) ($value['size'] ?? $value['value'] ?? '');
        }
        return (string) $value;
    }

    private function dimensionsDefault(mixed $value): string
    {
        if (!is_array($value)) {
            return (string) $value;
        }
        $unit = (string) ($value['unit'] ?? 'px');
        $parts = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $parts[] = (string) ($value[$side] ?? '0') . $unit;
        }
        return implode(' ', $parts);
    }

    private function iconDefault(mixed $value): string
    {
        $raw = trim(is_array($value) ? (string) ($value['value'] ?? '') : (string) $value);
        if ($raw === '' || str_contains($raw, '||') || preg_match('/^%%\d+%%$/', $raw) === 1) {
            return $raw;
        }

        return $this->fontAwesomeClassToDiviIcon($raw);
    }

    private function normalizeIconValue(mixed $value): array
    {
        if (is_array($value)) {
            return [
                'value' => sanitize_text_field((string) ($value['value'] ?? '')),
                'library' => sanitize_key((string) ($value['library'] ?? '')),
            ];
        }
        $raw = trim((string) $value);
        if ($raw !== '' && $raw[0] === '{') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->normalizeIconValue($decoded);
            }
        }
        if (str_contains($raw, '||') || preg_match('/^%%\d+%%$/', $raw) === 1) {
            return ['value' => sanitize_text_field($raw), 'library' => 'divi'];
        }
        $library = str_contains($raw, 'fab ') ? 'fa-brands' : 'fa-solid';
        return ['value' => sanitize_text_field($raw), 'library' => $library];
    }

    /**
     * Converts an Elementor/Font Awesome class into Divi's select_icon value.
     */
    private function fontAwesomeClassToDiviIcon(string $classes): string
    {
        if (preg_match('/(?:^|\s)fa-([a-z0-9-]+)(?:\s|$)/i', $classes, $matches) !== 1) {
            return '';
        }

        $name = strtolower($matches[1]);
        $name = [
            'house'        => 'home',
            'circle-check' => 'check-circle',
            // Divi 4.x ships the former Twitter icon but predates X/Twitter.
            'x-twitter'    => 'twitter',
        ][$name] ?? $name;
        $preferredWeight = str_contains($classes, 'fas ') ? 900 : 400;

        if (function_exists('et_pb_get_extended_font_icon_symbols')) {
            foreach ((array) et_pb_get_extended_font_icon_symbols() as $icon) {
                if (!is_array($icon)
                    || !empty($icon['is_divi_icon'])
                    || strtolower((string) ($icon['name'] ?? '')) !== $name
                    || (int) ($icon['font_weight'] ?? 400) !== $preferredWeight) {
                    continue;
                }

                $unicode = (string) ($icon['unicode'] ?? '');
                if ($unicode !== '') {
                    return $unicode . '||fa||' . $preferredWeight;
                }
            }
        }

        // Keep defaults usable when Divi requests third-party module fields
        // before its complete icon catalogue has been initialized.
        $fallbacks = [
            'whatsapp'      => '&#xf232;||fa||400',
            'th-large'      => '&#xf009;||fa||900',
            'map-marker-alt'=> '&#xf3c5;||fa||900',
        ];
        if (isset($fallbacks[$name])) {
            return $fallbacks[$name];
        }

        return '';
    }

    private function normalizeUrlValue(mixed $value): array
    {
        if (is_array($value)) {
            return [
                'url' => esc_url_raw((string) ($value['url'] ?? '')),
                'is_external' => !empty($value['is_external']),
                'nofollow' => !empty($value['nofollow']),
            ];
        }
        $raw = trim((string) $value);
        if ($raw !== '' && $raw[0] === '{') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->normalizeUrlValue($decoded);
            }
        }
        return ['url' => esc_url_raw($raw), 'is_external' => false, 'nofollow' => false];
    }

    private function normalizeSliderValue(mixed $value, array $control): array
    {
        if (is_array($value)) {
            return [
                'size' => (string) ($value['size'] ?? $value['value'] ?? ''),
                'value' => (string) ($value['value'] ?? $value['size'] ?? ''),
                'unit' => (string) ($value['unit'] ?? $this->controlUnit($control)),
            ];
        }
        $raw = trim((string) $value);
        if ($raw !== '' && in_array($raw[0], ['{', '['], true)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->normalizeSliderValue($decoded, $control);
            }
        }
        if (preg_match('/^(-?(?:\d+|\d*\.\d+))\s*([a-z%]*)$/i', $raw, $matches)) {
            return ['size' => $matches[1], 'value' => $matches[1], 'unit' => $matches[2] ?: $this->controlUnit($control)];
        }
        return ['size' => $raw, 'value' => $raw, 'unit' => $this->controlUnit($control)];
    }

    private function normalizeDimensionsValue(mixed $value, array $control): array
    {
        if (is_array($value)) {
            $unit = (string) ($value['unit'] ?? $this->controlUnit($control));
            return [
                'top' => (string) ($value['top'] ?? ''),
                'right' => (string) ($value['right'] ?? $value['top'] ?? ''),
                'bottom' => (string) ($value['bottom'] ?? $value['top'] ?? ''),
                'left' => (string) ($value['left'] ?? $value['right'] ?? $value['top'] ?? ''),
                'unit' => $unit,
            ];
        }
        $raw = trim(str_replace('|', ' ', (string) $value));
        if ($raw !== '' && in_array($raw[0], ['{', '['], true)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->normalizeDimensionsValue($decoded, $control);
            }
        }
        $parts = preg_split('/\s+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = array_slice($parts, 0, 4);
        if ($parts === []) {
            $parts = [''];
        }
        $expanded = match (count($parts)) {
            1 => [$parts[0], $parts[0], $parts[0], $parts[0]],
            2 => [$parts[0], $parts[1], $parts[0], $parts[1]],
            3 => [$parts[0], $parts[1], $parts[2], $parts[1]],
            default => $parts,
        };
        $unit = $this->controlUnit($control);
        $values = [];
        foreach ($expanded as $part) {
            if (preg_match('/^(-?(?:\d+|\d*\.\d+))\s*([a-z%]*)$/i', $part, $matches)) {
                $values[] = $matches[1];
                $unit = $matches[2] ?: $unit;
            } else {
                $values[] = $part;
            }
        }
        return ['top' => $values[0], 'right' => $values[1], 'bottom' => $values[2], 'left' => $values[3], 'unit' => $unit];
    }

    private function controlUnit(array $control): string
    {
        $default = $control['default'] ?? [];
        if (is_array($default) && !empty($default['unit'])) {
            return $this->sanitizeCssUnit((string) $default['unit']);
        }
        $units = (array) ($control['size_units'] ?? []);
        return $this->sanitizeCssUnit((string) ($units[0] ?? 'px'));
    }

    private function sanitizeCssUnit(string $unit): string
    {
        $unit = strtolower(trim($unit));
        return in_array($unit, ['px', '%', 'em', 'rem', 'vw', 'vh', 'vmin', 'vmax', 'deg', 's', 'ms'], true)
            ? $unit
            : 'px';
    }

    private function cssLength(string $value): string
    {
        return is_numeric($value) ? $value . 'px' : $value;
    }

    private function optionLabels(array $options): array
    {
        $labels = [];
        foreach ($options as $value => $option) {
            $label = is_array($option)
                ? (string) ($option['title'] ?? $option['label'] ?? $value)
                : (string) $option;
            $labels[(string) $value] = esc_html($label);
        }
        return $labels;
    }

    private function applyConditions(array &$field, array $conditions): void
    {
        foreach ($conditions as $rawName => $rawValue) {
            if (!is_string($rawName) || $rawName === '') {
                continue;
            }
            $negative = str_ends_with($rawName, '!');
            $name = $negative ? substr($rawName, 0, -1) : $rawName;
            $values = array_map(
                fn($value): string => $this->normalizeConditionValue($value),
                (array) $rawValue
            );
            $target = $negative ? 'show_if_not' : 'show_if';
            $field[$target][$name] = count($values) === 1 ? $values[0] : $values;
        }
    }

    private function normalizeConditionValue(mixed $value): string
    {
        if ($value === 'yes' || $value === true || $value === 1 || $value === '1') {
            return 'on';
        }
        if ($value === 'no' || $value === false || $value === 0 || $value === '0') {
            return 'off';
        }
        return (string) $value;
    }

    private function conditionsMatch(array $conditions, array $settings): bool
    {
        foreach ($conditions as $rawName => $expected) {
            if (!is_string($rawName) || $rawName === '') {
                continue;
            }
            $negative = str_ends_with($rawName, '!');
            $name = $negative ? substr($rawName, 0, -1) : $rawName;
            $actual = $settings[$name] ?? null;
            $expectedValues = array_map(
                fn($item): string => $this->normalizeConditionValue($item),
                (array) $expected
            );
            $normalizedActual = $this->normalizeConditionValue($actual);
            $matches = in_array($normalizedActual, $expectedValues, true);
            if ((!$negative && !$matches) || ($negative && $matches)) {
                return false;
            }
        }
        return true;
    }

    private function encodeDefault(mixed $value): string
    {
        if (is_array($value)) {
            return (string) wp_json_encode($value);
        }
        return (string) $value;
    }
}
