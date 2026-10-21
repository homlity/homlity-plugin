<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyDuplicateService;
use Homlity\PluginInmobiliario\Services\PropertyDuplicateDetector;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\PropertyQualityFixtures;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/** El diagnóstico debe ser accesible desde el admin y la consola sin cambiar datos. */
final class PropertyDuplicateServiceTest extends TestCase
{
    use PropertyQualityFixtures;

    public function testRegistroSinWpCliYBadgesConEnlaceAHermanos(): void
    {
        $this->property(1, 'Casa amplia', [], '', 'Pie de la Popa');
        $this->property(2, 'Casa amplia');
        $this->assignTerm(1, PropertyTaxonomies::TAXONOMY_TYPE, 'Oficina');
        $service = new PropertyDuplicateService();
        $service->register();
        self::assertArrayHasKey('homlity_duplicates', $service->columns(['title' => 'Título']));
        ob_start();
        try {
            $service->renderColumn('homlity_duplicates', 1);
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        self::assertStringContainsString('Descripción duplicada (1)', $html);
        self::assertStringContainsString('homlity_duplicate_of=1', $html);
        self::assertStringContainsString('Tipo ≠ texto', $html);
        self::assertStringContainsString('Texto copiado:', $html);
    }

    public function testBorrarUnInmuebleInvalidaLaCachePeroOtrosPostTypesNo(): void
    {
        (new PropertyDuplicateService())->register();
        $post = $this->property(1);
        $cached = ['grupo' => ['ids' => [1, 2]]];
        set_transient(PropertyDuplicateDetector::TRANSIENT, $cached, 12 * HOUR_IN_SECONDS);
        do_action('deleted_post', 2, new \WP_Post(['ID' => 2, 'post_type' => 'post']));
        self::assertSame($cached, get_transient(PropertyDuplicateDetector::TRANSIENT));
        // deleted_post recibe la ficha eliminada: ya no se puede consultar por ID.
        unset(WpStubs::$postObjects[1]);
        do_action('deleted_post', 1, $post);
        self::assertFalse(get_transient(PropertyDuplicateDetector::TRANSIENT));
    }

    public function testEnlaceDelBadgeFiltraLosHermanosYUnGrupoObsoletoNoMuestraTodo(): void
    {
        $this->property(1);
        $this->property(2);
        $query = new class {
            public array $vars = ['post_type' => 'property'];
            public function is_main_query(): bool { return true; }
            public function get(string $key) { return $this->vars[$key] ?? ''; }
            public function set(string $key, $value): void { $this->vars[$key] = $value; }
        };
        $originalGet = $_GET;
        try {
            WpStubs::$isAdminScreen = true;
            $_GET['homlity_duplicate_of'] = '1';
            $service = new PropertyDuplicateService();
            $service->filterSiblings($query);
            self::assertSame([2], $query->vars['post__in']);
            $_GET['homlity_duplicate_of'] = '999';
            $service->filterSiblings($query);
            self::assertSame([0], $query->vars['post__in']);
        } finally {
            $_GET = $originalGet;
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testComandoWpCliRegistraYExportaDuplicadosYConflictosEnTresFormatos(): void
    {
        eval('namespace { class WP_CLI { public static array $commands = []; public static function add_command($name, $callback) { self::$commands[$name] = $callback; } public static function error($message) { throw new \\RuntimeException($message); } } } namespace WP_CLI\\Utils { function format_items($format, $rows, $fields) { $GLOBALS["homlity_cli_output"] = [$format, $rows, $fields]; } }');
        $this->property(1);
        $this->property(2);
        $this->property(3, 'Casa frente al parque', [], '', 'Pie de la Popa');
        $this->assignTerm(3, PropertyTaxonomies::TAXONOMY_TYPE, 'Oficina');
        $this->property(4, 'Texto correcto');
        (new PropertyDuplicateService())->register();
        self::assertArrayHasKey('homlity duplicates', \WP_CLI::$commands);
        foreach (['table', 'json', 'csv'] as $format) {
            WpStubs::$posts[] = [1, 2, 3, 4];
            (\WP_CLI::$commands['homlity duplicates'])([], ['format' => $format]);
            [$actualFormat, $rows, $fields] = $GLOBALS['homlity_cli_output'];
            self::assertSame($format, $actualFormat);
            self::assertSame([1, 2, 3], array_column($rows, 'post_id'));
            self::assertSame('copied_text', $rows[0]['classification']);
            self::assertSame('2', $rows[0]['siblings']);
            self::assertSame('casa', $rows[2]['type_mismatch']);
        }
        $this->expectException(\RuntimeException::class);
        (\WP_CLI::$commands['homlity duplicates'])([], ['format' => 'yaml']);
    }
}
