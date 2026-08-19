<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations\CRM\DTO;

use Homlity\PluginInmobiliario\Integrations\CRM\DTO\NormalizedProperty;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

final class NormalizedPropertyTest extends TestCase
{
    public function testConservaElPayloadOriginal(): void
    {
        $data = ['source_key' => 'wasi', 'external_id' => 'A-1', 'extra' => ['a' => 1]];

        self::assertSame($data, (new NormalizedProperty($data))->toArray());
    }

    public function testNormalizaElSourceKey(): void
    {
        $property = new NormalizedProperty(['source_key' => 'Web Homlity!']);

        self::assertSame('webhomlity', $property->sourceKey());
    }

    public function testSaneaElIdYElHashExternos(): void
    {
        $property = new NormalizedProperty([
            'external_id'   => "  A-1<script>  ",
            'external_hash' => " 9f86d0\n",
        ]);

        self::assertSame('A-1', $property->externalId());
        self::assertSame('9f86d0', $property->externalHash());
    }

    public function testDevuelveCadenasVaciasCuandoFaltanLosCampos(): void
    {
        $property = new NormalizedProperty([]);

        self::assertSame('', $property->sourceKey());
        self::assertSame('', $property->externalId());
        self::assertSame('', $property->externalHash());
    }

    public function testConvierteValoresNoTextualesACadena(): void
    {
        $property = new NormalizedProperty(['external_id' => 12345]);

        self::assertSame('12345', $property->externalId());
    }
}
