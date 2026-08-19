<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\AgentProfileService;
use Homlity\PluginInmobiliario\Services\CapabilityService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * El perfil público del asesor vive en su URL de usuario, /author/{nicename}/.
 */
final class AgentProfileServiceTest extends TestCase
{
    /** @param array<string,mixed> $fields */
    private function givenAdvisor(int $id, string $nicename, array $fields = [], array $meta = []): \WP_User
    {
        return WpStubs::setUser(
            $id,
            $nicename,
            $fields,
            [CapabilityService::ROLE_ASSESSOR],
            $meta
        );
    }

    private function givenPlainAuthor(int $id, string $nicename): \WP_User
    {
        return WpStubs::setUser($id, $nicename);
    }

    /** Ningún usuario tiene inmuebles salvo los que se indiquen aquí. */
    private function givenPropertyCounts(array $countsByAgentId): void
    {
        WpStubs::$queryResolver = static function (array $args) use ($countsByAgentId): array {
            $agentId = 0;
            foreach ($args['meta_query'] ?? [] as $clause) {
                if (($clause['key'] ?? '') === '_property_agent_id') {
                    $agentId = (int) ($clause['value'] ?? 0);
                }
            }

            return ['found_posts' => (int) ($countsByAgentId[$agentId] ?? 0)];
        };
    }

    private function onAuthorArchiveOf(\WP_User $user): void
    {
        WpStubs::$isAuthor = true;
        WpStubs::$queriedObject = $user;
    }

    private function onLegacyRouteOf(string $nicename): void
    {
        WpStubs::$queryVars[AgentProfileService::QUERY_VAR] = $nicename;
    }

    private function disableAuthorUrls(): void
    {
        WpStubs::addFilter('homlity_agent_profile_use_author_url', static fn(): bool => false);
    }

    // ── URLs ──────────────────────────────────────────────────────────────

    public function testProfileUrlIsTheUsersOwnAuthorUrl(): void
    {
        $agent = $this->givenAdvisor(7, 'egiraldo');

        self::assertSame('https://example.test/author/egiraldo/', AgentProfileService::profileUrl($agent));
    }

    public function testProfileUrlAcceptsAUserId(): void
    {
        $this->givenAdvisor(7, 'egiraldo');

        self::assertSame('https://example.test/author/egiraldo/', AgentProfileService::profileUrl(7));
    }

    public function testProfileUrlAcceptsANicename(): void
    {
        $this->givenAdvisor(7, 'egiraldo');

        self::assertSame('https://example.test/author/egiraldo/', AgentProfileService::profileUrl('egiraldo'));
    }

    /**
     * Algunos plugins SEO permiten desactivar los archivos de autor. El filtro
     * devuelve el perfil a la ruta antigua en lugar de dejarlo inalcanzable.
     */
    public function testProfileUrlFallsBackToTheLegacyRouteWhenAuthorUrlsAreDisabled(): void
    {
        $agent = $this->givenAdvisor(7, 'egiraldo');
        $this->disableAuthorUrls();

        self::assertSame(
            'https://example.test/property-agent/egiraldo/',
            AgentProfileService::profileUrl($agent)
        );
    }

    /** Sin usuario que resolver no hay URL de autor que construir. */
    public function testProfileUrlOfAnUnknownSlugUsesTheLegacyRoute(): void
    {
        self::assertSame(
            'https://example.test/property-agent/nadie/',
            AgentProfileService::profileUrl('nadie')
        );
    }

    public function testProfileUrlIsEmptyWithoutAnAgent(): void
    {
        self::assertSame('', AgentProfileService::profileUrl(null));
        self::assertSame('', AgentProfileService::profileUrl(0));
    }

    public function testLegacyProfileUrlAlwaysPointsAtTheOldRoute(): void
    {
        $agent = $this->givenAdvisor(7, 'egiraldo');

        self::assertSame(
            'https://example.test/property-agent/egiraldo/',
            AgentProfileService::legacyProfileUrl($agent)
        );
    }

    public function testCanonicalUrlIsTheAuthorUrl(): void
    {
        $agent = $this->givenAdvisor(7, 'egiraldo');
        $this->onAuthorArchiveOf($agent);

        self::assertSame('https://example.test/author/egiraldo/', AgentProfileService::canonicalUrl($agent));
    }

    public function testCanonicalUrlKeepsThePaginationSegment(): void
    {
        $agent = $this->givenAdvisor(7, 'egiraldo');
        WpStubs::$queryVars['paged'] = 3;

        self::assertSame('https://example.test/author/egiraldo/page/3/', AgentProfileService::canonicalUrl($agent));
    }

    // ── Quién es asesor ───────────────────────────────────────────────────

    public function testTheAdvisorRoleQualifies(): void
    {
        $agent = $this->givenAdvisor(7, 'egiraldo');
        $this->givenPropertyCounts([]);

        self::assertTrue(AgentProfileService::qualifiesAsAgent($agent));
    }

