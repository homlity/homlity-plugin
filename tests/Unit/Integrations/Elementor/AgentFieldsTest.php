<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations\Elementor;

use Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags\AgentFields;
use Homlity\PluginInmobiliario\Services\CapabilityService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;
use WP_User;

/**
 * Datos del asesor expuestos a Elementor.
 *
 * Las etiquetas en sí no se pueden instanciar sin Elementor cargado, así que
 * lo que se prueba es el resolutor del que tiran las tres: qué asesor toman y
 * qué devuelven por cada campo.
 */
final class AgentFieldsTest extends TestCase
{
    /**
     * @param array<string,mixed> $fields
     * @param array<string,mixed> $meta
     */
    private function givenAdvisor(int $id, string $nicename, array $fields = [], array $meta = []): WP_User
    {
        return WpStubs::setUser($id, $nicename, $fields, [CapabilityService::ROLE_ASSESSOR], $meta);
    }

    private function onProfileOf(WP_User $agent): void
    {
        WpStubs::$isAuthor = true;
        WpStubs::$queriedObject = $agent;
    }

    /** Coloca la petición en la ficha de un inmueble asignado a $agentId. */
    private function onPropertyOf(int $agentId, int $postId = 900): void
    {
        WpStubs::$postObjects[$postId] = (object) [
            'ID' => $postId,
            'post_type' => PropertyPostType::POST_TYPE,
            'post_status' => 'publish',
        ];
        WpStubs::setPostMeta($postId, ['_property_agent_id' => (string) $agentId]);
        WpStubs::$currentPostId = $postId;
    }

    // ── A quién se refiere la etiqueta ────────────────────────────────────

    public function testTomaAlAsesorDelPerfilQueSeEstaViendo(): void
    {
        $agent = $this->givenAdvisor(7, 'joquendo', ['display_name' => 'Jorge Oquendo']);
        $this->onProfileOf($agent);

        self::assertSame(7, (int) AgentFields::resolveAgent()->ID);
    }

    /**
     * En el editor de Elementor no hay perfil que consultar: sin el control de
     * asesor la plantilla se montaría a ciegas.
     */
    public function testElAsesorFijadoEnElControlManda(): void
    {
        $delPerfil = $this->givenAdvisor(7, 'joquendo');
        $this->givenAdvisor(9, 'mrestrepo');
        $this->onProfileOf($delPerfil);

        self::assertSame(9, (int) AgentFields::resolveAgent('9')->ID);
    }

    /** Las mismas etiquetas valen en la ficha del inmueble. */
    public function testFueraDelPerfilTomaAlAsesorDelInmueble(): void
    {
        $this->givenAdvisor(9, 'mrestrepo');
        $this->onPropertyOf(9);

        self::assertSame(9, (int) AgentFields::resolveAgent()->ID);
    }

    public function testElPerfilTienePreferenciaSobreElInmueble(): void
    {
        $delPerfil = $this->givenAdvisor(7, 'joquendo');
        $this->givenAdvisor(9, 'mrestrepo');
        $this->onProfileOf($delPerfil);
        $this->onPropertyOf(9);

        self::assertSame(7, (int) AgentFields::resolveAgent()->ID);
    }

    public function testSinAsesorEnNingunSitioNoResuelveNada(): void
    {
        self::assertNull(AgentFields::resolveAgent());
    }

    /** Un post que no es un inmueble no aporta asesor por su metadato. */
    public function testUnPostCualquieraNoAportaAsesor(): void
    {
        $this->givenAdvisor(9, 'mrestrepo');
        WpStubs::$postObjects[900] = (object) ['ID' => 900, 'post_type' => 'post', 'post_status' => 'publish'];
        WpStubs::setPostMeta(900, ['_property_agent_id' => '9']);
        WpStubs::$currentPostId = 900;

        self::assertNull(AgentFields::resolveAgent());
    }

    // ── Campos de texto ───────────────────────────────────────────────────

    public function testDevuelveCadaCampoDeTexto(): void
    {
        $agent = $this->givenAdvisor(
            7,
            'joquendo',
            [
                'display_name' => 'Jorge Oquendo',
                'user_email' => 'jorge@royal.test',
                'user_url' => 'https://royal.test/jorge',
            ],
            [
                'first_name' => 'Jorge',
                'last_name' => 'Oquendo',
                '_homlity_advisor_role' => 'Asesor comercial',
                '_homlity_advisor_phone' => '+57 300 123 4567',
                'description' => 'Doce años vendiendo en el Poblado.',
            ]
        );

        self::assertSame('Jorge Oquendo', AgentFields::text($agent, 'name'));
        self::assertSame('Jorge', AgentFields::text($agent, 'first_name'));
        self::assertSame('Oquendo', AgentFields::text($agent, 'last_name'));
        self::assertSame('Asesor comercial', AgentFields::text($agent, 'role'));
        self::assertSame('+57 300 123 4567', AgentFields::text($agent, 'phone'));
        self::assertSame('jorge@royal.test', AgentFields::text($agent, 'email'));
        self::assertSame('Doce años vendiendo en el Poblado.', AgentFields::text($agent, 'bio'));
        self::assertSame('https://royal.test/jorge', AgentFields::text($agent, 'website'));
    }

