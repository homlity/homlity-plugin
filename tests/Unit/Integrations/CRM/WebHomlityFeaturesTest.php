<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations\CRM;

use Homlity\PluginInmobiliario\Integrations\CRM\Adapters\WebHomlity\WebHomlityAdapter;
use Homlity\PluginInmobiliario\Integrations\CRM\PropertyUpsertService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class WebHomlityFeaturesTest extends TestCase
{
    public function testGroupedFeaturesReachThePublicTaxonomy(): void
    {
        WpStubs::$registeredTaxonomies = [PropertyTaxonomies::TAXONOMY_FEATURE];
        $normalized = (new WebHomlityAdapter())->mapRecordToNormalized([
            'id' => 'homi-3429', 'code' => 'VTAP1380002', 'title' => 'Apartamento',
            'features' => [
                ['category' => ['id' => 2, 'name' => 'Características exteriores'], 'features' => [
                    ['id' => 12, 'name' => 'Piscina', 'value' => 'Sí'],
                    ['id' => 13, 'name' => 'Sauna', 'value' => 'No'],
                ]],
                ['id' => 3, 'name' => 'Características interiores', 'features' => [
                    ['id' => 14, 'name' => 'Estudio', 'value' => true],
                ]],
            ],
        ]);
        self::assertSame(['Piscina', 'Estudio'], $normalized['taxonomy']['property_feature']);
        $result = (new PropertyUpsertService())->upsert($normalized);
        self::assertTrue($result['ok']);
        $ids = WpStubs::$objectTerms[$result['post_id']]['property_feature'] ?? [];
        $terms = array_map(static fn ($id) => get_term($id, 'property_feature'), $ids);
        $terms = PropertyTaxonomies::filterVisibleFeatureTerms($terms);
        $names = array_map(static fn ($term) => $term->name, $terms);
        sort($names);
        self::assertSame(['Estudio', 'Piscina'], $names);
    }

    public function testIntegrationResourceDeduplicatesGroupsAndExcludesDisabledValues(): void
    {
        $pool = ['id' => 12, 'name' => 'Piscina', 'value' => 'Sí', 'value_raw' => 1];
        $normalized = (new WebHomlityAdapter())->mapRecordToNormalized(['features' => [
            'all' => [$pool, ['name' => 'Sauna', 'value' => 'Sí', 'value_raw' => 0]],
            'externas' => [$pool], 'internas' => [],
        ]]);
        self::assertSame(['Piscina'], $normalized['taxonomy']['property_feature']);
    }

    public function testPlainWebhookAndNestedFeatureRemainSupported(): void
    {
        $adapter = new WebHomlityAdapter();
        self::assertSame(['Piscina', 'Zona BBQ'], $adapter->mapRecordToNormalized([
            'features' => ['Piscina', 'Zona BBQ'],
        ])['taxonomy']['property_feature']);
        self::assertSame(['Estudio'], $adapter->mapRecordToNormalized([
            'caracteristicas' => [['feature' => ['id' => 14, 'name' => 'Estudio'], 'value' => 1]],
        ])['taxonomy']['property_feature']);
    }
}
