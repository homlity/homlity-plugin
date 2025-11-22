<?php

namespace Codwelt\PluginInmobiliario\Integrations\Elementor\Widgets;

use Codwelt\PluginInmobiliario\Services\TemplateService;

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
        return __('Propiedades relacionadas', 'inmopress-listings-inmobiliaria');
    }

    public function get_icon(): string
    {
        return 'eicon-posts-carousel';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'inmopress-listings-inmobiliaria')]);
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
