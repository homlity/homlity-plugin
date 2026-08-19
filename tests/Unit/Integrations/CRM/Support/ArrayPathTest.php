<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations\CRM\Support;

use Homlity\PluginInmobiliario\Integrations\CRM\Support\ArrayPath;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

final class ArrayPathTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $payload = [
        'external' => ['id' => 'ABC-1', 'source' => 'wasi'],
        'location' => ['coords' => ['lat' => 4.65, 'lng' => -74.05]],
        'media'    => [],
        'nulo'     => null,
    ];

    public function testDevuelveUnValorDePrimerNivel(): void
    {
        self::assertSame($this->payload['external'], ArrayPath::get($this->payload, 'external'));
    }

    public function testDevuelveUnValorAnidado(): void
    {
        self::assertSame(4.65, ArrayPath::get($this->payload, 'location.coords.lat'));
    }

    public function testDevuelveNullCuandoElSegmentoNoExiste(): void
    {
        self::assertNull(ArrayPath::get($this->payload, 'location.coords.altitude'));
    }

    public function testDevuelveNullCuandoLaRutaAtraviesaUnEscalar(): void
    {
        self::assertNull(ArrayPath::get($this->payload, 'external.id.demasiado.profundo'));
    }

    public function testDistingueUnValorNullDeUnaRutaInexistente(): void
    {
        self::assertNull(ArrayPath::get($this->payload, 'nulo'));
        self::assertSame([], ArrayPath::get($this->payload, 'media'));
    }

    public function testDevuelveNullConArregloVacio(): void
    {
        self::assertNull(ArrayPath::get([], 'cualquiera'));
    }
}
