<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Homologation;

use Homlity\PluginInmobiliario\Homologation\EntityType;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

final class EntityTypeTest extends TestCase
{
    public function testAllDevuelveTodosLosTiposEtiquetados(): void
    {
        $all = EntityType::all();

        self::assertContains(EntityType::FEATURE, $all);
        self::assertContains(EntityType::CURRENCY, $all);
        self::assertCount(count(EntityType::LABELS), $all);
    }

    /** @dataProvider tiposConTaxonomia */
    public function testTaxonomyMapeaCadaTipoASuTaxonomia(string $entityType, string $taxonomy): void
    {
        self::assertSame($taxonomy, EntityType::taxonomy($entityType));
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function tiposConTaxonomia(): array
    {
        return [
            'feature'      => [EntityType::FEATURE, 'property_feature'],
            'country'      => [EntityType::COUNTRY, 'property_country'],
            'city'         => [EntityType::CITY, 'property_city'],
            'neighborhood' => [EntityType::NEIGHBORHOOD, 'property_neighborhood'],
            'operation'    => [EntityType::OPERATION, 'property_operation'],
        ];
    }

    public function testCurrencyNoTieneTaxonomia(): void
    {
        self::assertTrue(EntityType::isValid(EntityType::CURRENCY));
        self::assertNull(EntityType::taxonomy(EntityType::CURRENCY));
    }

    public function testTaxonomyDevuelveNullParaTipoDesconocido(): void
    {
        self::assertNull(EntityType::taxonomy('inexistente'));
    }

    public function testLabelDevuelveLaEtiquetaHumana(): void
    {
        self::assertSame('Ciudades / Municipios', EntityType::label(EntityType::CITY));
    }

    public function testLabelDevuelveElTipoCuandoNoHayEtiqueta(): void
    {
        self::assertSame('inexistente', EntityType::label('inexistente'));
    }

    public function testIsValidRechazaTiposDesconocidos(): void
    {
        self::assertFalse(EntityType::isValid('inexistente'));
        self::assertFalse(EntityType::isValid(''));
    }

    public function testTodaTaxonomiaMapeadaCorrespondeAUnTipoValido(): void
    {
        foreach (array_keys(EntityType::TAXONOMY_MAP) as $entityType) {
            self::assertTrue(EntityType::isValid($entityType), $entityType . ' no está etiquetado');
        }
    }
}
