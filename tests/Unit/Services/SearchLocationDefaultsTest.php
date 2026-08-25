<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\SearchLocationDefaults;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Ubicación base que el buscador preselecciona.
 *
 * Lo que importa aquí es que la preselección solo ocurra cuando el
 * administrador la pidió: la ubicación base ya existía y la usa el editor de
 * inmuebles, así que activarla sola cambiaría el buscador de sitios que hoy
 * funcionan bien.
 */
final class SearchLocationDefaultsTest extends TestCase
{
    /** @param array<string,mixed> $extra */
    private function givenSettings(array $extra): void
    {
        WpStubs::setOption(HOMLITY_PLUGIN_SETTINGS_OPTION, $extra);
    }

    private function givenCity(int $termId, string $slug): void
    {
        WpStubs::setTerm($termId, PropertyTaxonomies::TAXONOMY_CITY, $slug, $slug);
    }

    // ── Interruptor ──────────────────────────────────────────────────────

    public function testNoPreseleccionaNadaSiElAjusteEstaApagado(): void
    {
        $this->givenCity(11, 'bogota');
        $this->givenSettings(['default_city' => 11]);

        self::assertFalse(SearchLocationDefaults::isEnabled());
        self::assertSame([], SearchLocationDefaults::slugs());
    }

    public function testPreseleccionaLaCiudadCuandoElAjusteEstaEncendido(): void
    {
        $this->givenCity(11, 'bogota');
        $this->givenSettings([
            'default_city' => 11,
            SearchLocationDefaults::SETTING_KEY => true,
        ]);

        self::assertTrue(SearchLocationDefaults::isEnabled());
        self::assertSame(['city' => 'bogota'], SearchLocationDefaults::slugs());
    }

    // ── Qué niveles devuelve ─────────────────────────────────────────────

    public function testDevuelveCadaNivelConfiguradoYOmiteLosVacios(): void
    {
        WpStubs::setTerm(1, PropertyTaxonomies::TAXONOMY_COUNTRY, 'colombia', 'Colombia');
        $this->givenCity(11, 'bogota');
        WpStubs::setTerm(21, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, 'chapinero', 'Chapinero');

        $this->givenSettings([
            'default_country' => 1,
            'default_state' => 0,
            'default_city' => 11,
            'default_neighborhood' => 21,
            SearchLocationDefaults::SETTING_KEY => true,
        ]);

        self::assertSame(
            ['country' => 'colombia', 'city' => 'bogota', 'neighborhood' => 'chapinero'],
            SearchLocationDefaults::slugs()
        );
    }

    /**
     * Un término borrado deja de preseleccionarse en vez de dejar el buscador
     * apuntando a una ciudad que ya no existe.
     */
    public function testOmiteUnTerminoQueYaNoExiste(): void
    {
        $this->givenSettings([
            'default_city' => 999,
            SearchLocationDefaults::SETTING_KEY => true,
        ]);

        self::assertSame([], SearchLocationDefaults::slugs());
    }

    public function testNoFallaSiNoHayAjustesGuardados(): void
    {
        self::assertSame([], SearchLocationDefaults::slugs());
    }

    // ── Qué gana: el visitante o el ajuste ───────────────────────────────

    /** @var array<string,string> */
    private const SLUGS = ['city' => 'bogota'];

    public function testUsaLaCiudadConfiguradaCuandoLaPeticionNoMencionaElCampo(): void
    {
        self::assertSame(
            'bogota',
            SearchLocationDefaults::pick(self::SLUGS, 'city', false, '')
        );
    }

    public function testRespetaLaCiudadQueEligioElVisitante(): void
    {
        self::assertSame(
            'medellin',
            SearchLocationDefaults::pick(self::SLUGS, 'city', true, 'medellin')
        );
    }

    /**
     * El caso que hace falta acertar: el formulario envía todos los campos
     * activos, vacíos incluidos. Si el visitante borra la ciudad y busca, el
     * campo llega presente y vacío, y debe quedarse vacío.
     */
    public function testNoReponeLaCiudadQueElVisitanteBorro(): void
    {
        self::assertSame(
            '',
            SearchLocationDefaults::pick(self::SLUGS, 'city', true, '')
        );
    }

    public function testDejaElCampoIntactoSiEseNivelNoEstaConfigurado(): void
    {
        self::assertSame(
            '',
            SearchLocationDefaults::pick(self::SLUGS, 'neighborhood', false, '')
        );
    }

    /** Sin preselección activa el campo se comporta como siempre. */
    public function testSinPreseleccionDevuelveLoQueTraeLaPeticion(): void
    {
        self::assertSame('', SearchLocationDefaults::pick([], 'city', false, ''));
        self::assertSame('cali', SearchLocationDefaults::pick([], 'city', true, 'cali'));
    }

    /** Una ciudad múltiple llega como arreglo y no debe aplanarse. */
    public function testConservaUnaSeleccionMultipleDelVisitante(): void
    {
        self::assertSame(
            ['bogota', 'cali'],
            SearchLocationDefaults::pick(self::SLUGS, 'city', true, ['bogota', 'cali'])
        );
    }
}
