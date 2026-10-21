<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/** Las URLs SEO mantienen una sola variante para las gestiones sinónimas. */
final class PropertyTaxonomiesTest extends TestCase
{
    public function testLasGestionesCanonicasConsolidanSinonimosYConservanLasEspecializadas(): void
    {
        WpStubs::$registeredTaxonomies[] = PropertyTaxonomies::TAXONOMY_OPERATION;
        foreach ([1 => ['arriendo', 'Arriendo'], 2 => ['alquiler', 'Alquiler'], 3 => ['venta', 'Venta'],
            4 => ['arriendo-venta', 'Arriendo/Venta'], 5 => ['alquiler-venta', 'Alquiler/Venta'],
            6 => ['alquiler-temporal', 'Alquiler temporal']] as $id => [$slug, $name]) {
            WpStubs::setTerm($id, PropertyTaxonomies::TAXONOMY_OPERATION, $slug, $name);
        }
        self::assertSame('alquiler', PropertyTaxonomies::canonicalOperationSlug('arriendo'));
        self::assertSame('alquiler', PropertyTaxonomies::canonicalOperationSlug('alquiler'));
        self::assertSame('alquiler-venta', PropertyTaxonomies::canonicalOperationSlug('arriendo-venta'));
        self::assertSame('alquiler-venta', PropertyTaxonomies::canonicalOperationSlug('alquiler-venta'));
        self::assertSame('alquiler-temporal', PropertyTaxonomies::canonicalOperationSlug('alquiler-temporal'));
        self::assertSame('venta', PropertyTaxonomies::canonicalOperationSlug('venta'));
    }
}
