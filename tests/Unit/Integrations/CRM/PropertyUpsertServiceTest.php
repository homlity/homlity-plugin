<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations\CRM;

use Homlity\PluginInmobiliario\Integrations\CRM\PropertyUpsertService;
use Homlity\PluginInmobiliario\Integrations\CRM\Repository\SyncIndexRepository;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * La escritura de un inmueble que llega de un CRM.
 *
 * Es el punto de entrada de todo lo que sincroniza el plugin, y el que más
 * daño puede hacer: si la deduplicación falla, cada pase crea un inmueble
 * nuevo y el catálogo se llena de copias; si acierta de más, sobrescribe un
 * inmueble que no era. Ninguna de las dos cosas lanza un error.
 */
final class PropertyUpsertServiceTest extends TestCase
{
    private PropertyUpsertService $service;

    protected function setUp(): void
    {
        parent::setUp();
        WpStubs::$registeredTaxonomies = [
            PropertyTaxonomies::TAXONOMY_OPERATION,
            PropertyTaxonomies::TAXONOMY_TYPE,
            PropertyTaxonomies::TAXONOMY_CATEGORY,
            PropertyTaxonomies::TAXONOMY_FEATURE,
            PropertyTaxonomies::TAXONOMY_COUNTRY,
            PropertyTaxonomies::TAXONOMY_STATE,
            PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            PropertyTaxonomies::TAXONOMY_LOCATION,
            PropertyTaxonomies::TAXONOMY_NEARBY,
            PropertyTaxonomies::TAXONOMY_TAG,
        ];
        $this->service = new PropertyUpsertService();
    }

    /** La carga normalizada mínima que acepta el servicio. */
    private function normalized(array $overrides = []): array
    {
        return array_replace_recursive([
            'external' => ['source' => 'wasi', 'id' => 'EXT-77'],
            'post'     => ['title' => 'Apartamento en El Poblado'],
        ], $overrides);
    }

    private function meta(int $postId, string $key): mixed
    {
        return get_post_meta($postId, $key, true);
    }

    /** Términos asignados al post en una taxonomía. */
    private function terminosDe(int $postId, string $taxonomy): array
    {
        return WpStubs::$objectTerms[$postId][$taxonomy] ?? [];
    }

    /** Nombres de los términos asignados, para leerlos sin depender de los ids. */
    private function nombresDe(int $postId, string $taxonomy): array
    {
        $nombres = array_map(
            static fn(int $id): string => WpStubs::$terms[$taxonomy][$id]->name ?? '',
            $this->terminosDe($postId, $taxonomy)
        );
        sort($nombres);

        return $nombres;
    }

    private function filasDelIndice(): array
    {
        return $GLOBALS['wpdb']->engine->rows('wp_' . SyncIndexRepository::TABLE);
    }

    // ── Validación de entrada ────────────────────────────────────────────────

    /**
     * Sin origen o sin identificador externo no hay forma de reconocer el
     * inmueble en la siguiente sincronización: cada pase crearía uno nuevo.
     * Mejor rechazar que crear basura.
     */
    public function testSinOrigenOSinIdentificadorExternoSeRechaza(): void
    {
        foreach ([
            'sin origen'         => ['external' => ['source' => '', 'id' => 'EXT-77']],
            'sin identificador'  => ['external' => ['source' => 'wasi', 'id' => '']],
            'sin bloque externo' => ['external' => null],
        ] as $caso => $override) {
            $result = $this->service->upsert(array_replace($this->normalized(), $override));

            self::assertFalse($result['ok'], $caso);
            self::assertStringContainsString('external', $result['error'], $caso);
        }

        self::assertSame([], WpStubs::$postObjects, 'no se creó ningún post');
    }

    /** Un inmueble sin título saldría en el listado como una fila en blanco. */
    public function testSinTituloSeRechaza(): void
    {
        $result = $this->service->upsert($this->normalized(['post' => ['title' => '']]));

        self::assertFalse($result['ok']);
        self::assertStringContainsString('post.title', $result['error']);
        self::assertSame([], WpStubs::$postObjects);
    }

