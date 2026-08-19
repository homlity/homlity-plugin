<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\SocialShareMessageService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class SocialShareMessageServiceTest extends TestCase
{
    private const POST_ID = 77;
    private const URL = 'https://inmobiliaria.test/inmueble/apartamento-chapinero/';

    protected function setUp(): void
    {
        parent::setUp();
        WpStubs::setPost(self::POST_ID, 'Apartamento en <strong>Chapinero</strong>', self::URL, [
            '_property_code'       => 'APT-450',
            '_property_bedrooms'   => '3',
            '_property_bathrooms'  => '2',
            '_property_parking'    => '1',
            '_property_area'       => '85',
            '_property_price_sale' => '450000000',
        ]);
    }

    public function testDefaultsYFieldDefinitionsCubrenLasMismasPlataformas(): void
    {
        self::assertSame(
            array_keys(SocialShareMessageService::defaults()),
            array_keys(SocialShareMessageService::fieldDefinitions())
        );
    }

    public function testTemplatesDevuelveLosValoresPorDefectoSinAjustes(): void
    {
        self::assertSame(SocialShareMessageService::defaults(), SocialShareMessageService::templates());
    }

    public function testTemplatesSobrescribeSoloLasPlataformasConfiguradas(): void
    {
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, [
            'share_messages' => ['whatsapp' => 'Hola, info del {code}', 'inexistente' => 'ignorado'],
        ]);

        $templates = SocialShareMessageService::templates();

        self::assertSame('Hola, info del {code}', $templates['whatsapp']);
        self::assertSame(SocialShareMessageService::defaults()['facebook'], $templates['facebook']);
        self::assertArrayNotHasKey('inexistente', $templates);
    }

    public function testPropertyContextLimpiaElTituloYComponeElResumen(): void
    {
        $context = SocialShareMessageService::propertyContext(self::POST_ID);

        self::assertSame('Apartamento en Chapinero', $context['title']);
        self::assertSame(self::URL, $context['url']);
        self::assertSame('APT-450', $context['code']);
        self::assertSame('$ 450.000.000', $context['price']);
        self::assertStringContainsString('alcobas: 3', $context['summary']);
        self::assertStringContainsString('baños: 2', $context['summary']);
        self::assertStringContainsString('área: 85m2', $context['summary']);
    }

    public function testPropertyContextUsaElIdCuandoNoHayCodigo(): void
    {
        WpStubs::setPost(88, 'Casa', 'https://inmobiliaria.test/casa/', []);

        $context = SocialShareMessageService::propertyContext(88);

        self::assertSame('88', $context['code']);
        self::assertSame('', $context['price']);
    }

    public function testPropertyContextUsaElPrecioDeArriendoCuandoNoHayVenta(): void
    {
        WpStubs::setPost(99, 'Local', 'https://inmobiliaria.test/local/', [
            '_property_price_sale' => '',
            '_property_price_rent' => '2500000',
        ]);

        self::assertSame('$ 2.500.000', SocialShareMessageService::propertyContext(99)['price']);
    }

    public function testMessageForReemplazaTodosLosMarcadores(): void
    {
        $message = SocialShareMessageService::messageFor('whatsapp', self::POST_ID);

        self::assertStringContainsString('APT-450', $message);
        self::assertStringContainsString('Apartamento en Chapinero', $message);
        self::assertStringContainsString(self::URL, $message);
        self::assertStringNotContainsString('{', $message);
    }

    public function testMessageForAceptaUnaPlantillaPersonalizada(): void
    {
        $message = SocialShareMessageService::messageFor('x', self::POST_ID, '{code} · {bedrooms} alcobas · {price}');

        self::assertSame('APT-450 · 3 alcobas · $ 450.000.000', $message);
    }

    public function testMessageForEliminaLaUrlRepetida(): void
    {
        $message = SocialShareMessageService::messageFor('telegram', self::POST_ID, '{url} mira esto {url}');

        self::assertSame(1, substr_count($message, self::URL));
    }

    public function testMessageForUsaElResumenParaPlataformasDesconocidas(): void
    {
        $message = SocialShareMessageService::messageFor('tiktok', self::POST_ID);

        self::assertStringContainsString('código: APT-450', $message);
    }

    public function testMessageForPuedeFiltrarse(): void
    {
        WpStubs::addFilter(
            'homlity_social_share_message',
            static fn (string $message, string $platform): string => strtoupper($platform) . ': ' . $message
        );

        self::assertStringStartsWith('X: ', SocialShareMessageService::messageFor('x', self::POST_ID));
    }

    public function testMessageWithoutUrlQuitaElEnlace(): void
    {
        $message = 'Mira este inmueble ' . self::URL . ' ahora';

        self::assertSame('Mira este inmueble ahora', SocialShareMessageService::messageWithoutUrl($message, self::URL));
    }

    public function testMessageWithoutUrlConUrlVaciaSoloRecorta(): void
    {
        self::assertSame('Mensaje', SocialShareMessageService::messageWithoutUrl('  Mensaje  ', ''));
    }
}
