<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Tests\Support\TestCase;

final class ConsignmentAssetsTest extends TestCase
{
    public function testElConsignadorTieneUnBundlePublicableConTodasSusDependencias(): void
    {
        $dist = HOMLITY_PLUGIN_PATH . 'assets/dist/';
        $manifestPath = $dist . 'index.asset.php';
        $scriptPath = $dist . 'index.js';
        $stylePath = $dist . 'index.css';

        self::assertFileExists($manifestPath);
        self::assertFileExists($scriptPath);
        self::assertFileExists($stylePath);
        self::assertGreaterThan(0, filesize($scriptPath));
        self::assertGreaterThan(0, filesize($stylePath));

        $manifest = require $manifestPath;

        self::assertIsArray($manifest);
        self::assertArrayHasKey('dependencies', $manifest);
        self::assertContains('react-jsx-runtime', $manifest['dependencies']);
        self::assertContains('wp-element', $manifest['dependencies']);

        $bundle = (string) file_get_contents($scriptPath);
        self::assertStringContainsString('ReactJSXRuntime', $bundle);
        self::assertStringContainsString('data-homlity-consignment-root', $bundle);
    }
}