    /** Un fallo al guardar tiene que propagarse, no devolverse como éxito. */
    public function testUnErrorAlInsertarSePropaga(): void
    {
        WpStubs::$postInsertError = 'la base de datos dijo que no';

        $result = $this->service->upsert($this->normalized());

        self::assertFalse($result['ok']);
        self::assertSame('la base de datos dijo que no', $result['error']);
        self::assertSame([], $this->filasDelIndice(), 'tampoco se anota en el índice');
        // Lo importante: no se escribe nada en NINGÚN post. Casteando el
        // WP_Error a entero se obtiene 1, y el servicio acababa volcando las
        // metas del inmueble sobre el post con ID 1 del sitio.
        self::assertSame([], WpStubs::$postMeta, 'ningún post recibe metadatos');
        self::assertSame([], WpStubs::$objectTerms, 'ningún post recibe términos');
    }

    // ── Alta ─────────────────────────────────────────────────────────────────

    public function testUnInmuebleNuevoSeCreaConSusDatosBasicos(): void
    {
        $result = $this->service->upsert($this->normalized(['post' => [
            'title'             => 'Apartamento en El Poblado',
            'description'       => '<p>Amplio y luminoso.</p><script>alert(1)</script>',
            'short_description' => 'Amplio y luminoso',
        ]]));

        self::assertTrue($result['ok']);
        $postId = $result['post_id'];
        self::assertSame(PropertyPostType::POST_TYPE, WpStubs::$postObjects[$postId]->post_type);
        self::assertSame('Apartamento en El Poblado', WpStubs::$postTitles[$postId]);
        self::assertStringContainsString('Amplio y luminoso', WpStubs::$postContent[$postId]);
        self::assertStringNotContainsString('<script>', WpStubs::$postContent[$postId]);
    }

    /**
     * El estado llega del CRM y acaba en la base de datos. Un valor inventado
     * dejaría el inmueble en un estado que WordPress no consulta nunca: ni
     * publicado ni borrador, sencillamente invisible.
     */
    public function testUnEstadoDesconocidoSePublica(): void
    {
        foreach (['publish', 'draft', 'pending', 'private'] as $estado) {
            $result = $this->service->upsert($this->normalized([
                'external' => ['id' => 'EXT-' . $estado],
                'post'     => ['status' => $estado],
            ]));
            self::assertSame($estado, WpStubs::$postStatuses[$result['post_id']], $estado);
        }

        $result = $this->service->upsert($this->normalized([
            'external' => ['id' => 'EXT-raro'],
            'post'     => ['status' => 'estado-inventado'],
        ]));
        self::assertSame('publish', WpStubs::$postStatuses[$result['post_id']]);
    }

    // ── Deduplicación ────────────────────────────────────────────────────────

    /**
     * El caso que más daño hace: la segunda sincronización tiene que
     * actualizar, no crear. Con el índice de sincronización ya poblado es una
     * consulta directa a la tabla propia.
     */
    public function testLaSegundaSincronizacionActualizaElMismoInmueble(): void
    {
        $primero = $this->service->upsert($this->normalized());
        $segundo = $this->service->upsert($this->normalized(['post' => ['title' => 'Título actualizado']]));

        self::assertSame($primero['post_id'], $segundo['post_id']);
        self::assertCount(1, WpStubs::$postObjects);
        self::assertSame('Título actualizado', WpStubs::$postTitles[$primero['post_id']]);
        self::assertCount(1, $this->filasDelIndice());
    }

