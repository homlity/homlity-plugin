<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Tests\Support\TestCase;

/**
 * Un submenú sólo puede registrarse después de su menú padre.
 *
 * AdminMenuService crea 'homlity-real-estate-settings' con add_menu_page() en
 * 'admin_menu' con prioridad 10. Si otro servicio cuelga su add_submenu_page()
 * de 'admin_menu' con esa misma prioridad y se instancia antes en
 * PluginBootstrap, el submenú se registra cuando el padre todavía no existe:
 * get_plugin_page_hookname() lo guarda entonces como 'admin_page_…' y en la
 * petición real lo busca como 'homlity_page_…'. Al no coincidir,
 * user_can_access_admin_page() falla y WordPress responde «Lo siento, no tienes
 * permisos para acceder a esta página», que oculta la causa real.
 *
 * Le pasó a la página de Plantillas Elementor. Esta prueba lo detecta por
 * inspección del código fuente, sin necesidad de levantar WordPress.
 */
final class AdminSubmenuPriorityTest extends TestCase
{
    private const PARENT_SLUG = 'homlity-real-estate-settings';
    private const PARENT_PRIORITY = 10;

    public function testTodoSubmenuDeAjustesSeRegistraDespuesDeSuMenuPadre(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $file) {
            $code = (string) file_get_contents($file);

            // El propio servicio que crea el menú padre queda fuera: registra padre
            // e hijos en la misma llamada, así que el orden ya es correcto por
            // construcción.
            if (!$this->addsSubmenuToSettings($code) || $this->createsParentMenu($code)) {
                continue;
            }

            foreach ($this->adminMenuPriorities($code) as $priority) {
                if ($priority <= self::PARENT_PRIORITY) {
                    $offenders[] = sprintf(
                        '%s (prioridad %d)',
                        str_replace(dirname(__DIR__, 3) . '/', '', $file),
                        $priority
                    );
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            "Estos servicios cuelgan un submenú de '" . self::PARENT_SLUG . "' en 'admin_menu' con prioridad <= "
            . self::PARENT_PRIORITY . ", así que pueden ejecutarse antes de que exista el menú padre. "
            . 'Usa una prioridad mayor (30, como Divi y WPBakery): ' . implode(', ', $offenders)
        );
    }

    public function testLaPruebaVeRealmenteLosServiciosQueRegistranSubmenus(): void
    {
        $found = 0;
        foreach ($this->sourceFiles() as $file) {
            $code = (string) file_get_contents($file);
            if ($this->addsSubmenuToSettings($code) && !$this->createsParentMenu($code)) {
                $found++;
            }
        }

        self::assertGreaterThan(
            0,
            $found,
            'El detector no encontró ningún add_submenu_page(): la prueba pasaría en vacío.'
        );
    }

    private function addsSubmenuToSettings(string $code): bool
    {
        return preg_match(
            '/add_submenu_page\s*\(\s*[^;]*?' . preg_quote(self::PARENT_SLUG, '/') . '/s',
            $code
        ) === 1;
    }

    private function createsParentMenu(string $code): bool
    {
        return preg_match(
            '/add_menu_page\s*\(\s*[^;]*?' . preg_quote(self::PARENT_SLUG, '/') . '/s',
            $code
        ) === 1;
    }

    /** @return list<int> */
    private function adminMenuPriorities(string $code): array
    {
        preg_match_all(
            "/add_action\s*\(\s*'admin_menu'\s*,(?:[^()]|\([^()]*\))*?(?:,\s*(\d+))?\s*\)\s*;/s",
            $code,
            $matches
        );

        return array_map(
            static fn(string $priority): int => $priority === '' ? 10 : (int) $priority,
            $matches[1]
        );
    }

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $files = [];
        $root = dirname(__DIR__, 3) . '/src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
