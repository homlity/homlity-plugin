<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Developer;

use Homlity\Developer\Homlity;
use Homlity\Developer\Support\Hooks;
use Homlity\PluginInmobiliario\Core\PropertyEventDispatcher;
use Homlity\PluginInmobiliario\Integrations\CRM\PropertyUpsertService;
use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Los filtros públicos de la Developer API.
 *
 * Un filtro es más peligroso que una acción: lo que devuelve se guarda o se
 * consulta. Estas pruebas fijan las dos garantías que hacen que un filtro sea
 * seguro de publicar — que llega con los datos prometidos, y que devolver
 * cualquier otra cosa no rompe el plugin.
 */
final class PublicFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PropertyEventDispatcher::reset();
        Homlity::setPropertyRepository(null);

        WpStubs::$registeredTaxonomies = [
            PropertyTaxonomies::TAXONOMY_OPERATION,
            PropertyTaxonomies::TAXONOMY_TYPE,
            PropertyTaxonomies::TAXONOMY_FEATURE,
            PropertyTaxonomies::TAXONOMY_LOCATION,
        ];
    }

    protected function tearDown(): void
    {
        PropertyEventDispatcher::reset();
        parent::tearDown();
    }

    /** @param array<string,mixed> $overrides */
    private function normalized(array $overrides = []): array
    {
        return array_replace_recursive([
            'external' => ['source' => 'wasi', 'id' => 'EXT-77'],
            'post'     => ['title' => 'Apartamento en El Poblado'],
        ], $overrides);
    }

    // ─── homlity/property/normalized ─────────────────────────────────────

    public function testElFiltroDeCargaNormalizadaPuedeCambiarLoQueSeGuarda(): void
    {
        add_filter(Hooks::FILTER_PROPERTY_NORMALIZED, static function (array $normalized): array {
            $normalized['post']['title'] = 'Título impuesto por la extensión';

            return $normalized;
        });

        $result = (new PropertyUpsertService())->upsert($this->normalized());

        self::assertTrue($result['ok']);
        self::assertSame(
            'Título impuesto por la extensión',
            Homlity::properties()->find((int) $result['post_id'])->getTitle()
        );
    }

    public function testElFiltroDeCargaNormalizadaRecibeElOrigenYElCrm(): void
    {
        $visto = [];

        add_filter(
            Hooks::FILTER_PROPERTY_NORMALIZED,
            static function (array $normalized, string $source, string $origin) use (&$visto): array {
                $visto = ['source' => $source, 'origin' => $origin];

                return $normalized;
            },
            10,
            3
        );

        (new PropertyUpsertService())->upsert($this->normalized(), 'consignment');

        self::assertSame(['source' => 'wasi', 'origin' => 'consignment'], $visto);
    }

    public function testUnFiltroQueDevuelveBasuraNoBorraElInmueble(): void
    {
        add_filter(Hooks::FILTER_PROPERTY_NORMALIZED, static fn() => null);

        $result = (new PropertyUpsertService())->upsert($this->normalized());

        self::assertTrue($result['ok'], 'La carga original tiene que sobrevivir al filtro roto.');
        self::assertSame(
            'Apartamento en El Poblado',
            Homlity::properties()->find((int) $result['post_id'])->getTitle()
        );
    }

    public function testElFiltroCorreDelanteDeLaValidacionYPuedeCompletarLaCarga(): void
    {
        // Una integración puede rellenar un campo obligatorio que el CRM omite.
        add_filter(Hooks::FILTER_PROPERTY_NORMALIZED, static function (array $normalized): array {
            $normalized['post']['title'] = 'Título de respaldo';

            return $normalized;
        });

        $result = (new PropertyUpsertService())->upsert($this->normalized(['post' => ['title' => '']]));

        self::assertTrue($result['ok']);
    }

    // ─── homlity/property/query_args ─────────────────────────────────────

    public function testElFiltroDeConsultaRecibeLosArgumentosYaConstruidos(): void
    {
        $visto = null;

        add_filter(Hooks::FILTER_PROPERTY_QUERY_ARGS, static function (array $args) use (&$visto): array {
            $visto = $args;

            return $args;
        });

        (new PropertySearchService())->buildQueryArgs(['per_page' => 24]);

        self::assertIsArray($visto);
        self::assertSame('property', $visto['post_type']);
        self::assertSame(24, $visto['posts_per_page']);
    }

    public function testElFiltroDeConsultaPuedeCambiarLosArgumentos(): void
    {
        add_filter(Hooks::FILTER_PROPERTY_QUERY_ARGS, static function (array $args): array {
            $args['posts_per_page'] = 3;

            return $args;
        });

        $args = (new PropertySearchService())->buildQueryArgs([]);

        self::assertSame(3, $args['posts_per_page']);
    }

    public function testElFiltroDeConsultaRecibeLosParametrosDeLaPeticion(): void
    {
        $visto = null;

        add_filter(
            Hooks::FILTER_PROPERTY_QUERY_ARGS,
            static function (array $args, array $params) use (&$visto): array {
                $visto = $params;

                return $args;
            },
            10,
            2
        );

        (new PropertySearchService())->buildQueryArgs(['per_page' => 5, 'orderby' => 'title']);

        self::assertSame(['per_page' => 5, 'orderby' => 'title'], $visto);
    }

    public function testUnFiltroDeConsultaQueDevuelveBasuraNoRompeLaBusqueda(): void
    {
        add_filter(Hooks::FILTER_PROPERTY_QUERY_ARGS, static fn() => 'no soy un array');

        $args = (new PropertySearchService())->buildQueryArgs([]);

        self::assertIsArray($args);
        self::assertSame('property', $args['post_type']);
    }

    public function testSinFiltrosLaBusquedaSigueDevolviendoLoDeSiempre(): void
    {
        $args = (new PropertySearchService())->buildQueryArgs([]);

        self::assertSame('property', $args['post_type']);
        self::assertSame('publish', $args['post_status']);
        self::assertSame(12, $args['posts_per_page']);
    }
}
