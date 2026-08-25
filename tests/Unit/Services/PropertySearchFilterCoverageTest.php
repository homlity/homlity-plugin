<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Contract across the whole search chain: browser → AJAX endpoint → query.
 *
 * A filter can be dropped at any of the three links and the page still renders
 * — it just quietly returns the unfiltered catalogue. These tests fail instead,
 * and they fail for a new filter that was only wired half-way.
 */
final class PropertySearchFilterCoverageTest extends TestCase
{
    /**
     * Every filter the search supports, with a value that must change the query.
     * Presentation-only settings (card layout, icons, template) are not here.
     *
     * @return array<string,array<string,mixed>>
     */
    private const FILTERS = [
        'search'              => ['search' => 'balcón'],
        'category'            => ['category' => 31],
        'operation'           => ['operation' => 31],
        'type'                => ['type' => 31],
        'tag'                 => ['tag' => 31],
        'feature'             => ['feature' => 31],
        'country'             => ['country' => 31],
        'state'               => ['state' => 31],
        'city'                => ['city' => 31],
        'neighborhood'        => ['neighborhood' => 31],
        'nearby'              => ['nearby' => 31],
        'preset_category'     => ['preset_category' => 31],
        'preset_operation'    => ['preset_operation' => 31],
        'preset_type'         => ['preset_type' => 31],
        'preset_tag'          => ['preset_tag' => 31],
        'preset_feature'      => ['preset_feature' => 31],
        'preset_country'      => ['preset_country' => 31],
        'preset_state'        => ['preset_state' => 31],
        'preset_city'         => ['preset_city' => 31],
        'preset_neighborhood' => ['preset_neighborhood' => 31],
        'preset_nearby'       => ['preset_nearby' => 31],
        'preset_tag_ids'      => ['preset_tag_ids' => [31]],
        'preset_agent'        => ['preset_agent' => 42],
        'featured'            => ['featured' => true],
        'price_min'           => ['price_min' => 100],
        'price_max'           => ['price_max' => 500],
        'bedrooms'            => ['bedrooms' => 2],
        'bathrooms'           => ['bathrooms' => 2],
        'parking'             => ['parking' => 1],
        'area_min'            => ['area_min' => 50],
        'area_max'            => ['area_max' => 200],
        'geo_radius'          => ['geo_latitude' => 6.23, 'geo_longitude' => -75.15, 'geo_radius_km' => 5],
        'orderby'             => ['orderby' => 'price_asc'],
        'page'                => ['page' => 3],
        'per_page'            => ['per_page' => 24],
    ];

    /** Filters handled outside buildQueryArgs' plain parameter list. */
    private const LOCALITY_FILTERS = ['locality', 'preset_locality'];

    /**
     * Form fields that are not filters and must stay unknown to the search.
     *
     * `homlity_search` marks a submitted form so the base location configured
     * in the settings is not re-applied over a field the visitor cleared — an
     * unselected multiple select sends nothing at all. It never narrows the
     * catalogue, so teaching it to PropertySearchService would be dead code.
     */
    private const NON_FILTER_FIELDS = ['homlity_search'];

