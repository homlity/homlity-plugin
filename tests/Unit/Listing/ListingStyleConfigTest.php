<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Listing;

use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Services\AgentProfileService;
use Homlity\PluginInmobiliario\Services\CapabilityService;
use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class ListingStyleConfigTest extends TestCase
{
    public function testLegacyAgentWithoutAdvisorOnPageKeepsShowingTheCatalogue(): void
    {
        $config = ListingConfig::fromBuilderSettings(['query_mode' => 'custom', 'use_current_agent' => 'yes']);

        self::assertSame('custom', $config->queryMode());
        self::assertTrue($config->useCurrentAgent());
    }

    public function testLegacyAgentMigrationPreservesCombinedFilters(): void
    {
        WpStubs::setUser(9, 'egiraldo', [], [CapabilityService::ROLE_ASSESSOR]);
        WpStubs::$queryVars[AgentProfileService::QUERY_VAR] = 'egiraldo';

        self::assertSame('related_agent', ListingConfig::fromBuilderSettings(['use_current_agent' => 'yes'])->queryMode());
        foreach ([['preset_city' => 7], ['preset_tag' => 3], ['preset_agent' => 10], ['search_keyword' => 'terraza'], ['geo_radius_km' => 5], ['featured_only' => 'yes']] as $preset) {
            $config = ListingConfig::fromBuilderSettings($preset + ['query_mode' => 'custom', 'use_current_agent' => 'yes']);
            self::assertSame('custom', $config->queryMode());
            self::assertTrue($config->useCurrentAgent());
        }
        foreach (['current_agent', 'use_current_agent'] as $key) {
            foreach (['yes', 'on'] as $value) {
                self::assertTrue(ListingConfig::fromAtts([$key => $value])->useCurrentAgent());
            }
        }
    }

    public function testLegacyTagsSurviveBuilderShortcodeAndAjax(): void
    {
        self::assertSame([4], ListingConfig::fromBuilderSettings(['preset_tag' => 4])->presetTagIds());
        self::assertSame([7, 8], ListingConfig::fromBuilderSettings(['preset_tag' => 4, 'preset_tag_ids' => [7, 8]])->presetTagIds());
        self::assertSame([4], ListingConfig::fromAtts(['tag' => 4])->presetTagIds());
        self::assertSame([4], ListingConfig::fromArray(['preset_tag' => 4])->presetTagIds());
    }

    public function testViewVariablesAreInlineOnlyForShortcodes(): void
    {
        self::assertSame([], ListingConfig::fromBuilderSettings(['view_toggle_text_color' => '#123456'])->viewToggleCssVariables());
        self::assertSame(['--homlity-view-toggle-text' => '#123456'], ListingConfig::fromAtts(['view_toggle_text_color' => '#123456'])->viewToggleCssVariables());
    }

    private function render(ListingConfig $config): string
    {
        $query = new class(['post__in' => [0]]) extends \WP_Query { public int $max_num_pages = 0; };
        $search = new PropertySearchService();
        $params = $config->toQueryParams();
        ob_start();
        include HOMLITY_PLUGIN_PATH . 'templates/parts/' . $config->listingTemplate();
        return (string) ob_get_clean();
    }

    public function testBothTemplatesUseResponsiveMapAndConfigurableEmptyMessage(): void
    {
        foreach (['default', 'bootstrap'] as $template) {
            $builder = $this->render(ListingConfig::fromBuilderSettings(['template' => $template, 'empty_message' => 'Sin coincidencias & más', 'map_height' => ['size' => 640]]));
            self::assertStringNotContainsString('style="height:', $builder);
            self::assertStringContainsString('Sin coincidencias &amp; más', $builder);
            $shortcode = $this->render(ListingConfig::fromAtts(['template' => $template, 'empty_message' => 'Ningún inmueble', 'map_height' => 640]));
            self::assertStringContainsString('style="height:640px;"', $shortcode);
            self::assertStringContainsString('Ningún inmueble', $shortcode);
        }
    }

    public function testBootstrapColumnsAreOptInAndDefaultClassesRemain(): void
    {
        $html = $this->render(ListingConfig::fromBuilderSettings(['template' => 'bootstrap']));
        self::assertStringContainsString('homlity-real-estate-search property-listing property-listing--bootstrap', $html);
        self::assertStringNotContainsString('property-listing--custom-columns', $html);
        self::assertStringContainsString('property-listing--custom-columns', $this->render(ListingConfig::fromBuilderSettings(['template' => 'bootstrap', 'custom_columns' => 'yes', 'columns' => 5])));
    }
}
