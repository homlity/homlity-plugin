<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

/**
 * The property archive orders its own query, apart from the listing widget.
 *
 * That is why it carried the same two defects on its own: it pinned the sort
 * to `_property_price_sale` for the whole archive — rentals store 0 there — and
 * it left `post_date` ties to MySQL, which on a synced install means batches of
 * a dozen properties coming back in no order at all.
 *
 * `filterArchiveQuery()` needs a live `WP_Query` plus a good part of the
 * WordPress conditional-tags machinery, so this reads the source instead: what
 * matters is that the ordering block cannot silently go back to either shape.
 */
final class ArchiveOrderingTest extends TestCase
{
    private const SOURCE = __DIR__ . '/../../../src/Services/TemplateService.php';

    private function orderingBlock(): string
    {
        $source = (string) file_get_contents(self::SOURCE);
        $start = strpos($source, "\$order = sanitize_key((string) (\$settings['archive_order']");
        self::assertNotFalse($start, 'No se encontró el bloque de orden del archivo.');

        $end = strpos($source, 'public function maybeLoadTemplate', $start);
        self::assertNotFalse($end);

        return substr($source, $start, $end - $start);
    }

    /**
     * The archive must not sort everything by the sale price.
     *
     * `meta_key` + `orderby => meta_value_num` is the shape it used. Rentals
     * have 0 in that meta, so they all tied and came back in the internal
     * order of the database while their cards printed the rent price.
     */
    public function testElArchivoNoFijaElPrecioDeVentaParaTodoElListado(): void
    {
        $block = $this->orderingBlock();

        self::assertStringNotContainsString('meta_value_num', $block);
        self::assertStringNotContainsString('_property_price_sale', $block);
    }

    /** It delegates to the same per-row price ordering as the listing. */
    public function testElArchivoUsaElMismoOrdenPorPrecioQueElListado(): void
    {
        self::assertStringContainsString(
            'PropertySearchService::PRICE_ORDER_QUERY_VAR',
            $this->orderingBlock()
        );
        self::assertSame('homlity_price_order', PropertySearchService::PRICE_ORDER_QUERY_VAR);
    }

    /**
     * Every ordering the archive sets breaks ties on the post ID.
     *
     * Both `post_date` and `post_modified` are written by the sync in batches,
     * so ties are the rule, not the exception.
     */
    public function testCadaOrdenDelArchivoDesempataPorId(): void
    {
        preg_match_all(
            "/\\\$query->set\\(\\s*'orderby'\\s*,\\s*(.+?)\\);/s",
            $this->orderingBlock(),
            $matches
        );

        self::assertNotEmpty($matches[1], 'El detector no encontró ningún orden que revisar.');

        foreach ($matches[1] as $argument) {
            self::assertStringContainsString("'ID' => 'DESC'", $argument, $argument);
        }
    }
}
