<?php

declare(strict_types=1);

namespace {
    if (!class_exists('ET_Builder_Module')) {
        class ET_Builder_Module {}
    }
}

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations {
    use Homlity\PluginInmobiliario\Integrations\Divi\Widgets\PropertyListingWidget;
    use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyListingWidget as BakeryWidget;
    use Homlity\PluginInmobiliario\Integrations\WPBakery\WPBakeryIntegrationService;
    use Homlity\PluginInmobiliario\Tests\Support\TestCase;

    final class PropertyListingStylesTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            $GLOBALS['wpdb'] = new class extends \HomlityTestWpdb {
                public function get_col(string $query, int $column = 0): array { return []; }
            };
            require_once HOMLITY_PLUGIN_PATH . 'src/Integrations/Divi/Compatibility/DiviWidgetApi.php';
            require_once HOMLITY_PLUGIN_PATH . 'src/Integrations/WPBakery/Compatibility/WPBakeryWidgetApi.php';
            require_once HOMLITY_PLUGIN_PATH . 'src/Integrations/Divi/Modules/WidgetModule.php';
        }

        private function expandedIds(array $controls): array
        {
            $ids = array_keys($controls);
            foreach ($controls as $name => $control) {
                $suffixes = match ($control['group_type'] ?? '') {
                    'border' => ['border', 'width', 'color', 'border_type', 'border_width', 'border_color', 'border_radius'],
                    'box_shadow' => ['box_shadow_type', 'box_shadow', 'box_shadow_position', 'shadow'],
                    'typography' => ['typography', 'font_family', 'font_size', 'font_weight', 'text_transform', 'font_style', 'text_decoration', 'line_height', 'letter_spacing', 'word_spacing'],
                    default => [],
                };
                foreach ($suffixes as $suffix) {
                    $ids[] = $name . '_' . $suffix;
                }
            }
            return $ids;
        }

        public function testSharedDefinitionsPreserveAllLegacyIdsIncludingGroupFields(): void
        {
            $divi = (new PropertyListingWidget())->get_controls();
            self::assertSame($divi, (new BakeryWidget())->get_controls());
            $fixture = json_decode(file_get_contents(HOMLITY_PLUGIN_PATH . 'tests/Fixtures/property-listing-legacy-controls.json'), true);
            foreach ($fixture as $builder => $ids) {
                self::assertSame([], array_values(array_diff($ids, $this->expandedIds($divi))), $builder);
            }
            foreach (['Elementor', 'Divi', 'WPBakery'] as $builder) {
                $base = HOMLITY_PLUGIN_PATH . 'src/Integrations/' . $builder . '/Widgets/';
                self::assertStringContainsString('Shared\\PropertyListingControlsTrait', file_get_contents($base . 'PropertyListingWidget.php'));
                self::assertStringContainsString('Shared\\PropertyCardStylesTrait', file_get_contents($base . 'PropertyCardStylesTrait.php'));
                self::assertStringContainsString('use PropertyCardStylesTrait;', file_get_contents($base . 'PropertyCardWidget.php'));
            }
            foreach ($divi as $control) {
                foreach (array_keys($control['selectors'] ?? []) as $selector) {
                    self::assertStringStartsWith('{{WRAPPER}}', $selector);
                }
            }
            self::assertSame('layout', $divi['view_toggle_grid_icon']['section']);
            self::assertSame('query', $divi['geo_latitude']['section']);
            self::assertSame(['query_mode' => '__legacy_hidden__'], $divi['preset_tag']['condition']);
            self::assertSame(['query_mode' => '__legacy_hidden__'], $divi['use_current_agent']['condition']);
        }

        private function css(string $builder, array $settings): string
        {
            $reflection = new \ReflectionClass($builder === 'Divi' ? \Homlity_Divi_Widget_Module::class : WPBakeryIntegrationService::class);
            $method = $reflection->getMethod($builder === 'Divi' ? 'buildCss' : 'buildElementCss');
            return $method->invoke($reflection->newInstanceWithoutConstructor(), (new PropertyListingWidget())->get_controls(), $settings, '.test');
        }

        public function testBothAdaptersEmitResponsiveStylesAndViewVariablesWithoutImportant(): void
        {
            foreach (['Divi', 'WPBakery'] as $builder) {
                $css = $this->css($builder, [
                    'columns' => '5', 'columns_tablet' => '3', 'columns_phone' => '2',
                    'grid_column_gap' => ['size' => 32, 'unit' => 'px'],
                    'grid_row_gap' => ['size' => 18, 'unit' => 'px'],
                    'toolbar_margin_bottom' => ['size' => 28, 'unit' => 'px'],
                    'sort_height' => ['size' => 46, 'unit' => 'px'],
                    'sort_focus_border_color' => '#123456',
                    'accent_color' => '#654321', 'empty_color' => '#abcdef',
                    'empty_alignment' => 'left', 'view_toggle_text_color_hover' => '#112233',
                    'view_toggle_icon_color_active' => '#445566',
                    'map_height_tablet' => ['size' => 360, 'unit' => 'px'],
                    'map_shadow_box_shadow' => ['horizontal' => 0, 'vertical' => 2, 'blur' => 6, 'spread' => 0, 'color' => '#000'],
                    'loading_spinner_size' => ['size' => 48, 'unit' => 'px'],
                ]);
                foreach (['--hpl-columns: 5;', '--hpl-columns: 3;', '--hpl-columns: 2;', '--hpl-column-gap: 32px;', '--hpl-row-gap: 18px;', '--hpl-toolbar-margin: 28px;', 'height: 46px;', 'border-color: #123456;', '--homlity-primary-color: #654321;', '--hpl-empty-color: #abcdef;', 'text-align: left;', '--homlity-view-toggle-text-hover: #112233;', '--homlity-view-toggle-icon-active: #445566;', '--hpl-map-height: 360px;', '0px 2px 6px 0px #000', '--hpl-spinner-size: 48px;'] as $expected) {
                    self::assertStringContainsString($expected, $css, $builder);
                }
                self::assertStringNotContainsString('!important', $css);
                self::assertStringNotContainsString('{{', $css);
            }
        }

        public function testBootstrapGroupUtilitiesAndQueryHeadings(): void
        {
            foreach (['Divi', 'WPBakery'] as $builder) {
                $css = $this->css($builder, [
                    'card_border_border_type' => 'solid',
                    'card_border_border_width' => '3',
                    'card_shadow_shadow' => '0 2px 8px #000',
                    'card_title_typography_font_size' => '24',
                    'card_whatsapp_text_align' => 'right',
                ]);
                self::assertStringContainsString('.property-card-bs.border-0{border-style:solid !important;border-width:3px !important;}', $css);
                self::assertStringContainsString('.property-card-bs.shadow-sm{box-shadow:0 2px 8px #000 !important;}', $css);
                self::assertStringContainsString('.card-title.fs-6{font-size:24px !important;}', $css);
                self::assertStringContainsString('text-align: right;', $css);
            }
            $controls = \Homlity\PluginInmobiliario\Integrations\Shared\ListingControlSections::forCompatibilityPanel((new PropertyListingWidget())->get_controls());
            self::assertSame('Consulta · Ubicación', $controls['preset_city']['section_label']);
            self::assertSame('Consulta · Georreferenciación', $controls['geo_radius_km']['section_label']);
        }

        public function testLegacyCardGroupNamesHaveNoNewControlCollisions(): void
        {
            $controls = (new PropertyListingWidget())->get_controls();
            foreach ($controls as $name => $control) {
                if (($control['group_type'] ?? '') === 'border') {
                    foreach (['border', 'width', 'color'] as $suffix) {
                        self::assertArrayNotHasKey($name . '_' . $suffix, $controls);
                    }
                }
            }
            self::assertStringNotContainsString('col-', implode('', $controls['card_width']['selectors']));
            self::assertStringContainsString('max-width', implode('', $controls['card_width']['selectors']));
            foreach ($controls as $name => $control) {
                if (str_starts_with($name, 'card_whatsapp_') && $name !== 'card_whatsapp_margin') {
                    self::assertStringNotContainsString('!important', implode('', $control['selectors'] ?? []));
                }
            }
        }
    }
}
