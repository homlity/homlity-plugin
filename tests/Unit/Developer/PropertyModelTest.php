<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Developer;

use Homlity\Developer\Homlity;
use Homlity\Developer\Models\Money;
use Homlity\Developer\Models\Property;
use Homlity\Developer\Services\PropertyRepository;
use Homlity\Developer\Support\Hooks;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * El modelo público de inmueble.
 *
 * Existe para que una extensión no tenga que saber que la galería de un
 * inmueble se guarda de cuatro maneras distintas según de dónde vino, ni que
 * hay metadatos con los datos personales del propietario justo al lado de los
 * comerciales. Si este modelo se equivoca, el error sale publicado en un
 * portal externo con el nombre de la inmobiliaria encima.
 */
final class PropertyModelTest extends TestCase
{
    private PropertyRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PropertyRepository();
        Homlity::setPropertyRepository($this->repository);
    }

    protected function tearDown(): void
    {
        Homlity::setPropertyRepository(null);
        parent::tearDown();
    }

    /** Crea un post de inmueble en el WordPress simulado. */
    private function inmueble(int $postId = 10, array $meta = [], array $overrides = []): void
    {
        WpStubs::$postObjects[$postId] = new \WP_Post(array_merge([
            'ID'           => $postId,
            'post_type'    => PropertyPostType::POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => 'Apartamento en El Poblado',
            'post_content' => 'Descripción larga.',
            'post_excerpt' => 'Descripción corta.',
        ], $overrides));

        WpStubs::$postTitles[$postId] = (string) ($overrides['post_title'] ?? 'Apartamento en El Poblado');
        WpStubs::$permalinks[$postId] = 'https://example.test/inmueble/apartamento/';
        WpStubs::$postMeta[$postId]   = $meta;
    }

    private function termino(int $postId, string $taxonomy, string $slug, string $name): void
    {
        WpStubs::$postTerms[$postId][$taxonomy][] = (object) ['slug' => $slug, 'name' => $name];
    }

    // ─── Identidad y estado ──────────────────────────────────────────────

    public function testUnInmuebleSeLeeConSusDatosBasicos(): void
    {
        $this->inmueble(10, ['_property_code' => 'VTAP132']);

        $property = $this->repository->find(10);

        self::assertInstanceOf(Property::class, $property);
        self::assertSame(10, $property->getId());
        self::assertSame('VTAP132', $property->getCode());
        self::assertSame('Apartamento en El Poblado', $property->getTitle());
        self::assertSame('Descripción larga.', $property->getDescription());
        self::assertSame('Descripción corta.', $property->getShortDescription());
        self::assertSame('https://example.test/inmueble/apartamento/', $property->getUrl());
        self::assertSame('publish', $property->getStatus());
    }

    public function testUnPostQueNoEsInmuebleNoSeDevuelve(): void
    {
        $this->inmueble(10, [], ['post_type' => 'page']);

        self::assertNull($this->repository->find(10));
    }

    public function testUnIdInexistenteNoRevienta(): void
    {
        self::assertNull($this->repository->find(0));
        self::assertNull($this->repository->find(-1));
        self::assertNull($this->repository->find(999));
    }

    public function testUnInmuebleSinMetaDeEstadoEstaDisponible(): void
    {
        $this->inmueble(10);

        self::assertTrue($this->repository->find(10)->isAvailable());
    }

    public function testUnInmuebleRetiradoDelMercadoNoEstaDisponible(): void
    {
        $this->inmueble(10, ['_property_status' => 'sold']);

        self::assertFalse($this->repository->find(10)->isAvailable());
    }

    public function testUnInmuebleEnBorradorNoEstaDisponible(): void
    {
        $this->inmueble(10, [], ['post_status' => 'draft']);

        $property = $this->repository->find(10);
        self::assertSame('draft', $property->getStatus());
        self::assertFalse($property->isAvailable());
    }

    public function testLaBanderaDeDisponibilidadAceptaLasFormasQueUsaElSitio(): void
    {
        $this->inmueble(10, ['_property_available' => 'yes']);
        self::assertTrue($this->repository->find(10)->isAvailable());

        $this->inmueble(11, ['_property_available' => '0']);
        self::assertFalse($this->repository->find(11)->isAvailable());
    }

    // ─── Precios ─────────────────────────────────────────────────────────

    public function testUnPrecioDeVentaSeLeeConSuMoneda(): void
    {
        $this->inmueble(10, [
            '_property_price_sale'    => '450000000',
            '_property_currency_sale' => 'cop',
        ]);

        $price = $this->repository->find(10)->getSalePrice();

        self::assertInstanceOf(Money::class, $price);
        self::assertSame(450000000.0, $price->getAmount());
        self::assertSame('COP', $price->getCurrency(), 'La moneda se normaliza a mayúsculas.');
    }

    public function testUnPrecioSinMonedaCaeEnLaMonedaPorDefecto(): void
    {
        $this->inmueble(10, ['_property_price_sale' => '450000000']);

        self::assertSame('COP', $this->repository->find(10)->getSalePrice()->getCurrency());
    }

    public function testUnPrecioEnCeroNoEsUnPrecio(): void
    {
        $this->inmueble(10, ['_property_price_sale' => '0']);

        self::assertNull($this->repository->find(10)->getSalePrice());
        self::assertNull($this->repository->find(10)->getPrice());
        self::assertSame('', $this->repository->find(10)->getCurrency());
    }

    public function testUnPrecioConSeparadoresSeInterpretaComoNumero(): void
    {
        $this->inmueble(10, ['_property_price_rent' => '$ 2.500.000']);

        // Los puntos son separadores de millar en la notación local, no decimales.
        self::assertSame(2500000.0, $this->repository->find(10)->getRentPrice()->getAmount());
    }

    /**
     * @dataProvider notacionesDePrecio
     */
    public function testUnPrecioSeLeeEnCualquieraDeLasNotacionesQueMandanLosCrm(string $raw, ?float $expected): void
    {
        $this->inmueble(10, ['_property_price_sale' => $raw]);

        $price = $this->repository->find(10)->getSalePrice();

        if ($expected === null) {
            self::assertNull($price);

            return;
        }

        self::assertNotNull($price, 'No se interpretó «' . $raw . '».');
        self::assertSame($expected, $price->getAmount());
    }

    /** @return array<string,array{0:string,1:?float}> */
    public static function notacionesDePrecio(): array
    {
        return [
            'entero plano'             => ['450000000', 450000000.0],
            'miles con punto'          => ['2.500.000', 2500000.0],
            'miles con coma'           => ['2,500,000', 2500000.0],
            'con símbolo y espacios'   => ['$ 2.500.000', 2500000.0],
            'anglosajón con decimales' => ['1,234.56', 1234.56],
            'europeo con decimales'    => ['1.234,56', 1234.56],
            'un solo grupo de miles'   => ['2.500', 2500.0],
            'decimal corto'            => ['1234.5', 1234.5],
            'vacío'                    => ['', null],
            'sin dígitos'              => ['a consultar', null],
            'cero'                     => ['0', null],
        ];
    }

    public function testElPrecioPrincipalEsElDeVentaCuandoHayLosDos(): void
    {
        $this->inmueble(10, [
            '_property_price_sale' => '450000000',
            '_property_price_rent' => '2500000',
        ]);

        self::assertSame(450000000.0, $this->repository->find(10)->getPrice()->getAmount());
    }

    public function testElPrecioPrincipalEsElArriendoCuandoNoHayVenta(): void
    {
        $this->inmueble(10, ['_property_price_rent' => '2500000']);

        self::assertSame(2500000.0, $this->repository->find(10)->getPrice()->getAmount());
    }

    // ─── Clasificación ───────────────────────────────────────────────────

    public function testLaOperacionYElTipoSeLeenDeLasTaxonomias(): void
    {
        $this->inmueble(10);
        $this->termino(10, PropertyTaxonomies::TAXONOMY_OPERATION, 'venta', 'Venta');
        $this->termino(10, PropertyTaxonomies::TAXONOMY_TYPE, 'apartamento', 'Apartamento');
        $this->termino(10, PropertyTaxonomies::TAXONOMY_FEATURE, 'piscina', 'Piscina');
        $this->termino(10, PropertyTaxonomies::TAXONOMY_FEATURE, 'ascensor', 'Ascensor');

        $property = $this->repository->find(10);

        self::assertSame('venta', $property->getOperation());
        self::assertSame(['venta'], $property->getOperations());
        self::assertSame('apartamento', $property->getPropertyType());
        self::assertSame(['piscina', 'ascensor'], $property->getFeatures());
    }

    public function testUnInmuebleSinTaxonomiasDevuelveListasVaciasNoNulos(): void
    {
        $this->inmueble(10);

        $property = $this->repository->find(10);

        self::assertSame('', $property->getOperation());
        self::assertSame([], $property->getOperations());
        self::assertSame([], $property->getFeatures());
    }

    // ─── Ubicación ───────────────────────────────────────────────────────

    public function testLaUbicacionCombinaMetadatosYJerarquiaGeografica(): void
    {
        $this->inmueble(10, [
            '_property_address'   => 'Calle 10 # 43-25',
            '_property_latitude'  => '6.2088',
            '_property_longitude' => '-75.5736',
        ]);
        $this->termino(10, PropertyTaxonomies::TAXONOMY_COUNTRY, 'colombia', 'Colombia');
        $this->termino(10, PropertyTaxonomies::TAXONOMY_CITY, 'medellin', 'Medellín');
        $this->termino(10, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, 'el-poblado', 'El Poblado');

        $location = $this->repository->find(10)->getLocation();

        self::assertSame('Calle 10 # 43-25', $location->getAddress());
        self::assertSame('Colombia', $location->getCountry());
        self::assertSame('Medellín', $location->getCity());
        self::assertSame('El Poblado', $location->getNeighborhood());
        self::assertTrue($location->hasCoordinates());
        self::assertSame(6.2088, $location->getLatitude());
    }

    public function testUnaDireccionOcultaNoSeExponeAunqueEsteGuardada(): void
    {
        $this->inmueble(10, [
            '_property_address'            => 'Calle 10 # 43-25',
            '_property_address_complement' => 'Torre 3 Apto 502',
            '_property_show_exact_address' => '0',
        ]);

        $location = $this->repository->find(10)->getLocation();

        self::assertSame('', $location->getAddress());
        self::assertSame('', $location->getAddressComplement());
        self::assertFalse($location->isExactAddressPublic());
    }

    public function testSinCoordenadasLaUbicacionLoDiceEnVezDeDevolverCeros(): void
    {
        $this->inmueble(10);

        $location = $this->repository->find(10)->getLocation();

        self::assertFalse($location->hasCoordinates());
        self::assertNull($location->getLatitude());
        self::assertNull($location->getLongitude());
    }

    // ─── Galería ─────────────────────────────────────────────────────────

    public function testUnaGaleriaGuardadaComoListaDeUrlSeLee(): void
    {
        $this->inmueble(10, [
            '_property_gallery' => ['https://cdn.test/1.jpg', 'https://cdn.test/2.jpg'],
        ]);

        $images = $this->repository->find(10)->getImages();

        self::assertCount(2, $images);
        self::assertSame('https://cdn.test/1.jpg', $images[0]->getUrl());
        self::assertFalse($images[0]->isLocal(), 'Una imagen remota no tiene adjunto.');
        self::assertSame(0, $images[0]->getAttachmentId());
    }

    public function testUnaGaleriaGuardadaComoCsvDeAdjuntosSeLee(): void
    {
        WpStubs::$attachmentUrls = [77 => 'https://example.test/uploads/77.jpg'];
        $this->inmueble(10, ['_property_gallery' => '77']);

        $images = $this->repository->find(10)->getImages();

        self::assertCount(1, $images);
        self::assertSame(77, $images[0]->getAttachmentId());
        self::assertTrue($images[0]->isLocal());
        self::assertSame('https://example.test/uploads/77.jpg', $images[0]->getUrl());
    }

    public function testUnaGaleriaGuardadaComoJsonSeLee(): void
    {
        $this->inmueble(10, [
            '_property_gallery' => '["https:\/\/cdn.test\/a.jpg","https:\/\/cdn.test\/b.jpg"]',
        ]);

        self::assertCount(2, $this->repository->find(10)->getImages());
    }

    public function testUnaGaleriaDeArraysAnidadosSeAplana(): void
    {
        $this->inmueble(10, [
            '_property_gallery' => [['url' => 'https://cdn.test/x.jpg'], ['src' => 'https://cdn.test/y.jpg']],
        ]);

        $images = $this->repository->find(10)->getImages();

        self::assertCount(2, $images);
        self::assertSame('https://cdn.test/y.jpg', $images[1]->getUrl());
    }

    public function testUnAdjuntoBorradoNoDejaUnaImagenSinUrl(): void
    {
        // Un id declarado con URL vacía es un adjunto que ya no existe.
        WpStubs::$attachmentUrls = [77 => ''];
        $this->inmueble(10, ['_property_gallery' => '77']);

        self::assertSame([], $this->repository->find(10)->getImages());
    }

    public function testSinGaleriaSeUsaLaPortadaRemotaDelCrm(): void
    {
        $this->inmueble(10, ['_property_featured_image_url' => 'https://cdn.test/portada.jpg']);

        $images = $this->repository->find(10)->getImages();

        self::assertCount(1, $images);
        self::assertSame('https://cdn.test/portada.jpg', $images[0]->getUrl());
    }

    // ─── Asesor ──────────────────────────────────────────────────────────

    public function testElAsesorSeLeeCuandoElInmuebleTieneUnoAsignado(): void
    {
        $this->inmueble(10, [
            '_property_agent_id'    => '42',
            '_property_agent_name'  => 'Ana Ruiz',
            '_property_agent_email' => 'ana@example.test',
            '_property_agent_phone' => '+573001112233',
        ]);

        $agent = $this->repository->find(10)->getAgent();

        self::assertNotNull($agent);
        self::assertSame(42, $agent->getUserId());
        self::assertSame('Ana Ruiz', $agent->getName());
        self::assertSame('ana@example.test', $agent->getEmail());
    }

    public function testUnInmuebleSinAsesorDevuelveNuloNoUnAsesorVacio(): void
    {
        $this->inmueble(10);

        self::assertNull($this->repository->find(10)->getAgent());
    }

    // ─── Datos que no deben salir ────────────────────────────────────────

    public function testLosDatosPersonalesDelPropietarioNoLleganAlModelo(): void
    {
        $this->inmueble(10, [
            '_property_contact_name'  => 'Propietario Privado',
            '_property_contact_email' => 'privado@example.test',
            '_property_contact_phone' => '+573009998877',
            '_property_identification' => '1020304050',
            '_property_sync_payload'  => '{"token":"secreto"}',
            '_property_code'          => 'VTAP132',
        ]);

        $serialized = (string) wp_json_encode($this->repository->find(10)->toArray());

        self::assertStringNotContainsString('Propietario Privado', $serialized);
        self::assertStringNotContainsString('privado@example.test', $serialized);
        self::assertStringNotContainsString('1020304050', $serialized);
        self::assertStringNotContainsString('secreto', $serialized);
        self::assertStringContainsString('VTAP132', $serialized, 'Los datos comerciales sí salen.');
    }

    // ─── Procedencia ─────────────────────────────────────────────────────

    public function testUnInmuebleSincronizadoDeclaraSuOrigen(): void
    {
        $this->inmueble(10, [
            '_property_external_source' => 'wasi',
            '_property_external_id'     => 'EXT-77',
            '_property_last_sync_at'    => '2026-08-01T10:00:00+00:00',
        ]);

        $property = $this->repository->find(10);

        self::assertTrue($property->isSynced());
        self::assertSame('wasi', $property->getExternalSource());
        self::assertSame('EXT-77', $property->getExternalId());
        self::assertSame('2026-08-01T10:00:00+00:00', $property->getLastSyncedAt());
    }

    public function testUnInmuebleCargadoAManoNoEstaSincronizado(): void
    {
        $this->inmueble(10);

        self::assertFalse($this->repository->find(10)->isSynced());
    }

    // ─── Serialización ───────────────────────────────────────────────────

    public function testElModeloSerializaAJsonSinObjetosSueltos(): void
    {
        $this->inmueble(10, ['_property_price_sale' => '450000000']);

        $array = $this->repository->find(10)->toArray();
        $json  = wp_json_encode($array);

        self::assertIsString($json);
        self::assertIsArray($array['sale_price']);
        self::assertSame(450000000.0, $array['sale_price']['amount']);
        self::assertNull($array['rent_price']);
        self::assertSame([], $array['images']);
    }

    // ─── Búsqueda ────────────────────────────────────────────────────────

    public function testUnCodigoVacioNoBuscaNada(): void
    {
        self::assertNull($this->repository->findByCode(''));
        self::assertNull($this->repository->findByCode('   '));
    }

    public function testUnOrigenIncompletoNoBuscaNada(): void
    {
        self::assertNull($this->repository->findByExternalId('', 'EXT-1'));
        self::assertNull($this->repository->findByExternalId('wasi', ''));
    }

    // ─── Filtro público ──────────────────────────────────────────────────

    public function testElFiltroDeDatosPuedeCambiarLoQueVeLaExtension(): void
    {
        $this->inmueble(10);

        add_filter(Hooks::FILTER_PROPERTY_DATA, static function (array $data): array {
            $data['title'] = 'Título reescrito';

            return $data;
        });

        self::assertSame('Título reescrito', $this->repository->find(10)->getTitle());
    }

    public function testUnFiltroQueDevuelveBasuraSeIgnora(): void
    {
        $this->inmueble(10);

        add_filter(Hooks::FILTER_PROPERTY_DATA, static fn() => 'esto no es un array');

        self::assertSame('Apartamento en El Poblado', $this->repository->find(10)->getTitle());
    }
}