    /**
     * Si el índice se pierde —una migración, una tabla truncada—, todavía hay
     * un post con las metas de origen. Buscarlo evita duplicar el catálogo
     * entero en la siguiente sincronización.
     */
    public function testSinIndiceElInmuebleSeReconocePorSusMetasDeOrigen(): void
    {
        $primero = $this->service->upsert($this->normalized());
        $GLOBALS['wpdb']->engine->delete('wp_' . SyncIndexRepository::TABLE, []);
        WpStubs::$queryResolver = static fn(array $args): array => ['posts' => [$primero['post_id']]];

        $segundo = $this->service->upsert($this->normalized());

        self::assertSame($primero['post_id'], $segundo['post_id']);
        self::assertCount(1, WpStubs::$postObjects);
    }

    /**
     * Última red: el mismo inmueble importado antes con otro identificador
     * externo —un cambio de CRM— se reconoce por su código.
     */
    public function testUnInmuebleConElMismoCodigoNoSeDuplica(): void
    {
        $existente = wp_insert_post(['post_type' => PropertyPostType::POST_TYPE, 'post_title' => 'Ya estaba']);
        update_post_meta($existente, '_property_code', 'HOM-123');
        // El índice no lo conoce y la búsqueda por metas de origen no lo
        // encuentra: sólo queda el código.
        WpStubs::$queryResolver = static fn(array $args): array => [
            'posts' => ($args['meta_query'][0]['key'] ?? '') === '_property_code' ? [$existente] : [],
        ];

        $result = $this->service->upsert($this->normalized(['metrics' => ['code' => 'HOM-123']]));

        self::assertSame($existente, $result['post_id']);
        self::assertCount(1, WpStubs::$postObjects);
    }

    /** La búsqueda por metas mira también los borradores. */
    public function testLaBusquedaDeDuplicadosIncluyeLosNoPublicados(): void
    {
        $consultas = [];
        WpStubs::$queryResolver = static function (array $args) use (&$consultas): array {
            $consultas[] = $args;

            return ['posts' => []];
        };

        $this->service->upsert($this->normalized(['metrics' => ['code' => 'HOM-123']]));

        // Las dos búsquedas —por metas de origen y por código— tienen que mirar
        // los borradores: un inmueble despublicado sigue existiendo, y no verlo
        // es exactamente lo que produce el duplicado.
        foreach ($consultas as $indice => $consulta) {
            self::assertSame(['publish', 'pending', 'draft', 'private'], $consulta['post_status'], (string) $indice);
        }
        self::assertCount(2, $consultas, 'se sondean las dos vías');
    }

    // ── Metadatos ────────────────────────────────────────────────────────────

    public function testLosCamposNormalizadosSeGuardanEnSusMetas(): void
    {
        $result = $this->service->upsert($this->normalized([
            'pricing'  => ['sale_price' => '250000000', 'sale_currency' => 'usd'],
            'metrics'  => ['bedrooms' => 3, 'area' => '85.5'],
            'location' => ['address' => 'Calle 10 # 40-20'],
        ]));

        $postId = $result['post_id'];
        self::assertSame('250000000', $this->meta($postId, '_property_price_sale'));
        self::assertSame('USD', $this->meta($postId, '_property_currency_sale'), 'la moneda se normaliza');
        self::assertSame('3', $this->meta($postId, '_property_bedrooms'));
        self::assertSame('Calle 10 # 40-20', $this->meta($postId, '_property_address'));
    }

    /** Sin moneda el precio quedaría sin unidad; se asume la del sitio. */
    public function testSinMonedaSeGuardaLaPredeterminada(): void
    {
        $result = $this->service->upsert($this->normalized(['pricing' => ['sale_price' => '250000000']]));

        self::assertSame('COP', $this->meta($result['post_id'], '_property_currency_sale'));
    }

    /**
     * Una ficha parcial no puede vaciar lo que ya había: es lo que pasa cuando
     * la API responde con menos campos de los habituales.
     */
    public function testUnCampoAusenteNoBorraElValorGuardado(): void
    {
        $primero = $this->service->upsert($this->normalized(['pricing' => ['sale_price' => '250000000']]));
        $this->service->upsert($this->normalized());

        self::assertSame('250000000', $this->meta($primero['post_id'], '_property_price_sale'));
    }

