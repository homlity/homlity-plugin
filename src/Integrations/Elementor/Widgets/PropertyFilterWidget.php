<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyFilterWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'property_filter';
    }

    public function get_title(): string
    {
        return __('Filtro de inmuebles', 'homlity-plugin');
    }

    public function get_icon(): string
    {
        return 'eicon-filter';
    }

    public function get_categories(): array
    {
        return ['homlity-plugin'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Campos', 'homlity-plugin')]);

        $this->add_control('target_page_id', [
            'label' => __('Página de resultados', 'homlity-plugin'),
            'type' => Controls_Manager::SELECT2,
            'options' => $this->getPagesOptions(),
            'default' => (string) get_option('homlity_plugin_archive_page_id', 0),
        ]);

        foreach ([
            'show_keyword' => __('Palabra clave', 'homlity-plugin'),
            'show_category' => __('Categoría', 'homlity-plugin'),
            'show_operation' => __('Gestión', 'homlity-plugin'),
            'show_type' => __('Tipo de inmueble', 'homlity-plugin'),
            'show_tag' => __('Etiqueta', 'homlity-plugin'),
            'show_country' => __('País', 'homlity-plugin'),
            'show_state' => __('Departamento / Provincia', 'homlity-plugin'),
            'show_city' => __('Ciudad', 'homlity-plugin'),
            'show_neighborhood' => __('Barrio', 'homlity-plugin'),
            'show_nearby' => __('Lugar cercano', 'homlity-plugin'),
            'show_price' => __('Rango de precio', 'homlity-plugin'),
            'show_bedrooms' => __('Habitaciones', 'homlity-plugin'),
            'show_bathrooms' => __('Baños', 'homlity-plugin'),
        ] as $key => $label) {
            $this->add_control($key, [
                'label' => $label,
                'type' => Controls_Manager::SWITCHER,
                'default' => in_array($key, ['show_keyword', 'show_operation', 'show_type', 'show_city', 'show_price'], true) ? 'yes' : '',
            ]);
        }

        $this->end_controls_section();

        $this->start_controls_section('actions', ['label' => __('Acciones', 'homlity-plugin')]);

        $this->add_control('submit_label', [
            'label' => __('Texto del botón buscar', 'homlity-plugin'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Buscar', 'homlity-plugin'),
        ]);

        $this->add_control('reset_label', [
            'label' => __('Texto del botón limpiar', 'homlity-plugin'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Limpiar', 'homlity-plugin'),
        ]);

        $this->add_control('show_reset', [
            'label' => __('Mostrar botón limpiar', 'homlity-plugin'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->end_controls_section();
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
        $options = ['0' => __('Página automática de resultados', 'homlity-plugin')];

        foreach ($pages as $page) {
            $options[(string) $page->ID] = $page->post_title;
        }

        return $options;
    }
}
