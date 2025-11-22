<?php

namespace Codwelt\PluginInmobiliario\Integrations\Elementor\Widgets;

use Codwelt\PluginInmobiliario\Services\TemplateService;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyGalleryWidget extends BasePropertyWidget
{
    public function get_name(): string
    {
        return 'property_gallery';
    }

    public function get_title(): string
    {
        return __('Galería de inmueble', 'plugin-inmobiliario');
    }

    public function get_icon(): string
    {
        return 'eicon-gallery-grid';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Contenido', 'plugin-inmobiliario')]);
        $this->register_property_control();
        $this->end_controls_section();
    }

    protected function render(): void
    {
        TemplateService::includeComponent('property-gallery.php', [
            'post_id' => $this->current_property_id(),
        ]);
    }
}
