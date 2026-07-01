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
                'source'     => 'string|required',
                'id'         => 'string|required',
                'updated_at' => 'string|optional',
                'raw'        => 'array|optional',
            ],
            'post' => [
                'title'             => 'string|required',
                'description'       => 'string|optional',
                'short_description' => 'string|optional',
                'status'            => 'string|optional:publish|draft|pending|private',
            ],
            'location' => [
                'address'   => 'string|required',
                'address_dane' => 'string|optional',
                'latitude'  => 'float|required',
                'longitude' => 'float|required',
                'show_exact_address' => 'bool|optional',
                'address_complement' => 'string|optional',
                'location_reference' => 'string|optional',
                'maps_url' => 'string|optional',
            ],
            'pricing' => [
                'sale_price'     => 'string|optional',
                'sale_currency'  => 'string|optional',
                'rent_price'     => 'string|optional',
                'rent_currency'  => 'string|optional',
                'admin_price'    => 'string|optional',
                'admin_currency' => 'string|optional',
                'admin_included' => 'bool|optional',
                'negotiable'     => 'bool|optional',
                'commercial_note' => 'string|optional',
            ],
            'metrics' => [
                'area'        => 'string|optional',
                'area_lot'    => 'string|optional',
                'area_private'=> 'string|optional',
                'area_built'  => 'string|optional',
                'bedrooms'    => 'int|optional',
                'bathrooms'   => 'int|optional',
                'parking'     => 'int|optional',
                'condition'   => 'string|optional',
                'year_built'  => 'int|optional',
                'code'        => 'string|optional',
                'stratum'     => 'int|optional',
                'floor'       => 'int|optional',
                'levels'      => 'int|optional',
                'elevators'   => 'int|optional',
                'featured'    => 'bool|optional',
            ],
            'taxonomy' => [
                PropertyTaxonomies::TAXONOMY_OPERATION    => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_TYPE         => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_CATEGORY     => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_TAG          => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_FEATURE      => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_COUNTRY      => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_STATE        => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_CITY         => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => 'string[] slugs|optional',
                PropertyTaxonomies::TAXONOMY_NEARBY       => 'string[] slugs|optional',
            ],
            'media' => [
                'gallery'            => 'string[] urls|optional',
                'featured_image_url' => 'string url|optional',
                'videos'             => 'string[] urls|optional',
                'tour_360'           => 'string[] urls|optional',
                'photos_360'         => 'string[] urls|optional',
                'brochure'           => 'string url|optional',
                'photo_note'         => 'string|optional',
            ],
            'advisor' => [
                'external_id' => 'string|optional',
                'name'        => 'string|optional',
                'email'       => 'string|optional',
                'phone'       => 'string|optional',
                'photo'       => 'string url|optional',
                'role'        => 'string|optional',
                'user_id'     => 'int|optional',
            ],
        ];
    }

    /**
     * Maps normalized dot-path → WordPress post meta key.
     * Only scalar (string/number/bool) fields belong here.
     * Array fields (gallery, videos, tour_360, photos_360) are saved separately.
     *
     * @return array<string,string>
     */
    public static function metaMap(): array
    {
        return [
            // Location
            'location.address'   => '_property_address',
            'location.address_dane' => '_property_address_dane',
            'location.latitude'  => '_property_latitude',
            'location.longitude' => '_property_longitude',
            'location.show_exact_address' => '_property_show_exact_address',
            'location.address_complement' => '_property_address_complement',
            'location.location_reference' => '_property_location_reference',
            'location.maps_url' => '_property_maps_url',

            // Pricing
            'pricing.sale_price'     => '_property_price_sale',
            'pricing.sale_currency'  => '_property_currency_sale',
            'pricing.rent_price'     => '_property_price_rent',
            'pricing.rent_currency'  => '_property_currency_rent',
            'pricing.admin_price'    => '_property_price_admin',
            'pricing.admin_currency' => '_property_currency_admin',
            'pricing.admin_included' => '_property_admin_included',
            'pricing.negotiable'     => '_property_negotiable',
            'pricing.commercial_note' => '_property_commercial_note',

            // Metrics
            'metrics.area'        => '_property_area',
            'metrics.area_lot'    => '_property_area_lot',
            'metrics.area_private'=> '_property_area_private',
            'metrics.area_built'  => '_property_area_built',
            'metrics.bedrooms'    => '_property_bedrooms',
            'metrics.bathrooms'   => '_property_bathrooms',
            'metrics.parking'     => '_property_parking',
            'metrics.condition'   => '_property_condition',
            'metrics.year_built'  => '_property_age',
            'metrics.code'        => '_property_code',
            'metrics.stratum'     => '_property_stratum',
            'metrics.floor'       => '_property_floor',
            'metrics.levels'      => '_property_levels',
            'metrics.elevators'   => '_property_elevators',
            'metrics.featured'    => '_property_featured',

            // Media (scalar only)
            'media.featured_image_url' => '_property_featured_image_url',
            'media.brochure'           => '_property_brochure',
            'media.photo_note'         => '_property_photo_note',

            // Advisor (fields displayed on the property post for quick access)
            'advisor.external_id' => '_property_agent_external_id',
            'advisor.name'  => '_property_agent_name',
            'advisor.email' => '_property_agent_email',
            'advisor.phone' => '_property_agent_phone',
            'advisor.role'  => '_property_agent_role',
            'advisor.photo' => '_property_agent_photo',

            // Raw consignment contact / audit fields
            'external.raw.identification' => '_property_identification',
            'external.raw.contact_name'   => '_property_contact_name',
            'external.raw.contact_email'  => '_property_contact_email',
            'external.raw.contact_phone'  => '_property_contact_phone',
            'external.raw.contact_whatsapp' => '_property_contact_whatsapp',
            'external.raw.consignant_type' => '_property_consignant_type',
            'external.raw.data_consent'   => '_consignment_data_consent',
            'external.raw.authorization_consent' => '_consignment_authorization_consent',
            'external.raw.truth_declaration' => '_consignment_truth_declaration',
            'external.raw.contact_consent' => '_consignment_contact_consent',
        ];
    }
}
