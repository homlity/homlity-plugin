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

    /**
     * Una plantilla propia sirve para que cada sitio donde se pone el enlace
     * diga a qué viene, sin cambiar el mensaje del resto de la web.
     */
    public function testLaPlantillaPropiaSustituyeAlMensajeDeLosAjustes(): void
    {
        $link = WhatsAppLinkService::buildPropertyLinkWithTemplate(
            self::POST_ID,
            '3015554433',
            'Quiero ver el inmueble {code}'
        );

        self::assertStringContainsString('&text=' . rawurlencode('Quiero ver el inmueble CS-12'), $link);
    }

    public function testSinPlantillaPropiaSeUsaElMensajeDeLosAjustes(): void
    {
        self::assertSame(
            WhatsAppLinkService::buildPropertyLink(self::POST_ID, '3015554433'),
            WhatsAppLinkService::buildPropertyLinkWithTemplate(self::POST_ID, '3015554433')
        );
    }

    public function testLaPlantillaPropiaNoInventaUnNumero(): void
    {
        self::assertSame('', WhatsAppLinkService::buildPropertyLinkWithTemplate(self::POST_ID, '', 'Hola'));
    }

    public function testElTelefonoDelInmuebleSaleDeSuPropiaMeta(): void
    {
        WpStubs::setPostMeta(self::POST_ID, ['_property_agent_phone' => '+57 300 444 5566']);

        self::assertSame('+57 300 444 5566', WhatsAppLinkService::advisorPhoneForProperty(self::POST_ID));
    }

    /**
     * Cuando el CRM no manda el teléfono en el inmueble solo queda el usuario
     * del asesor, y cada integración lo guardó con un nombre distinto.
     */
    public function testSinTelefonoEnElInmuebleSeBuscaEnElPerfilDelAsesor(): void
    {
        WpStubs::setPostMeta(self::POST_ID, ['_property_agent_id' => 77]);
        WpStubs::setUser(77, 'asesor', [], ['author'], ['billing_phone' => '3009998877']);

        self::assertSame('3009998877', WhatsAppLinkService::advisorPhoneForProperty(self::POST_ID));
    }

    public function testSinAsesorNiTelefonoDevuelveCadenaVacia(): void
    {
        WpStubs::setPostMeta(self::POST_ID, []);

        self::assertSame('', WhatsAppLinkService::advisorPhoneForProperty(self::POST_ID));
    }
}
