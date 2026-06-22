<?php
namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyFeaturedCitiesWidget extends PropertyFeaturedTermsBaseWidget
{
    public function get_name(): string
    {
        return 'property_featured_cities';
    }

    public function get_title(): string
    {
        return __('Ciudades destacadas', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-map-pin';
    }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }

    protected function get_featured_terms_config(): array
    {
        return [
            'title' => __('Destacados por ciudad', 'homlity-real-estate'),
            'limit' => 8,
            'taxonomy' => PropertyTaxonomies::TAXONOMY_CITY,
            'segment' => 'ciudad',
            'item_text' => __('Encuentra inmuebles en {{term}}', 'homlity-real-estate'),
            'icon' => ['value' => 'fas fa-location-dot', 'library' => 'fa-solid'],
        ];
    }
}
