<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

final class TemplateServiceBrandColorTest extends TestCase
{
    public function testElCssPublicaLaVariableGlobalDeColorDeMarca(): void
    {
        $method = new \ReflectionMethod(TemplateService::class, 'buildPrimaryColorCss');
        $method->setAccessible(true);

        $css = (string) $method->invoke(new TemplateService(), '#123456');

        self::assertStringContainsString('--homlity-site-primary-color:#123456', $css);
        self::assertStringContainsString('--homlity-site-primary-rgb:18,52,86', $css);
    }
}
