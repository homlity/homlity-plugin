<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyTitleComposer;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * La gestión del inmueble se integra en la frase del título en lugar de
 * quedar pegada al final ("Apartamento en Chapinero Venta").
 */
final class PropertyTitleComposerTest extends TestCase
{
    /**
     * @return array<string, array{0:string,1:list<string>,2:string}>
     */
    public static function titles(): array
    {
        return [
            'antes de la ubicación'         => ['Apartamento en Chapinero, Bogotá', ['Venta'], 'Apartamento en venta en Chapinero, Bogotá'],
            'con atributos antes de "en"'   => ['Apartamento amoblado en El Poblado', ['Arriendo'], 'Apartamento amoblado en arriendo en El Poblado'],
            'antes de una coma'             => ['Casa, 3 habitaciones en Cali', ['Arriendo'], 'Casa en arriendo, 3 habitaciones en Cali'],
            'antes de un guion'             => ['Apartamento - Chapinero', ['Venta'], 'Apartamento en venta - Chapinero'],
            'antes de una barra vertical'   => ['Local comercial | Centro', ['Arriendo'], 'Local comercial en arriendo | Centro'],
            'al final si no hay conector'   => ['Hermoso apartamento con vista', ['Venta'], 'Hermoso apartamento con vista en venta'],
            'varias gestiones'              => ['Casa en Envigado', ['Venta', 'Arriendo'], 'Casa en venta y arriendo en Envigado'],
            'tres gestiones'                => ['Bodega en Itagüí', ['Venta', 'Arriendo', 'Permuta'], 'Bodega en venta, arriendo y permuta en Itagüí'],
            'título en mayúsculas'          => ['APARTAMENTO EN BELÉN', ['Venta'], 'APARTAMENTO EN VENTA EN BELÉN'],
            'gestión en mayúsculas'         => ['Apartamento en Belén', ['VENTA'], 'Apartamento en venta en Belén'],
            'gestión compuesta'             => ['Apartaestudio en Laureles', ['Arriendo Temporal'], 'Apartaestudio en arriendo temporal en Laureles'],
            'sigla dentro de la gestión'    => ['Apartamento en Bello', ['Venta VIS'], 'Apartamento en venta VIS en Bello'],
            'ya dice venta'                 => ['Venta de casa en Envigado', ['Venta'], 'Venta de casa en Envigado'],
            'ya dice en venta'              => ['Apartamento en venta en Laureles', ['Venta'], 'Apartamento en venta en Laureles'],
            'sinónimo de arriendo'          => ['Se arrienda apartamento en Bello', ['Arriendo'], 'Se arrienda apartamento en Bello'],
            'sinónimo alquiler'             => ['Alquiler de oficina en Medellín', ['Arriendo'], 'Alquiler de oficina en Medellín'],
            'no confunde palabras parciales'=> ['Casa en Ventanas', ['Venta'], 'Casa en venta en Ventanas'],
            'sin gestión'                   => ['Apartamento en Belén', [], 'Apartamento en Belén'],
            'gestiones vacías'              => ['Apartamento en Belén', ['', '  '], 'Apartamento en Belén'],
        ];
    }

    /**
     * @param list<string> $operations
     */
    #[DataProvider('titles')]
    public function testIntegraLaGestionEnElTitulo(string $title, array $operations, string $expected): void
    {
        self::assertSame($expected, PropertyTitleComposer::compose($title, $operations));
    }

    public function testSeparaLaFraseDeGestionParaPoderEstilizarla(): void
    {
        self::assertSame(
            ['Apartamento ', 'en venta', ' en Chapinero'],
            PropertyTitleComposer::split('Apartamento en Chapinero', ['Venta'])
        );
        self::assertSame(['Apartamento en Chapinero', '', ''], PropertyTitleComposer::split('Apartamento en Chapinero', []));
    }
}
