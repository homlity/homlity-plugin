<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Listing;

use Homlity\PluginInmobiliario\Listing\PaginationWindow;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

final class PaginationWindowTest extends TestCase
{
    public function testDevuelveTodasLasPaginasCuandoCabenEnLaVentana(): void
    {
        self::assertSame([1, 2, 3], PaginationWindow::items(1, 3));
    }

    public function testAlInicioMuestraLaVentanaCompletaYUnaElipsisFinal(): void
    {
        self::assertSame([1, 2, 3, 4, 5, 'ellipsis'], PaginationWindow::items(1, 10));
    }

    public function testEnElCentroMuestraElipsisAAmbosLados(): void
    {
        self::assertSame(['ellipsis', 3, 4, 5, 6, 7, 'ellipsis'], PaginationWindow::items(5, 10));
    }

    public function testAlFinalMuestraLaVentanaCompletaYUnaElipsisInicial(): void
    {
        self::assertSame(['ellipsis', 6, 7, 8, 9, 10], PaginationWindow::items(10, 10));
    }

    public function testRespetaUnRadioPersonalizado(): void
    {
        self::assertSame(['ellipsis', 4, 5, 6, 'ellipsis'], PaginationWindow::items(5, 10, 1));
    }

    /**
     * @dataProvider valoresFueraDeRango
     * @param array<int,int|string> $esperado
     */
    public function testNormalizaValoresFueraDeRango(int $pagina, int $total, array $esperado): void
    {
        self::assertSame($esperado, PaginationWindow::items($pagina, $total));
    }

    /** @return array<string,array{0:int,1:int,2:array<int,int|string>}> */
    public static function valoresFueraDeRango(): array
    {
        return [
            'sin resultados'      => [0, 0, [1]],
            'pagina negativa'     => [-5, 3, [1, 2, 3]],
            'pagina mayor a total' => [99, 3, [1, 2, 3]],
        ];
    }

    public function testUnRadioInvalidoSeAjustaAlMinimo(): void
    {
        self::assertSame(PaginationWindow::items(5, 10, 1), PaginationWindow::items(5, 10, 0));
    }
}
