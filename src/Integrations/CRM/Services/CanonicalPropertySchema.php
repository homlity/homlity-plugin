<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Services;

if (!defined('ABSPATH')) {
    exit;
}

class CanonicalPropertySchema
{
    /**
     * @return array<string,mixed>
     */
    public static function definition(): array
    {
        return [
            'source_key' => 'string|required',
            'external_id' => 'string|required',
            'external_hash' => 'string|optional',
            'title' => 'string|required',
            'description' => 'string|optional',
            'property_type' => 'string|optional',
            'management_type' => 'string|optional',
            'status' => 'string|optional',
            'price' => 'number|optional',
            'currency' => 'string|optional',
            'administration_price' => 'number|optional',
            'area_private' => 'number|optional',
            'area_built' => 'number|optional',
            'bedrooms' => 'int|optional',
            'bathrooms' => 'int|optional',
            'garages' => 'int|optional',
            'floor' => 'string|optional',
            'stratum' => 'string|optional',
            'city' => 'string|optional',
            'state' => 'string|optional',
            'neighborhood' => 'string|optional',
            'address' => 'string|optional',
            'latitude' => 'float|optional',
            'longitude' => 'float|optional',
            'features' => 'array|optional',
            'images' => 'array|optional',
            'media' => [
                'images' => self::imageDefinition(),
            ],
            'advisor' => 'array|optional',
            'raw_payload' => 'array|optional',
            'updated_at_source' => 'string|optional',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function imageDefinition(): array
    {
        return [
            'url' => 'string|required HTTP/HTTPS',
            'external_url' => 'string|legacy alias of url',
            'external_image_id' => 'string|optional',
            'title' => 'string|optional',
            'alt' => 'string|optional',
            'position' => 'int|optional',
            'order' => 'int|legacy alias of position',
            'is_featured' => 'bool|optional',
            'hash' => 'string|optional',
            'checksum' => 'string|optional alias of hash',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function advisorDefinition(): array
    {
        return [
            'external_id' => 'string|optional',
            'name' => 'string|optional',
            'email' => 'string|optional',
            'phone' => 'string|optional',
            'photo' => 'string|optional',
        ];
    }
}
