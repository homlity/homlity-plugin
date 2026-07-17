<?php

declare(strict_types=1);

use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Autonomous Divi adapter for Homlity's canonical widget definitions.
 * Elementor itself is not required; ElementorShim supplies only the small
 * declarative/runtime contract used by Homlity widgets.
 */
#[\AllowDynamicProperties]
class Homlity_Divi_Elementor_Widget_Module extends ET_Builder_Module
{
    public $vb_support = 'partial';

    // ET_Builder_Element assigns these legacy extension points during its
    // constructor. Declaring them prevents PHP 8.2 dynamic-property notices.
    public $text_shadow = null;
    public $margin_padding = null;
    public $_additional_fields_options = [];

    private string $widgetClass;
    private ?\Elementor\Widget_Base $prototype = null;

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
            $toggles[$tab]['toggles'][$section] = ucwords(str_replace('_', ' ', $section));
        }
        return $toggles;
    }

    public function render($attrs, $content = null, $function_name = ''): string
    {
        $widget = new $this->widgetClass();
        $settings = $this->normalizeSettings((array) $this->props, $widget->get_controls());
        $instance = substr(md5($this->slug . wp_json_encode($settings) . wp_rand()), 0, 10);
        $css = $this->buildCss($widget->get_controls(), $settings, '.homlity-divi-' . $instance);

        if (method_exists($widget, 'homlitySetSettings')) {
            $widget->homlitySetSettings($settings);
            $markup = $widget->homlityRender();
        } elseif (class_exists('\\Elementor\\Plugin')) {
            $data = ['id' => $instance, 'elType' => 'widget', 'widgetType' => $widget->get_name(), 'settings' => $settings];
            $element = \Elementor\Plugin::instance()->elements_manager->create_element_instance($data);
            ob_start();
            if ($element) {
                $element->print_element();
            }
            $markup = (string) ob_get_clean();
        } else {
            $markup = '';
        }

        return ($css !== '' ? '<style>' . wp_strip_all_tags($css) . '</style>' : '')
            . '<div class="homlity-divi-widget homlity-divi-' . esc_attr($instance) . '">'
            . $markup
            . '</div>';
    }

    private function widget(): \Elementor\Widget_Base
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
            $field['options'] = array_map('esc_html', (array) ($control['options'] ?? []));
        } elseif ($type === Controls_Manager::TEXTAREA) {
            $field['type'] = 'textarea';
        } elseif ($type === Controls_Manager::COLOR) {
            $field['type'] = 'color-alpha';
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
        $definitions = str_contains($group, 'typography') ? [
            'font_family' => ['Fuente', 'text'], 'font_size' => ['Tamaño', 'text'],
            'font_weight' => ['Peso', 'text'], 'text_transform' => ['Transformación', 'text'],
            'font_style' => ['Estilo', 'text'], 'text_decoration' => ['Decoración', 'text'],
            'line_height' => ['Altura de línea', 'text'], 'letter_spacing' => ['Espaciado', 'text'],
        ] : (str_contains($group, 'border') ? [
            'border_type' => ['Tipo de borde', 'text'], 'border_width' => ['Ancho de borde', 'text'],
            'border_color' => ['Color de borde', 'color-alpha'], 'border_radius' => ['Radio', 'text'],
        ] : (str_contains($group, 'background') ? [
            'background_color' => ['Color de fondo', 'color-alpha'],
        ] : (str_contains($group, 'shadow') ? [
            'shadow' => ['Sombra CSS', 'text'],
        ] : [])));

        $fields = [];
        $tab = (($control['tab'] ?? '') === Controls_Manager::TAB_STYLE) ? 'advanced' : 'general';
        $toggle = sanitize_key((string) ($control['section'] ?? 'main_content')) ?: 'main_content';
        foreach ($definitions as $suffix => [$label, $type]) {
            $fields[$name . '_' . $suffix] = [
                'label' => esc_html__($label, 'homlity-real-estate'),
                'type' => $type,
                'default' => '',
                'tab_slug' => $tab,
                'toggle_slug' => $toggle,
            ];
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
            if (is_string($value) && in_array(substr(trim($value), 0, 1), ['{', '['], true)) {
                $decoded = json_decode($value, true);
                $settings[$name] = is_array($decoded) ? $decoded : $value;
                continue;
            }
            if ($type === Controls_Manager::MEDIA && is_string($value)) {
                $settings[$name] = ['id' => attachment_url_to_postid($value), 'url' => esc_url_raw($value)];
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
        foreach ($controls as $name => $control) {
            if (($control['type'] ?? '') === 'homlity_group') {
                $this->appendGroupCss($rules, (string) $name, (array) $control, $settings, $wrapper);
                continue;
            }
            $value = $settings[$name] ?? ($control['default'] ?? null);
            if ($value === null || $value === '') {
                continue;
            }
            foreach ((array) ($control['selectors'] ?? []) as $selector => $declaration) {
                $selector = str_replace('{{WRAPPER}}', $wrapper, (string) $selector);
                $declaration = $this->replaceTokens((string) $declaration, $value);
                if ($selector !== '' && $declaration !== '' && !str_contains($declaration, '{{')) {
                    $rules[$selector][] = $declaration;
                }
            }
        }
        $css = '';
        foreach ($rules as $selector => $declarations) {
            $css .= $selector . '{' . implode('', $declarations) . '}';
        }
        return $css;
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
                $rules[$selector][] = $property . ':' . $value . ';';
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

    private function encodeDefault(mixed $value): string
    {
        if (is_array($value)) {
            return (string) wp_json_encode($value);
        }
        return (string) $value;
    }
}
