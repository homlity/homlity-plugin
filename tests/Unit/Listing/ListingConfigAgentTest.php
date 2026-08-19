<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Listing;

use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

/**
 * Advisor scoping of the property listing: builders and the shortcode must all
 * reach the same ListingConfig, and the query params handed to the search
 * service must carry it.
 */
final class ListingConfigAgentTest extends TestCase
{
    public function testSinConfiguracionNoFiltraPorAsesor(): void
    {
        $config = ListingConfig::fromBuilderSettings([]);

        self::assertSame(0, $config->presetAgent());
        self::assertFalse($config->useCurrentAgent());
    }

    public function testTomaElAsesorFijoDeLosAjustesDelConstructor(): void
    {
        $config = ListingConfig::fromBuilderSettings([
            'preset_agent' => '42',
            'use_current_agent' => 'yes',
        ]);

        self::assertSame(42, $config->presetAgent());
        self::assertTrue($config->useCurrentAgent());
    }

    public function testElShortcodeAceptaAgentYCurrentAgent(): void
    {
        $config = ListingConfig::fromAtts([
            'agent' => '7',
            'current_agent' => 'true',
        ]);

        self::assertSame(7, $config->presetAgent());
        self::assertTrue($config->useCurrentAgent());
    }

    public function testLosParametrosDeConsultaIncluyenElAsesor(): void
    {
        $params = ListingConfig::fromBuilderSettings([
            'preset_agent' => 42,
            'use_current_agent' => 'yes',
        ])->toQueryParams();

        self::assertSame(42, $params['preset_agent']);
        self::assertTrue($params['use_current_agent']);
    }
}