    public function testElNumeroDeInmueblesEsElDelAsesor(): void
    {
        $agent = $this->givenAdvisor(7, 'joquendo');
        WpStubs::$queryResolver = static fn(array $args): array => ['found_posts' => 23];

        self::assertSame('23', AgentFields::text($agent, 'property_count'));
    }

    /**
     * Una etiqueta guardada en una página sobrevive a que el campo desaparezca
     * de la lista: tiene que quedarse en blanco, no reventar la página.
     */
    public function testUnCampoDesconocidoQuedaEnBlanco(): void
    {
        $agent = $this->givenAdvisor(7, 'joquendo');

        self::assertSame('', AgentFields::text($agent, 'inventado'));
    }

    public function testSinAsesorTodoCampoQuedaEnBlanco(): void
    {
        self::assertSame('', AgentFields::text(null, 'name'));
    }

    /** Lo que ofrece el desplegable tiene que estar realmente resuelto. */
    public function testTodoCampoOfrecidoEnElDesplegableSeResuelve(): void
    {
        $agent = $this->givenAdvisor(7, 'joquendo', [
            'display_name' => 'Jorge Oquendo',
            'user_email' => 'jorge@royal.test',
            'user_url' => 'https://royal.test/jorge',
        ], [
            'first_name' => 'Jorge',
            'last_name' => 'Oquendo',
            '_homlity_advisor_role' => 'Asesor',
            '_homlity_advisor_phone' => '3001234567',
            'description' => 'Bio',
        ]);
        WpStubs::$queryResolver = static fn(array $args): array => ['found_posts' => 4];

        foreach (array_keys(AgentFields::textChoices()) as $field) {
            self::assertNotSame('', AgentFields::text($agent, $field), 'Campo sin resolver: ' . $field);
        }
    }

    // ── Enlaces ───────────────────────────────────────────────────────────

    public function testDevuelveCadaEnlace(): void
    {
        $agent = $this->givenAdvisor(
            7,
            'joquendo',
            ['user_email' => 'jorge@royal.test', 'user_url' => 'https://royal.test/jorge'],
            ['_homlity_advisor_phone' => '+57 300 123 4567']
        );

        self::assertSame('https://example.test/author/joquendo/', AgentFields::url($agent, 'profile'));
        self::assertStringContainsString('573001234567', AgentFields::url($agent, 'whatsapp'));
        self::assertSame('tel:+573001234567', AgentFields::url($agent, 'phone'));
        self::assertSame('mailto:jorge@royal.test', AgentFields::url($agent, 'email'));
        self::assertSame('https://royal.test/jorge', AgentFields::url($agent, 'website'));
    }

    /**
     * Un `tel:` o un `mailto:` sin destino sería un botón que no lleva a
     * ninguna parte; en blanco, Elementor no pinta el enlace.
     */
    public function testUnEnlaceSinDatoDeOrigenQuedaEnBlanco(): void
    {
        $agent = $this->givenAdvisor(7, 'joquendo', ['user_email' => '', 'user_url' => '']);

        self::assertSame('', AgentFields::url($agent, 'phone'));
        self::assertSame('', AgentFields::url($agent, 'whatsapp'));
        self::assertSame('', AgentFields::url($agent, 'email'));
        self::assertSame('', AgentFields::url($agent, 'website'));
    }

    public function testUnCorreoInvalidoNoSeConvierteEnEnlace(): void
    {
        $agent = $this->givenAdvisor(7, 'joquendo', ['user_email' => 'no-es-un-correo']);

        self::assertSame('', AgentFields::url($agent, 'email'));
    }

    public function testSinAsesorTodoEnlaceQuedaEnBlanco(): void
    {
        self::assertSame('', AgentFields::url(null, 'profile'));
    }

    // ── Foto ──────────────────────────────────────────────────────────────

    /**
     * Con la foto en la biblioteca se devuelve el id: es lo que le permite a
     * Elementor generar los tamaños intermedios y el srcset.
     */
    public function testLaFotoDelCrmGuardadaComoAdjuntoDevuelveSuId(): void
    {
        $agent = $this->givenAdvisor(7, 'joquendo', [], ['_homlity_advisor_photo' => '481']);

        self::assertSame(
            ['id' => 481, 'url' => 'https://example.test/uploads/481.jpg'],
            AgentFields::image($agent)
        );
    }

    /** Una foto que el CRM sirve desde su propio dominio no tiene id. */
    public function testLaFotoDelCrmComoUrlDevuelveSoloLaUrl(): void
    {
        $agent = $this->givenAdvisor(7, 'joquendo', [], [
            '_homlity_advisor_photo' => 'https://crm.test/fotos/joquendo.jpg',
        ]);

        self::assertSame(
            ['id' => 0, 'url' => 'https://crm.test/fotos/joquendo.jpg'],
            AgentFields::image($agent)
        );
    }

    public function testSinFotoPropiaCaeAlAvatarDeWordPress(): void
    {
        $agent = $this->givenAdvisor(7, 'joquendo');

        self::assertSame(0, AgentFields::image($agent)['id']);
        self::assertStringContainsString('gravatar.test/7', AgentFields::image($agent)['url']);
    }

    public function testSinAsesorNoHayFoto(): void
    {
        self::assertSame(['id' => 0, 'url' => ''], AgentFields::image(null));
    }
}
