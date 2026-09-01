<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

final class PropertyFilterWidgetTest extends TestCase
{
    public function testElPlaceholderDePalabraClaveSePuedeConfigurar(): void
    {
        $html = $this->render($this->keywordOnlySettings([
            'keyword_placeholder' => 'Busca por código & ciudad',
        ]));

        self::assertStringContainsString(
            'placeholder="Busca por código &amp; ciudad"',
            $html
        );
    }

    public function testElPlaceholderPredeterminadoSigueSiendoBuscar(): void
    {
        self::assertStringContainsString(
            'placeholder="Buscar"',
            $this->render($this->keywordOnlySettings())
        );
    }

    public function testLosTresConstructoresExponenLaConfiguracion(): void
    {
        foreach (['Elementor', 'Divi', 'WPBakery'] as $integration) {
            $source = (string) file_get_contents(sprintf(
                '%ssrc/Integrations/%s/Widgets/PropertyFilterWidget.php',
                HOMLITY_PLUGIN_PATH,
                $integration
            ));

            self::assertStringContainsString(
                "add_control('keyword_placeholder'",
                $source,
                sprintf('Falta el control del placeholder en %s.', $integration)
            );
        }
    }

    /** @param array<string,mixed> $settings */
    private function render(array $settings): string
    {
        ob_start();
        TemplateService::includeComponent('property-filter.php', compact('settings'));

        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $overrides */
    private function keywordOnlySettings(array $overrides = []): array
    {
        return array_merge([
            'show_keyword' => 'yes',
            'show_category' => '',
            'show_operation' => '',
            'show_type' => '',
            'show_tag' => '',
            'show_country' => '',
            'show_state' => '',
            'show_city' => '',
            'show_locality' => '',
            'show_neighborhood' => '',
            'show_nearby' => '',
            'show_price' => '',
            'show_area' => '',
            'show_bedrooms' => '',
            'show_bathrooms' => '',
            'show_parking' => '',
            'mobile_sidebar_enabled' => '',
            'show_reset' => '',
        ], $overrides);
    }
}
