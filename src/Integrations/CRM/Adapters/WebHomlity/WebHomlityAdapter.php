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

        $advisorObj = is_array($record['advisor'] ?? null) ? $record['advisor'] : [];
        $mediaObj   = is_array($record['media'] ?? null) ? $record['media'] : [];

        [$gallery, $featuredImageUrl] = $this->extractPhotos(
            $mediaObj['photos'] ?? $record['gallery'] ?? $record['imagenes'] ?? []
        );

        $rawStatus = strtolower((string) ($record['status'] ?? 'active'));
        $postStatus = match ($rawStatus) {
            'active', 'publish'   => 'publish',
            'inactive', 'draft'   => 'draft',
            'pending'             => 'pending',
            'private'             => 'private',
            default               => 'publish',
        };

        return [
            'external' => [
                'source'     => $this->key(),
                'id'         => (string) ($record['id'] ?? $record['external_id'] ?? ''),
                'updated_at' => (string) ($record['updated_at'] ?? $record['fecha_actualizacion'] ?? ''),
                'raw'        => $payload,
            ],
            'post' => [
                'title'             => (string) ($record['title'] ?? $record['titulo'] ?? ''),
                'description'       => (string) ($record['description'] ?? $record['descripcion'] ?? ''),
                'short_description' => (string) ($record['excerpt'] ?? $record['short_description'] ?? $record['descripcion_corta'] ?? ''),
                'status'            => $postStatus,
            ],
            'location' => [
                'address'   => (string) ($record['address'] ?? $record['direccion'] ?? ''),
                'latitude'  => $record['latitude'] ?? $record['latitud'] ?? null,
                'longitude' => $record['longitude'] ?? $record['longitud'] ?? null,
            ],
            'pricing' => [
                'sale_price'    => (string) ($record['price_sale'] ?? $record['sale_price'] ?? $record['precio_venta'] ?? ''),
                'sale_currency' => (string) ($record['currency_sale'] ?? $record['sale_currency'] ?? $record['moneda_venta'] ?? ''),
                'rent_price'    => (string) ($record['price_rent'] ?? $record['rent_price'] ?? $record['precio_arriendo'] ?? ''),
                'rent_currency' => (string) ($record['currency_rent'] ?? $record['rent_currency'] ?? $record['moneda_arriendo'] ?? ''),
                'admin_price'   => (string) ($record['price_admin'] ?? $record['admin_price'] ?? $record['precio_administracion'] ?? ''),
                'admin_currency'=> (string) ($record['currency_admin'] ?? $record['admin_currency'] ?? $record['moneda_administracion'] ?? ''),
                'admin_included'=> (bool) ($record['admin_included'] ?? $record['administracion_incluida'] ?? false),
            ],
            'metrics' => [
                'area'        => (string) ($record['area'] ?? ''),
                'area_lot'    => (string) ($record['area_lot'] ?? $record['area_lote'] ?? ''),
                'area_private'=> (string) ($record['area_private'] ?? $record['area_privada'] ?? ''),
                'area_built'  => (string) ($record['area_built'] ?? $record['area_construida'] ?? ''),
                'bedrooms'    => $record['bedrooms'] ?? $record['alcobas'] ?? null,
                'bathrooms'   => $record['bathrooms'] ?? $record['banos'] ?? null,
                'parking'     => $record['parking'] ?? $record['garajes'] ?? null,
                'condition'   => (string) ($record['condition'] ?? $record['estado'] ?? ''),
                'year_built'  => $record['age'] ?? $record['year_built'] ?? $record['anio_construido'] ?? null,
                'code'        => (string) ($record['code'] ?? $record['codigo'] ?? ''),
                'featured'    => (bool) ($record['featured'] ?? false),
            ],
            'taxonomy' => [
                PropertyTaxonomies::TAXONOMY_OPERATION    => $this->asList($record['operation'] ?? $record['gestion'] ?? null),
                PropertyTaxonomies::TAXONOMY_TYPE         => $this->asList($record['type'] ?? $record['tipo'] ?? null),
                PropertyTaxonomies::TAXONOMY_CATEGORY     => $this->asList($record['category'] ?? $record['categories'] ?? $record['categorias'] ?? []),
                PropertyTaxonomies::TAXONOMY_TAG          => $this->asList($record['tags'] ?? $record['etiquetas'] ?? []),
                PropertyTaxonomies::TAXONOMY_FEATURE      => $this->asList($record['features'] ?? $record['caracteristicas'] ?? []),
                PropertyTaxonomies::TAXONOMY_COUNTRY      => $this->asList($record['country'] ?? $record['pais'] ?? []),
                PropertyTaxonomies::TAXONOMY_STATE        => $this->asList($record['state'] ?? $record['departamento'] ?? []),
                PropertyTaxonomies::TAXONOMY_CITY         => $this->asList($record['city'] ?? $record['ciudad'] ?? []),
                PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => $this->asList($record['neighborhood'] ?? $record['barrio'] ?? []),
                PropertyTaxonomies::TAXONOMY_NEARBY       => $this->asList($record['nearby'] ?? $record['nearby_places'] ?? $record['lugares_cercanos'] ?? []),
            ],
            'media' => [
                'gallery'           => $gallery,
                'featured_image_url'=> $featuredImageUrl,
                'videos'            => $this->extractUrls($mediaObj['videos'] ?? []),
                'tour_360'          => $this->extractUrls($mediaObj['tour_360'] ?? []),
                'photos_360'        => $this->extractUrls($mediaObj['photos_360'] ?? []),
                'brochure'          => (string) ($mediaObj['brochure'] ?? $record['brochure'] ?? ''),
            ],
            'advisor' => [
                'external_id' => (string) ($advisorObj['id'] ?? $record['advisor_id'] ?? ''),
                'name'        => (string) ($advisorObj['name'] ?? $record['advisor_name'] ?? ''),
                'email'       => (string) ($advisorObj['email'] ?? $record['advisor_email'] ?? $record['asesor_email'] ?? ''),
                'phone'       => (string) ($advisorObj['phone'] ?? $record['advisor_phone'] ?? $record['asesor_telefono'] ?? ''),
                'photo'       => (string) ($advisorObj['photo'] ?? $record['advisor_photo'] ?? ''),
                'role'        => (string) ($advisorObj['role'] ?? $record['advisor_role'] ?? ''),
                'user_id'     => 0,
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

        $list = is_array($value) ? $value : explode(',', (string) $value);

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

    /**
     * Extract URL list and featured image URL from a photos array.
     * Each photo can be { url, featured } or a plain URL string.
     *
     * @param mixed $photos
     * @return array{0: string[], 1: string}
     */
    private function extractPhotos($photos): array
    {
        if (!is_array($photos)) {
            return [[], ''];
        }

        $gallery     = [];
        $featuredUrl = '';

        foreach ($photos as $photo) {
            if (is_array($photo)) {
                $url = (string) ($photo['url'] ?? '');
                if ($url === '') {
                    continue;
                }
                $gallery[] = $url;
                if ($featuredUrl === '' && !empty($photo['featured'])) {
                    $featuredUrl = $url;
                }
            } elseif (is_string($photo) && $photo !== '') {
                $gallery[] = $photo;
            }
        }

        if ($featuredUrl === '' && !empty($gallery)) {
            $featuredUrl = $gallery[0];
        }

        return [$gallery, $featuredUrl];
    }

    /**
     * Extract URL strings from an array of { url } objects or plain strings.
     *
     * @param mixed $items
     * @return string[]
     */
    private function extractUrls($items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $urls = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $url = (string) ($item['url'] ?? '');
                if ($url !== '') {
                    $urls[] = $url;
                }
            } elseif (is_string($item) && $item !== '') {
                $urls[] = $item;
            }
        }

        return $urls;
    }
}
