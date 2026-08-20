<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\AgentProfileService;
use Homlity\PluginInmobiliario\Services\CapabilityService;
use Homlity\PluginInmobiliario\Services\UserMetaService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * El interruptor «Mostrar en la web» del perfil del asesor.
 *
 * Un asesor que deja la inmobiliaria conserva sus inmuebles publicados —siguen
 * a la venta— y conserva su rol, así que ni el recuento ni el rol distinguen
 * al que sigue del que se fue. Este interruptor sí, y por eso hay que poder
 * apagarlo sin que se apague nadie más y sin que un formulario ajeno lo pise.
 */
final class AgentPublicListingTest extends TestCase
{
    private const AGENT_ID = 7;

    protected function setUp(): void
    {
        parent::setUp();

        WpStubs::$capabilities = ['edit_user'];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    /** @param array<string,mixed> $meta */
    private function givenAgent(array $meta = []): \WP_User
    {
        return WpStubs::setUser(
            self::AGENT_ID,
            'egiraldo',
            ['display_name' => 'Elena Giraldo'],
            [CapabilityService::ROLE_ASSESSOR],
            $meta
        );
    }

    private function render(\WP_User $user): string
    {
        ob_start();
        (new UserMetaService())->renderPhoneField($user);

        return (string) ob_get_clean();
    }

    private function storedValue(): string
    {
        return (string) (WpStubs::$userMeta[self::AGENT_ID][AgentProfileService::PUBLIC_META] ?? '');
    }

    // ── Lo que decide el listado ──────────────────────────────────────────

    /**
     * El interruptor llegó cuando los sitios ya tenían su plantilla montada.
     * Si la ausencia de valor significara «oculto», actualizar el plugin
     * vaciaría de golpe la página de asesores de todos ellos.
     */
    public function testSinValorGuardadoElAsesorSeLista(): void
    {
        self::assertTrue(AgentProfileService::isPubliclyListed($this->givenAgent()));
    }

    public function testGuardadoAceroElAsesorNoSeLista(): void
    {
        self::assertFalse(AgentProfileService::isPubliclyListed(
            $this->givenAgent([AgentProfileService::PUBLIC_META => '0'])
        ));
    }

    public function testGuardadoAUnoElAsesorSeLista(): void
    {
        self::assertTrue(AgentProfileService::isPubliclyListed(
            $this->givenAgent([AgentProfileService::PUBLIC_META => '1'])
        ));
    }

    public function testUnFiltroPuedeCambiarLaDecision(): void
    {
        $agent = $this->givenAgent();
        WpStubs::addFilter('homlity_agent_is_publicly_listed', static fn(): bool => false);

        self::assertFalse(AgentProfileService::isPubliclyListed($agent));
    }

    // ── El campo en el perfil ─────────────────────────────────────────────

    public function testElPerfilDeUnAsesorTraeElInterruptor(): void
    {
        $html = $this->render($this->givenAgent());

        self::assertStringContainsString('name="homlity_agent_public"', $html);
        self::assertStringContainsString("checked='checked'", $html);
    }

    public function testUnAsesorApagadoLoEnsenaDesmarcado(): void
    {
        $html = $this->render($this->givenAgent([AgentProfileService::PUBLIC_META => '0']));

        self::assertStringContainsString('name="homlity_agent_public"', $html);
        self::assertStringNotContainsString("checked='checked'", $html);
    }

    /**
     * En el perfil de un suscriptor o de un redactor la casilla no significa
     * nada, y una casilla inerte en la mayoría de los perfiles del sitio es
     * ruido.
     */
    public function testElPerfilDeQuienNoEsAsesorNoLoTrae(): void
    {
        $user = WpStubs::setUser(9, 'blogger', ['display_name' => 'Alguien'], ['subscriber']);

        self::assertStringNotContainsString('homlity_agent_public', $this->render($user));
    }

    /** El teléfono, que ya estaba, sigue en su sitio para todos. */
    public function testElCampoDeTelefonoSigueSaliendo(): void
    {
        self::assertStringContainsString(
            'name="homlity_plugin_phone"',
            $this->render(WpStubs::setUser(9, 'blogger', [], ['subscriber']))
        );
    }

    /**
     * Una casilla sin marcar no se envía. Sin el campo testigo no habría forma
     * de distinguir «la desmarqué» de «este formulario no la traía», y apagar
     * a un asesor sería imposible.
     */
    public function testElFormularioTraeElCampoTestigo(): void
    {
        self::assertStringContainsString(
            'name="homlity_agent_public_present"',
            $this->render($this->givenAgent())
        );
    }

    // ── Al guardar ────────────────────────────────────────────────────────

    public function testMarcarLaCasillaEnciendeAlAsesor(): void
    {
        $this->givenAgent([AgentProfileService::PUBLIC_META => '0']);
        $_POST = ['homlity_agent_public_present' => '1', 'homlity_agent_public' => '1'];

        (new UserMetaService())->savePhone(self::AGENT_ID);

        self::assertSame('1', $this->storedValue());
    }

    public function testDesmarcarLaCasillaApagaAlAsesor(): void
    {
        $this->givenAgent();
        $_POST = ['homlity_agent_public_present' => '1'];

        (new UserMetaService())->savePhone(self::AGENT_ID);

        self::assertSame('0', $this->storedValue());
    }

    /**
     * El alta de usuario y las pantallas de perfil de otros plugins disparan
     * los mismos ganchos. Sin el testigo, cualquiera de ellas llegaría aquí
     * sin la casilla y apagaría al asesor sin que nadie lo hubiera pedido.
     */
    public function testUnFormularioSinElCampoTestigoNoTocaNada(): void
    {
        $this->givenAgent([AgentProfileService::PUBLIC_META => '1']);
        $_POST = ['homlity_plugin_phone' => '3001234567'];

        (new UserMetaService())->savePhone(self::AGENT_ID);

        self::assertSame('1', $this->storedValue());
    }

    /** Y tampoco lo estrena en quien nunca lo tuvo puesto. */
    public function testUnFormularioSinElCampoTestigoNoEstrenaElValor(): void
    {
        $this->givenAgent();
        $_POST = ['homlity_plugin_phone' => '3001234567'];

        (new UserMetaService())->savePhone(self::AGENT_ID);

        self::assertArrayNotHasKey(
            AgentProfileService::PUBLIC_META,
            WpStubs::$userMeta[self::AGENT_ID]
        );
    }

    /** Quien no puede editar al usuario tampoco puede ocultarlo. */
    public function testSinPermisoNoSeGuardaNada(): void
    {
        $this->givenAgent();
        WpStubs::$capabilities = [];
        $_POST = ['homlity_agent_public_present' => '1'];

        (new UserMetaService())->savePhone(self::AGENT_ID);

        self::assertArrayNotHasKey(
            AgentProfileService::PUBLIC_META,
            WpStubs::$userMeta[self::AGENT_ID]
        );
    }
}
