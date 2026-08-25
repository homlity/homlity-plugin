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
        }
    }

    /** The direction actually reaches the query. */
    public function testCadaSentidoDelOrdenPorPrecioLlegaALaConsulta(): void
    {
        self::assertSame('ASC', $this->args(['orderby' => 'price_asc'])[PropertySearchService::PRICE_ORDER_QUERY_VAR]);
        self::assertSame('DESC', $this->args(['orderby' => 'price_desc'])[PropertySearchService::PRICE_ORDER_QUERY_VAR]);
    }

    /**
     * Nothing may pin the whole listing to a single price meta key.
     *
     * This is the defect the visitor reported. A listing that mixes venta and
     * arriendo — the default for the listing widget, which has no operation
     * filter of its own — sorted every row by `_property_price_sale`. Rentals
     * store 0 there, so all of them tied and MySQL returned them in whatever
     * order it pleased, while their cards printed the rent price. The column
     * of prices on screen had no order at all.
     */
    public function testElPrecioDeOrdenNoSeFijaParaTodoElListado(): void
    {
        foreach ([[], ['operation' => 3]] as $extra) {
            $args = $this->args(['orderby' => 'price_desc'] + $extra);

            foreach ($args['meta_query'] as $key => $group) {
                self::assertNotSame(PropertySearchService::PRICE_ORDER_QUERY_VAR, $key);
                self::assertArrayNotHasKey('key', is_array($group) ? $group : []);
            }
        }
    }

    /** Ordering by date or title must not ask for the price ordering at all. */
    public function testLosDemasOrdenesNoTocanElMetadatoDePrecio(): void
    {
        foreach (['date', 'title', ''] as $orderby) {
            $args = $this->args(['orderby' => $orderby]);

            self::assertArrayNotHasKey(PropertySearchService::PRICE_ORDER_QUERY_VAR, $args, $orderby);
            self::assertArrayNotHasKey(PropertySearchService::PRICE_ORDER_QUERY_VAR, $args['meta_query'], $orderby);
        }
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

    // ── Ties ─────────────────────────────────────────────────────────────────

    /**
     * Every sort breaks ties on the post ID.
     *
     * `post_date` is the moment the sync created the property, and the sync
     * works in batches: on a real install a dozen properties share the same
     * second. Without a tie-break MySQL returns each of those groups in
     * whatever order it likes, so «más nuevo a más viejo» came out shuffled
     * inside every batch — and how the ties fall across pages is not
     * guaranteed either, so a card could repeat while another never showed up.
     *
     * @dataProvider ordenesQueEmpatan
     */
    public function testTodosLosOrdenesDesempatanPorId(string $orderby, string $column): void
    {
        $args = $this->args(['orderby' => $orderby]);

        self::assertIsArray($args['orderby']);
        self::assertArrayHasKey('ID', $args['orderby']);
        // El desempate va después del criterio pedido, no antes.
        self::assertSame([$column, 'ID'], array_keys($args['orderby']));
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function ordenesQueEmpatan(): array
    {
        return [
            'más recientes'   => ['date', 'date'],
            'nombre'          => ['title', 'title'],
            'orden inventado' => ['cualquier-cosa', 'date'],
        ];
    }

    /**
     * «Más recientes» puts the last property in first when the date ties.
     *
     * Descending IDs, or the newest of each batch would come out last — which
     * is the opposite of what the option promises.
     */
    public function testElDesempateDeLosMasRecientesPoneAlUltimoPrimero(): void
    {
        self::assertSame('DESC', $this->args(['orderby' => 'date'])['orderby']['ID']);
    }

    /**
     * «Nombre A–Z» has to sort A–Z.
     *
     * No caller sends a direction — not the listing dropdown, not the
     * shortcode, not any of the three builders — so the shared DESC default
     * applied and the option delivered exactly the opposite of its label.
     */
    public function testNombreAZOrdenaAlfabeticamente(): void
    {
        self::assertSame('ASC', $this->args(['orderby' => 'title'])['orderby']['title']);
        self::assertSame('ASC', $this->args(['orderby' => 'title'])['order']);
    }

    /** An explicit direction still wins. */
    public function testElOrdenPorNombreAceptaLaDireccionContraria(): void
    {
        $args = $this->args(['orderby' => 'title', 'order' => 'desc']);

        self::assertSame('DESC', $args['orderby']['title']);
        self::assertSame('DESC', $args['order']);
    }

    // ── Price ordering SQL ────────────────────────────────────────

    /**
     * Each row sorts by the price its own card prints.
     *
     * `templates/parts/property-card.php` shows the sale price and falls back
     * to the rent one. The ORDER BY has to say the same thing, or the printed
     * numbers come out shuffled.
     */
    public function testElOrdenSaleDelPrecioQueImprimeCadaFicha(): void
    {
        $orderby = $this->priceClauses('DESC')['orderby'];

        self::assertStringContainsString('homlity_pm_sale.meta_value', $orderby);
        self::assertStringContainsString('homlity_pm_rent.meta_value', $orderby);
        // Venta primero y arriendo como respaldo, igual que la tarjeta.
        self::assertLessThan(
            strpos($orderby, 'homlity_pm_rent'),
            strpos($orderby, 'homlity_pm_sale'),
        );
    }

    /**
     * A property with no price meta must sink to the bottom, not vanish.
     *
     * The previous implementation asked for the key with `compare => EXISTS`,
     * which WP_Query turns into an INNER JOIN: a manually created property
     * without the key dropped out of the listing entirely as soon as the
     * visitor sorted by price.
     */
    public function testUnInmuebleSinPrecioNoDesapareceDelListado(): void
    {
        $join = $this->priceClauses('ASC')['join'];

        self::assertStringContainsString('LEFT JOIN', $join);
        self::assertStringNotContainsString('INNER JOIN', $join);
        self::assertSame(2, substr_count($join, 'LEFT JOIN'));
    }

    /** Both directions reach the SQL. */
    public function testElSentidoDelOrdenLlegaAlSql(): void
    {
        self::assertMatchesRegularExpression('/\bASC\b/', $this->priceClauses('ASC')['orderby']);
        self::assertMatchesRegularExpression('/\bDESC,/', $this->priceClauses('DESC')['orderby']);
    }

    /**
     * The price expression is aggregated and the query groups by post ID.
     *
     * Same trap as the keyword ranking: under ONLY_FULL_GROUP_BY — on by
     * default since MySQL 5.7 — an ORDER BY over a non-aggregated joined
     * column brings the whole query down and the listing renders empty.
     */
    public function testLaExpresionDePrecioEsAgregadaParaSobrevivirOnlyFullGroupBy(): void
    {
        $clauses = $this->priceClauses('DESC');

        // Ninguna referencia a las columnas unidas puede quedar sin agregar.
        self::assertSame(0, preg_match('/(?<!MAX\()homlity_pm_(sale|rent)\.meta_value/', $clauses['orderby']));
        self::assertStringContainsString('ID', $clauses['groupby']);
    }

    /** Text sorting would put 900.000 above 1.000.000. */
    public function testElPrecioSeOrdenaComoNumeroYNoComoTexto(): void
    {
        $orderby = $this->priceClauses('ASC')['orderby'];

        // Las dos columnas, no una: basta con que una se compare como texto
        // para que ese lado del orden salga mal.
        foreach (['sale', 'rent'] as $side) {
            $column = 'homlity_pm_' . $side . '.meta_value';

            self::assertSame(
                substr_count($orderby, $column),
                preg_match_all('/CAST\\(\\s*MAX\\(\\s*' . preg_quote($column, '/') . '\\s*\\)\\s+AS\\s+DECIMAL/', $orderby),
                $side
            );
        }
    }

    /**
     * Ties break on a stable column.
     *
     * Without one, two properties at the same price can come back in a
     * different order on each page and the visitor sees a card twice while
     * another never shows up.
     */
    public function testElEmpateSeRompeConUnCriterioEstable(): void
    {
        self::assertStringContainsString('wp_posts.ID DESC', $this->priceClauses('DESC')['orderby']);
    }

    /** Without the query var the clauses come back untouched. */
    public function testSinOrdenPorPrecioNoSeTocaLaConsulta(): void
    {
        $original = ['join' => '', 'where' => '', 'orderby' => 'post_date DESC', 'groupby' => ''];
        $query = new \WP_Query(['post_type' => 'property']);

        self::assertSame($original, (new PropertySearchService())->applyPriceOrder($original, $query));
    }

    /** Other post types keep their own ordering. */
    public function testElOrdenPorPrecioSoloAplicaAInmuebles(): void
    {
        $original = ['join' => '', 'where' => '', 'orderby' => 'post_date DESC', 'groupby' => ''];
        $query = new \WP_Query([
            'post_type' => 'post',
            PropertySearchService::PRICE_ORDER_QUERY_VAR => 'DESC',
        ]);

        self::assertSame($original, (new PropertySearchService())->applyPriceOrder($original, $query));
    }

    /**
     * Running the filter twice must not repeat the joins.
     *
     * Two identical aliases make MySQL reject the statement outright
     * («Not unique table/alias») and the listing renders empty.
     */
    public function testLosJoinsDePrecioNoSeDuplican(): void
    {
        $service = new PropertySearchService();
        $query = new \WP_Query([
            'post_type' => 'property',
            PropertySearchService::PRICE_ORDER_QUERY_VAR => 'DESC',
        ]);

        $once = $service->applyPriceOrder(['join' => '', 'where' => '', 'orderby' => '', 'groupby' => ''], $query);
        $twice = $service->applyPriceOrder($once, $query);

        self::assertSame($once, $twice);
    }

    /**
     * With a keyword, relevance outranks price.
     *
     * The two filters are hooked at 10 and 20 so that the ranking prepends
     * itself. Searching for a code has to surface that code first, whatever
     * the sort dropdown says.
     */
    public function testConPalabraClaveLaRelevanciaMandaSobreElPrecio(): void
    {
        $service = new PropertySearchService();
        $query = new \WP_Query([
            'post_type' => 'property',
            'homlity_keyword_search' => 'AW-1045',
            PropertySearchService::PRICE_ORDER_QUERY_VAR => 'DESC',
        ]);

        $clauses = ['join' => '', 'where' => '', 'orderby' => '', 'distinct' => '', 'groupby' => ''];
        $clauses = $service->applyPriceOrder($clauses, $query);
        $clauses = $service->applyPriorityKeywordSearch($clauses, $query);

        self::assertStringStartsWith('MIN(', trim($clauses['orderby']));
        self::assertStringContainsString('homlity_pm_sale', $clauses['orderby']);
    }

    /**
     * @return array<string,string>
     */
    private function priceClauses(string $direction): array
    {
        $clauses = ['join' => '', 'where' => '', 'orderby' => '', 'groupby' => ''];
        $query = new \WP_Query([
            'post_type' => 'property',
            PropertySearchService::PRICE_ORDER_QUERY_VAR => $direction,
        ]);

        return (new PropertySearchService())->applyPriceOrder($clauses, $query);
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