    public function testLosBooleanosSeGuardanComoUnoOCero(): void
    {
        $result = $this->service->upsert($this->normalized([
            'pricing' => ['admin_included' => true, 'negotiable' => false],
        ]));

        self::assertSame('1', $this->meta($result['post_id'], '_property_admin_included'));
        self::assertSame('0', $this->meta($result['post_id'], '_property_negotiable'));
    }

    public function testSeGuardaLaTrazaDelOrigen(): void
    {
        $result = $this->service->upsert($this->normalized());
        $postId = $result['post_id'];

        self::assertSame('wasi', $this->meta($postId, '_property_external_source'));
        self::assertSame('EXT-77', $this->meta($postId, '_property_external_id'));
        self::assertNotSame('', $this->meta($postId, '_property_last_sync_at'));
    }

    /** El payload crudo se guarda para poder depurar una sincronización rara. */
    public function testElPayloadCrudoSeGuardaComoJson(): void
    {
        $result = $this->service->upsert($this->normalized([
            'external' => ['raw' => ['id_property' => 77, 'title' => 'Original']],
        ]));

        self::assertSame(
            ['id_property' => 77, 'title' => 'Original'],
            json_decode((string) $this->meta($result['post_id'], '_property_sync_payload'), true)
        );
    }

    // ── Multimedia ───────────────────────────────────────────────────────────

    public function testLasGaleriasSeGuardanComoListasDeUrl(): void
    {
        $result = $this->service->upsert($this->normalized(['media' => [
            'gallery'  => ['https://img.test/1.jpg', '', 'https://img.test/2.jpg'],
            'videos'   => ['https://video.test/1'],
            'tour_360' => ['https://tour.test/1'],
        ]]));

        $postId = $result['post_id'];
        self::assertSame(
            ['https://img.test/1.jpg', 'https://img.test/2.jpg'],
            $this->meta($postId, '_property_gallery'),
            'los huecos se descartan y la lista se renumera'
        );
        self::assertSame(['https://video.test/1'], $this->meta($postId, '_property_videos'));
        self::assertSame(['https://tour.test/1'], $this->meta($postId, '_property_tour_360'));
    }

    /** Una respuesta sin fotos no puede dejar el inmueble sin galería. */
    public function testUnaRespuestaSinFotosNoBorraLaGaleria(): void
    {
        $primero = $this->service->upsert($this->normalized([
            'media' => ['gallery' => ['https://img.test/1.jpg']],
        ]));
        $this->service->upsert($this->normalized());

        self::assertSame(['https://img.test/1.jpg'], $this->meta($primero['post_id'], '_property_gallery'));
    }

    // ── Taxonomías ───────────────────────────────────────────────────────────

    /** Etiquetas y puntos de interés se crean tal cual, sin homologar. */
    public function testLasTaxonomiasDirectasSeAsignan(): void
    {
        $result = $this->service->upsert($this->normalized(['taxonomy' => [
            PropertyTaxonomies::TAXONOMY_TAG    => ['Oportunidad', 'Estrenar'],
            PropertyTaxonomies::TAXONOMY_NEARBY => ['Parque Lleras'],
        ]]));

        $postId = $result['post_id'];
        self::assertSame(['Estrenar', 'Oportunidad'], $this->nombresDe($postId, PropertyTaxonomies::TAXONOMY_TAG));
        self::assertSame(['Parque Lleras'], $this->nombresDe($postId, PropertyTaxonomies::TAXONOMY_NEARBY));
    }

