<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\FieldMap;

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyFieldSchema
{
    /**
     * @return array<string,mixed>
     */
    public static function schema(): array
    {
        return [
            'external' => [
                'source' => 'string|required',
                'id' => 'string|required',
                'updated_at' => 'string|optional',
                'raw' => 'array|optional',
            ],
            'post' => [
                'title' => 'string|required',
                'description' => 'string|optional',
                'short_description' => 'string|optional',
                'status' => 'string|optional:publish|draft|pending|private',
            ],
            'location' => [
                'address' => 'string|required',
                'latitude' => 'float|required',
                'longitude' => 'float|required',
            ],
            'pricing' => [
                'sale_price' => 'string|optional',
                'sale_currency' => 'string|optional',
                'rent_price' => 'string|optional',
                'rent_currency' => 'string|optional',
                'admin_price' => 'string|optional',
                'admin_currency' => 'string|optional',
                'admin_included' => 'bool|optional',
            ],
            'metrics' => [
                'area' => 'string|optional',
                'area_lot' => 'string|optional',
                'area_private' => 'string|optional',
                'area_built' => 'string|optional',
                'bedrooms' => 'int|optional',
                'bathrooms' => 'int|optional',
                'parking' => 'int|optional',
                'condition' => 'string|optional',
                'year_built' => 'int|optional',
                'code' => 'string|optional',
                'featured' => 'bool|optional',
            ],
            'taxonomy' => [
                PropertyTaxonomies::TAXONOMY_OPERATION => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_TYPE => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_CATEGORY => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_TAG => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_FEATURE => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_COUNTRY => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_STATE => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_CITY => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_NEARBY => 'string[] slugs|optional',
            ],
            'media' => [
                'gallery' => 'string[] urls|optional',
                'featured_image' => 'string url|optional',
            ],
            'advisor' => [
                'email' => 'string|optional',
                'phone' => 'string|optional',
                'user_id' => 'int|optional',
            ],
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function metaMap(): array
    {
        return [
            'location.address' => '_property_address',
            'location.latitude' => '_property_latitude',
            'location.longitude' => '_property_longitude',
            'pricing.sale_price' => '_property_price_sale',
            'pricing.sale_currency' => '_property_currency_sale',
            'pricing.rent_price' => '_property_price_rent',
            'pricing.rent_currency' => '_property_currency_rent',
            'pricing.admin_price' => '_property_price_admin',
            'pricing.admin_currency' => '_property_currency_admin',
            'pricing.admin_included' => '_property_admin_included',
            'metrics.area' => '_property_area',
            'metrics.area_lot' => '_property_area_lot',
            'metrics.area_private' => '_property_area_private',
            'metrics.area_built' => '_property_area_built',
            'metrics.bedrooms' => '_property_bedrooms',
            'metrics.bathrooms' => '_property_bathrooms',
            'metrics.parking' => '_property_parking',
            'metrics.condition' => '_property_condition',
            'metrics.year_built' => '_property_age',
            'metrics.code' => '_property_code',
            'metrics.featured' => '_property_featured',
            'advisor.user_id' => '_property_agent_id',
            'advisor.phone' => '_property_agent_phone',
            'advisor.email' => '_property_agent_email',
        ];
    }
}
