<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\AgentProfileService;
use Homlity\PluginInmobiliario\Services\CapabilityService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\TechnicalSheetService;
use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * En qué peticiones carga el plugin su hoja de estilos.
 *
 * Bajo Elementor esto es lo único que la carga fuera del get_style_depends()
 * de cada widget: si una ruta del plugin no entra aquí, se pinta sin estilos.
 */
final class PublicAssetsScopeTest extends TestCase
{
    private function ownsRequest(): bool
    {
        return (new TemplateService())->ownsCurrentRequest();
    }

    private function onProfileOf(int $id, string $nicename): \WP_User
    {
        $user = WpStubs::setUser($id, $nicename, [], [CapabilityService::ROLE_ASSESSOR]);
        WpStubs::$isAuthor = true;
        WpStubs::$queriedObject = $user;

        return $user;
    }

    // ── Páginas del asesor ────────────────────────────────────────────────

    /**
     * Regresión: al mover el perfil a /author/{asesor}/ la comprobación seguía
     * mirando la query var de la ruta antigua, así que el perfil entero —
     * widget de asesor incluido — se servía sin la hoja de estilos.
     */
    public function testLaPaginaDelAsesorCargaLosEstilos(): void
    {
        $this->onProfileOf(7, 'egiraldo');

        self::assertTrue($this->ownsRequest());
    }

    public function testLaRutaAntiguaDelAsesorSigueCargandoLosEstilos(): void
    {
        WpStubs::setUser(7, 'egiraldo', [], [CapabilityService::ROLE_ASSESSOR]);
        WpStubs::$queryVars[AgentProfileService::QUERY_VAR] = 'egiraldo';

        self::assertTrue($this->ownsRequest());
    }

    /** El archivo de un autor cualquiera lo sirve el tema; no es cosa nuestra. */
    public function testElArchivoDeUnAutorCorrienteNoCargaLosEstilos(): void
    {
        WpStubs::setUser(9, 'blogger');
        WpStubs::$isAuthor = true;
        WpStubs::$queriedObject = WpStubs::$users[9];

        self::assertFalse($this->ownsRequest());
    }

    // ── Ficha técnica ─────────────────────────────────────────────────────

    public function testLaFichaTecnicaCargaLosEstilos(): void
    {
        WpStubs::$queryVars[TechnicalSheetService::QUERY_VAR] = 'apartamento-guatape';

        self::assertTrue($this->ownsRequest());
    }

    // ── Lo que ya funcionaba ──────────────────────────────────────────────

    public function testElDetalleDelInmuebleCargaLosEstilos(): void
    {
        WpStubs::$singularPostType = PropertyPostType::POST_TYPE;

        self::assertTrue($this->ownsRequest());
    }

    public function testElArchivoDeInmueblesCargaLosEstilos(): void
    {
        WpStubs::$postTypeArchive = PropertyPostType::POST_TYPE;

        self::assertTrue($this->ownsRequest());
    }

    public function testLaPaginaDeArchivoConfiguradaCargaLosEstilos(): void
    {
        WpStubs::$options['homlity_plugin_archive_page_id'] = 55;
        WpStubs::$currentPageId = 55;

        self::assertTrue($this->ownsRequest());
    }

    public function testUnArchivoDeTerminoDeInmueblesCargaLosEstilos(): void
    {
        WpStubs::$currentTaxonomy = PropertyTaxonomies::TAXONOMY_CITY;

        self::assertTrue($this->ownsRequest());
    }

    public function testUnaPaginaCualquieraNoCargaLosEstilos(): void
    {
        WpStubs::$currentPageId = 12;

        self::assertFalse($this->ownsRequest());
    }
}