    private function source(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** Registers term 31 in every taxonomy so any taxonomy filter can match. */
    private function givenTermInEveryTaxonomy(): void
    {
        foreach ([
            PropertyTaxonomies::TAXONOMY_CATEGORY, PropertyTaxonomies::TAXONOMY_OPERATION,
            PropertyTaxonomies::TAXONOMY_TYPE, PropertyTaxonomies::TAXONOMY_TAG,
            PropertyTaxonomies::TAXONOMY_FEATURE, PropertyTaxonomies::TAXONOMY_COUNTRY,
            PropertyTaxonomies::TAXONOMY_STATE, PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, PropertyTaxonomies::TAXONOMY_NEARBY,
        ] as $taxonomy) {
            WpStubs::setTerm(31, $taxonomy, 'termino-31');
        }
    }

    /**
     * The core guarantee: every filter changes the query it produces. A filter
     * that is accepted and then ignored fails here.
     */
    public function testCadaFiltroModificaLaConsultaGenerada(): void
    {
        $this->givenTermInEveryTaxonomy();
        $service = new PropertySearchService();
        $baseline = $service->buildQueryArgs([]);

        foreach (self::FILTERS as $name => $params) {
            self::assertNotEquals(
                $baseline,
                $service->buildQueryArgs($params),
                sprintf('El filtro "%s" no tuvo ningún efecto sobre la consulta.', $name)
            );
        }
    }

    /** The locality bridge is resolved against published localities. */
    public function testLaLocalidadYSuPresetModificanLaConsulta(): void
    {
        WpStubs::$postObjects[70] = new \WP_Post([
            'ID' => 70, 'post_type' => 'property_locality', 'post_status' => 'publish', 'post_name' => 'el-poblado',
        ]);
        WpStubs::$localityNeighborhoods[70] = [401];

        $service = new PropertySearchService();
        $baseline = $service->buildQueryArgs([]);

        foreach (self::LOCALITY_FILTERS as $filter) {
            self::assertNotEquals(
                $baseline,
                $service->buildQueryArgs([$filter => 70]),
                sprintf('El filtro "%s" no tuvo ningún efecto sobre la consulta.', $filter)
            );
        }
    }

    /**
     * Every taxonomy used for filtering must be reachable both directly and as
     * a widget preset. Adding one to the service without both wires fails here.
     */
    public function testCadaTaxonomiaFiltrableTieneFiltroDirectoYPreset(): void
    {
        $this->givenTermInEveryTaxonomy();
        $service = new PropertySearchService();

        foreach ([
            'category' => PropertyTaxonomies::TAXONOMY_CATEGORY,
            'operation' => PropertyTaxonomies::TAXONOMY_OPERATION,
            'type' => PropertyTaxonomies::TAXONOMY_TYPE,
            'tag' => PropertyTaxonomies::TAXONOMY_TAG,
            'feature' => PropertyTaxonomies::TAXONOMY_FEATURE,
            'country' => PropertyTaxonomies::TAXONOMY_COUNTRY,
            'state' => PropertyTaxonomies::TAXONOMY_STATE,
            'city' => PropertyTaxonomies::TAXONOMY_CITY,
            'neighborhood' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            'nearby' => PropertyTaxonomies::TAXONOMY_NEARBY,
        ] as $param => $taxonomy) {
            foreach ([$param, 'preset_' . $param] as $key) {
                $clauses = array_filter(
                    $service->buildQueryArgs([$key => 31])['tax_query'],
                    static fn($clause): bool => is_array($clause) && ($clause['taxonomy'] ?? '') === $taxonomy
                );
                self::assertCount(1, $clauses, sprintf('"%s" no consultó %s.', $key, $taxonomy));
            }
        }
    }

    // ── Chain contract ───────────────────────────────────────────────────────

    /**
     * Filter keys the browser puts in the AJAX body, read from the script
     * itself so the list cannot drift away from what actually ships.
     *
     * @return list<string>
     */
    private function filterKeysSentByTheBrowser(): array
    {
        $script = $this->source('assets/js/property-listing.js');
        $start = strpos($script, "action:            'homlity_listing'");
        self::assertNotFalse($start, 'No se encontró el cuerpo de la petición AJAX en el script.');
        $end = strpos($script, '}, formParams));', $start);
        self::assertNotFalse($end, 'No se encontró el final del cuerpo de la petición AJAX.');
        $body = substr($script, $start, $end - $start);

        preg_match_all('/^\s*([a-z_0-9]+):\s+/m', $body, $matches);
        $keys = array_values(array_unique($matches[1]));

        // Everything that is not a filter: transport and card presentation.
        $notFilters = static fn(string $key): bool => !in_array($key, ['action', 'nonce', 'template'], true)
            && !str_starts_with($key, 'card_');

        $filters = array_values(array_filter($keys, $notFilters));

        // Guard against a parsing change quietly emptying the contract and
        // turning the tests below into no-ops.
        self::assertGreaterThan(30, count($filters), 'No se pudieron leer los filtros del script.');

        return $filters;
    }

    /** The browser must not send a filter the endpoint never reads. */
    public function testElEndpointAjaxLeeTodoLoQueElNavegadorEnvia(): void
    {
        $ajax = $this->source('src/Services/PropertyAjaxService.php');

        foreach ($this->filterKeysSentByTheBrowser() as $key) {
            self::assertStringContainsString(
                "\$_POST['" . $key . "']",
                $ajax,
                sprintf('El navegador envía "%s" pero el endpoint AJAX no lo lee.', $key)
            );
        }
    }

    /** And what it reads must reach the query builder, not stop at the config. */
    public function testElEndpointAjaxTrasladaTodosLosFiltrosAlBuscador(): void
    {
        $ajax = $this->source('src/Services/PropertyAjaxService.php');
        $start = (int) strpos($ajax, '$filterParams = [');
        $filterParams = substr($ajax, $start, (int) strpos($ajax, '];', $start) - $start);

        // `query_mode` and `per_page` travel under their own names in the list.
        $aliases = ['per_page' => 'per_page'];

        foreach ($this->filterKeysSentByTheBrowser() as $key) {
            $expected = $aliases[$key] ?? $key;
            self::assertStringContainsString(
                "'" . $expected . "'",
                $filterParams,
                sprintf('El filtro "%s" no llega a buildQueryArgs desde el endpoint AJAX.', $key)
            );
        }
    }

    /**
     * Every filter the endpoint forwards has to be one the query builder knows
     * about, so a rename on either side is caught.
     */
    public function testTodoFiltroReenviadoPorElEndpointEsConocidoPorElBuscador(): void
    {
        $ajax = $this->source('src/Services/PropertyAjaxService.php');
        $start = (int) strpos($ajax, '$filterParams = [');
        $filterParams = substr($ajax, $start, (int) strpos($ajax, '];', $start) - $start);
        preg_match_all("/^\s*'([a-z_0-9]+)'\s*=>/m", $filterParams, $matches);

        $known = array_merge(
            array_keys(self::FILTERS),
            self::LOCALITY_FILTERS,
            ['query_mode', 'geo_latitude', 'geo_longitude', 'geo_radius_km']
        );

        foreach ($matches[1] as $key) {
            self::assertContains(
                $key,
                $known,
                sprintf('El endpoint reenvía "%s", que no está cubierto por estas pruebas ni por el buscador.', $key)
            );
        }
    }

    /**
     * The public form fields must be aliases the URL reader understands, or the
     * form submits parameters nobody consumes.
     */
    public function testCadaCampoDelFormularioPublicoEsUnAliasReconocido(): void
    {
        $form = $this->source('templates/parts/property-filter.php');
        preg_match_all('/name="([a-z_0-9]+)(?:\[\])?"/', $form, $matches);

        $service = $this->source('src/Services/PropertySearchService.php');

        $fields = array_diff(array_unique($matches[1]), self::NON_FILTER_FIELDS);
        self::assertNotEmpty($fields, 'El formulario dejó de enviar campos de filtro.');

        foreach ($fields as $field) {
            self::assertStringContainsString(
                "'" . $field . "'",
                $service,
                sprintf('El formulario envía "%s" pero el buscador no lo reconoce.', $field)
            );
        }
    }
}
