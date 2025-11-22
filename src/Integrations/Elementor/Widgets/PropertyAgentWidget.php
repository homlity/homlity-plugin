<?php

namespace Codwelt\PluginInmobiliario\Integrations\Elementor\Widgets;

use Codwelt\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyAgentWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_agent';
    }

    public function get_title(): string
    {
        return __('Asesor del inmueble', 'plugin-inmobiliario');
    }

    public function get_icon(): string
    {
        return 'eicon-user-circle-o';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'plugin-inmobiliario')]);
        $this->register_property_control();
        $this->end_controls_section();
    }

    protected function render(): void
    {
        TemplateService::includeComponent('property-agent-info.php', [
            'post_id' => $this->current_property_id(),
        ]);
    }
}
