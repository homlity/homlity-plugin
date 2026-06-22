<?php
namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyFeaturedNeighborhoodsWidget extends PropertyFeaturedTermsBaseWidget
{
    public function get_name(): string
    {
        return 'property_featured_neighborhoods';
    }

    public function get_title(): string
    {
        return __('Barrios destacados', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-location';
    }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }

    protected function get_featured_terms_config(): array
    {
        return [
            'title' => __('Destacados por barrio', 'homlity-real-estate'),
            'limit' => 8,
            'taxonomy' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            'segment' => 'barrios',
            'item_text' => __('Encuentra inmuebles en {{term}}', 'homlity-real-estate'),
            'icon' => ['value' => 'fas fa-map-marker-alt', 'library' => 'fa-solid'],
        ];
    }
}
