<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Listing;

use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;
use Homlity\PluginInmobiliario\Services\CapabilityService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Origen de consulta "Inmuebles del asesor de la página": el listado se acota
 * al asesor de la página que se está viendo, /author/{asesor}/.
 */
final class ListingAgentScopeTest extends TestCase
{
    private function onProfileOf(int $id, string $nicename): void
    {
        $user = WpStubs::setUser($id, $nicename, [], [CapabilityService::ROLE_ASSESSOR]);
        WpStubs::$isAuthor = true;
        WpStubs::$queriedObject = $user;
    }

    /** @param array<string,mixed> $overrides */
    private function paramsInAgentMode(array $overrides = []): array
    {
        return array_merge(
            ListingConfig::fromBuilderSettings(['query_mode' => 'related_agent'])->toQueryParams(),
            $overrides
        );
    }

    // ── El modo llega hasta la configuración ──────────────────────────────

    public function testElConstructorGuardaElNuevoOrigenDeConsulta(): void
    {
        $config = ListingConfig::fromBuilderSettings([
            'query_mode' => 'related_agent',
            'related_agent_id' => '42',
        ]);

        self::assertSame('related_agent', $config->queryMode());
        self::assertSame(42, $config->relatedAgentId());
    }

    public function testSinAsesorDeReferenciaElValorEsCero(): void
    {
        $config = ListingConfig::fromBuilderSettings(['query_mode' => 'related_agent']);

        self::assertSame(0, $config->relatedAgentId());
    }

    // ── Resolución del asesor ─────────────────────────────────────────────

    public function testTomaElAsesorDeLaPaginaQueSeEstaViendo(): void
    {
        $this->onProfileOf(7, 'egiraldo');

        $params = ListingRenderer::scopeToAgent($this->paramsInAgentMode());

        self::assertNotNull($params);
        self::assertSame(7, $params['preset_agent']);
    }

    /** El asesor elegido en el widget manda sobre el de la página. */
    public function testElAsesorFijadoEnElWidgetTienePrioridad(): void
    {
        $this->onProfileOf(7, 'egiraldo');

        $params = ListingRenderer::scopeToAgent($this->paramsInAgentMode(), 42);

        self::assertNotNull($params);
        self::assertSame(42, $params['preset_agent']);
    }

    /**
     * Fuera de una página de asesor no hay a quién acotar. Devolver el catálogo
     * entero sería peor que no pintar nada.
     */
    public function testFueraDeUnaPaginaDeAsesorNoHayConsulta(): void
    {
        self::assertNull(ListingRenderer::scopeToAgent($this->paramsInAgentMode()));
    }

    // ── Qué le llega a la consulta ────────────────────────────────────────

    /**
     * En este modo los filtros del widget quedan ocultos; arrastrar el valor de
     * un modo anterior filtraría el listado desde un control invisible.
     */
    public function testLimpiaLosFiltrosQueElModoOculta(): void
    {
        $this->onProfileOf(7, 'egiraldo');

        $params = ListingRenderer::scopeToAgent($this->paramsInAgentMode([
            'search' => 'penthouse',
            'preset_city' => 33,
            'preset_operation' => 12,
            'preset_tag_ids' => [4, 5],
            'use_current_property_tags' => true,
        ]));

        self::assertNotNull($params);
        self::assertSame('', $params['search']);
        self::assertSame(0, $params['preset_city']);
        self::assertSame(0, $params['preset_operation']);
        self::assertSame([], $params['preset_tag_ids']);
        self::assertFalse($params['use_current_property_tags']);
    }

    /** Los controles que el modo sí muestra se respetan. */
    public function testRespetaOrdenPaginacionYDestacados(): void
    {
        $this->onProfileOf(7, 'egiraldo');

        $params = ListingRenderer::scopeToAgent($this->paramsInAgentMode([
            'orderby' => 'price_desc',
            'per_page' => 6,
            'featured' => true,
        ]));

        self::assertNotNull($params);
        self::assertSame('price_desc', $params['orderby']);
        self::assertSame(6, $params['per_page']);
        self::assertTrue($params['featured']);
    }

    /**
     * A partir de aquí es una consulta normal con el asesor fijado. Eso es lo
     * que el listado le pasa al AJAX de paginación, que corre en admin-ajax y
     * no tiene página de asesor de la que deducirlo.
     */
    public function testDejaLaConsultaListaParaElAjaxDePaginacion(): void
    {
        $this->onProfileOf(7, 'egiraldo');

        $params = ListingRenderer::scopeToAgent($this->paramsInAgentMode());

        self::assertNotNull($params);
        self::assertSame('custom', $params['query_mode']);
        self::assertSame(7, $params['preset_agent']);
    }
}
