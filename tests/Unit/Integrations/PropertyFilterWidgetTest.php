<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations;

use Homlity\PluginInmobiliario\Listing\FilterPlaceholders;
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
                'FilterPlaceholders::definitions()',
                $source,
                sprintf('Faltan los controles de placeholders en %s.', $integration)
            );
        }
    }

    public function testCadaCampoTieneSuControlDePlaceholder(): void
    {
        $toggles = array_column(FilterPlaceholders::definitions(), 'toggle');

        foreach (['show_keyword', 'show_category', 'show_operation', 'show_type', 'show_tag', 'show_country', 'show_state', 'show_city', 'show_locality', 'show_neighborhood', 'show_nearby', 'show_price', 'show_area', 'show_bedrooms', 'show_bathrooms', 'show_parking'] as $toggle) {
            self::assertContains($toggle, $toggles, sprintf('Falta placeholder para %s.', $toggle));
        }
    }

    public function testLosPlaceholdersDeRangosSePuedenConfigurar(): void
    {
        $html = $this->render($this->keywordOnlySettings([
            'show_price' => 'yes',
            'show_area' => 'yes',
            'placeholder_price_min' => 'Desde $',
            'placeholder_price_max' => 'Hasta $',
            'placeholder_area_min' => 'm² desde',
        ]));

        self::assertStringContainsString('placeholder="Desde $"', $html);
        self::assertStringContainsString('placeholder="Hasta $"', $html);
        self::assertStringContainsString('placeholder="m² desde"', $html);
        self::assertStringContainsString('placeholder="Área máx."', $html);
    }

    public function testElPrimerOptionDeLosSelectsSePuedeConfigurar(): void
    {
        $html = $this->render($this->keywordOnlySettings([
            'show_bedrooms' => 'yes',
            'show_bathrooms' => 'yes',
            'placeholder_bedrooms' => 'Alcobas',
            'placeholder_bathrooms' => '   ',
        ]));

        self::assertStringContainsString('<option value="">Alcobas</option>', $html);
        self::assertStringContainsString('<option value="">Baños</option>', $html);
    }

    public function testElBuscadorDeLasListasSePuedeConfigurar(): void
    {
        $html = $this->render($this->keywordOnlySettings([
            'placeholder_options_search' => 'Escribe para filtrar',
        ]));

        self::assertStringContainsString('data-search-placeholder="Escribe para filtrar"', $html);
    }

    public function testLabelModeAndDefaultClasses(): void
    {
        $default = $this->render($this->keywordOnlySettings());
        self::assertStringNotContainsString('<label', $default);
        self::assertStringContainsString('property-listing property-filter-widget property-filter-widget--labels-hidden', $default);
        self::assertStringContainsString('class="property-listing__filters"', $default);
        self::assertStringContainsString('class="property-listing__filter-input"', $default);
        foreach (['label', 'outside'] as $mode) {
            $html = $this->render($this->keywordOnlySettings(['field_label_mode' => $mode, 'show_price' => 'yes', 'show_area' => 'yes']));
            self::assertStringContainsString('<label class="property-listing__filter-label" for="homlity-filter-s">Buscar</label>', $html);
            self::assertStringNotContainsString('property-filter-widget--labels-hidden', $html);
            self::assertStringContainsString('aria-label="Precio mínimo"', $html);
            self::assertStringContainsString('aria-label="Área máxima (m²)"', $html);
        }
        self::assertStringNotContainsString('<label', $this->render($this->keywordOnlySettings(['field_label_mode' => 'placeholder'])));
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
