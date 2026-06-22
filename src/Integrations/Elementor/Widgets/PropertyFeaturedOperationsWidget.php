<?php
namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyFeaturedOperationsWidget extends PropertyFeaturedTermsBaseWidget
{
    public function get_name(): string
    {
        return 'property_featured_operations';
    }

    public function get_title(): string
    {
        return __('Gestión destacada', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-price-list';
    }

    public function get_categories(): array
    {
        return ['homlity-real-estate'];
    }

    protected function get_featured_terms_config(): array
    {
        return [
            'title' => __('Destacados por gestión', 'homlity-real-estate'),
            'limit' => 6,
            'taxonomy' => PropertyTaxonomies::TAXONOMY_OPERATION,
            'segment' => 'gestion',
            'item_text' => __('Encuentra inmuebles en {{term}}', 'homlity-real-estate'),
            'icon' => ['value' => 'fas fa-tags', 'library' => 'fa-solid'],
        ];
    }
}
