<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations\CRM\FieldMap;

use Homlity\PluginInmobiliario\Integrations\CRM\FieldMap\PropertyFieldSchema;
use Homlity\PluginInmobiliario\Integrations\CRM\Support\ArrayPath;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

/**
 * Pruebas de contrato del esquema canónico: protegen el mapeo entre la
 * estructura normalizada del CRM y las meta keys de WordPress.
 */
final class PropertyFieldSchemaTest extends TestCase
{
    public function testElEsquemaDeclaraTodasLasSecciones(): void
    {
        $schema = PropertyFieldSchema::schema();

        foreach (['external', 'post', 'location', 'pricing', 'metrics', 'taxonomy', 'media', 'advisor'] as $section) {
            self::assertArrayHasKey($section, $schema);
            self::assertNotEmpty($schema[$section]);
        }
    }

    public function testLosCamposObligatoriosSonLosEsperados(): void
    {
        $schema = PropertyFieldSchema::schema();
        $required = [];
        foreach ($schema as $section => $fields) {
            foreach ($fields as $field => $rule) {
                if (str_contains((string) $rule, '|required')) {
                    $required[] = $section . '.' . $field;
                }
            }
        }

        self::assertSame([
            'external.source',
            'external.id',
            'post.title',
            'location.address',
            'location.latitude',
            'location.longitude',
        ], $required);
    }

    public function testCadaMetaKeyEsUnicaYUsaElPrefijoDelPlugin(): void
    {
        $metaMap = PropertyFieldSchema::metaMap();

        self::assertSame(count($metaMap), count(array_unique($metaMap)), 'Hay meta keys duplicadas en metaMap()');
        foreach ($metaMap as $path => $metaKey) {
            self::assertMatchesRegularExpression('/^_(property|consignment)_/', $metaKey, $path);
        }
    }

    public function testCadaRutaEscalarDelMapaExisteEnElEsquema(): void
    {
        $schema = PropertyFieldSchema::schema();

        foreach (array_keys(PropertyFieldSchema::metaMap()) as $path) {
            // Los campos de external.raw provienen del payload crudo del CRM.
            if (str_starts_with($path, 'external.raw.')) {
                continue;
            }

            [$section, $field] = explode('.', $path, 2);
            self::assertArrayHasKey($section, $schema, $path);
            self::assertArrayHasKey($field, $schema[$section], $path);
        }
    }

    public function testElMapaNoIncluyeCamposDeTipoArreglo(): void
    {
        $metaMap = PropertyFieldSchema::metaMap();

        foreach (['media.gallery', 'media.videos', 'media.tour_360', 'media.photos_360'] as $arrayField) {
            self::assertArrayNotHasKey($arrayField, $metaMap);
        }
    }

    public function testElMapaSeResuelveSobreUnPayloadNormalizado(): void
    {
        $normalized = [
            'location' => ['address' => 'Calle 100 #10-20', 'latitude' => 4.68, 'longitude' => -74.05],
            'pricing'  => ['sale_price' => '450000000', 'sale_currency' => 'COP'],
            'metrics'  => ['bedrooms' => 3, 'code' => 'APT-450'],
            'external' => ['raw' => ['contact_email' => 'asesor@ejemplo.com']],
        ];

        $meta = [];
        foreach (PropertyFieldSchema::metaMap() as $path => $metaKey) {
            $value = ArrayPath::get($normalized, $path);
            if ($value !== null) {
                $meta[$metaKey] = $value;
            }
        }

        self::assertSame([
            '_property_address'       => 'Calle 100 #10-20',
            '_property_latitude'      => 4.68,
            '_property_longitude'     => -74.05,
            '_property_price_sale'    => '450000000',
            '_property_currency_sale' => 'COP',
            '_property_bedrooms'      => 3,
            '_property_code'          => 'APT-450',
            '_property_contact_email' => 'asesor@ejemplo.com',
        ], $meta);
    }
}
