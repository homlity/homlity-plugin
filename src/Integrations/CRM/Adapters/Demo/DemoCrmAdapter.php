<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Adapters\Demo;

use Homlity\PluginInmobiliario\Integrations\CRM\Contracts\CrmAdapterInterface;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

class DemoCrmAdapter implements CrmAdapterInterface
{
    public function key(): string
    {
        return 'homlity_demo';
    }

    public function label(): string
    {
        return 'Homlity Demo CRM';
    }

    public function capabilities(): array
    {
        return ['import', 'upsert', 'webhook', 'demo'];
    }

    public function mapRecordToNormalized(array $payload, array $context = []): array
    {
        $record = is_array($payload['property'] ?? null) ? $payload['property'] : $payload;
        $externalId = (string) ($record['external_id'] ?? $payload['external_id'] ?? $payload['id'] ?? '');

        return [
            'external' => [
                'source' => $this->key(),
                'id' => $externalId,
                'hash' => (string) ($payload['external_hash'] ?? md5(wp_json_encode($record))),
                'updated_at' => (string) ($record['updated_at'] ?? $payload['updated_at_source'] ?? ''),
                'raw' => $payload,
            ],
            'post' => [
                'title' => (string) ($record['title'] ?? 'Inmueble demo ' . $externalId),
                'description' => (string) ($record['description'] ?? ''),
                'short_description' => (string) ($record['short_description'] ?? ''),
                'status' => (string) ($record['status'] ?? 'draft'),
            ],
            'location' => [
                'address' => (string) ($record['address'] ?? ''),
                'latitude' => $record['latitude'] ?? '',
                'longitude' => $record['longitude'] ?? '',
            ],
            'pricing' => [
                'sale_price' => (string) ($record['price'] ?? $record['sale_price'] ?? ''),
                'sale_currency' => (string) ($record['currency'] ?? 'COP'),
                'rent_price' => (string) ($record['rent_price'] ?? ''),
                'rent_currency' => (string) ($record['currency'] ?? 'COP'),
                'admin_price' => (string) ($record['administration_price'] ?? ''),
                'admin_currency' => (string) ($record['currency'] ?? 'COP'),
                'admin_included' => (bool) ($record['admin_included'] ?? false),
            ],
            'metrics' => [
                'area' => (string) ($record['area_built'] ?? $record['area_private'] ?? ''),
                'area_private' => (string) ($record['area_private'] ?? ''),
                'area_built' => (string) ($record['area_built'] ?? ''),
                'bedrooms' => $record['bedrooms'] ?? '',
                'bathrooms' => $record['bathrooms'] ?? '',
                'parking' => $record['garages'] ?? '',
                'condition' => (string) ($record['condition'] ?? ''),
                'year_built' => $record['year_built'] ?? '',
                'code' => (string) ($record['code'] ?? ''),
                'featured' => (bool) ($record['featured'] ?? false),
            ],
            'taxonomy' => [
                PropertyTaxonomies::TAXONOMY_OPERATION => $this->listOf($record['management_type'] ?? $record['business_type'] ?? []),
                PropertyTaxonomies::TAXONOMY_TYPE => $this->listOf($record['property_type'] ?? []),
                PropertyTaxonomies::TAXONOMY_FEATURE => $this->listOf($record['features'] ?? []),
                PropertyTaxonomies::TAXONOMY_CITY => $this->listOf($record['city'] ?? []),
                PropertyTaxonomies::TAXONOMY_STATE => $this->listOf($record['state'] ?? []),
                PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => $this->listOf($record['neighborhood'] ?? []),
            ],
            'media' => [
                'gallery' => $this->listOf($record['images'] ?? []),
                'featured_image' => (string) ($record['featured_image'] ?? ''),
            ],
            'advisor' => [
                'email' => (string) ($record['advisor']['email'] ?? ''),
                'phone' => (string) ($record['advisor']['phone'] ?? ''),
                'user_id' => isset($record['advisor']['user_id']) ? absint($record['advisor']['user_id']) : 0,
            ],
        ];
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function listOf($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value) ? $value : [$value];
        $out = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $item = $item['external_url'] ?? $item['name'] ?? $item['slug'] ?? '';
            }
            $item = sanitize_text_field((string) $item);
            if ($item !== '') {
                $out[] = $item;
            }
        }

        return array_values(array_unique($out));
    }
}
