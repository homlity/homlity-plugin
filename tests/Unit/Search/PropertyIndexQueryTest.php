<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Search;

use Homlity\PluginInmobiliario\Search\PropertyIndexAdminPage;
use Homlity\PluginInmobiliario\Search\PropertyIndexQuery;
use Homlity\PluginInmobiliario\Search\PropertyIndexSchema;
use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

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
}