    /**
     * Tipo, operación, categoría y características pasan por homologación, que
     * es lo que evita un "Apartamento" por cada CRM conectado.
     */
    public function testDosCrmDistintosCompartenLosTerminosHomologados(): void
    {
        $wasi = $this->service->upsert($this->normalized(['taxonomy' => [
            PropertyTaxonomies::TAXONOMY_TYPE => ['Apartamento'],
        ]]));
        $simi = $this->service->upsert($this->normalized([
            'external' => ['source' => 'simi', 'id' => 'S-1'],
            'taxonomy' => [PropertyTaxonomies::TAXONOMY_TYPE => ['Apartamento']],
        ]));

        self::assertSame(
            $this->terminosDe($wasi['post_id'], PropertyTaxonomies::TAXONOMY_TYPE),
            $this->terminosDe($simi['post_id'], PropertyTaxonomies::TAXONOMY_TYPE)
        );
        self::assertCount(1, WpStubs::$terms[PropertyTaxonomies::TAXONOMY_TYPE]);
    }

    public function testLasCaracteristicasSeAsignanTodas(): void
    {
        $result = $this->service->upsert($this->normalized(['taxonomy' => [
            PropertyTaxonomies::TAXONOMY_FEATURE => ['Piscina', 'Gimnasio', ''],
        ]]));

        self::assertSame(
            ['Gimnasio', 'Piscina'],
            $this->nombresDe($result['post_id'], PropertyTaxonomies::TAXONOMY_FEATURE)
        );
    }

    // ── Jerarquía geográfica ─────────────────────────────────────────────────

    private function conGeografia(array $override = []): array
    {
        return $this->service->upsert($this->normalized(array_replace_recursive([
            'taxonomy' => [
                PropertyTaxonomies::TAXONOMY_COUNTRY      => ['Colombia'],
                PropertyTaxonomies::TAXONOMY_STATE        => ['Antioquia'],
                PropertyTaxonomies::TAXONOMY_CITY         => ['Medellín'],
                PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => ['El Poblado'],
            ],
        ], $override)));
    }

    public function testLaJerarquiaGeograficaSeAsignaNivelAAivel(): void
    {
        $result = $this->conGeografia();
        $postId = $result['post_id'];

        self::assertSame(['Colombia'], $this->nombresDe($postId, PropertyTaxonomies::TAXONOMY_COUNTRY));
        self::assertSame(['Antioquia'], $this->nombresDe($postId, PropertyTaxonomies::TAXONOMY_STATE));
        self::assertSame(['Medellín'], $this->nombresDe($postId, PropertyTaxonomies::TAXONOMY_CITY));
        self::assertSame(['El Poblado'], $this->nombresDe($postId, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD));
    }

    /** Y además todos los niveles se agrupan en la taxonomía de ubicación. */
    public function testTodosLosNivelesSeReflejanEnLaTaxonomiaDeUbicacion(): void
    {
        $postId = $this->conGeografia()['post_id'];

        self::assertCount(4, $this->terminosDe($postId, PropertyTaxonomies::TAXONOMY_LOCATION));
    }

    /** Cada nivel cuelga del anterior: sin eso el árbol de zonas queda plano. */
    public function testCadaNivelGeograficoCuelgaDelAnterior(): void
    {
        $postId = $this->conGeografia()['post_id'];

        $paisId = $this->terminosDe($postId, PropertyTaxonomies::TAXONOMY_COUNTRY)[0];
        $estadoId = $this->terminosDe($postId, PropertyTaxonomies::TAXONOMY_STATE)[0];
        $ciudadId = $this->terminosDe($postId, PropertyTaxonomies::TAXONOMY_CITY)[0];
        $barrioId = $this->terminosDe($postId, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD)[0];

        self::assertSame($paisId, WpStubs::$terms[PropertyTaxonomies::TAXONOMY_STATE][$estadoId]->parent);
        self::assertSame($estadoId, WpStubs::$terms[PropertyTaxonomies::TAXONOMY_CITY][$ciudadId]->parent);
        self::assertSame($ciudadId, WpStubs::$terms[PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD][$barrioId]->parent);
    }

