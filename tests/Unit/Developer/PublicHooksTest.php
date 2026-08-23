<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Developer;

use Homlity\Developer\Events\PropertyChanges;
use Homlity\Developer\Events\PropertyContext;
use Homlity\Developer\Homlity;
use Homlity\Developer\Models\Property;
use Homlity\Developer\Support\Hooks;
use Homlity\PluginInmobiliario\Core\DeveloperApiService;
use Homlity\PluginInmobiliario\Core\PropertyEventDispatcher;
use Homlity\PluginInmobiliario\Integrations\CRM\PropertyUpsertService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Las acciones públicas del ciclo de vida de un inmueble.
 *
 * Son el contrato entero de la Developer API: una extensión que empuja
 * inmuebles a un portal externo no hace otra cosa que escuchar aquí. Que un
 * hook no dispare significa un inmueble que nunca se publica; que dispare
 * antes de tiempo significa publicar un inmueble a medio escribir, sin fotos
 * ni precio.
 */
final class PublicHooksTest extends TestCase
{
    private PropertyUpsertService $upsert;

    protected function setUp(): void
    {
        parent::setUp();
        PropertyEventDispatcher::reset();
        Homlity::setPropertyRepository(null);

        WpStubs::$registeredTaxonomies = [
            PropertyTaxonomies::TAXONOMY_OPERATION,
            PropertyTaxonomies::TAXONOMY_TYPE,
            PropertyTaxonomies::TAXONOMY_FEATURE,
            PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            PropertyTaxonomies::TAXONOMY_LOCATION,
        ];

        $this->upsert = new PropertyUpsertService();
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

    /** @return array<int,array<int,mixed>> */
    private function disparos(string $hook): array
    {
        return WpStubs::$actions[$hook] ?? [];
    }

    // ─── Creación ────────────────────────────────────────────────────────

    public function testCrearUnInmuebleDisparaLaAccionDeCreacion(): void
    {
        $result = $this->upsert->upsert($this->normalized());

        self::assertTrue($result['ok']);
        self::assertCount(1, $this->disparos(Hooks::PROPERTY_CREATED));
        self::assertSame([], $this->disparos(Hooks::PROPERTY_UPDATED));
    }

    public function testLaAccionDeCreacionEntregaElModeloPublicoYaGuardado(): void
    {
        $this->upsert->upsert($this->normalized([
            'metrics' => ['code' => 'VTAP132', 'bedrooms' => 3],
            'pricing' => ['sale_price' => '450000000', 'sale_currency' => 'COP'],
        ]));

        [$property, $context] = $this->disparos(Hooks::PROPERTY_CREATED)[0];

        self::assertInstanceOf(Property::class, $property);
        self::assertSame('Apartamento en El Poblado', $property->getTitle());
        self::assertSame('VTAP132', $property->getCode(), 'El código ya está escrito cuando dispara el hook.');
        self::assertSame(3, $property->getBedrooms());
        self::assertSame(450000000.0, $property->getSalePrice()->getAmount());

        self::assertInstanceOf(PropertyContext::class, $context);
        self::assertTrue($context->isNew());
        self::assertSame('wasi', $context->getSource());
    }

    public function testUnaCargaInvalidaNoDisparaNingunaAccion(): void
    {
        $this->upsert->upsert(['external' => ['source' => '', 'id' => '']]);
        $this->upsert->upsert($this->normalized(['post' => ['title' => '']]));

        self::assertSame([], $this->disparos(Hooks::PROPERTY_CREATED));
        self::assertSame([], $this->disparos(Hooks::PROPERTY_UPDATED));
    }

    // ─── Actualización ───────────────────────────────────────────────────

    public function testActualizarUnInmuebleDisparaLaAccionDeActualizacion(): void
    {
        $this->upsert->upsert($this->normalized());
        WpStubs::$actions = [];

        $this->upsert->upsert($this->normalized(['post' => ['title' => 'Otro título']]));

        self::assertSame([], $this->disparos(Hooks::PROPERTY_CREATED));
        self::assertCount(1, $this->disparos(Hooks::PROPERTY_UPDATED));
    }

    public function testLaActualizacionEntregaUnDiffRealDeLosCamposQueCambiaron(): void
    {
        $this->upsert->upsert($this->normalized(['pricing' => ['sale_price' => '450000000']]));
        WpStubs::$actions = [];

        $this->upsert->upsert($this->normalized(['pricing' => ['sale_price' => '400000000']]));

        [, $changes] = $this->disparos(Hooks::PROPERTY_UPDATED)[0];

        self::assertInstanceOf(PropertyChanges::class, $changes);
        self::assertFalse($changes->isEmpty());
        self::assertTrue($changes->has('pricing.sale_price'));
        self::assertSame('450000000', $changes->previous('pricing.sale_price'));
        self::assertSame('400000000', $changes->current('pricing.sale_price'));
        self::assertTrue($changes->hasGroup('pricing'));
        self::assertFalse($changes->hasGroup('metrics'));
    }

    public function testReenviarElMismoRegistroDisparaConUnDiffVacio(): void
    {
        // Un CRM que reenvía sin cambios es lo normal; la extensión tiene que
        // poder distinguirlo para no volver a publicar en el portal externo.
        $payload = $this->normalized(['pricing' => ['sale_price' => '450000000']]);

        $this->upsert->upsert($payload);
        WpStubs::$actions = [];
        $this->upsert->upsert($payload);

        [, $changes] = $this->disparos(Hooks::PROPERTY_UPDATED)[0];

        self::assertTrue($changes->isEmpty(), 'Sin cambios reales no debe haber campos en el diff.');
    }

    public function testElTituloCambiadoApareceEnElDiff(): void
    {
        $this->upsert->upsert($this->normalized());
        WpStubs::$actions = [];

        $this->upsert->upsert($this->normalized(['post' => ['title' => 'Título nuevo']]));

        [, $changes] = $this->disparos(Hooks::PROPERTY_UPDATED)[0];

        self::assertTrue($changes->has('post.title'));
        self::assertSame('Apartamento en El Poblado', $changes->previous('post.title'));
        self::assertSame('Título nuevo', $changes->current('post.title'));
    }

    // ─── Sincronización ──────────────────────────────────────────────────

    public function testUnaEscrituraDeCrmTambienAnunciaSincronizacion(): void
    {
        $this->upsert->upsert($this->normalized());

        self::assertCount(1, $this->disparos(Hooks::PROPERTY_SYNCHRONIZED));

        [, , $context] = $this->disparos(Hooks::PROPERTY_SYNCHRONIZED)[0];
        self::assertSame(PropertyContext::ORIGIN_CRM, $context->getOrigin());
        self::assertTrue($context->isExternal());
    }

    public function testUnaConsignacionSeDistingueDeUnaSincronizacionDeCrm(): void
    {
        $this->upsert->upsert($this->normalized(), PropertyContext::ORIGIN_CONSIGNMENT);

        [, , $context] = $this->disparos(Hooks::PROPERTY_SYNCHRONIZED)[0];

        self::assertSame(PropertyContext::ORIGIN_CONSIGNMENT, $context->getOrigin());
        self::assertTrue($context->isExternal());
    }

    public function testUnaEscrituraDeAdministradorNoAnunciaSincronizacion(): void
    {
        $this->upsert->upsert($this->normalized(), PropertyContext::ORIGIN_ADMIN);

        self::assertCount(1, $this->disparos(Hooks::PROPERTY_CREATED));
        self::assertSame([], $this->disparos(Hooks::PROPERTY_SYNCHRONIZED));
    }

    // ─── Galería ─────────────────────────────────────────────────────────

    public function testCambiarLaGaleriaAnunciaQueLasImagenesCambiaron(): void
    {
        $this->upsert->upsert($this->normalized([
            'media' => ['gallery' => ['https://cdn.test/1.jpg']],
        ]));
        WpStubs::$actions = [];

        $this->upsert->upsert($this->normalized([
            'media' => ['gallery' => ['https://cdn.test/1.jpg', 'https://cdn.test/2.jpg']],
        ]));

        self::assertCount(1, $this->disparos(Hooks::PROPERTY_IMAGES_CHANGED));

        [$property] = $this->disparos(Hooks::PROPERTY_IMAGES_CHANGED)[0];
        self::assertCount(2, $property->getImages());
    }

    public function testUnaGaleriaIntactaNoAnunciaCambioDeImagenes(): void
    {
        $payload = $this->normalized(['media' => ['gallery' => ['https://cdn.test/1.jpg']]]);

        $this->upsert->upsert($payload);
        WpStubs::$actions = [];
        $this->upsert->upsert($payload);

        self::assertSame([], $this->disparos(Hooks::PROPERTY_IMAGES_CHANGED));
    }

    // ─── Borrado y estado ────────────────────────────────────────────────

    public function testBorrarUnInmuebleLoAnunciaMientrasTodaviaSePuedeLeer(): void
    {
        $this->upsert->upsert($this->normalized(['metrics' => ['code' => 'VTAP132']]));
        $postId = (int) $this->disparos(Hooks::PROPERTY_CREATED)[0][0]->getId();
        WpStubs::$actions = [];

        (new DeveloperApiService())->announceDeletion($postId);

        self::assertCount(1, $this->disparos(Hooks::PROPERTY_DELETED));

        [$property, $id] = $this->disparos(Hooks::PROPERTY_DELETED)[0];
        self::assertSame($postId, $id);
        self::assertSame('VTAP132', $property->getCode(), 'El inmueble todavía se puede leer entero.');
    }

    public function testBorrarUnPostQueNoEsInmuebleNoAnunciaNada(): void
    {
        WpStubs::$postObjects[500] = new \WP_Post(['ID' => 500, 'post_type' => 'page']);

        (new DeveloperApiService())->announceDeletion(500);

        self::assertSame([], $this->disparos(Hooks::PROPERTY_DELETED));
    }

    public function testDespublicarUnInmuebleAnunciaElCambioDeEstado(): void
    {
        $this->upsert->upsert($this->normalized());
        $postId = (int) $this->disparos(Hooks::PROPERTY_CREATED)[0][0]->getId();
        WpStubs::$actions = [];

        $post = WpStubs::$postObjects[$postId];
        (new DeveloperApiService())->announceStatusChange('draft', 'publish', $post);

        self::assertCount(1, $this->disparos(Hooks::PROPERTY_STATUS_CHANGED));

        [$property, $new, $old] = $this->disparos(Hooks::PROPERTY_STATUS_CHANGED)[0];
        self::assertSame('draft', $new);
        self::assertSame('publish', $old);
        self::assertInstanceOf(Property::class, $property);
    }

    public function testLaCreacionDeUnPostNoSeCuentaComoCambioDeEstado(): void
    {
        $this->upsert->upsert($this->normalized());
        $postId = (int) $this->disparos(Hooks::PROPERTY_CREATED)[0][0]->getId();
        WpStubs::$actions = [];

        (new DeveloperApiService())->announceStatusChange('publish', 'new', WpStubs::$postObjects[$postId]);

        self::assertSame([], $this->disparos(Hooks::PROPERTY_STATUS_CHANGED));
    }

    public function testUnaTransicionSinCambioNoAnunciaNada(): void
    {
        $this->upsert->upsert($this->normalized());
        $postId = (int) $this->disparos(Hooks::PROPERTY_CREATED)[0][0]->getId();
        WpStubs::$actions = [];

        (new DeveloperApiService())->announceStatusChange('publish', 'publish', WpStubs::$postObjects[$postId]);

        self::assertSame([], $this->disparos(Hooks::PROPERTY_STATUS_CHANGED));
    }

    // ─── Ciclo de vida del plugin ────────────────────────────────────────

    public function testElRegistroDeExtensionesAnunciaAperturaYCierre(): void
    {
        Homlity::setExtensionRegistry(null);

        (new DeveloperApiService())->registerExtensions();

        self::assertArrayHasKey(Hooks::EXTENSIONS_REGISTER, WpStubs::$actions);
        self::assertArrayHasKey(Hooks::EXTENSIONS_REGISTERED, WpStubs::$actions);
        self::assertTrue(Homlity::extensions()->isDispatched());

        Homlity::setExtensionRegistry(null);
    }

    public function testLaInicializacionSeAnuncia(): void
    {
        (new DeveloperApiService())->announceInitialized();

        self::assertArrayHasKey(Hooks::INITIALIZED, WpStubs::$actions);
    }

    // ─── Datos que no deben salir ────────────────────────────────────────

    public function testElDiffNoTransportaLosDatosPersonalesDelPropietario(): void
    {
        $this->upsert->upsert($this->normalized([
            'external' => ['raw' => ['contact_email' => 'privado@example.test']],
        ]));
        WpStubs::$actions = [];

        $this->upsert->upsert($this->normalized([
            'external' => ['raw' => ['contact_email' => 'otro@example.test']],
            'post'     => ['title' => 'Cambio para forzar la actualización'],
        ]));

        [, $changes] = $this->disparos(Hooks::PROPERTY_UPDATED)[0];

        self::assertFalse($changes->has('external.raw.contact_email'));
        self::assertStringNotContainsString(
            'privado@example.test',
            (string) wp_json_encode($changes->toArray())
        );
    }

    // ─── Nombres de los hooks ────────────────────────────────────────────

    public function testTodosLosHooksPublicosSiguenLaConvencionConBarras(): void
    {
        foreach (array_merge(Hooks::actions(), Hooks::filters()) as $hook) {
            self::assertStringStartsWith('homlity/', $hook, $hook . ' no sigue la convención.');
            self::assertStringNotContainsString('_', explode('/', $hook)[1] ?? '', $hook);
        }
    }

    public function testNoHayDosHooksPublicosConElMismoNombre(): void
    {
        $all = array_merge(Hooks::actions(), Hooks::filters());

        self::assertSame(count($all), count(array_unique($all)));
    }
}
