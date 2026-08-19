<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

final class PropertySearchServiceAgentTest extends TestCase
{
    public function testElAsesorSeTraduceAUnFiltroDeMetadato(): void
    {
        $args = (new PropertySearchService())->buildQueryArgs(['preset_agent' => 42]);

        $agentClauses = array_values(array_filter(
            $args['meta_query'],
            static fn($clause): bool => is_array($clause) && ($clause['key'] ?? '') === '_property_agent_id'
        ));

        self::assertCount(1, $agentClauses);
        self::assertSame(42, $agentClauses[0]['value']);
    }

    public function testSinAsesorNoSeAgregaElFiltro(): void
    {
        foreach ([[], ['preset_agent' => 0], ['preset_agent' => '']] as $params) {
            $args = (new PropertySearchService())->buildQueryArgs($params);

            foreach ($args['meta_query'] as $clause) {
                self::assertNotSame('_property_agent_id', is_array($clause) ? ($clause['key'] ?? '') : '');
            }
        }
    }
}
