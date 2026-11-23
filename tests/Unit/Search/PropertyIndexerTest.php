<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Search;

use Homlity\PluginInmobiliario\Search\PropertyIndexAdminPage;
use Homlity\PluginInmobiliario\Search\PropertyIndexer;
use Homlity\PluginInmobiliario\Search\PropertyIndexRebuilder;
use Homlity\PluginInmobiliario\Search\PropertyIndexSchema;
use Homlity\PluginInmobiliario\Search\PropertyIndexSync;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class PropertyIndexerTest extends TestCase
{
    public function testBuildRowNormalizaUnInmuebleCompleto(): void
    {
        $this->property(10, [
            '_property_status' => 'active',
            '_property_available' => 'yes',
            '_property_price_sale' => '1.200.000',
            '_property_price_rent' => '900000',
            '_property_bedrooms' => '3',
            '_property_bathrooms' => '2',
            '_property_parking' => '1',
            '_property_area' => '120 m²',
            '_property_latitude' => '4.7110',
            '_property_longitude' => '-74.0721',
            '_property_featured' => '1',
            '_property_agent_id' => '44',
            '_property_code' => 'AW-1045',
            '_homlity_sync_id' => 'SYNC-9',
        ]);
        WpStubs::setTerm(1, PropertyTaxonomies::TAXONOMY_FEATURE, 'piscina', 'Piscina');
        WpStubs::$postTerms[10][PropertyTaxonomies::TAXONOMY_FEATURE] = [WpStubs::$terms[PropertyTaxonomies::TAXONOMY_FEATURE][1]];
        WpStubs::setTerm(2, PropertyTaxonomies::TAXONOMY_CITY, 'bogota', 'Bogotá');
        WpStubs::$postTerms[10][PropertyTaxonomies::TAXONOMY_CITY] = [WpStubs::$terms[PropertyTaxonomies::TAXONOMY_CITY][2]];

        $row = (new PropertyIndexer())->buildRow(10);

        self::assertSame(1, $row['is_visible']);
        self::assertSame(1200000.0, $row['price_sale']);
        self::assertSame(1200000.0, $row['display_price']);
        self::assertSame(120.0, $row['area']);
        self::assertStringContainsString('piscina', $row['search_text']);
        self::assertStringContainsString('bogota', $row['search_text']);
        self::assertStringNotContainsString('á', $row['search_text']);
    }

    public function testBuildRowMantieneVisibleSinMetasDeEstadoYDisponibilidad(): void
    {
        $this->property(11);
        self::assertSame(1, (new PropertyIndexer())->buildRow(11)['is_visible']);
    }

    public function testBuildRowOcultaInactivoYUsaPrecioDeArriendo(): void
    {
        $this->property(12, ['_property_status' => 'inactive']);
        self::assertSame(0, (new PropertyIndexer())->buildRow(12)['is_visible']);

        $this->property(13, ['_property_price_rent' => '850000']);
        $row = (new PropertyIndexer())->buildRow(13);
        self::assertNull($row['price_sale']);
        self::assertSame(850000.0, $row['display_price']);
    }

    public function testBuildRowReplicaLaVisibilidadDeMetaQueryConDuplicados(): void
    {
        $this->property(14, ['_property_status' => 'Active', '_property_available' => 'Active']);
        self::assertSame(1, (new PropertyIndexer())->buildRow(14)['is_visible']);

        $this->property(15, ['_property_status' => '', '_property_available' => 'yes']);
        self::assertSame(0, (new PropertyIndexer())->buildRow(15)['is_visible']);

        $this->property(16, ['_property_status' => ['inactive', 'active'], '_property_available' => ['no', 'active']]);
        self::assertSame(1, (new PropertyIndexer())->buildRow(16)['is_visible']);

        $this->property(17, ['_property_featured' => 'yes']);
        self::assertSame(0, (new PropertyIndexer())->buildRow(17)['featured']);
    }

    public function testNumberOrNullInterpretaFormatosCopYDecimales(): void
    {
        $cases = [
            '1.200' => 1200.0,
            '1.200.000' => 1200000.0,
            '350.000.000' => 350000000.0,
            '120,5' => 120.5,
            '120.5' => 120.5,
            '120 m²' => 120.0,
            '$ 1.500.000 COP' => 1500000.0,
        ];
        $id = 100;
        foreach ($cases as $value => $expected) {
            $this->property(++$id, ['_property_area' => $value]);
            self::assertSame($expected, (new PropertyIndexer())->buildRow($id)['area'], $value);
        }
    }

    public function testLasCoordenadasUsanDecimalesSimplesYRechazanFueraDeRango(): void
    {
        $this->property(120, ['_property_latitude' => '4.711', '_property_longitude' => '-74.072']);
        self::assertSame(4.711, (new PropertyIndexer())->buildRow(120)['latitude']);
        self::assertSame(-74.072, (new PropertyIndexer())->buildRow(120)['longitude']);

        $this->property(121, ['_property_latitude' => '4,711', '_property_longitude' => '-74.0721456']);
        self::assertSame(4.711, (new PropertyIndexer())->buildRow(121)['latitude']);
        self::assertSame(-74.0721456, (new PropertyIndexer())->buildRow(121)['longitude']);

        $this->property(122, ['_property_latitude' => '200']);
        self::assertNull((new PropertyIndexer())->buildRow(122)['latitude']);
    }

    public function testFlushSinEsquemaNoEjecutaSqlYConservaIdsPendientes(): void
    {
        $this->property(123);
        $sync = new PropertyIndexSync();
        $sync->queuePost(123);
        $sync->flush();

        self::assertSame([], $GLOBALS['wpdb']->rawQueries);
        self::assertContains(123, (array) get_option(PropertyIndexRebuilder::PENDING_IDS_OPTION, []));
    }

    public function testEnsureEIsReadyNoConsultanLaBaseConVersionAlDia(): void
    {
        update_option(PropertyIndexSchema::SCHEMA_VERSION_OPTION, PropertyIndexSchema::SCHEMA_VERSION, true);
        update_option(PropertyIndexSchema::READY_OPTION, PropertyIndexSchema::SCHEMA_VERSION, true);
        PropertyIndexSchema::resetMemo();

        PropertyIndexSchema::ensure();
        self::assertTrue(PropertyIndexSchema::isReady());
        self::assertSame([], $GLOBALS['wpdb']->rawQueries);
    }

    public function testLaColaDeShutdownAgrupaEscriturasDelMismoPost(): void
    {
        update_option(PropertyIndexSchema::SCHEMA_VERSION_OPTION, PropertyIndexSchema::SCHEMA_VERSION, true);
        $this->property(20, ['_property_code' => 'A-20']);
        $sync = new PropertyIndexSync();
        $sync->queuePost(20);
        $sync->queueMeta(1, 20, '_property_code', 'A-20');
        $sync->queueMeta(2, 20, '_property_price_sale', '100');
        $sync->flush();

        self::assertCount(1, $GLOBALS['wpdb']->rawQueries);
        self::assertStringContainsString('INSERT INTO wp_homlity_property_index', $GLOBALS['wpdb']->rawQueries[0]);
    }

    public function testElRebuilderContinuaDesdeElCursorGuardado(): void
    {
        update_option(PropertyIndexSchema::SCHEMA_VERSION_OPTION, PropertyIndexSchema::SCHEMA_VERSION, true);
        $this->property(31);
        $this->property(32);
        WpStubs::$sqlResults['SELECT ID FROM wp_posts'] = [['ID' => 31], ['ID' => 32]];

        $rebuilder = new PropertyIndexRebuilder();
        $result = $rebuilder->runBatch();

        self::assertSame(32, $result['cursor']);
        self::assertSame(32, (int) get_option(PropertyIndexRebuilder::CURSOR_OPTION));
        self::assertStringContainsString('ID > 0', $GLOBALS['wpdb']->rawQueries[0]);
    }

    public function testElBuscadorNoSeConsideraHabilitadoSiElIndiceNoEstaListo(): void
    {
        update_option(PropertyIndexAdminPage::USE_OPTION, 1, false);
        self::assertFalse(PropertyIndexAdminPage::useIndex());
    }

    /** @param array<string,mixed> $meta */
    private function property(int $id, array $meta = []): void
    {
        WpStubs::$postObjects[$id] = new \WP_Post([
            'ID' => $id,
            'post_type' => 'property',
            'post_status' => 'publish',
            'post_title' => 'Casa en Bogotá',
            'post_excerpt' => 'Amplia propiedad',
        ]);
        WpStubs::setPost($id, 'Casa en Bogotá', '', $meta);
    }
}