    /** Los antepasados quedan también en term meta, para consultarlos sin recorrer el árbol. */
    public function testCadaNivelRegistraSusAntepasados(): void
    {
        $postId = $this->conGeografia()['post_id'];
        $paisId = $this->terminosDe($postId, PropertyTaxonomies::TAXONOMY_COUNTRY)[0];
        $estadoId = $this->terminosDe($postId, PropertyTaxonomies::TAXONOMY_STATE)[0];
        $ciudadId = $this->terminosDe($postId, PropertyTaxonomies::TAXONOMY_CITY)[0];
        $barrioId = $this->terminosDe($postId, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD)[0];

        self::assertSame($paisId, WpStubs::$termMeta[$estadoId]['_parent_country']);
        self::assertSame($estadoId, WpStubs::$termMeta[$ciudadId]['_parent_state']);
        self::assertSame($ciudadId, WpStubs::$termMeta[$barrioId]['_parent_city']);
        self::assertSame($paisId, WpStubs::$termMeta[$barrioId]['_parent_country']);
    }

    /**
     * "San Antonio" es municipio en varios departamentos. Si el identificador
     * de homologación no llevara el estado, el primero en sincronizarse se
     * quedaría con el mapeo y los inmuebles del otro acabarían en la ciudad
     * equivocada.
     */
    public function testLaMismaCiudadEnDosEstadosSonTerminosDistintos(): void
    {
        $enAntioquia = $this->conGeografia([
            'taxonomy' => [
                PropertyTaxonomies::TAXONOMY_CITY         => ['San Antonio'],
                PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => [],
            ],
        ]);
        $enTolima = $this->conGeografia([
            'external' => ['id' => 'EXT-79'],
            'taxonomy' => [
                PropertyTaxonomies::TAXONOMY_STATE        => ['Tolima'],
                PropertyTaxonomies::TAXONOMY_CITY         => ['San Antonio'],
                PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => [],
            ],
        ]);

        self::assertNotSame(
            $this->terminosDe($enAntioquia['post_id'], PropertyTaxonomies::TAXONOMY_CITY),
            $this->terminosDe($enTolima['post_id'], PropertyTaxonomies::TAXONOMY_CITY)
        );
    }

    /**
     * "El Centro" de Medellín y "El Centro" de Cali no son el mismo barrio. El
     * identificador de origen incluye el nivel superior justamente para eso.
     */
    public function testElMismoBarrioEnDosCiudadesSonTerminosDistintos(): void
    {
        $enMedellin = $this->conGeografia([
            'taxonomy' => [PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => ['El Centro']],
        ]);
        $enCali = $this->conGeografia([
            'external' => ['id' => 'EXT-78'],
            'taxonomy' => [
                PropertyTaxonomies::TAXONOMY_STATE        => ['Valle del Cauca'],
                PropertyTaxonomies::TAXONOMY_CITY         => ['Cali'],
                PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD => ['El Centro'],
            ],
        ]);

        self::assertNotSame(
            $this->terminosDe($enMedellin['post_id'], PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD),
            $this->terminosDe($enCali['post_id'], PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD)
        );
    }

    /** Con sólo ciudad, los niveles ausentes no inventan términos vacíos. */
    public function testLosNivelesGeograficosAusentesNoCreanTerminos(): void
    {
        $result = $this->service->upsert($this->normalized(['taxonomy' => [
            PropertyTaxonomies::TAXONOMY_CITY => ['Medellín'],
        ]]));

        self::assertSame(['Medellín'], $this->nombresDe($result['post_id'], PropertyTaxonomies::TAXONOMY_CITY));
        self::assertSame([], $this->terminosDe($result['post_id'], PropertyTaxonomies::TAXONOMY_COUNTRY));
        self::assertArrayNotHasKey(PropertyTaxonomies::TAXONOMY_STATE, WpStubs::$terms);
    }

    // ── Asesor ───────────────────────────────────────────────────────────────

