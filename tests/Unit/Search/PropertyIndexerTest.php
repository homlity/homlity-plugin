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

    public function testLaColaDeShutdownAgrupaEscriturasDelMismoPost(): void
    {
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
