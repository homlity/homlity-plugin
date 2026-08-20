<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Homologation;

use Homlity\PluginInmobiliario\Homologation\EntityType;
use Homlity\PluginInmobiliario\Homologation\HomologationRepository;
use Homlity\PluginInmobiliario\Homologation\HomologationService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * El mapeo canónico: lo que hace que dos CRM distintos no dupliquen la
 * taxonomía del sitio.
 *
 * `resolveOrCreate()` **crea términos** cuando no encuentra correspondencia.
 * Un fallo aquí no lanza nada: llena la taxonomía de duplicados —"Apartamento"
 * dos veces, "Medellín" tres— y eso ensucia los filtros del buscador, los
 * archivos y el schema, y deshacerlo a mano es caro. Por eso casi todas las
 * pruebas de abajo comprueban a la vez el término devuelto y **cuántos
 * términos existen** después.
 */
final class HomologationServiceTest extends TestCase
{
    private const CIUDAD = PropertyTaxonomies::TAXONOMY_CITY;
    private const TIPO = PropertyTaxonomies::TAXONOMY_TYPE;

    private HomologationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        WpStubs::$registeredTaxonomies = [self::CIUDAD, self::TIPO, PropertyTaxonomies::TAXONOMY_STATE];
        $this->service = new HomologationService();
    }

    /** Los términos existentes en la taxonomía, por nombre. */
    private function terminos(string $taxonomy): array
    {
        return array_values(array_map(
            static fn(\WP_Term $t): string => $t->name,
            WpStubs::$terms[$taxonomy] ?? []
        ));
    }

    /** Las filas de la tabla de homologación. */
    private function mapeos(): array
    {
        return $GLOBALS['wpdb']->engine->rows('wp_homlity_homologation');
    }

    private function resolver(string $source, string $sourceId, string $name, int $parent = 0, string $taxonomy = self::CIUDAD): int
    {
        return $this->service->resolveOrCreate(
            EntityType::CITY,
            $taxonomy,
            $source,
            $sourceId,
            $name,
            $parent
        );
    }

    // ── Creación y reutilización ─────────────────────────────────────────────

    public function testUnTerminoDesconocidoSeCreaYSeRegistraElMapeo(): void
    {
        $termId = $this->resolver('wasi', 'wasi-105', 'Medellín');

        self::assertGreaterThan(0, $termId);
        self::assertSame(['Medellín'], $this->terminos(self::CIUDAD));

        $mapeo = $this->mapeos()[0];
        self::assertSame(EntityType::CITY, $mapeo['entity_type']);
        self::assertSame('wasi', $mapeo['source']);
        self::assertSame('wasi-105', $mapeo['source_id']);
        self::assertSame($termId, $mapeo['canonical_term_id']);
        self::assertSame('Medellín', $mapeo['canonical_name']);
    }

    /** La segunda sincronización tiene que ser un acierto de mapeo, no una creación. */
    public function testElMismoOrigenDevuelveSiempreElMismoTermino(): void
    {
        $primero = $this->resolver('wasi', 'wasi-105', 'Medellín');
        $segundo = $this->resolver('wasi', 'wasi-105', 'Medellín');

        self::assertSame($primero, $segundo);
        self::assertCount(1, $this->terminos(self::CIUDAD));
        self::assertCount(1, $this->mapeos());
    }

    /**
     * La razón de ser del módulo: dos CRM que llaman igual a la misma ciudad
     * con identificadores distintos apuntan al mismo término del sitio.
     */
    public function testDosCrmDistintosApuntanAlMismoTerminoCanonico(): void
    {
        $wasi = $this->resolver('wasi', 'wasi-105', 'Medellín');
        $simi = $this->resolver('simi', '4407', 'Medellín');

        self::assertSame($wasi, $simi);
        self::assertCount(1, $this->terminos(self::CIUDAD), 'una sola ciudad en la taxonomía');
        self::assertCount(2, $this->mapeos(), 'pero un mapeo por cada CRM');
    }

    /** Y el mismo CRM con dos identificadores para dos ciudades no las funde. */
    public function testDosNombresDistintosProducenDosTerminos(): void
    {
        $medellin = $this->resolver('wasi', 'wasi-105', 'Medellín');
        $cali = $this->resolver('wasi', 'wasi-106', 'Cali');

        self::assertNotSame($medellin, $cali);
        self::assertCount(2, $this->terminos(self::CIUDAD));
    }

    /**
     * Un término que ya existe en WordPress —creado a mano por el comercial—
     * se reutiliza. Crear uno nuevo dejaría dos "Medellín" y repartiría los
     * inmuebles entre los dos.
     */
    public function testUnTerminoCreadoAManoSeReutilizaEnLugarDeDuplicarse(): void
    {
        $existente = WpStubs::setTerm(55, self::CIUDAD, 'medellin', 'Medellín');

        $termId = $this->resolver('wasi', 'wasi-105', 'Medellín');

        self::assertSame($existente->term_id, $termId);
        self::assertCount(1, $this->terminos(self::CIUDAD));
    }

    // ── Jerarquía ────────────────────────────────────────────────────────────

    /**
     * "El Centro" existe en casi todas las ciudades. Sin la restricción de
     * padre, el primero que llegue se lleva los inmuebles de todos.
     */
    public function testElMismoNombreBajoPadresDistintosSonTerminosDistintos(): void
    {
        $enMedellin = $this->resolver('wasi', 'wasi-1', 'El Centro', 501);
        $enCali = $this->resolver('wasi', 'wasi-2', 'El Centro', 502);

        self::assertNotSame($enMedellin, $enCali);
        self::assertCount(2, WpStubs::$terms[self::CIUDAD]);
    }

    public function testElTerminoCreadoConservaSuPadre(): void
    {
        $termId = $this->resolver('wasi', 'wasi-1', 'El Poblado', 501);

        self::assertSame(501, WpStubs::$terms[self::CIUDAD][$termId]->parent);
    }

    /**
     * Sin padre, la búsqueda se amplía a toda la taxonomía: un término que
     * alguien colgó de otro sitio se reutiliza igual.
     */
    public function testSinPadreSeReutilizaUnTerminoAunqueEsteAnidado(): void
    {
        WpStubs::$terms[self::CIUDAD][77] = new \WP_Term(77, self::CIUDAD, 'medellin', 'Medellín', 9);

        self::assertSame(77, $this->resolver('wasi', 'wasi-105', 'Medellín'));
    }

    // ── Mapeos obsoletos ─────────────────────────────────────────────────────

    /**
     * El caso que se da de verdad: alguien borra el término en WordPress y el
     * mapeo se queda apuntando a un id que ya no existe. Si no se detectara,
     * cada inmueble se asignaría a un término fantasma y desaparecería de los
     * filtros.
     */
    public function testUnMapeoQueApuntaAUnTerminoBorradoSeRehace(): void
    {
        $original = $this->resolver('wasi', 'wasi-105', 'Medellín');
        unset(WpStubs::$terms[self::CIUDAD][$original]);
        WpStubs::$cache = []; // el término desaparece entre peticiones

        $nuevo = $this->resolver('wasi', 'wasi-105', 'Medellín');

        self::assertGreaterThan(0, $nuevo);
        self::assertNotSame($original, $nuevo);
        self::assertCount(1, $this->mapeos(), 'el mapeo obsoleto se sustituye, no se acumula');
        self::assertSame($nuevo, $this->mapeos()[0]['canonical_term_id']);
    }

    // ── Guardas ──────────────────────────────────────────────────────────────

    /**
     * Una taxonomía que no está registrada —otro plugin desactivado, un
     * adaptador con una constante vieja— no puede provocar la creación de
     * términos huérfanos.
     */
    public function testUnaTaxonomiaNoRegistradaNoCreaNada(): void
    {
        $termId = $this->resolver('wasi', 'wasi-105', 'Medellín', 0, 'taxonomia_inexistente');

        self::assertSame(0, $termId);
        self::assertSame([], $this->mapeos());
    }

    /** Sin identificador de origen, el nombre normalizado hace de identificador. */
    public function testSinIdentificadorDeOrigenSeUsaElNombreNormalizado(): void
    {
        $this->resolver('wasi', '', 'Medellín');

        self::assertSame('medellin', $this->mapeos()[0]['source_id']);
    }

    /** Y aun así el segundo pase reutiliza el término, sin duplicar. */
    public function testSinIdentificadorDeOrigenNoSeDuplicaElTermino(): void
    {
        $primero = $this->resolver('wasi', '', 'Medellín');
        $segundo = $this->resolver('wasi', '', 'Medellín');

        self::assertSame($primero, $segundo);
        self::assertCount(1, $this->terminos(self::CIUDAD));
    }

    // ── resolveToCanonical ───────────────────────────────────────────────────

    public function testSinMapeoLaResolucionDevuelveNulo(): void
    {
        self::assertNull($this->service->resolveToCanonical(EntityType::CITY, 'wasi', 'wasi-105'));
    }

    public function testConMapeoLaResolucionDevuelveElTermino(): void
    {
        $termId = $this->resolver('wasi', 'wasi-105', 'Medellín');

        self::assertSame($termId, $this->service->resolveToCanonical(EntityType::CITY, 'wasi', 'wasi-105'));
    }

    /** El mapeo es por tipo de entidad: una ciudad y un tipo pueden compartir id. */
    public function testLaResolucionDistingueElTipoDeEntidad(): void
    {
        $this->resolver('wasi', '7', 'Medellín');

        self::assertNotNull($this->service->resolveToCanonical(EntityType::CITY, 'wasi', '7'));
        self::assertNull($this->service->resolveToCanonical(EntityType::TYPE, 'wasi', '7'));
    }

    /** Y por origen: el id 7 de un CRM no es el 7 de otro. */
    public function testLaResolucionDistingueElOrigen(): void
    {
        $this->resolver('wasi', '7', 'Medellín');

        self::assertNull($this->service->resolveToCanonical(EntityType::CITY, 'simi', '7'));
    }

    // ── Registro manual ──────────────────────────────────────────────────────

    /** La corrección a mano desde el panel: apuntar un origen a otro término. */
    public function testElRegistroManualSustituyeElMapeoExistente(): void
    {
        $original = $this->resolver('wasi', 'wasi-105', 'Medellin sin tilde');
        $correcto = WpStubs::setTerm(88, self::CIUDAD, 'medellin', 'Medellín');

        $this->service->register(EntityType::CITY, 'wasi', 'wasi-105', 'Medellin sin tilde', $correcto->term_id);

        self::assertNotSame($original, $correcto->term_id);
        self::assertCount(1, $this->mapeos(), 'se actualiza la fila, no se añade otra');
        self::assertSame(88, $this->mapeos()[0]['canonical_term_id']);
        self::assertSame('Medellín', $this->mapeos()[0]['canonical_name'], 'el nombre canónico se toma del término');
    }

    /**
     * Lo que hace útil la corrección manual: a partir de ella, el mapeo manda
     * sobre el nombre que siga mandando el CRM. Si `resolveOrCreate()` volviera
     * a buscar por nombre, la siguiente sincronización recrearía "Medellin sin
     * tilde" y desharía la corrección en cada pase.
     */
    public function testTrasLaCorreccionManualElCrmSigueApuntandoAlTerminoCorregido(): void
    {
        $this->resolver('wasi', 'wasi-105', 'Medellin sin tilde');
        $correcto = WpStubs::setTerm(88, self::CIUDAD, 'medellin', 'Medellín');
        $this->service->register(EntityType::CITY, 'wasi', 'wasi-105', 'Medellin sin tilde', $correcto->term_id);

        // El CRM vuelve a mandar el nombre mal escrito en la siguiente sincronización.
        $resuelto = $this->resolver('wasi', 'wasi-105', 'Medellin sin tilde');

        self::assertSame(88, $resuelto);
        self::assertSame(88, $this->service->resolveToCanonical(EntityType::CITY, 'wasi', 'wasi-105'));
    }

    public function testElRegistroManualAceptaUnNombreCanonicoExplicito(): void
    {
        $this->service->register(EntityType::CITY, 'wasi', 'wasi-105', 'MDE', 88, 'Medellín', 'medellin');

        self::assertSame('Medellín', $this->mapeos()[0]['canonical_name']);
        self::assertSame('medellin', $this->mapeos()[0]['canonical_slug']);
    }

    public function testBorrarUnMapeoLoQuitaDeLaTabla(): void
    {
        $this->resolver('wasi', 'wasi-105', 'Medellín');
        $id = (int) $this->mapeos()[0]['id'];

        self::assertTrue($this->service->deleteMapping($id));
        self::assertSame([], $this->mapeos());
    }

    public function testBorrarUnMapeoInexistenteNoRompe(): void
    {
        self::assertFalse($this->service->deleteMapping(0));
        self::assertFalse($this->service->deleteMapping(9999));
    }

    // ── Consultas del panel ──────────────────────────────────────────────────

    private function sembrarVarios(): void
    {
        $this->resolver('wasi', 'w1', 'Medellín');
        $this->resolver('wasi', 'w2', 'Cali');
        $this->resolver('simi', 's1', 'Bogotá');
        $this->service->resolveOrCreate(EntityType::TYPE, self::TIPO, 'wasi', 't1', 'Apartamento');
    }

    public function testElTotalCuentaConLosFiltrosPedidos(): void
    {
        $this->sembrarVarios();

        self::assertSame(4, $this->service->getTotal());
        self::assertSame(3, $this->service->getTotal(EntityType::CITY));
        self::assertSame(3, $this->service->getTotal(null, 'wasi'));
        self::assertSame(2, $this->service->getTotal(EntityType::CITY, 'wasi'));
        self::assertSame(0, $this->service->getTotal(EntityType::CITY, 'inexistente'));
    }

    public function testLosOrigenesSeListanSinRepetir(): void
    {
        $this->sembrarVarios();

        self::assertSame(['simi', 'wasi'], $this->service->getSources());
    }

    public function testLasEstadisticasAgrupanPorEntidadYOrigen(): void
    {
        $this->sembrarVarios();

        self::assertSame([
            EntityType::CITY => ['simi' => 1, 'wasi' => 2],
            EntityType::TYPE => ['wasi' => 1],
        ], $this->service->getStats());
    }

    public function testElListadoSePagina(): void
    {
        $this->sembrarVarios();

        self::assertCount(4, $this->service->getMappings());
        self::assertCount(2, $this->service->getMappings(null, null, 1, 2));
        self::assertCount(2, $this->service->getMappings(null, null, 2, 2));
        self::assertCount(0, $this->service->getMappings(null, null, 3, 2));
    }

    public function testElListadoAceptaFiltrarPorEntidadYPorOrigen(): void
    {
        $this->sembrarVarios();

        self::assertCount(3, $this->service->getMappings(EntityType::CITY));
        self::assertCount(3, $this->service->getMappings(null, 'wasi'));
        self::assertCount(2, $this->service->getMappings(EntityType::CITY, 'wasi'));
    }

    // ── Caché ────────────────────────────────────────────────────────────────

    /**
     * La caché de `sources` y `stats` se invalida al escribir. Sin ello, el
     * panel de homologación seguiría mostrando los contadores viejos durante
     * cinco minutos después de una sincronización.
     */
    public function testLasEstadisticasSeRefrescanTrasUnaEscritura(): void
    {
        $this->resolver('wasi', 'w1', 'Medellín');
        self::assertSame([EntityType::CITY => ['wasi' => 1]], $this->service->getStats());
        self::assertSame(['wasi'], $this->service->getSources());

        $this->resolver('simi', 's1', 'Bogotá');

        self::assertSame(
            [EntityType::CITY => ['simi' => 1, 'wasi' => 1]],
            $this->service->getStats()
        );
        self::assertSame(['simi', 'wasi'], $this->service->getSources());
    }

    /** Repetir la consulta no puede cambiar el resultado. */
    public function testLaCacheDevuelveLoMismoQueLaPrimeraConsulta(): void
    {
        $this->sembrarVarios();

        self::assertSame($this->service->getStats(), $this->service->getStats());
        self::assertSame($this->service->getSources(), $this->service->getSources());
    }
}
