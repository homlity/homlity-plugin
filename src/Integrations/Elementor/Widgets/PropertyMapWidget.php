<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyMapWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_map';
    }

    public function get_title(): string
    {
        return __('Mapa y Street View', 'homlity-plugin');
    }

    public function get_icon(): string
    {
        return 'eicon-google-maps';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-plugin')]);
        $this->register_property_control();
        $this->end_controls_section();
    }

    protected function render(): void
    {
        TemplateService::includeComponent('property-map.php', [
            'post_id' => $this->current_property_id(),
        ]);
    }
}
