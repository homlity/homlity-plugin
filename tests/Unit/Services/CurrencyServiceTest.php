<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\CurrencyService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class CurrencyServiceTest extends TestCase
{
    private CurrencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CurrencyService();
    }

    public function testRegisterEnganchaLosFiltrosPublicos(): void
    {
        $this->service->register();

        self::assertArrayHasKey('homlity_plugin_supported_currencies', WpStubs::$filters);
        self::assertArrayHasKey('homlity_plugin_format_price', WpStubs::$filters);
        self::assertArrayHasKey('homlity_plugin_convert_price', WpStubs::$filters);
    }

    public function testSupportedCurrenciesDevuelveLaListaPorDefecto(): void
    {
        $currencies = $this->service->supportedCurrencies();

        self::assertContains('COP', $currencies);
        self::assertContains('USD', $currencies);
        self::assertSame($currencies, array_values(array_unique($currencies)));
    }

    public function testSupportedCurrenciesNormalizaYDeduplicaLaListaRecibida(): void
    {
        self::assertSame(['USD', 'COP'], $this->service->supportedCurrencies(['usd', 'USD', 'cop', '']));
    }

    public function testBaseCurrencyUsaCopCuandoNoHayAjustes(): void
    {
        self::assertSame(CurrencyService::DEFAULT_CURRENCY, $this->service->baseCurrency());
    }

    public function testBaseCurrencyLeeLaMonedaConfigurada(): void
    {
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['base_currency' => 'usd']);

        self::assertSame('USD', $this->service->baseCurrency());
    }

    public function testBaseCurrencyRechazaValoresQueNoParecenIso(): void
    {
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['base_currency' => 'dolares gringos']);

        self::assertSame(CurrencyService::DEFAULT_CURRENCY, $this->service->baseCurrency());
    }

    public function testBaseCurrencyAceptaMonedasDeclaradasPorFiltro(): void
    {
        WpStubs::addFilter('homlity_plugin_supported_currencies', static fn ($currencies): array => ['COP', 'UF']);
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['base_currency' => 'UF']);

        self::assertSame('UF', $this->service->baseCurrency());
    }

    public function testConvertPriceDevuelveElMismoValorEntreMonedasIguales(): void
    {
        self::assertSame(1500.0, $this->service->convertPrice(1500, 'USD', 'USD'));
    }

    public function testConvertPriceAplicaLaTasaPorDefecto(): void
    {
        self::assertSame(380000.0, $this->service->convertPrice(100, 'USD', 'COP'));
        self::assertEqualsWithDelta(1.0, $this->service->convertPrice(3800, 'COP', 'USD'), 0.0001);
    }

    public function testConvertPriceUsaLasTasasInyectadasPorFiltro(): void
    {
        WpStubs::addFilter('homlity_plugin_exchange_rates', static fn (): array => ['USD' => 1.0, 'COP' => 4000.0]);

        self::assertSame(400000.0, $this->service->convertPrice(100, 'USD', 'COP'));
    }

    public function testConvertPriceDevuelveElPrecioOriginalSiFaltaLaTasa(): void
    {
        self::assertSame(250.0, $this->service->convertPrice(250, 'XYZ', 'COP'));
        self::assertSame(250.0, $this->service->convertPrice(250, 'COP', 'XYZ'));
    }

    public function testConvertPriceSinArgumentosUsaLaMonedaBase(): void
    {
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['base_currency' => 'COP']);

        self::assertSame(999.0, $this->service->convertPrice('999'));
    }

    public function testFormatPriceIncluyeElImporteFormateado(): void
    {
        $formatted = $this->service->formatPrice(1500000, 'COP', 'es_CO');

        self::assertMatchesRegularExpression('/1[.,\x{00A0}\x{202F} ]?500[.,\x{00A0}\x{202F} ]?000/u', $formatted);
        // Los precios se muestran sin decimales.
        self::assertSame('1500000', preg_replace('/\D/', '', $formatted));
    }

    public function testFormatPriceUsaLaMonedaBaseCuandoNoSeIndica(): void
    {
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['base_currency' => 'USD']);

        self::assertNotSame('', $this->service->formatPrice(100, null, 'en_US'));
    }
}
