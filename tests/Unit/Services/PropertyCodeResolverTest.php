<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyCodeResolver;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class PropertyCodeResolverTest extends TestCase
{
    public function testPrefiereElCodigoPublicoDeSimi(): void
    {
        WpStubs::setPostMeta(10, [
            '_simi_sync_code' => 'SIMI-100',
            '_simi_sync_id'   => '55512',
            '_property_code'  => 'INT-9',
        ]);

        self::assertSame('SIMI-100', PropertyCodeResolver::forDisplay(10));
    }

    public function testUsaElIdDeSimiCuandoNoHayCodigoPublico(): void
    {
        WpStubs::setPostMeta(10, [
            '_simi_sync_code' => '   ',
            '_simi_sync_id'   => '55512',
            '_property_code'  => 'INT-9',
        ]);

        self::assertSame('55512', PropertyCodeResolver::forDisplay(10));
    }

    public function testCaeAlCodigoCanonicoDelPlugin(): void
    {
        WpStubs::setPostMeta(10, ['_property_code' => ' INT-9 ']);

        self::assertSame('INT-9', PropertyCodeResolver::forDisplay(10));
    }

    public function testDevuelveCadenaVaciaSinMetadatos(): void
    {
        self::assertSame('', PropertyCodeResolver::forDisplay(10));
    }

    /** @dataProvider idsInvalidos */
    public function testDevuelveCadenaVaciaConIdInvalido(int $postId): void
    {
        WpStubs::setPostMeta($postId, ['_property_code' => 'INT-9']);

        self::assertSame('', PropertyCodeResolver::forDisplay($postId));
    }

    /** @return array<string,array{0:int}> */
    public static function idsInvalidos(): array
    {
        return ['cero' => [0], 'negativo' => [-3]];
    }
}
