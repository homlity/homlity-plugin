<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Listing;

use Homlity\PluginInmobiliario\Listing\HeroSliderConfig;
use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/** Price rendering through the same card path used for AJAX pagination. */
final class CardPriceTemplateTest extends TestCase
{
    private const POST_ID = 501;

    protected function setUp(): void
    {
        parent::setUp();
        WpStubs::setPost(self::POST_ID, 'Apartamento', 'https://example.test/property/501', [
            '_property_price_sale' => '900000',
            '_property_currency_sale' => 'USD',
            '_property_price_rent' => '2500',
            '_property_currency_rent' => 'CRC',
            '_property_price_admin' => '100',
            '_property_currency_admin' => 'EUR',
        ]);
        $this->operation('arriendo-venta');
        WpStubs::addFilter('homlity_plugin_format_price', static fn ($amount, $currency): string => $currency . ' ' . $amount);
    }

    private function operation(string $slug): void
    {
        WpStubs::$postTerms[self::POST_ID][PropertyTaxonomies::TAXONOMY_OPERATION] = [
            WpStubs::setTerm(77, PropertyTaxonomies::TAXONOMY_OPERATION, $slug, $slug),
        ];
    }

    private function render(string $template, string $preset, bool $showPrice = true): string
    {
        $query = new \WP_Query();
        $query->posts = [self::POST_ID];
        $query->post_count = 1;

        if ($template === 'hero') {
            ob_start();
            try {
                TemplateService::includeComponent('property-hero-slider.php', [
                    'query' => $query,
                    'options' => HeroSliderConfig::fromElementor(['show_price' => $showPrice ? 'yes' : ''])->templateOptions(),
                ]);
            } finally {
                $html = (string) ob_get_clean();
            }
            return $html;
        }

        return (new ListingRenderer())->renderCards($query, ListingConfig::fromArray([
            'template' => $template,
            'card_visual_preset' => $preset,
            'card_show_price' => $showPrice,
            'card_show_whatsapp' => false,
        ]));
    }

    public static function templates(): array
    {
        return [
            'default' => ['default', 'default', 'property-card'],
            'default overlay' => ['default', 'cover_overlay', 'property-card'],
            'bootstrap' => ['bootstrap', 'default', 'property-card'],
            'bootstrap overlay' => ['bootstrap', 'cover_overlay', 'property-card'],
            'hero' => ['hero', 'default', 'hml-hero-slider'],
        ];
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        return new \DOMXPath($dom);
    }

    /** @dataProvider templates */
    public function testMuestraAmbosPreciosYAdministracionSoloEnArriendo(string $template, string $preset, string $prefix): void
    {
        $html = $this->render($template, $preset);
        $xpath = $this->xpath($html);
        $lines = $xpath->query('//span[contains(@class, "' . $prefix . '__price-line--")]');

        self::assertCount(2, $lines);
        self::assertStringContainsString($prefix . '__price--multi', $html);
        self::assertStringContainsString('Arriendo', $lines[0]->textContent);
        self::assertStringContainsString('CRC 2500', $lines[0]->textContent);
        self::assertStringContainsString('EUR 100', $lines[0]->textContent);
        self::assertStringContainsString('Venta', $lines[1]->textContent);
        self::assertStringContainsString('USD 900000', $lines[1]->textContent);
        self::assertSame(0, $xpath->query('.//small', $lines[1])->length);
        if ($template === 'bootstrap') {
            self::assertStringContainsString('fs-6 fw-normal text-muted', $html);
            self::assertStringContainsString('itemprop="price"', $html);
            if ($preset === 'default') {
                self::assertStringContainsString('fw-bold fs-5 text-primary', $html);
            }
        }
        if ($preset === 'cover_overlay') {
            self::assertStringContainsString('property-card__overlay-price', $html);
        }
    }

    /** @dataProvider templates */
    public function testAdministracionIncluidaNoMuestraUnRecargo(string $template, string $preset, string $prefix): void
    {
        WpStubs::setPostMeta(self::POST_ID, ['_property_admin_included' => '1']);
        $html = $this->render($template, $preset);

        self::assertStringContainsString('adm. incluida', $html);
        self::assertStringNotContainsString('EUR 100', $html);
        $small = $this->xpath($html)->query('//small[contains(@class, "' . $prefix . '__price-admin")]');
        self::assertCount(1, $small);
        self::assertStringNotContainsString('+', $small[0]->textContent);
    }

    /** @dataProvider templates */
    public function testOperacionUnicaConservaPrecioSinEtiqueta(string $template, string $preset, string $prefix): void
    {
        $this->operation('venta');
        $html = $this->render($template, $preset);
        self::assertStringContainsString('USD 900000', $html);
        self::assertStringNotContainsString('CRC 2500', $html);
        self::assertStringNotContainsString('__price--multi', $html);
        self::assertStringNotContainsString('__price-label', $html);
        self::assertStringNotContainsString('__price-admin', $html);

        $this->operation('arriendo');
        $html = $this->render($template, $preset);
        self::assertStringContainsString('CRC 2500', $html);
        self::assertStringContainsString('EUR 100', $html);
        self::assertStringNotContainsString('USD 900000', $html);
        self::assertStringNotContainsString('__price--multi', $html);
        self::assertStringNotContainsString('__price-label', $html);
    }

    /** @dataProvider templates */
    public function testPrecioOcultoNoLlamaAlResolver(string $template, string $preset, string $prefix): void
    {
        WpStubs::addFilter('homlity_plugin_card_price_lines', static function (): array {
            self::fail('El precio oculto no debe resolverse.');
        });

        self::assertStringNotContainsString('CRC 2500', $this->render($template, $preset, false));
    }

    /** @dataProvider templates */
    public function testEscapaEtiquetasMontosYAdministracion(string $template, string $preset, string $prefix): void
    {
        WpStubs::addFilter('homlity_plugin_card_price_lines', static function (array $lines): array {
            $lines[0]['label'] = '<b>Arriendo</b>';
            $lines[0]['amount'] = '<script>amount</script>';
            $lines[0]['admin'] = '<img src=x onerror=alert(1)>';
            return $lines;
        });
        $html = $this->render($template, $preset);

        self::assertStringContainsString('&lt;b&gt;Arriendo&lt;/b&gt;', $html);
        self::assertStringContainsString('&lt;script&gt;amount&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringNotContainsString('<script>amount</script>', $html);
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
    }
}
