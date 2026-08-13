<?php
namespace Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets;

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyFeaturedTypesWidget extends PropertyFeaturedTermsBaseWidget
{
    public function get_name(): string
    {
        return 'property_featured_types';
    }

    public function get_title(): string
    {
        return __('Tipos destacados', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-home';
    }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }

    protected function get_featured_terms_config(): array
    {
        return [
            'title' => __('Destacados por tipo', 'homlity-real-estate'),
            'limit' => 8,
            'taxonomy' => PropertyTaxonomies::TAXONOMY_TYPE,
            'segment' => 'tipo',
            'item_text' => __('Encuentra {{term}} en arriendo y venta', 'homlity-real-estate'),
            'icon' => ['value' => 'fas fa-house', 'library' => 'fa-solid'],
        ];
    }
}
