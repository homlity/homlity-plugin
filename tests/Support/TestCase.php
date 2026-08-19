<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Support;

use Homlity\PluginInmobiliario\Services\AgentProfileService;
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
        // El asesor de la petición se memoiza para toda la vida del proceso;
        // sin esto una prueba heredaría el asesor resuelto por la anterior.
        AgentProfileService::resetRequestCache();
        $GLOBALS['wpdb'] = new \HomlityTestWpdb();
    }

    protected function tearDown(): void
    {
        WpStubs::reset();
        AgentProfileService::resetRequestCache();
        parent::tearDown();
    }
}
