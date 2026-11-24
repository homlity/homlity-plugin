<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations;

use Homlity\PluginInmobiliario\Integrations\Divi\Widgets\PropertyTechnicalSheetButtonWidget;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class PropertyTechnicalSheetButtonWidgetStylesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once HOMLITY_PLUGIN_PATH . 'src/Integrations/Divi/Compatibility/DiviWidgetApi.php';
        WpStubs::$sqlResults['_property_agent_id'] = [];
    }

    public function testElBotonPermiteElegirAnchoCompleto(): void
    {
        $control = (new PropertyTechnicalSheetButtonWidget())->get_controls()['button_width'];

        self::assertArrayHasKey('full', $control['options']);
        self::assertSame('100%', $control['selectors_dictionary']['full']);
        self::assertStringContainsString(
            '.property-tech-sheet-btn',
            array_key_first($control['selectors'])
        );
    }
}
