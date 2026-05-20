<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

class PropertySummaryWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_summary';
    }

    public function get_title(): string
    {
        return __('Resumen del inmueble', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-info-circle-o';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();
        $this->end_controls_section();
    }

    protected function render(): void
    {
        TemplateService::includeComponent('property-summary.php', [
            'post_id' => $this->current_property_id(),
        ]);
    }
}