    public function testElAsesorSeCreaYSeEnlazaAlInmueble(): void
    {
        $result = $this->service->upsert($this->normalized(['advisor' => [
            'external_id' => 'A-1',
            'email'       => 'joquendo@royal.test',
            'name'        => 'Juan Oquendo',
            'phone'       => '+57 300 000 0000',
        ]]));

        $userId = (int) $this->meta($result['post_id'], '_property_agent_id');
        self::assertGreaterThan(0, $userId);
        self::assertSame('joquendo@royal.test', WpStubs::$users[$userId]->user_email);
        self::assertSame('A-1', WpStubs::$userMeta[$userId]['_homlity_external_advisor_id']);
        self::assertSame('wasi', WpStubs::$userMeta[$userId]['_homlity_source_key']);
    }

    /** Dos inmuebles del mismo asesor no pueden crear dos usuarios. */
    public function testDosInmueblesDelMismoAsesorComparteUsuario(): void
    {
        $advisor = ['external_id' => 'A-1', 'email' => 'joquendo@royal.test', 'name' => 'Juan Oquendo'];
        $primero = $this->service->upsert($this->normalized(['advisor' => $advisor]));
        $segundo = $this->service->upsert($this->normalized([
            'external' => ['id' => 'EXT-78'],
            'advisor'  => $advisor,
        ]));

        self::assertSame(
            $this->meta($primero['post_id'], '_property_agent_id'),
            $this->meta($segundo['post_id'], '_property_agent_id')
        );
        self::assertCount(1, WpStubs::$users);
    }

    /** Sin datos de asesor no se toca nada: el inmueble puede no tener uno. */
    public function testSinDatosDeAsesorNoSeCreaNingunUsuario(): void
    {
        $result = $this->service->upsert($this->normalized(['advisor' => ['phone' => '+57 300 000 0000']]));

        self::assertSame([], WpStubs::$users);
        self::assertSame('', $this->meta($result['post_id'], '_property_agent_id'));
    }

    // ── Índice de sincronización ─────────────────────────────────────────────

    public function testElIndiceDeSincronizacionRegistraElInmueble(): void
    {
        $result = $this->service->upsert($this->normalized([
            'external' => ['hash' => 'abc123', 'updated_at' => '2026-01-15 10:00:00'],
        ]));

        $fila = $this->filasDelIndice()[0];
        self::assertSame('wasi', $fila['source_key']);
        self::assertSame('EXT-77', $fila['external_id']);
        self::assertSame($result['post_id'], $fila['post_id']);
        self::assertSame('abc123', $fila['external_hash']);
        self::assertSame('synced', $fila['sync_status']);
        self::assertSame('2026-01-15 10:00:00', $fila['last_source_updated_at']);
    }

    /** Una fecha que no se puede interpretar se guarda como nula, no como basura. */
    public function testUnaFechaDeOrigenIlegibleSeGuardaComoNula(): void
    {
        $this->service->upsert($this->normalized(['external' => ['updated_at' => 'ayer por la tarde']]));

        self::assertNull($this->filasDelIndice()[0]['last_source_updated_at']);
    }

    /** El índice se actualiza, no se duplica, en cada sincronización. */
    public function testElIndiceSeActualizaEnLaSegundaPasada(): void
    {
        $this->service->upsert($this->normalized(['external' => ['hash' => 'primero']]));
        $this->service->upsert($this->normalized(['external' => ['hash' => 'segundo']]));

        self::assertCount(1, $this->filasDelIndice());
        self::assertSame('segundo', $this->filasDelIndice()[0]['external_hash']);
    }

    // ── Metas heredadas ──────────────────────────────────────────────────────

    /** Se siguen escribiendo por compatibilidad con lo que lee homlity-sync. */
    public function testSeMantienenLasMetasDeSincronizacionHeredadas(): void
    {
        $result = $this->service->upsert($this->normalized([
            'external' => ['updated_at' => '2026-01-15 10:00:00'],
        ]));

        $postId = $result['post_id'];
        self::assertSame('EXT-77', $this->meta($postId, '_homlity_sync_id'));
        self::assertSame('homlity-sync', $this->meta($postId, '_homlity_sync_source'));
        self::assertSame('2026-01-15 10:00:00', $this->meta($postId, '_homlity_sync_updated_at'));
    }
}
