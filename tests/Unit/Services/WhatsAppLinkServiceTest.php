<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\WhatsAppLinkService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class WhatsAppLinkServiceTest extends TestCase
{
    private const POST_ID = 21;

    protected function setUp(): void
    {
        parent::setUp();
        WpStubs::setPost(self::POST_ID, 'Casa campestre', 'https://inmobiliaria.test/casa/', [
            '_property_code' => 'CS-12',
        ]);
    }

    public function testUsaElTelefonoDeRespaldoCuandoNoHayWpChatApp(): void
    {
        $link = WhatsAppLinkService::buildPropertyLink(self::POST_ID, '+57 (301) 555-4433');

        self::assertStringStartsWith('https://api.whatsapp.com/send?phone=573015554433', $link);
        self::assertStringContainsString('&text=', $link);
        self::assertStringContainsString(rawurlencode('CS-12'), $link);
    }

    public function testDevuelveCadenaVaciaSinTelefonoUtilizable(): void
    {
        self::assertSame('', WhatsAppLinkService::buildPropertyLink(self::POST_ID, 'sin numero'));
        self::assertSame('', WhatsAppLinkService::buildPropertyLink(self::POST_ID));
    }

    public function testPrefiereLaCuentaActivaDeWpChatApp(): void
    {
        WpStubs::$postTypes[] = 'whatsapp-accounts';
        WpStubs::$posts[] = [WpStubs::makePost(500, [
            'nta_wa_account_info' => ['number' => '+57 300 111 2233', 'predefinedText' => 'Hola'],
        ])];

        $link = WhatsAppLinkService::buildPropertyLink(self::POST_ID, '+57 (301) 555-4433');

        self::assertStringStartsWith('https://api.whatsapp.com/send?phone=573001112233', $link);
        self::assertSame('nta_wa_widget_show', WpStubs::$getPostsCalls[0]['meta_query'][0]['key']);
    }

    public function testConsultaLaCuentaMasRecienteCuandoNingunaEstaVisible(): void
    {
        WpStubs::$postTypes[] = 'whatsapp-accounts';
        WpStubs::$posts[] = [];                       // primera consulta (widget visible) sin resultados
        WpStubs::$posts[] = [WpStubs::makePost(501, [ // segunda consulta (más reciente)
            'nta_wa_account_info' => ['number' => '3009998877'],
        ])];

        $link = WhatsAppLinkService::buildPropertyLink(self::POST_ID, '3015554433');

        self::assertStringStartsWith('https://api.whatsapp.com/send?phone=3009998877', $link);
        self::assertCount(2, WpStubs::$getPostsCalls);
    }

    public function testIgnoraCuentasSinNumeroYUsaElRespaldo(): void
    {
        WpStubs::$postTypes[] = 'whatsapp-accounts';
        WpStubs::$posts[] = [WpStubs::makePost(502, ['nta_wa_account_info' => ['number' => '']])];
        WpStubs::$posts[] = [];

        $link = WhatsAppLinkService::buildPropertyLink(self::POST_ID, '3015554433');

        self::assertStringStartsWith('https://api.whatsapp.com/send?phone=3015554433', $link);
    }

    public function testOmiteElTextoCuandoLaPlantillaQuedaVacia(): void
    {
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, ['share_messages' => ['whatsapp' => '']]);

        self::assertSame(
            'https://api.whatsapp.com/send?phone=3015554433',
            WhatsAppLinkService::buildPropertyLink(self::POST_ID, '3015554433')
        );
    }
}
