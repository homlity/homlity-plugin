<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Integrations\Divi\Widgets {
    function get_pages(array $args): array { return []; }
}
namespace Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets {
    function get_pages(array $args): array { return []; }
}
namespace {
    if (!class_exists('ET_Builder_Module')) {
        class ET_Builder_Module {}
    }
}
namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations {
    use Homlity\PluginInmobiliario\Integrations\Divi\Widgets\PropertyFilterWidget;
    use Homlity\PluginInmobiliario\Integrations\WPBakery\Widgets\PropertyFilterWidget as BakeryWidget;
    use Homlity\PluginInmobiliario\Integrations\WPBakery\WPBakeryIntegrationService;
    use Homlity\PluginInmobiliario\Integrations\Shared\PropertyFilterStylesTrait;
    use Homlity\PluginInmobiliario\Tests\Support\TestCase;

    final class PropertyFilterStylesTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            require_once HOMLITY_PLUGIN_PATH . 'src/Integrations/Divi/Compatibility/DiviWidgetApi.php';
            require_once HOMLITY_PLUGIN_PATH . 'src/Integrations/WPBakery/Compatibility/WPBakeryWidgetApi.php';
            require_once HOMLITY_PLUGIN_PATH . 'src/Integrations/Divi/Modules/WidgetModule.php';
        }

        public function testSharedControlsAndAllLegacyIds(): void
        {
            $divi = (new PropertyFilterWidget())->get_controls();
            $bakery = (new BakeryWidget())->get_controls();
            self::assertSame(
                array_filter($divi, static fn (array $control): bool => ($control['tab'] ?? '') === 'style' && ($control['name'] ?? '') !== 'form_background'),
                array_filter($bakery, static fn (array $control): bool => ($control['tab'] ?? '') === 'style' && ($control['name'] ?? '') !== 'form_background')
            );
            $ids = json_decode((string) file_get_contents(HOMLITY_PLUGIN_PATH . 'tests/Fixtures/property-filter-legacy-controls.json'), true);
            foreach (['Divi' => $divi, 'WPBakery' => $bakery] as $builder => $controls) {
                foreach ($ids[$builder] as $id) {
                    self::assertArrayHasKey($id, $controls);
                }
            }
            foreach (['Elementor', 'Divi', 'WPBakery'] as $builder) {
                $source = (string) file_get_contents(HOMLITY_PLUGIN_PATH . 'src/Integrations/' . $builder . '/Widgets/PropertyFilterWidget.php');
                self::assertStringContainsString('use \\' . PropertyFilterStylesTrait::class . ';', $source);
                self::assertSame(1, substr_count($source, '$this->registerFilterStyleControls();'));
                self::assertStringNotContainsString("start_controls_section('style_", $source);
            }
            foreach ($divi as $control) {
                foreach (array_merge(array_keys($control['selectors'] ?? []), isset($control['selector']) ? [$control['selector']] : []) as $selector) {
                    foreach (explode(', ', $selector) as $part) {
                        self::assertStringStartsWith('{{WRAPPER}} .property-filter-widget', $part);
                    }
                }
                self::assertStringNotContainsString('!important', implode('', $control['selectors'] ?? []));
            }
        }

        public function testElementorGroupFieldsDoNotCollideWithStandaloneControls(): void
        {
            $controls = (new PropertyFilterWidget())->get_controls();
            foreach ($controls as $name => $control) {
                if (($control['group_type'] ?? '') === 'border') {
                    foreach (['border', 'width', 'color'] as $suffix) {
                        self::assertArrayNotHasKey($name . '_' . $suffix, $controls);
                    }
                }
            }
        }

        private function css(string $builder, array $settings): string
        {
            $class = $builder === 'Divi' ? \Homlity_Divi_Widget_Module::class : WPBakeryIntegrationService::class;
            $reflection = new \ReflectionClass($class);
            $method = $reflection->getMethod($builder === 'Divi' ? 'buildCss' : 'buildElementCss');
            return $method->invoke($reflection->newInstanceWithoutConstructor(), (new PropertyFilterWidget())->get_controls(), $settings, '.test');
        }

        public function testBothAdaptersGenerateFieldAndMenuCss(): void
        {
            foreach (['Divi', 'WPBakery'] as $builder) {
                $css = $this->css($builder, [
                    'field_height' => ['size' => 54, 'unit' => 'px'],
                    'button_height' => ['size' => 50, 'unit' => 'px'],
                    'accent_color' => '#123456',
                    'field_placeholder_color' => '#abcdef',
                    'field_focus_border_color' => '#654321',
                    'multi_menu_max_height' => ['size' => 280, 'unit' => 'px'],
                    'multi_menu_border_width' => ['size' => 2, 'unit' => 'px'],
                    'multi_search_bg_color' => '#eeeeee',
                ]);
                foreach (['--hpf-field-height: 54px;', '--hpf-button-height: 50px;', '--homlity-primary-color: #123456;', '::placeholder{color: #abcdef;}', '.hpf-multi:focus-within .hpf-multi__trigger', 'border-color: #654321;', '.hpf-multi__menu{border-width: 2px;max-height: 280px;}', '.hpf-multi__search{background-color: #eeeeee;}'] as $expected) {
                    self::assertStringContainsString($expected, $css, $builder);
                }
                self::assertStringNotContainsString('!important', $css);
                self::assertStringNotContainsString('{{', $css);
            }
        }

        public function testResponsiveOnlyValuesAndLayout(): void
        {
            foreach (['Divi', 'WPBakery'] as $builder) {
                $css = $this->css($builder, [
                    'form_distribution_tablet' => 'columns',
                    'form_columns_tablet' => ['size' => 2, 'unit' => 'px'],
                    'field_height_phone' => ['size' => 36, 'unit' => 'px'],
                    'actions_full_row_phone' => 'full',
                ]);
                self::assertStringContainsString('@media(max-width:980px)', $css);
                self::assertStringContainsString('--hpf-display:grid;', $css);
                self::assertStringContainsString('--hpf-columns: 2;', $css);
                self::assertStringContainsString('@media(max-width:767px)', $css);
                self::assertStringContainsString('--hpf-field-height: 36px;', $css);
                self::assertStringContainsString('--hpf-actions-column:1 / -1;', $css);
                $vertical = $this->css($builder, ['form_layout' => 'vertical', 'form_distribution' => 'columns']);
                self::assertStringContainsString('display:flex;flex-direction:column;', $vertical);
                self::assertStringContainsString('min-width:100%;flex:1 1 100%;', $vertical);
            }
        }

        public function testShadowsSupportNativeValuesAndElementorArrays(): void
        {
            foreach (['Divi', 'WPBakery'] as $builder) {
                $css = $this->css($builder, [
                    'form_shadow_shadow' => '0 2px 8px #123456',
                    'field_focus_shadow_box_shadow' => ['horizontal' => 0, 'vertical' => 0, 'blur' => 3, 'spread' => 1, 'color' => '#abcdef'],
                    'multi_menu_shadow_disabled' => 'yes',
                ]);
                self::assertStringContainsString('box-shadow:0 2px 8px #123456;', $css);
                self::assertStringContainsString('box-shadow:0px 0px 3px 1px #abcdef;', $css);
                self::assertStringContainsString('box-shadow:none;', $css);
            }
            foreach (['Divi', 'WPBakery'] as $builder) {
                self::assertStringContainsString('box-shadow:0px 2px 6px 0px #123456;', $this->css($builder, [
                    'form_shadow_shadow_horizontal' => '0',
                    'form_shadow_shadow_vertical' => '2',
                    'form_shadow_shadow_blur' => '6',
                    'form_shadow_shadow_color' => '#123456',
                ]));
            }
        }

        public function testGradientSupportAndDiviShadowFields(): void
        {
            $divi = (new PropertyFilterWidget())->get_controls();
            $bakery = (new BakeryWidget())->get_controls();
            self::assertSame(['classic'], $divi['form_background']['types']);
            self::assertSame(['classic', 'gradient'], $bakery['form_background']['types']);
            $css = $this->css('WPBakery', [
                'form_background_background_gradient_color' => '#123456',
                'form_background_background_gradient_color_b' => '#abcdef',
                'form_background_background_gradient_angle' => '90',
            ]);
            self::assertStringContainsString('linear-gradient(90deg,#123456,#abcdef)', $css);
            $reflection = new \ReflectionClass(\Homlity_Divi_Widget_Module::class);
            $fields = $reflection->getMethod('groupControlFields')->invoke(
                $reflection->newInstanceWithoutConstructor(), 'form_shadow', $divi['form_shadow']
            );
            self::assertSame('color-alpha', $fields['form_shadow_shadow_color']['type']);
            self::assertArrayHasKey('form_shadow_shadow_blur', $fields);
            self::assertSame('select', $fields['form_shadow_shadow_position']['type']);
            self::assertArrayNotHasKey('form_shadow_shadow', $fields);
        }

        public function testMobileConditionsAndUnconfiguredDefaults(): void
        {
            $controls = (new PropertyFilterWidget())->get_controls();
            foreach ($controls as $control) {
                if ($control['section'] === 'style_mobile_panel') {
                    self::assertSame(['mobile_sidebar_enabled' => 'yes'], $control['condition']);
                }
            }
            foreach (['Divi', 'WPBakery'] as $builder) {
                $css = $this->css($builder, []);
                self::assertStringNotContainsString('height:', $css);
                self::assertStringNotContainsString('background', $css);
                self::assertStringNotContainsString('box-shadow', $css);
                $settings = ['mobile_panel_bg_color' => '#123456', 'mobile_sidebar_enabled' => ''];
                self::assertStringNotContainsString('--hpf-panel-bg', $this->css($builder, $settings));
                $settings['mobile_sidebar_enabled'] = 'yes';
                self::assertStringContainsString('--hpf-panel-bg: #123456;', $this->css($builder, $settings));
            }
        }
    }
}
