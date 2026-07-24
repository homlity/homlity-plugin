<?php

namespace Homlity\PluginInmobiliario\Integrations\Divi\Widgets;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyCardWidget extends BasePropertyWidget
{
    use PropertyCardStylesTrait;

    public function get_name(): string
    {
        return 'property_card';
    }

    public function get_title(): string
    {
        return __('Tarjeta de inmueble', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-post';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'homlity-real-estate')]);
        $this->register_property_control();
        $this->end_controls_section();

        $this->registerCardContentControls();
        $this->registerCardStyleControls();
    }

    protected function render(): void
    {
        $config = ListingConfig::fromBuilderSettings($this->get_settings_for_display());
        TemplateService::includeComponent('property-card.php', [
            'post_id' => $this->current_property_id(),
            'card_options' => $config->cardOptions(),
        ]);
    }
}