    public function testTheLegacyAdvisorRoleQualifies(): void
    {
        $agent = WpStubs::setUser(7, 'egiraldo', [], [CapabilityService::LEGACY_ROLE_ASSESSOR]);
        $this->givenPropertyCounts([]);

        self::assertTrue(AgentProfileService::qualifiesAsAgent($agent));
    }

    /**
     * Los asesores que llegan desde un CRM suelen crearse sin el rol; tener
     * inmuebles asignados es lo que los identifica.
     */
    public function testAUserHoldingPropertiesQualifiesWithoutTheRole(): void
    {
        $agent = $this->givenPlainAuthor(7, 'egiraldo');
        $this->givenPropertyCounts([7 => 12]);

        self::assertTrue(AgentProfileService::qualifiesAsAgent($agent));
    }

    public function testAPlainAuthorDoesNotQualify(): void
    {
        $author = $this->givenPlainAuthor(9, 'blogger');
        $this->givenPropertyCounts([]);

        self::assertFalse(AgentProfileService::qualifiesAsAgent($author));
    }

    public function testTheVerdictIsFilterable(): void
    {
        $author = $this->givenPlainAuthor(9, 'blogger');
        $this->givenPropertyCounts([]);
        WpStubs::addFilter('homlity_user_is_agent', static fn(): bool => true);

        self::assertTrue(AgentProfileService::qualifiesAsAgent($author));
    }

    // ── Detección de la petición ──────────────────────────────────────────

    public function testAnAdvisorAuthorArchiveIsAProfileRequest(): void
    {
        $agent = $this->givenAdvisor(7, 'egiraldo');
        $this->givenPropertyCounts([]);
        $this->onAuthorArchiveOf($agent);

        self::assertTrue(AgentProfileService::isAuthorArchiveRequest());
        self::assertTrue(AgentProfileService::isAgentProfileRequest());
    }

    /** El archivo de autor de quien no es asesor sigue siendo del tema. */
    public function testAPlainAuthorArchiveIsLeftAlone(): void
    {
        $author = $this->givenPlainAuthor(9, 'blogger');
        $this->givenPropertyCounts([]);
        $this->onAuthorArchiveOf($author);

        self::assertFalse(AgentProfileService::isAuthorArchiveRequest());
        self::assertFalse(AgentProfileService::isAgentProfileRequest());
    }

    public function testAnOrdinaryRequestIsNotAProfileRequest(): void
    {
        self::assertFalse(AgentProfileService::isAgentProfileRequest());
    }

    public function testTheAuthorArchiveIsIgnoredWhenAuthorUrlsAreDisabled(): void
    {
        $agent = $this->givenAdvisor(7, 'egiraldo');
        $this->givenPropertyCounts([]);
        $this->onAuthorArchiveOf($agent);
        $this->disableAuthorUrls();

        self::assertFalse(AgentProfileService::isAgentProfileRequest());
    }

    public function testTheLegacyRouteIsStillAProfileRequest(): void
    {
        $this->givenAdvisor(7, 'egiraldo');
        $this->onLegacyRouteOf('egiraldo');

        self::assertTrue(AgentProfileService::isAgentProfileRequest());
    }

    // ── Asesor de la petición ─────────────────────────────────────────────

    public function testCurrentAgentComesFromTheAuthorArchive(): void
    {
        $agent = $this->givenAdvisor(7, 'egiraldo');
        $this->givenPropertyCounts([]);
        $this->onAuthorArchiveOf($agent);

        self::assertSame(7, AgentProfileService::currentAgentId());
    }

    public function testCurrentAgentStillComesFromTheLegacyQueryVar(): void
    {
        $this->givenAdvisor(7, 'egiraldo');
        $this->onLegacyRouteOf('egiraldo');

        self::assertSame(7, AgentProfileService::currentAgentId());
    }

    public function testThereIsNoCurrentAgentOnAPlainAuthorArchive(): void
    {
        $author = $this->givenPlainAuthor(9, 'blogger');
        $this->givenPropertyCounts([]);
        $this->onAuthorArchiveOf($author);

        self::assertNull(AgentProfileService::currentAgent());
    }

    // ── Datos del perfil ──────────────────────────────────────────────────

