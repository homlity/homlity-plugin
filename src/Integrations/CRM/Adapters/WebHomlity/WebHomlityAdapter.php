<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Adapters\WebHomlity;

use Homlity\PluginInmobiliario\Integrations\CRM\Contracts\CrmAdapterInterface;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

class WebHomlityAdapter implements CrmAdapterInterface
{
    public function key(): string
    {
        return 'web_homlity';
    }

    public function label(): string
    {
        return 'Web Homlity';
    }

    public function capabilities(): array
    {
        return ['import', 'upsert', 'webhook'];
    }

    public function mapRecordToNormalized(array $payload, array $context = []): array
    {
        $record = is_array($payload['property'] ?? null) ? $payload['property'] : $payload;
        $op = $this->asList($record['operation'] ?? $record['gestion'] ?? null);
        $type = $this->asList($record['type'] ?? $record['tipo'] ?? null);

        return [
            'external' => [
                'source' => $this->key(),
                'id' => (string) ($record['external_id'] ?? $record['id'] ?? ''),
                'updated_at' => (string) ($record['updated_at'] ?? $record['fecha_actualizacion'] ?? ''),
                'raw' => $payload,
            ],
            'post' => [
                'title' => (string) ($record['title'] ?? $record['titulo'] ?? ''),
                'description' => (string) ($record['description'] ?? $record['descripcion'] ?? ''),
                'short_description' => (string) ($record['short_description'] ?? $record['descripcion_corta'] ?? ''),
                'status' => (string) ($record['status'] ?? 'publish'),
            ],
            'location' => [
                'address' => (string) ($record['address'] ?? $record['direccion'] ?? ''),
                'latitude' => $record['latitude'] ?? $record['latitud'] ?? null,
                'longitude' => $record['longitude'] ?? $record['longitud'] ?? null,
            ],
            'pricing' => [
                'sale_price' => (string) ($record['sale_price'] ?? $record['precio_venta'] ?? ''),
                'sale_currency' => (string) ($record['sale_currency'] ?? $record['moneda_venta'] ?? ''),
                'rent_price' => (string) ($record['rent_price'] ?? $record['precio_arriendo'] ?? ''),
                'rent_currency' => (string) ($record['rent_currency'] ?? $record['moneda_arriendo'] ?? ''),
                'admin_price' => (string) ($record['admin_price'] ?? $record['precio_administracion'] ?? ''),
                'admin_currency' => (string) ($record['admin_currency'] ?? $record['moneda_administracion'] ?? ''),
                'admin_included' => (bool) ($record['admin_included'] ?? $record['administracion_incluida'] ?? false),
            ],
            'metrics' => [
                'area' => (string) ($record['area'] ?? ''),
                'area_lot' => (string) ($record['area_lot'] ?? $record['area_lote'] ?? ''),
                'area_private' => (string) ($record['area_private'] ?? $record['area_privada'] ?? ''),
                'area_built' => (string) ($record['area_built'] ?? $record['area_construida'] ?? ''),
                'bedrooms' => $record['bedrooms'] ?? $record['alcobas'] ?? null,
                'bathrooms' => $record['bathrooms'] ?? $record['banos'] ?? null,
                'parking' => $record['parking'] ?? $record['garajes'] ?? null,
                'condition' => (string) ($record['condition'] ?? $record['estado'] ?? ''),
                'year_built' => $record['year_built'] ?? $record['anio_construido'] ?? null,
                'code' => (string) ($record['code'] ?? $record['codigo'] ?? ''),
                'featured' => (bool) ($record['featured'] ?? false),
            ],
            'taxonomy' => [
                PropertyTaxonomies::TAXONOMY_OPERATION => $op,
                PropertyTaxonomies::TAXONOMY_TYPE => $type,
                PropertyTaxonomies::TAXONOMY_CATEGORY => $this->asList($record['categories'] ?? $record['categorias'] ?? []),
                PropertyTaxonomies::TAXONOMY_TAG => $this->asList($record['tags'] ?? $record['etiquetas'] ?? []),
                PropertyTaxonomies::TAXONOMY_FEATURE => $this->asList($record['features'] ?? $record['caracteristicas'] ?? []),
                PropertyTaxonomies::TAXONOMY_COUNTRY => $this->asList($record['country'] ?? $record['pais'] ?? []),
                PropertyTaxonomies::TAXONOMY_STATE => $this->asList($record['state'] ?? $record['departamento'] ?? []),
                PropertyTaxonomies::TAXONOMY_CITY => $this->asList($record['city'] ?? $record['ciudad'] ?? []),
                PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => $this->asList($record['neighborhood'] ?? $record['barrio'] ?? []),
                PropertyTaxonomies::TAXONOMY_NEARBY => $this->asList($record['nearby_places'] ?? $record['lugares_cercanos'] ?? []),
            ],
            'media' => [
                'gallery' => $this->asList($record['gallery'] ?? $record['imagenes'] ?? []),
                'featured_image' => (string) ($record['featured_image'] ?? $record['imagen_destacada'] ?? ''),
            ],
            'advisor' => [
                'email' => (string) ($record['advisor_email'] ?? $record['asesor_email'] ?? ''),
                'phone' => (string) ($record['advisor_phone'] ?? $record['asesor_telefono'] ?? ''),
                'user_id' => isset($record['advisor_user_id']) ? absint($record['advisor_user_id']) : 0,
            ],
        ];
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function asList($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            $list = $value;
        } else {
            $list = explode(',', (string) $value);
        }

        $out = [];
        foreach ($list as $item) {
            if (is_array($item)) {
                $item = $item['slug'] ?? $item['name'] ?? '';
            }
            $item = sanitize_text_field((string) $item);
            if ($item === '') {
                continue;
            }
            $out[] = $item;
        }

        return array_values(array_unique($out));
    }
}
