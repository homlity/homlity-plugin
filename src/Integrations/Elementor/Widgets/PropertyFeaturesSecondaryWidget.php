<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyFeaturesSecondaryWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_features_secondary';
    }

    public function get_title(): string
    {
        return __('Características secundarias', 'homlity-plugin');
    }

    public function get_icon(): string
    {
        return 'eicon-info-circle-o';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-plugin')]);
        $this->register_property_control();
        $this->end_controls_section();
    }

    protected function render(): void
    {
        TemplateService::includeComponent('property-features-secondary.php', [
            'post_id' => $this->current_property_id(),
        ]);
    }
}
