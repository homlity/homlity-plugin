<?php
/**
 * WPBakery integration.
 *
 * Besides the standalone listing shortcode, this service exposes every Homlity
 * Elementor widget in WPBakery. The WPBakery fields are built from the Elementor
 * control definitions, so both builders keep the same defaults and options.
 */

namespace Homlity\PluginInmobiliario\Integrations\WPBakery;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyAgentWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyAgentsAvailableWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyBreadcrumbWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyCardWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyContentWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyDynamicCodeButtonWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturedCitiesWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturedNeighborhoodsWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturedOperationsWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturedTermsWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturedTypesWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturesPrimaryWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFeaturesSecondaryWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyFilterWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyGalleryWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyListingWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyMapWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyMediaTabsWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyOperationPriceWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyRelatedWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyResultsTitleWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyShareWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertySummaryWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyTechnicalSheetButtonWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyTitleWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\PropertyVideoWidget;
use Homlity\PluginInmobiliario\Integrations\Elementor\Widgets\SimulatorWidget;
use Homlity\PluginInmobiliario\Services\DataSeederService;

if (!defined('ABSPATH')) {
    exit;
}

class WPBakeryIntegrationService implements ServiceInterface
{
    private const CATEGORY = 'Homlity Plugin';

    private bool $mapped = false;
    private bool $listingMapped = false;

    public function register(): void
    {
        if (!defined('WPB_VC_VERSION')) {
            return;
        }

        add_action('vc_before_init', [$this, 'mapElements']);
        add_action('elementor/widgets/register', [$this, 'mapElements'], 100);
        add_action('elementor/widgets/widgets_registered', [$this, 'mapElements'], 100);
    }

    public function mapElements(): void
    {
        if ($this->mapped || !function_exists('vc_map')) {
            return;
        }

        if (!$this->listingMapped) {
            $this->mapListing();
            $this->listingMapped = true;
        }

        (new DataSeederService())->seedBuilderTemplates();

        // Elementor supplies the canonical control schema and renderer. Keeping
        // this conditional lets the standalone listing continue working when
        // Elementor is not installed.
        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }

        foreach ($this->widgetClasses() as $widgetClass) {
            $this->mapWidget($widgetClass);
        }

