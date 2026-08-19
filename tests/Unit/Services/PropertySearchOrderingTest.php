<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Ordering the results by price, and the keyword ranking SQL.
 *
 * Both had the same shape of defect: the query was built, WordPress accepted
 * it, and the visitor got a listing that simply ignored what they asked for.
 * Nothing threw, so only an assertion on the generated SQL catches a relapse.
 */
final class PropertySearchOrderingTest extends TestCase
{
    /** @param array<string,mixed> $params */
    private function args(array $params): array
    {
        return (new PropertySearchService())->buildQueryArgs($params);
    }

    /**
     * `meta_key` + `orderby => meta_value_num` must not come back.
     *
     * WP_Query resolves that pair against `reset($meta_clauses)`
     * (wp-includes/class-wp-query.php:1720). The first clause of this query is
     * the `_property_status` availability guard, whose value is either NULL or
     * `active`; both cast to 0, every row ties, and the sort does nothing.
     */
    public function testElOrdenPorPrecioNoUsaLaResolucionAmbiguaDeWordPress(): void
    {
        foreach (['price_asc', 'price_desc'] as $orderby) {
            $args = $this->args(['orderby' => $orderby]);

            self::assertArrayNotHasKey('meta_key', $args, $orderby);
            self::assertNotSame('meta_value_num', $args['orderby'] ?? null, $orderby);
            self::assertIsArray($args['orderby'], $orderby);
        }
    }

    /** The direction actually reaches the query. */
    public function testCadaSentidoDelOrdenPorPrecioLlegaALaConsulta(): void
    {
        self::assertSame(['homlity_price_order' => 'ASC'], $this->args(['orderby' => 'price_asc'])['orderby']);
        self::assertSame(['homlity_price_order' => 'DESC'], $this->args(['orderby' => 'price_desc'])['orderby']);
    }

    /** The clause is numeric, or 1.000.000 would sort before 900.000. */
    public function testElPrecioSeOrdenaComoNumeroYNoComoTexto(): void
    {
        self::assertSame('NUMERIC', $this->args(['orderby' => 'price_asc'])['meta_query']['homlity_price_order']['type']);
    }

    /** Sorting by price must not disturb the availability guards. */
    public function testElOrdenPorPrecioConservaLosFiltrosDeDisponibilidad(): void
    {
        $args = $this->args(['orderby' => 'price_asc']);
        $keys = [];
        foreach ($args['meta_query'] as $group) {
            foreach (is_array($group) ? $group : [] as $clause) {
                if (is_array($clause) && isset($clause['key'])) {
                    $keys[] = $clause['key'];
                }
            }
        }

        self::assertContains('_property_status', $keys);
        self::assertContains('_property_available', $keys);
    }

    /** Ordering by date or title must not add the price clause. */
    public function testLosDemasOrdenesNoTocanElMetadatoDePrecio(): void
    {
        foreach (['date', 'title', ''] as $orderby) {
            $args = $this->args(['orderby' => $orderby]);

            self::assertArrayNotHasKey('homlity_price_order', $args['meta_query'], $orderby);
        }
    }

    /** A rent search sorts by the rent price, not the sale price. */
    public function testUnaBusquedaDeArriendoSeOrdenaPorElCanon(): void
    {
        WpStubs::setTerm(3, PropertyTaxonomies::TAXONOMY_OPERATION, 'arriendo', 'Arriendo');

        $args = $this->args(['orderby' => 'price_asc', 'operation' => 3]);

        self::assertSame('_property_price_rent', $args['meta_query']['homlity_price_order']['key']);
    }

    // ── Keyword ranking SQL ──────────────────────────────────────────────────

    /**
     * The ranking expression reads columns from the joined tables while the
     * query groups by post ID. MySQL rejects that under ONLY_FULL_GROUP_BY —
     * on by default since 5.7 — and the whole query fails, which is why a
     * search by code returned nothing at all. Aggregating the rank keeps the
     * statement valid.
     */
    public function testElRankingDeBusquedaEsAgregadoParaSobrevivirOnlyFullGroupBy(): void
    {
        $clauses = $this->keywordClauses('AW-1045');

        self::assertStringStartsWith('MIN(', trim($clauses['orderby']));
        self::assertStringContainsString('homlity_terms.name', $clauses['orderby']);
        self::assertSame('DISTINCT', $clauses['distinct']);
        self::assertStringContainsString('ID', $clauses['groupby']);
    }

    /** The code is matched exactly first, then by prefix, then anywhere. */
    public function testLaBusquedaPrioritizaElCodigoExacto(): void
    {
        $clauses = $this->keywordClauses('AW-1045');

        self::assertStringContainsString('homlity_pm_code.meta_value', $clauses['where']);
        self::assertStringContainsString('homlity_pm_sync.meta_value', $clauses['where']);
        // Rank 0 is the exact code match, so it has to appear before the rest.
        self::assertLessThan(
            strpos($clauses['orderby'], 'post_content'),
            strpos($clauses['orderby'], 'homlity_pm_code'),
        );
    }

    public function testSinPalabraClaveNoSeTocaLaConsulta(): void
    {
        $original = ['join' => '', 'where' => '', 'orderby' => 'post_date DESC', 'distinct' => '', 'groupby' => ''];
        $query = new \WP_Query(['post_type' => 'property', 'homlity_keyword_search' => '']);

        self::assertSame($original, (new PropertySearchService())->applyPriorityKeywordSearch($original, $query));
    }

    /**
     * @return array<string,string>
     */
    private function keywordClauses(string $term): array
    {
        $clauses = ['join' => '', 'where' => '', 'orderby' => '', 'distinct' => '', 'groupby' => ''];
        $query = new \WP_Query(['post_type' => 'property', 'homlity_keyword_search' => $term]);

        return (new PropertySearchService())->applyPriorityKeywordSearch($clauses, $query);
    }
}
