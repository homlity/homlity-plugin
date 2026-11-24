<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Listing;

use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class ListingRendererAssetsTest extends TestCase
{
    public function testElCallbackDelHookIgnoraArgumentosQueNoSeanConfiguracion(): void
    {
        ListingRenderer::enqueueAssets('wp_enqueue_scripts');

        self::assertArrayHasKey('homlity-real-estate-listing', WpStubs::$enqueuedScripts);
        self::assertArrayNotHasKey('homlity-real-estate-leaflet', WpStubs::$enqueuedScripts);
    }

    public function testUnaConfiguracionDeMapaSigueEncolandoLeaflet(): void
    {
        ListingRenderer::enqueueAssets(ListingConfig::fromBuilderSettings(['default_view' => 'map']));

        self::assertArrayHasKey('homlity-real-estate-leaflet', WpStubs::$enqueuedScripts);
    }
}
