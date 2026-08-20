<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\RelatedPropertiesQueryBuilder;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Los argumentos de WP_Query de "inmuebles relacionados".
 *
 * Aquí nada lanza excepciones: si la consulta sale mal, WordPress la acepta y
 * el visitante ve inmuebles que no tienen nada que ver, o no ve ninguno. La
 * única forma de detectar una regresión es afirmar sobre los argumentos
 * generados y sobre qué camino —principal o de reserva— se tomó.
 */
final class RelatedPropertiesQueryBuilderTest extends TestCase
{
    private const PROPERTY_ID = 501;

    /** @var array<int,array<string,mixed>> Sondas que el constructor ha lanzado. */
    private array $probes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->probes = [];
        // Por defecto ninguna consulta devuelve nada: así cada prueba declara
        // explícitamente qué encuentra y qué no.
        $this->resolveWith(static fn(array $args): bool => false);
    }

    /**
     * Registra el resolutor de WP_Query: $hasResults decide, mirando los
     * argumentos de la sonda, si esa consulta encuentra algo.
     *
     * @param callable(array<string,mixed>):bool $hasResults
     */
    private function resolveWith(callable $hasResults): void
    {
        $probes = &$this->probes;
        WpStubs::$queryResolver = static function (array $args) use (&$probes, $hasResults): array {
            $probes[] = $args;

            // `post__in => [0]` no devuelve nada en WordPress pase lo que pase:
            // el resolutor tiene que comportarse igual, o una prueba podría
            // afirmar sobre un camino que en producción es imposible.
            if (($args['post__in'] ?? null) === [0]) {
                return ['posts' => []];
            }

            return $hasResults($args) ? ['posts' => [1], 'found_posts' => 1] : ['posts' => []];
        };
    }

    /**
     * @param array<string,mixed> ...$overrides
     * @return array{args: array<string,mixed>, fallback_type: string}
     */
    private function build(
        array $taxonomies = [],
        string $strategy = RelatedPropertiesQueryBuilder::STRATEGY_ANY,
        string $fallback = RelatedPropertiesQueryBuilder::FALLBACK_RECENT,
        bool $excludeCurrent = true,
        int $postsPerPage = 6
    ): array {
        return (new RelatedPropertiesQueryBuilder())->build(
            self::PROPERTY_ID,
            $postsPerPage,
            $taxonomies,
            $strategy,
            $fallback,
            $excludeCurrent
        );
    }

    /** Encuentra resultados con cualquier consulta: para inspeccionar la principal. */
    private function resolveAlways(): void
    {
        $this->resolveWith(static fn(array $args): bool => true);
    }

    /** Devuelve la taxonomía de cada cláusula del tax_query, en orden. */
    private function taxonomiesOf(array $args): array
    {
        $clauses = $args['tax_query'] ?? [];
        if (!is_array($clauses)) {
            return [];
        }
        unset($clauses['relation']);

        return array_values(array_map(
            static fn(array $clause): string => (string) $clause['taxonomy'],
            $clauses
        ));
    }

    // ── Argumentos base ──────────────────────────────────────────────────────

    public function testLaConsultaSeLimitaAInmueblesPublicados(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);

        $args = $this->build()['args'];

        self::assertSame(PropertyPostType::POST_TYPE, $args['post_type']);
        self::assertSame('publish', $args['post_status']);
        self::assertSame('date', $args['orderby']);
        self::assertSame('DESC', $args['order']);
    }

    /**
     * El tope existe para que un widget mal configurado no pida 500 inmuebles
     * y el mínimo para que pedir 0 no deje la consulta sin límite: en WP_Query
     * `posts_per_page => 0` no devuelve cero resultados, devuelve el ajuste
     * por defecto del sitio.
     */
    public function testElNumeroDeResultadosSeAcotaEntreUnoYElMaximo(): void
    {
        self::assertSame(
            RelatedPropertiesQueryBuilder::MAX_RESULTS,
            $this->build(postsPerPage: 500)['args']['posts_per_page']
        );
        self::assertSame(1, $this->build(postsPerPage: 0)['args']['posts_per_page']);
        self::assertSame(1, $this->build(postsPerPage: -3)['args']['posts_per_page']);
        self::assertSame(4, $this->build(postsPerPage: 4)['args']['posts_per_page']);
    }

    /** Sin esto el inmueble que se está viendo aparece entre sus relacionados. */
    public function testElInmuebleDeOrigenSeExcluyeDeLosResultados(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);

        self::assertSame([self::PROPERTY_ID], $this->build()['args']['post__not_in']);
        self::assertArrayNotHasKey('post__not_in', $this->build(excludeCurrent: false)['args']);
    }

    // ── Selección de taxonomías ──────────────────────────────────────────────

    /** Lista vacía significa "todas", no "ninguna". */
    public function testSinTaxonomiasIndicadasSeUsanTodasLasPermitidas(): void
    {
        foreach ([PropertyTaxonomies::TAXONOMY_TAG, PropertyTaxonomies::TAXONOMY_TYPE, PropertyTaxonomies::TAXONOMY_CITY] as $taxonomy) {
            WpStubs::setPostTerms(self::PROPERTY_ID, $taxonomy, [11]);
        }

        $this->resolveAlways();

        $used = $this->taxonomiesOf($this->build()['args']);

        self::assertContains(PropertyTaxonomies::TAXONOMY_TAG, $used);
        self::assertContains(PropertyTaxonomies::TAXONOMY_TYPE, $used);
        self::assertContains(PropertyTaxonomies::TAXONOMY_CITY, $used);
    }

    /**
     * Una taxonomía que no esté en la lista blanca no puede colarse en el
     * tax_query: el valor llega de los ajustes del widget, que es entrada del
     * usuario.
     */
    public function testUnaTaxonomiaNoPermitidaSeDescarta(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);
        WpStubs::setPostTerms(self::PROPERTY_ID, 'category', [99]);

        $this->resolveAlways();

        $used = $this->taxonomiesOf($this->build(['category', PropertyTaxonomies::TAXONOMY_TYPE])['args']);

        self::assertSame([PropertyTaxonomies::TAXONOMY_TYPE], $used);
    }

    /** Si el widget pide sólo taxonomías inválidas la consulta no debe filtrar por nada. */
    public function testSoloTaxonomiasInvalidasNoProduceResultados(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);

        $result = $this->build(['category', 'post_tag'], fallback: RelatedPropertiesQueryBuilder::FALLBACK_HIDE);

        self::assertSame([0], $result['args']['post__in']);
        self::assertSame(RelatedPropertiesQueryBuilder::FALLBACK_HIDE, $result['fallback_type']);
    }

    // ── Estrategias ──────────────────────────────────────────────────────────

    /** Con una sola cláusula WP_Query no admite la clave `relation`. */
    public function testConUnaSolaTaxonomiaNoSeEmiteRelacion(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);

        $this->resolveAlways();

        $args = $this->build([PropertyTaxonomies::TAXONOMY_TYPE])['args'];

        self::assertArrayNotHasKey('relation', $args['tax_query']);
        self::assertSame([[
            'taxonomy' => PropertyTaxonomies::TAXONOMY_TYPE,
            'field'    => 'term_id',
            'terms'    => [7],
            'operator' => 'IN',
        ]], $args['tax_query']);
    }

    /**
     * 'any' busca parecidos —basta compartir algo—; 'all' exige coincidir en
     * todas. Confundir OR con AND no rompe nada visible: devuelve muchos
     * resultados irrelevantes, o ninguno.
     */
    public function testLaEstrategiaDecideSiLasCondicionesSeSumanOSeExigenTodas(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_CITY, [3]);
        $this->resolveAlways();

        $taxonomies = [PropertyTaxonomies::TAXONOMY_TYPE, PropertyTaxonomies::TAXONOMY_CITY];

        self::assertSame(
            'OR',
            $this->build($taxonomies, RelatedPropertiesQueryBuilder::STRATEGY_ANY)['args']['tax_query']['relation']
        );
        self::assertSame(
            'AND',
            $this->build($taxonomies, RelatedPropertiesQueryBuilder::STRATEGY_ALL)['args']['tax_query']['relation']
        );
    }

    /**
     * 'tags_first' es la estrategia con más lógica: primero prueba sólo con
     * etiquetas, que es lo que el comercial ha puesto a mano, y sólo si eso
     * está vacío abre a todas las taxonomías.
     */
    public function testTagsFirstDevuelveSoloEtiquetasCuandoEsasEncuentran(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TAG, [42]);
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_CITY, [3]);
        $this->resolveAlways();

        $result = $this->build([], RelatedPropertiesQueryBuilder::STRATEGY_TAGS_FIRST);

        self::assertSame('none', $result['fallback_type']);
        self::assertSame([PropertyTaxonomies::TAXONOMY_TAG], $this->taxonomiesOf($result['args']));
    }

    /** Si la consulta de etiquetas viene vacía, degrada a 'any' y no a la reserva. */
    public function testTagsFirstDegradaAAnyCuandoLasEtiquetasNoEncuentran(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TAG, [42]);
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_CITY, [3]);
        // Sólo encuentra resultados la consulta que NO se limita a etiquetas.
        $this->resolveWith(fn(array $args): bool => count($this->taxonomiesOf($args)) > 1);

        $result = $this->build([], RelatedPropertiesQueryBuilder::STRATEGY_TAGS_FIRST);

        self::assertSame('none', $result['fallback_type']);
        self::assertSame('OR', $result['args']['tax_query']['relation']);
        self::assertContains(PropertyTaxonomies::TAXONOMY_CITY, $this->taxonomiesOf($result['args']));
    }

    /** Sin etiquetas entre las taxonomías elegidas, la sonda previa sobra. */
    public function testTagsFirstNoSondeaEtiquetasSiNoEstanSeleccionadas(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TAG, [42]);
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_CITY, [3]);
        $this->resolveAlways();

        $this->build([PropertyTaxonomies::TAXONOMY_CITY], RelatedPropertiesQueryBuilder::STRATEGY_TAGS_FIRST);

        self::assertCount(1, $this->probes, 'la sonda de etiquetas no debería lanzarse');
        self::assertSame([PropertyTaxonomies::TAXONOMY_CITY], $this->taxonomiesOf($this->probes[0]));
    }

    // ── El inmueble sin términos ─────────────────────────────────────────────

    /**
     * Un inmueble recién importado puede no tener ningún término. Sin la
     * guarda, el tax_query sale vacío y WP_Query devuelve *todos* los
     * inmuebles como si fueran relacionados.
     */
    public function testUnInmuebleSinTerminosNoArrastraTodoElCatalogo(): void
    {
        $result = $this->build(fallback: RelatedPropertiesQueryBuilder::FALLBACK_EMPTY);

        self::assertSame([0], $result['args']['post__in']);
        self::assertArrayNotHasKey('tax_query', $result['args']);
    }

    /**
     * La guarda anterior importa aunque haya reserva: sin ella el tax_query
     * sale vacío, la sonda encuentra el catálogo entero y el resultado se da
     * por bueno —`fallback_type => 'none'`—, así que el visitante ve los
     * últimos inmuebles publicados presentados como "relacionados" con éste.
     */
    public function testSinTerminosLaConsultaNoSeDaPorBuena(): void
    {
        // La base de datos tiene inmuebles: cualquier consulta sin filtro los
        // encuentra. Es justo el escenario en el que la guarda hace falta.
        $this->resolveAlways();

        $result = $this->build(fallback: RelatedPropertiesQueryBuilder::FALLBACK_RECENT);

        self::assertSame(
            RelatedPropertiesQueryBuilder::FALLBACK_RECENT,
            $result['fallback_type'],
            'sin términos el resultado es una reserva, nunca una coincidencia real'
        );
    }

    /**
     * wp_get_post_terms() devuelve WP_Error si la taxonomía no está
     * registrada —pasa cuando otro plugin se desactiva—. Tratarlo como una
     * lista de términos haría que `absint()` operase sobre un objeto.
     */
    public function testUnErrorDeTaxonomiaSeTrataComoAusenciaDeTerminos(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);
        WpStubs::$postTermsError[PropertyTaxonomies::TAXONOMY_TYPE] = 'invalid taxonomy';

        $result = $this->build([PropertyTaxonomies::TAXONOMY_TYPE], fallback: RelatedPropertiesQueryBuilder::FALLBACK_HIDE);

        self::assertSame([0], $result['args']['post__in']);
    }

    // ── La sonda ─────────────────────────────────────────────────────────────

    /**
     * La sonda sólo existe para saber si hay algo: pedir un id y desactivar el
     * conteo total y las cachés de meta y términos es lo que la hace barata.
     * Si se olvida, cada widget hace dos consultas completas.
     */
    public function testLaSondaEsUnaConsultaBarataDeUnSoloId(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);

        $this->build(postsPerPage: 8);

        $probe = $this->probes[0];
        self::assertSame(1, $probe['posts_per_page']);
        self::assertSame('ids', $probe['fields']);
        self::assertTrue($probe['no_found_rows']);
        self::assertFalse($probe['update_post_meta_cache']);
        self::assertFalse($probe['update_post_term_cache']);
    }

    /** Los argumentos devueltos son los reales, no los recortados de la sonda. */
    public function testLosArgumentosDevueltosNoHeredanLosRecortesDeLaSonda(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);
        $this->resolveAlways();

        $args = $this->build(postsPerPage: 8)['args'];

        self::assertSame(8, $args['posts_per_page']);
        self::assertArrayNotHasKey('fields', $args);
        self::assertArrayNotHasKey('no_found_rows', $args);
    }

    // ── Reservas ─────────────────────────────────────────────────────────────

    /** La reserva 'recent' tiene que soltar el filtro que dejó la consulta vacía. */
    public function testLaReservaRecienteSueltaTodosLosFiltros(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);

        $result = $this->build(fallback: RelatedPropertiesQueryBuilder::FALLBACK_RECENT);

        self::assertSame(RelatedPropertiesQueryBuilder::FALLBACK_RECENT, $result['fallback_type']);
        self::assertArrayNotHasKey('tax_query', $result['args']);
        self::assertArrayNotHasKey('post__in', $result['args']);
        self::assertSame([self::PROPERTY_ID], $result['args']['post__not_in'], 'seguir excluyendo el inmueble actual');
    }

    public function testLaReservaDeMismaCiudadFiltraPorLaCiudadDelInmueble(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_CITY, [3]);
        // Sólo encuentra la consulta que filtra únicamente por ciudad.
        $this->resolveWith(fn(array $args): bool => $this->taxonomiesOf($args) === [PropertyTaxonomies::TAXONOMY_CITY]);

        $result = $this->build([PropertyTaxonomies::TAXONOMY_TYPE], fallback: RelatedPropertiesQueryBuilder::FALLBACK_SAME_CITY);

        self::assertSame(RelatedPropertiesQueryBuilder::FALLBACK_SAME_CITY, $result['fallback_type']);
        self::assertSame([PropertyTaxonomies::TAXONOMY_CITY], $this->taxonomiesOf($result['args']));
        self::assertSame([3], $result['args']['tax_query'][0]['terms']);
    }

    /**
     * Si en esa ciudad tampoco hay nada, la sección no puede quedarse vacía:
     * degrada a recientes. Es la cadena de dos saltos que se rompe fácil.
     */
    public function testLaReservaDeMismaCiudadDegradaARecientesSiLaCiudadNoTieneNada(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_CITY, [3]);

        $result = $this->build(fallback: RelatedPropertiesQueryBuilder::FALLBACK_SAME_CITY);

        self::assertSame(RelatedPropertiesQueryBuilder::FALLBACK_RECENT, $result['fallback_type']);
        self::assertArrayNotHasKey('tax_query', $result['args']);
    }

    /** Sin ciudad asignada la reserva no puede intentarse siquiera. */
    public function testLaReservaDeMismaCiudadSinCiudadVaARecientes(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);
        $this->resolveWith(fn(array $args): bool => $this->taxonomiesOf($args) === [PropertyTaxonomies::TAXONOMY_CITY]);

        $result = $this->build([PropertyTaxonomies::TAXONOMY_TYPE], fallback: RelatedPropertiesQueryBuilder::FALLBACK_SAME_CITY);

        self::assertSame(RelatedPropertiesQueryBuilder::FALLBACK_RECENT, $result['fallback_type']);
    }

    /**
     * 'hide' y 'empty' se diferencian sólo en el tipo devuelto —quien pinta
     * decide si oculta la sección o muestra el mensaje—, pero las dos tienen
     * que garantizar cero resultados.
     */
    public function testOcultarYVacioFuerzanCeroResultadosYSeDistinguen(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);

        foreach ([RelatedPropertiesQueryBuilder::FALLBACK_HIDE, RelatedPropertiesQueryBuilder::FALLBACK_EMPTY] as $fallback) {
            $result = $this->build(fallback: $fallback);

            self::assertSame($fallback, $result['fallback_type']);
            self::assertSame([0], $result['args']['post__in'], $fallback);
        }
    }

    /** Cuando la consulta principal encuentra algo, no se toca ninguna reserva. */
    public function testLaConsultaPrincipalConResultadosNoActivaNingunaReserva(): void
    {
        WpStubs::setPostTerms(self::PROPERTY_ID, PropertyTaxonomies::TAXONOMY_TYPE, [7]);
        $this->resolveAlways();

        $result = $this->build(fallback: RelatedPropertiesQueryBuilder::FALLBACK_HIDE);

        self::assertSame('none', $result['fallback_type']);
        self::assertArrayNotHasKey('post__in', $result['args']);
        self::assertCount(1, $this->probes);
    }
}