        $this->mapped = true;
    }

    private function mapListing(): void
    {
        vc_map([
            'name'        => __('Listado de inmuebles', 'homlity-real-estate'),
            'base'        => 'homlity_listing',
            'category'    => __(self::CATEGORY, 'homlity-real-estate'),
            'icon'        => HOMLITY_PLUGIN_URL . 'icono.png',
            'description' => __('Grilla/mapa de propiedades con filtros y orden.', 'homlity-real-estate'),
            'params'      => $this->listingParams(),
        ]);
    }

    /**
     * @param class-string<\Elementor\Widget_Base> $widgetClass
     */
    private function mapWidget(string $widgetClass): void
    {
        if (!class_exists($widgetClass)) {
            return;
        }

        /** @var \Elementor\Widget_Base $widget */
        $widget = new $widgetClass();
        $base = 'homlity_wpb_' . sanitize_key($widget->get_name());

        add_shortcode($base, function ($attributes = []) use ($widgetClass): string {
            return $this->renderWidget($widgetClass, (array) $attributes);
        });

        vc_map([
            'name'        => $widget->get_title(),
            'base'        => $base,
            'category'    => __(self::CATEGORY, 'homlity-real-estate'),
            'icon'        => HOMLITY_PLUGIN_URL . 'icono.png',
            'description' => sprintf(
                __('Widget Homlity equivalente a Elementor: %s.', 'homlity-real-estate'),
                $widget->get_title()
            ),
            'params'      => $this->controlsToParams($widget),
        ]);
    }

    /**
     * Render through Elementor's element pipeline. This preserves the widget
     * markup, render attributes, responsive settings and generated CSS classes.
     *
     * @param class-string<\Elementor\Widget_Base> $widgetClass
     */
    private function renderWidget(string $widgetClass, array $attributes): string
    {
        if (!class_exists('\Elementor\Plugin') || !class_exists($widgetClass)) {
            return '';
        }

        $prototype = new $widgetClass();
        $settings = $this->normalizeSettings($attributes, $prototype->get_controls());
        $elementId = substr(md5($widgetClass . wp_json_encode($settings) . wp_rand()), 0, 8);
        $data = [
            'id'         => $elementId,
            'elType'     => 'widget',
            'widgetType' => $prototype->get_name(),
            'settings'   => $settings,
        ];

        $element = \Elementor\Plugin::instance()->elements_manager->create_element_instance($data);
        if (!$element) {
            return '';
        }

        ob_start();
        $css = $this->buildElementCss($prototype->get_controls(), $settings, $elementId);
        if ($css !== '') {
            echo '<style>' . wp_strip_all_tags($css) . '</style>';
        }
        $element->print_element();
        return (string) ob_get_clean();
    }

    private function normalizeSettings(array $attributes, array $controls): array
    {
        $settings = [];

        foreach ($attributes as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_string($value) && in_array($value, ['true', 'false'], true)) {
                $settings[$key] = $value === 'true' ? 'yes' : '';
                continue;
            }

            $type = (string) ($controls[$key]['type'] ?? '');

            if (in_array($type, ['media', 'image'], true) && is_numeric($value)) {
                $attachmentId = (int) $value;
                $settings[$key] = [
                    'id' => $attachmentId,
                    'url' => (string) wp_get_attachment_url($attachmentId),
                ];
                continue;
            }

            if ($type === 'gallery' && is_string($value)) {
                $settings[$key] = array_map(static function ($id): array {
                    $attachmentId = (int) $id;
                    return ['id' => $attachmentId, 'url' => (string) wp_get_attachment_url($attachmentId)];
                }, array_filter(array_map('trim', explode(',', $value))));
                continue;
            }

            if ($type === 'url' && is_string($value) && function_exists('vc_build_link')) {
                $link = vc_build_link($value);
                $settings[$key] = [
                    'url' => (string) ($link['url'] ?? ''),
                    'is_external' => (($link['target'] ?? '') === '_blank') ? 'on' : '',
                    'nofollow' => str_contains((string) ($link['rel'] ?? ''), 'nofollow') ? 'on' : '',
                ];
                continue;
            }

            if (is_string($value) && in_array(substr(trim($value), 0, 1), ['{', '['], true)) {
                $decoded = json_decode($value, true);
                $settings[$key] = is_array($decoded) ? $decoded : $value;
                continue;
            }

            $settings[$key] = $value;
        }

        return $settings;
    }

    private function buildElementCss(array $controls, array $settings, string $elementId): string
    {
        $rules = [];
        $wrapper = '.elementor-element-' . sanitize_html_class($elementId);

        foreach ($controls as $name => $control) {
            $selectors = (array) ($control['selectors'] ?? []);
            if ($selectors === []) {
                continue;
            }

            $value = $settings[$name] ?? ($control['default'] ?? null);
            if ($value === null || $value === '') {
                continue;
            }

            foreach ($selectors as $selector => $declaration) {
                $selector = str_replace('{{WRAPPER}}', $wrapper, (string) $selector);
                $declaration = $this->replaceCssTokens((string) $declaration, $value, $settings);
                if ($declaration !== '' && !str_contains($declaration, '{{')) {
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

    /**
     * Resolve the token formats used by Elementor controls and group controls.
     *
     * @param mixed $value
     */
    private function replaceCssTokens(string $css, $value, array $settings): string
    {
        $scalar = is_array($value) ? (string) ($value['size'] ?? $value['value'] ?? '') : (string) $value;
        $unit = is_array($value) ? (string) ($value['unit'] ?? '') : '';

        $css = str_replace(
            ['{{VALUE}}', '{{SIZE}}', '{{UNIT}}'],
            [$scalar, $scalar, $unit],
            $css
        );

        return (string) preg_replace_callback(
            '/\{\{([A-Za-z0-9_]+)\.(VALUE|SIZE|UNIT)\}\}/',
            static function (array $matches) use ($settings): string {
                $setting = $settings[$matches[1]] ?? '';
                if (!is_array($setting)) {
                    return $matches[2] === 'UNIT' ? '' : (string) $setting;
                }

                $key = $matches[2] === 'UNIT' ? 'unit' : ($matches[2] === 'SIZE' ? 'size' : 'value');
                return (string) ($setting[$key] ?? ($key === 'value' ? ($setting['size'] ?? '') : ''));
            },
            $css
        );
    }

    private function controlsToParams(\Elementor\Widget_Base $widget): array
    {
        $params = [];
        $group = __('Contenido', 'homlity-real-estate');

        foreach ($widget->get_controls() as $name => $control) {
            $type = (string) ($control['type'] ?? '');

            if ($type === 'section') {
                $group = (string) ($control['label'] ?? $group);
                continue;
            }

            $param = $this->controlToParam((string) $name, (array) $control, $group);
            if ($param !== null) {
                $params[] = $param;
            }
        }

        if ($params === []) {
            $params[] = [
                'type' => 'textfield',
                'heading' => __('Sin opciones adicionales', 'homlity-real-estate'),
                'param_name' => '_homlity_placeholder',
                'group' => $group,
                'edit_field_class' => 'vc_col-sm-12 vc_hidden',
            ];
        }

        return $params;
    }

    private function controlToParam(string $name, array $control, string $group): ?array
    {
        $type = (string) ($control['type'] ?? '');
        $ignored = ['hidden', 'raw_html', 'divider', 'popover_toggle', 'tab'];
        if (in_array($type, $ignored, true) || $name === '') {
            return null;
        }

        $param = [
            'type'        => 'textfield',
            'heading'     => (string) ($control['label'] ?? ucwords(str_replace('_', ' ', $name))),
            'param_name'  => $name,
            'description' => wp_strip_all_tags((string) ($control['description'] ?? '')),
            'group'       => $group,
        ];

        $default = $control['default'] ?? '';
        if (is_array($default)) {
            $param['value'] = wp_json_encode($default);
        } elseif ($default !== '') {
            $param['value'] = (string) $default;
            $param['std'] = (string) $default;
        }

        if (in_array($type, ['select', 'select2', 'choose'], true)) {
            $param['type'] = 'dropdown';
            $param['value'] = $this->flipOptions((array) ($control['options'] ?? []));
        } elseif (in_array($type, ['switcher', 'checkbox'], true)) {
            $param['type'] = 'checkbox';
            $onValue = (string) ($control['return_value'] ?? 'yes');
            $param['value'] = [
                (string) ($control['label_on'] ?? __('Sí', 'homlity-real-estate')) => $onValue,
            ];
            $param['std'] = (string) $default;
        } elseif ($type === 'color') {
            $param['type'] = 'colorpicker';
        } elseif (in_array($type, ['textarea', 'wysiwyg', 'code'], true)) {
            $param['type'] = 'textarea';
        } elseif (in_array($type, ['media', 'image'], true)) {
            $param['type'] = 'attach_image';
            $param['value'] = is_array($default) ? (string) ($default['id'] ?? '') : '';
        } elseif ($type === 'gallery') {
            $param['type'] = 'attach_images';
        } elseif ($type === 'url') {
            $param['type'] = 'vc_link';
            $param['value'] = '';
        } elseif ($type === 'icon') {
            $param['type'] = 'iconpicker';
        } elseif (in_array($type, ['number', 'slider'], true)) {
            $param['type'] = 'textfield';
        }

        $dependency = $this->dependency((array) ($control['condition'] ?? []));
        if ($dependency !== null) {
            $param['dependency'] = $dependency;
        }

        return $param;
    }

    private function flipOptions(array $options): array
    {
        $values = [];
        foreach ($options as $value => $label) {
            $values[wp_strip_all_tags((string) $label)] = (string) $value;
        }
        return $values;
    }

    private function dependency(array $condition): ?array
    {
        if (count($condition) !== 1) {
            return null;
        }

        $field = (string) array_key_first($condition);
        $value = $condition[$field];
        $not = str_ends_with($field, '!');
        $field = rtrim($field, '!');

        if (is_array($value)) {
            return ['element' => $field, 'value' => array_map('strval', $value)];
        }

        return [
            'element' => $field,
            $not ? 'value_not_equal_to' : 'value' => (string) $value,
        ];
    }

    /**
     * @return array<class-string<\Elementor\Widget_Base>>
     */
    private function widgetClasses(): array
    {
        return [
            PropertyFilterWidget::class,
            PropertyListingWidget::class,
            PropertyResultsTitleWidget::class,
            PropertyTitleWidget::class,
            PropertyOperationPriceWidget::class,
            PropertyContentWidget::class,
            PropertySummaryWidget::class,
            PropertyGalleryWidget::class,
            PropertyBreadcrumbWidget::class,
            PropertyMediaTabsWidget::class,
            PropertyVideoWidget::class,
            PropertyTechnicalSheetButtonWidget::class,
            PropertyDynamicCodeButtonWidget::class,
            PropertyFeaturedCitiesWidget::class,
            PropertyFeaturedNeighborhoodsWidget::class,
            PropertyFeaturedOperationsWidget::class,
            PropertyFeaturedTypesWidget::class,
            PropertyFeaturedTermsWidget::class,
            PropertyAgentsAvailableWidget::class,
            PropertyFeaturesPrimaryWidget::class,
            PropertyFeaturesSecondaryWidget::class,
            PropertyMapWidget::class,
            PropertyAgentWidget::class,
            PropertyShareWidget::class,
            PropertyRelatedWidget::class,
            PropertyCardWidget::class,
            SimulatorWidget::class,
        ];
    }

    private function listingParams(): array
    {
        return [
            ['type' => 'dropdown', 'heading' => __('Diseño de plantilla', 'homlity-real-estate'), 'param_name' => 'template', 'value' => [__('Predeterminado (CSS propio)', 'homlity-real-estate') => 'default', __('Bootstrap 5', 'homlity-real-estate') => 'bootstrap'], 'std' => 'default', 'group' => __('Presentación', 'homlity-real-estate')],
            ['type' => 'dropdown', 'heading' => __('Vista por defecto', 'homlity-real-estate'), 'param_name' => 'view', 'value' => [__('Grilla / Cards', 'homlity-real-estate') => 'grid', __('Mapa', 'homlity-real-estate') => 'map'], 'std' => 'grid', 'group' => __('Presentación', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Botón para cambiar de vista', 'homlity-real-estate'), 'param_name' => 'view_toggle', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Presentación', 'homlity-real-estate')],
            ['type' => 'dropdown', 'heading' => __('Columnas en grilla', 'homlity-real-estate'), 'param_name' => 'columns', 'value' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'], 'std' => '3', 'group' => __('Presentación', 'homlity-real-estate')],
            ['type' => 'textfield', 'heading' => __('Inmuebles por página', 'homlity-real-estate'), 'param_name' => 'per_page', 'value' => '12', 'description' => __('Número entre 1 y 100.', 'homlity-real-estate'), 'group' => __('Consulta', 'homlity-real-estate')],
            ['type' => 'dropdown', 'heading' => __('Orden por defecto', 'homlity-real-estate'), 'param_name' => 'orderby', 'value' => [__('Más recientes', 'homlity-real-estate') => 'date', __('Precio: menor a mayor', 'homlity-real-estate') => 'price_asc', __('Precio: mayor a menor', 'homlity-real-estate') => 'price_desc', __('Nombre A–Z', 'homlity-real-estate') => 'title'], 'std' => 'date', 'group' => __('Consulta', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Solo destacados', 'homlity-real-estate'), 'param_name' => 'featured', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'group' => __('Consulta', 'homlity-real-estate')],
            ['type' => 'textfield', 'heading' => __('ID de término: Gestión fija', 'homlity-real-estate'), 'param_name' => 'operation', 'value' => '0', 'group' => __('Consulta', 'homlity-real-estate')],
            ['type' => 'textfield', 'heading' => __('ID de término: Tipo fijo', 'homlity-real-estate'), 'param_name' => 'type', 'value' => '0', 'group' => __('Consulta', 'homlity-real-estate')],
            ['type' => 'textfield', 'heading' => __('ID de localidad fija', 'homlity-real-estate'), 'param_name' => 'locality', 'value' => '0', 'group' => __('Consulta', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Mostrar panel de filtros', 'homlity-real-estate'), 'param_name' => 'filters', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Filtro: Gestión', 'homlity-real-estate'), 'param_name' => 'filter_operation', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Filtro: Tipo de inmueble', 'homlity-real-estate'), 'param_name' => 'filter_type', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Filtro: Ciudad', 'homlity-real-estate'), 'param_name' => 'filter_city', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Filtro: Rango de precio', 'homlity-real-estate'), 'param_name' => 'filter_price', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Filtro: Habitaciones', 'homlity-real-estate'), 'param_name' => 'filter_bedrooms', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'checkbox', 'heading' => __('Mostrar selector de orden', 'homlity-real-estate'), 'param_name' => 'sort', 'value' => [__('Sí', 'homlity-real-estate') => 'true'], 'std' => 'true', 'group' => __('Filtros', 'homlity-real-estate')],
            ['type' => 'textfield', 'heading' => __('Altura del mapa (px)', 'homlity-real-estate'), 'param_name' => 'map_height', 'value' => '500', 'group' => __('Mapa', 'homlity-real-estate')],
            ['type' => 'textfield', 'heading' => __('Zoom inicial del mapa', 'homlity-real-estate'), 'param_name' => 'map_zoom', 'value' => '12', 'group' => __('Mapa', 'homlity-real-estate')],
        ];
    }
}
