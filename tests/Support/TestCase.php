<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Support;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Caso base: garantiza que cada prueba arranque con el estado de WordPress
 * simulado en blanco (opciones, metadatos, filtros, etc.).
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WpStubs::reset();
        $GLOBALS['wpdb'] = new \HomlityTestWpdb();
    }

    protected function tearDown(): void
    {
        WpStubs::reset();
        parent::tearDown();
    }
}