    /**
     * Los campos que la ficha del asesor tiene que poder mostrar: foto,
     * nombre, correo, teléfono, cargo, descripción e inmuebles relacionados.
     */
    public function testAgentDataExposesEveryConfiguredField(): void
    {
        $agent = $this->givenAdvisor(
            7,
            'egiraldo',
            [
                'display_name' => 'Esteban Giraldo',
                'user_email' => 'egiraldo@example.test',
                'user_url' => 'https://egiraldo.example.test',
            ],
            [
                '_homlity_advisor_role' => 'Asesor comercial senior',
                '_homlity_advisor_phone' => '+57 300 123 4567',
                '_homlity_advisor_photo' => 'https://example.test/uploads/egiraldo.jpg',
                'description' => 'Doce años acompañando compradores en Guatapé.',
            ]
        );
        $this->givenPropertyCounts([7 => 12]);

        $data = AgentProfileService::agentData($agent);

        self::assertSame(7, $data['id']);
        self::assertSame('Esteban Giraldo', $data['name']);
        self::assertSame('Asesor comercial senior', $data['role']);
        self::assertSame('+57 300 123 4567', $data['phone']);
        self::assertSame('egiraldo@example.test', $data['email']);
        self::assertSame('Doce años acompañando compradores en Guatapé.', $data['bio']);
        self::assertSame('https://egiraldo.example.test', $data['website']);
        self::assertSame('https://example.test/uploads/egiraldo.jpg', $data['photo_url']);
        self::assertSame('https://example.test/author/egiraldo/', $data['profile_url']);
        self::assertSame(12, $data['property_count']);
        self::assertStringContainsString('egiraldo.jpg', $data['avatar_html']);
    }

    public function testAgentDataIsEmptyWithoutAnAgent(): void
    {
        $data = AgentProfileService::agentData(null);

        self::assertSame(0, $data['id']);
        self::assertSame('', $data['name']);
        self::assertSame(0, $data['property_count']);
    }

    /** Una foto guardada como id de adjunto se resuelve a su URL. */
    public function testTheCrmPhotoCanBeAnAttachmentId(): void
    {
        $agent = $this->givenAdvisor(7, 'egiraldo', [], ['_homlity_advisor_photo' => '481']);

        self::assertSame('https://example.test/uploads/481.jpg', AgentProfileService::photoUrl($agent));
    }

    public function testThePhoneFallsBackThroughTheKnownMetaKeys(): void
    {
        $agent = $this->givenAdvisor(7, 'egiraldo', [], ['billing_phone' => '3001112233']);

        self::assertSame('3001112233', AgentProfileService::agentPhone($agent));
    }

    // ── Redirección de la ruta antigua ────────────────────────────────────

    public function testTheLegacyRouteRedirectsToTheAuthorUrl(): void
    {
        $this->givenAdvisor(7, 'egiraldo');
        $this->onLegacyRouteOf('egiraldo');

        try {
            (new AgentProfileService())->redirectLegacyProfileUrl();
            self::fail('Se esperaba una redirección 301 hacia la URL de autor.');
        } catch (\HomlityTestRedirect $redirect) {
            self::assertSame('https://example.test/author/egiraldo/', $redirect->location);
            self::assertSame(301, $redirect->status);
        }
    }

    public function testTheLegacyRedirectKeepsThePaginationSegment(): void
    {
        $this->givenAdvisor(7, 'egiraldo');
        $this->onLegacyRouteOf('egiraldo');
        WpStubs::$queryVars['paged'] = 2;

        try {
            (new AgentProfileService())->redirectLegacyProfileUrl();
            self::fail('Se esperaba una redirección 301 hacia la URL de autor.');
        } catch (\HomlityTestRedirect $redirect) {
            self::assertSame('https://example.test/author/egiraldo/page/2/', $redirect->location);
        }
    }

    /** Un slug desconocido tiene que seguir su camino hasta el 404. */
    public function testAnUnknownLegacySlugIsNotRedirected(): void
    {
        $this->onLegacyRouteOf('nadie');

        (new AgentProfileService())->redirectLegacyProfileUrl();

        self::assertSame([], WpStubs::$redirects);
    }

    public function testNothingIsRedirectedWhenAuthorUrlsAreDisabled(): void
    {
        $this->givenAdvisor(7, 'egiraldo');
        $this->onLegacyRouteOf('egiraldo');
        $this->disableAuthorUrls();

        (new AgentProfileService())->redirectLegacyProfileUrl();

        self::assertSame([], WpStubs::$redirects);
    }

    public function testAnAuthorArchiveIsNeverRedirected(): void
    {
        $agent = $this->givenAdvisor(7, 'egiraldo');
        $this->givenPropertyCounts([]);
        $this->onAuthorArchiveOf($agent);

        (new AgentProfileService())->redirectLegacyProfileUrl();

        self::assertSame([], WpStubs::$redirects);
    }

    // ── 404 ───────────────────────────────────────────────────────────────

    /** El perfil de un asesor existente nunca puede acabar en 404. */
    public function testAValidAdvisorArchiveIsNotTurnedIntoA404(): void
    {
        $agent = $this->givenAdvisor(7, 'egiraldo');
        $this->givenPropertyCounts([]);
        $this->onAuthorArchiveOf($agent);

        $GLOBALS['wp_query'] = new \WP_Query();
        (new AgentProfileService())->maybeSendNotFound();

        self::assertFalse(isset($GLOBALS['wp_query']->is_404));
    }
}
