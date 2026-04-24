<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyRelatedWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_related';
    }

    public function get_title(): string
    {
        return __('Propiedades relacionadas', 'homlity-plugin');
    }

    public function get_icon(): string
    {
        return 'eicon-posts-carousel';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-plugin')]);
        $this->register_property_control();
        $this->end_controls_section();
    }

    protected function render(): void
    {
        TemplateService::includeComponent('property-related.php', [
            'post_id' => $this->current_property_id(),
        ]);
    }
}
