<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Search;

use Homlity\PluginInmobiliario\Search\PropertyIndexAdminPage;
use Homlity\PluginInmobiliario\Search\PropertyIndexQuery;
use Homlity\PluginInmobiliario\Search\PropertyIndexSchema;
use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class PropertyIndexQueryTest extends TestCase
{
    public function testTraduceFiltrosAlIndiceYConservaMetaDeTerceros(): void
    {
        update_option(PropertyIndexAdminPage::USE_OPTION, 1, true);
        update_option(PropertyIndexSchema::SCHEMA_VERSION_OPTION, PropertyIndexSchema::SCHEMA_VERSION, true);
        update_option(PropertyIndexSchema::READY_OPTION, PropertyIndexSchema::SCHEMA_VERSION, true);
        $args = (new PropertySearchService())->buildQueryArgs([
            'price_min' => 1000,
            'price_max' => 2000,
            'bedrooms' => 2,
            'search' => 'Casa',
            'orderby' => 'price_asc',
        ]);
        $args['meta_query']['third_party'] = ['key' => '_custom_filter', 'value' => 'x'];
        $query = new \WP_Query($args);
        $query->set('post_type', 'property');
        $service = new PropertyIndexQuery();
        $service->prepareQuery($query);
        $clauses = $service->applyClauses(['join' => '', 'where' => '', 'orderby' => '', 'groupby' => ''], $query);

        self::assertArrayHasKey('third_party', $query->get('meta_query'));
        self::assertArrayNotHasKey('homlity_price', $query->get('meta_query'));
        self::assertStringContainsString('INNER JOIN wp_homlity_property_index AS hpi', $clauses['join']);
        self::assertStringContainsString('hpi.price_sale BETWEEN 1000 AND 2000', $clauses['where']);
        self::assertStringNotContainsString('wp_postmeta', $clauses['join']);
        self::assertStringContainsString('hpi.display_price ASC', $clauses['orderby']);
    }

    public function testConIndiceNoListoDejaLosClausulasActuales(): void
    {
        update_option(PropertyIndexAdminPage::USE_OPTION, 1, true);
        $args = (new PropertySearchService())->buildQueryArgs(['price_min' => 1000]);
        $query = new \WP_Query($args);
        $query->set('post_type', 'property');
        $clauses = (new PropertyIndexQuery())->applyClauses(['join' => '', 'where' => '', 'orderby' => ''], $query);
        self::assertSame('', $clauses['join']);
    }

    /** @dataProvider indexFilterProvider */
    public function testCadaFiltroGeneraCondicionEnElIndice(array $params, string $needle, ?string $priceColumn = null): void
    {
        $args = (new PropertySearchService())->buildQueryArgs($params);
        if ($priceColumn !== null) $args['homlity_index_filters']['price_column'] = $priceColumn;
        $query = $this->activeQuery($args);
        $clauses = (new PropertyIndexQuery())->applyClauses(['join' => '', 'where' => '', 'orderby' => '', 'groupby' => ''], $query);
        self::assertStringContainsString($needle, $clauses['where'] . $clauses['orderby']);
        self::assertStringContainsString('hpi', $clauses['join']);
    }

    public static function indexFilterProvider(): array
    {
        return [
            'venta' => [['price_min' => 100], 'hpi.price_sale'],
            'arriendo' => [['price_min' => 100], 'hpi.price_rent', '_property_price_rent'],
            'alcobas' => [['bedrooms' => 3], 'hpi.bedrooms'],
            'banos' => [['bathrooms' => 2], 'hpi.bathrooms'],
            'garajes' => [['parking' => 1], 'hpi.parking'],
            'area' => [['area_min' => 80], 'hpi.area'],
            'geo' => [['geo_latitude' => 4.7, 'geo_longitude' => -74.0, 'geo_radius_km' => 5], 'hpi.latitude'],
            'agente' => [['preset_agent' => 9], 'hpi.agent_id'],
            'destacado' => [['featured' => true], 'hpi.featured'],
            'keyword' => [['search' => 'casa'], 'MATCH(hpi.search_text)'],
            'fecha' => [['orderby' => 'date'], 'hpi.post_date'],
            'precio asc' => [['orderby' => 'price_asc'], 'hpi.display_price ASC'],
            'precio desc' => [['orderby' => 'price_desc'], 'hpi.display_price DESC'],
            'titulo' => [['orderby' => 'title'], 'hpi.is_visible'],
        ];
    }

    public function testLaComparacionUsaUnaConsultaDelListadoYNoLaPrincipal(): void
    {
        WpStubs::$capabilities = ['manage_options'];
        $_GET['homlity_index_compare'] = '1';
        WpStubs::$queryResolver = static fn(array $args): array => [
            'posts' => [new \WP_Post(['ID' => 55, 'post_type' => 'property'])],
            'found_posts' => 1,
        ];
        $args = (new PropertySearchService())->buildQueryArgs(['search' => 'casa']);
        $query = $this->activeQuery($args);
        $service = new PropertyIndexQuery();
        self::assertNotNull($service->compareQuery($query));
        self::assertNull($service->compareQuery(new \WP_Query(['post_type' => 'property'])));
        unset($_GET['homlity_index_compare']);
    }

    /** @dataProvider keywordProvider */
    public function testKeywordConstruyeSqlSeguro(string $term, string $needle, string $forbidden = ''): void
    {
        $args = (new PropertySearchService())->buildQueryArgs(['search' => $term]);
        $query = $this->activeQuery($args);
        $clauses = (new PropertyIndexQuery())->applyClauses(['join' => '', 'where' => '', 'orderby' => '', 'groupby' => ''], $query);
        self::assertStringContainsString($needle, $clauses['where']);
        if ($forbidden !== '') self::assertStringNotContainsString($forbidden, $clauses['where']);
    }

    public static function keywordProvider(): array
    {
        return [
            'frase' => ['casa campestre', '+casa* +campestre*', 'AGAINST(\'casa campestre\''],
            'codigo' => ['AW-1045', "hpi.code = 'aw-1045'", 'MATCH('],
            'numero codigo' => ['2024', "hpi.code = '2024'", 'MATCH('],
            'codigo largo' => ['12345', "hpi.code = '12345'", 'MATCH('],
            'comilla' => ['"piscina', '+piscina*', '"piscina'],
            'correo' => ['correo@dominio.com', '+correo* +dominio.com*', '@'],
            'corto' => ['ab', "hpi.search_text LIKE '%ab%'", 'MATCH('],
            'ciudad' => ['chapinero', '+chapinero*', '"chapinero'],
            'texto con numero' => ['apartamento 3 habitaciones', "LIKE '%apartamento 3 habitaciones%'"],
            'calle con numero' => ['calle 100', "LIKE '%calle 100%'"],
            'guion sin numero' => ['semi-amoblado', '+semi* +amoblado*', "hpi.code = 'semi-amoblado'"] ,
        ];
    }

    public function testElRankingConservaElOrdenDeRelevancia(): void
    {
        $query = $this->activeQuery((new PropertySearchService())->buildQueryArgs(['search' => 'AW-1045']));
        $clauses = (new PropertyIndexQuery())->applyClauses(['join' => '', 'where' => '', 'orderby' => '', 'groupby' => ''], $query);
        $orderby = $clauses['orderby'];
        $positions = array_map(static fn(string $needle): int => strpos($orderby, $needle), [
            'THEN 0', 'THEN 1', 'THEN 2', 'THEN 3', 'THEN 4', 'THEN 5', 'ELSE 9',
        ]);
        self::assertNotContains(false, $positions);
        for ($i = 1; $i < count($positions); $i++) {
            self::assertLessThan($positions[$i], $positions[$i - 1]);
        }
        self::assertNotFalse(strpos($orderby, 'MIN(CASE'));
    }

    public function testConIndiceActivoUnaConsultaSinFiltrosDeIndiceNoSeModifica(): void
    {
        $query = $this->activeQuery(['post_type' => 'property']);
        $original = ['join' => 'JOIN custom_table c ON c.id = wp_posts.ID', 'where' => ' AND c.ok = 1', 'orderby' => 'wp_posts.ID DESC'];
        self::assertSame($original, (new PropertyIndexQuery())->applyClauses($original, $query));
    }

    public function testCiudadTipoYPrecioConservanTaxQuery(): void
    {
        WpStubs::setTerm(4, PropertyTaxonomies::TAXONOMY_CITY, 'bogota', 'Bogotá');
        WpStubs::setTerm(8, PropertyTaxonomies::TAXONOMY_TYPE, 'casa', 'Casa');
        $args = (new PropertySearchService())->buildQueryArgs(['city' => 4, 'type' => 8, 'price_min' => 100]);
        $query = $this->activeQuery($args);
        $clauses = (new PropertyIndexQuery())->applyClauses(['join' => '', 'where' => '', 'orderby' => '', 'groupby' => ''], $query);
        self::assertCount(2, array_filter($args['tax_query'], 'is_array'));
        self::assertStringContainsString('hpi.price_sale', $clauses['where']);
    }

    private function activeQuery(array $args): \WP_Query
    {
        update_option(PropertyIndexAdminPage::USE_OPTION, 1, true);
        update_option(PropertyIndexSchema::SCHEMA_VERSION_OPTION, PropertyIndexSchema::SCHEMA_VERSION, true);
        update_option(PropertyIndexSchema::READY_OPTION, PropertyIndexSchema::SCHEMA_VERSION, true);
        $query = new \WP_Query($args);
        $query->set('post_type', 'property');
        return $query;
    }
}
