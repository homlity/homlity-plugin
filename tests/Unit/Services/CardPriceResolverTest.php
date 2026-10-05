<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\CardPriceResolver;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class CardPriceResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WpStubs::addFilter('homlity_plugin_format_price', static fn ($amount, $currency): string => $currency . ' ' . $amount);
    }

    private function prices(array $overrides = []): array
    {
        return array_merge([
            'price_sale' => '900000',
            'currency_sale' => 'USD',
            'price_rent' => '2500',
            'currency_rent' => 'CRC',
            'price_admin' => '100',
            'currency_admin' => 'EUR',
            'admin_included' => false,
        ], $overrides);
    }

    public function testArriendoVentaPoneElArriendoPrimeroYAdministracionSoloEnArriendo(): void
    {
        self::assertSame([
            ['type' => 'rent', 'label' => 'Arriendo', 'amount' => 'CRC 2500', 'admin' => 'EUR 100', 'admin_included' => false],
            ['type' => 'sale', 'label' => 'Venta', 'amount' => 'USD 900000', 'admin' => null, 'admin_included' => false],
        ], CardPriceResolver::resolve($this->prices(), 3, ''));
    }

    public function testArriendoVentaConSoloVentaDevuelveUnaLinea(): void
    {
        $lines = CardPriceResolver::resolve($this->prices(['price_rent' => '']), 3, '');

        self::assertSame(['sale'], array_column($lines, 'type'));
        self::assertNull($lines[0]['admin']);
    }

    /** @dataProvider operations */
    public function testRespetaLaOperacionAunqueQuedenMetadatosDeLaOtra(int $id, string $text, array $types): void
    {
        self::assertSame($types, array_column(CardPriceResolver::resolve($this->prices(), $id, $text), 'type'));
    }

    public static function operations(): array
    {
        return [
            'arriendo' => [1, '', ['rent']],
            'venta' => [2, '', ['sale']],
            'desconocida' => [0, 'permuta', ['rent', 'sale']],
            'slug arriendo' => [0, 'arriendo Alquiler', ['rent']],
            'nombre venta' => [0, 'custom VENTA', ['sale']],
            'slug y nombre mixto' => [0, 'arriendo-venta Arriendo/Venta', ['rent', 'sale']],
            'base y nombre se combinan' => [1, 'venta', ['rent', 'sale']],
        ];
    }

    public function testAdministracionIncluidaSeMarcaSoloEnArriendo(): void
    {
        $lines = CardPriceResolver::resolve($this->prices(['admin_included' => '1']), 3, '');

        self::assertTrue($lines[0]['admin_included']);
        self::assertSame('EUR 100', $lines[0]['admin']);
        self::assertFalse($lines[1]['admin_included']);
        self::assertNull($lines[1]['admin']);
    }

    /** @dataProvider currencies */
    public function testAdministracionUsaSuMonedaLuegoArriendoLuegoBase(string $admin, string $rent, string $expected): void
    {
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['base_currency' => 'GBP']);
        $lines = CardPriceResolver::resolve($this->prices(['currency_admin' => $admin, 'currency_rent' => $rent]), 1, '');

        self::assertSame($expected . ' 100', $lines[0]['admin']);
        self::assertSame(($rent ?: 'GBP') . ' 2500', $lines[0]['amount']);
    }

    public static function currencies(): array
    {
        return ['admin' => ['EUR', 'CRC', 'EUR'], 'arriendo' => ['', 'CRC', 'CRC'], 'base' => ['', '', 'GBP']];
    }

    /** @dataProvider emptyPrices */
    public function testOmiteLosPreciosVaciosCeroYNegativos($value): void
    {
        self::assertSame([], CardPriceResolver::resolve($this->prices(['price_sale' => $value, 'price_rent' => $value]), 3, ''));
        $lines = CardPriceResolver::resolve($this->prices(['price_admin' => $value]), 1, '');
        self::assertNull($lines[0]['admin']);
    }

    public static function emptyPrices(): array
    {
        return ['vacio' => [''], 'null' => [null], 'cero' => [0], 'cero texto' => ['0'], 'negativo' => [-5]];
    }

    public function testDesconocidaOmiteSoloElPrecioSinValor(): void
    {
        self::assertSame(['rent'], array_column(CardPriceResolver::resolve($this->prices(['price_sale' => '0']), 0, ''), 'type'));
        self::assertSame([], CardPriceResolver::resolve([], 0, ''));
    }

    private function givenPost(string $slug = 'arriendo-venta', string $name = 'Arriendo/Venta'): \WP_Term
    {
        $meta = (new PropertyPostType())->metaKeys();
        foreach ($this->prices() as $key => $value) {
            update_post_meta(501, $meta[$key], $value);
        }
        $term = WpStubs::setTerm(77, PropertyTaxonomies::TAXONOMY_OPERATION, $slug, $name);
        WpStubs::$postTerms[501][PropertyTaxonomies::TAXONOMY_OPERATION] = [$term];
        return $term;
    }

    public function testForPostLeeLasClavesCanonicasYElTermino(): void
    {
        $this->givenPost();

        self::assertSame(CardPriceResolver::resolve($this->prices(), 3, 'arriendo-venta Arriendo/Venta'), CardPriceResolver::forPost(501));
    }

    public function testForPostUsaLaIdentidadDeUnaOperacionRenombrada(): void
    {
        $term = $this->givenPost('alquiler', 'Alquiler');
        update_term_meta($term->term_id, '_homlity_base_operation_id', 1);
        update_term_meta($term->term_id, '_homlity_base_operation_key', 'rent');

        self::assertSame(['rent'], array_column(CardPriceResolver::forPost(501), 'type'));
    }

    public function testForPostToleraTerminosAusentesYErrores(): void
    {
        $this->givenPost();
        WpStubs::$postTerms[501] = [];
        self::assertCount(2, CardPriceResolver::forPost(501));
        WpStubs::$postTermsError[PropertyTaxonomies::TAXONOMY_OPERATION] = 'Invalid taxonomy';
        self::assertCount(2, CardPriceResolver::forPost(501));
    }

    public function testForPostAplicaElFiltroConElIdDelInmueble(): void
    {
        $this->givenPost();
        WpStubs::addFilter('homlity_plugin_card_price_lines', static function (array $lines, int $postId): array {
            self::assertSame(501, $postId);
            self::assertSame(['rent', 'sale'], array_column($lines, 'type'));
            $lines[0]['label'] = 'Alquiler';
            return $lines;
        });

        self::assertSame('Alquiler', CardPriceResolver::forPost(501)[0]['label']);
    }
}
