<?php

namespace Homlity\PluginInmobiliario\Integrations\Divi\Widgets;

use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Controls_Manager;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Widget_Base;
use Homlity\PluginInmobiliario\Listing\FilterPlaceholders;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyFilterWidget extends Widget_Base
{
    use \Homlity\PluginInmobiliario\Integrations\Shared\PropertyFilterStylesTrait;

    public function get_name(): string
    {
        return 'property_filter';
    }

    public function get_title(): string
    {
        return __('Filtro de inmuebles', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-filter';
    }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Campos', 'homlity-real-estate')]);

        $this->add_control('target_page_id', [
            'label' => __('Página de resultados', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT2,
            'options' => $this->getPagesOptions(),
            'default' => (string) get_option('homlity_plugin_archive_page_id', 0),
        ]);

        foreach ([
            'show_keyword' => __('Palabra clave', 'homlity-real-estate'),
            'show_category' => __('Categoría', 'homlity-real-estate'),
            'show_operation' => __('Gestión', 'homlity-real-estate'),
            'show_type' => __('Tipo de inmueble', 'homlity-real-estate'),
            'show_tag' => __('Etiqueta', 'homlity-real-estate'),
            'show_country' => __('País', 'homlity-real-estate'),
            'show_state' => __('Departamento / Provincia', 'homlity-real-estate'),
            'show_city' => __('Ciudad', 'homlity-real-estate'),
            'show_locality' => __('Localidad', 'homlity-real-estate'),
            'show_neighborhood' => __('Barrio', 'homlity-real-estate'),
            'show_nearby' => __('Lugar cercano', 'homlity-real-estate'),
            'show_price' => __('Rango de precio', 'homlity-real-estate'),
            'show_area' => __('Rango de área', 'homlity-real-estate'),
            'show_bedrooms' => __('Habitaciones', 'homlity-real-estate'),
            'show_bathrooms' => __('Baños', 'homlity-real-estate'),
            'show_parking' => __('Garajes', 'homlity-real-estate'),
        ] as $key => $label) {
            $this->add_control($key, [
                'label' => $label,
                'type' => Controls_Manager::SWITCHER,
                'default' => in_array($key, ['show_keyword', 'show_operation', 'show_type', 'show_city', 'show_price'], true) ? 'yes' : '',
            ]);
        }


        $this->add_control('multiple_operation', [
            'label' => __('Gestión múltiple', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'condition' => ['show_operation' => 'yes'],
        ]);

        $this->add_control('multiple_type', [
            'label' => __('Tipo de inmueble múltiple', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'condition' => ['show_type' => 'yes'],
        ]);

        $this->add_control('multiple_tag', [
            'label' => __('Etiquetas múltiples', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => ['show_tag' => 'yes'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('placeholders', ['label' => __('Placeholders', 'homlity-real-estate')]);

        foreach (FilterPlaceholders::definitions() as $key => $field) {
            $control = [
                'label' => $field['label'],
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => $field['default'],
            ];
            if ($field['toggle'] !== null) {
                $control['condition'] = [$field['toggle'] => 'yes'];
            }
            $this->add_control($key, $control);
        }

        $this->end_controls_section();

        $this->start_controls_section('actions', ['label' => __('Acciones', 'homlity-real-estate')]);

        $this->add_control('submit_label', [
            'label' => __('Texto del botón buscar', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Buscar', 'homlity-real-estate'),
        ]);

        $this->add_control('reset_label', [
            'label' => __('Texto del botón limpiar', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Limpiar', 'homlity-real-estate'),
        ]);

        $this->add_control('show_reset', [
            'label' => __('Mostrar botón limpiar', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('mobile_sidebar_enabled', [
            'label' => __('Modo móvil: botón + sidebar', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('mobile_filter_button_label', [
            'label' => __('Texto botón móvil', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Filtrar inmuebles', 'homlity-real-estate'),
            'condition' => ['mobile_sidebar_enabled' => 'yes'],
        ]);

        $this->add_control('field_label_mode', [
            'label' => __('Mostrar etiqueta', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'placeholder',
            'options' => [
                'outside' => __('Afuera del campo (anterior)', 'homlity-real-estate'),
                'label' => __('Encima del campo', 'homlity-real-estate'),
                'placeholder' => __('Dentro del campo (placeholder)', 'homlity-real-estate'),
            ],
        ]);

        $this->add_control('select_first_option_mode', [
            'label' => __('Texto primer option en selects', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'auto',
            'options' => [
                'auto' => __('Automático (según modo de etiqueta)', 'homlity-real-estate'),
                'generic' => __('Genérico (Todos/Cualquiera)', 'homlity-real-estate'),
                'label' => __('Usar nombre del campo (Gestión, Tipo, etc.)', 'homlity-real-estate'),
            ],
        ]);

        $this->end_controls_section();

        $this->registerFilterStyleControls();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        TemplateService::includeComponent('property-filter.php', [
            'settings' => $settings,
        ]);
    }

    private function getPagesOptions(): array
    {
        $pages = get_pages(['sort_column' => 'post_title', 'sort_order' => 'ASC']);
        $options = ['0' => __('Página automática de resultados', 'homlity-real-estate')];

        foreach ($pages as $page) {
            $options[(string) $page->ID] = $page->post_title;
        }

        return $options;
    }
}
