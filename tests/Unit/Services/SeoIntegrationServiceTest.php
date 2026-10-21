<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\SeoIntegrationService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/** Los sitemaps heredados redirigen sin añadir duplicados al índice del proveedor. */
final class SeoIntegrationServiceTest extends TestCase
{
    public function testRegistroDelegaLosSitemapsAlProveedorYConservaCoreYSchema(): void
    {
        (new SeoIntegrationService())->register();
        foreach (['rank_math/sitemap/index', 'wpseo_sitemap_index', 'rank_math/sitemaps/post_types',
            'rank_math/sitemaps/taxonomies', 'wpseo_sitemap_post_types', 'wpseo_sitemap_taxonomies'] as $hook) {
            self::assertArrayNotHasKey($hook, WpStubs::$filters);
        }
        self::assertArrayHasKey('wp_sitemaps_post_types', WpStubs::$filters);
        self::assertArrayHasKey('rank_math/json_ld', WpStubs::$filters);
    }

    private function indexUrl(): string
    {
        return (new \ReflectionMethod(SeoIntegrationService::class, 'sitemapIndexUrl'))->invoke(new SeoIntegrationService());
    }

    public function testSinProveedorUsaElIndiceCore(): void
    {
        self::assertSame(home_url('/wp-sitemap.xml'), $this->indexUrl());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testYoastUsaSuIndice(): void
    {
        define('WPSEO_VERSION', 'test');
        self::assertSame(home_url('/sitemap_index.xml'), $this->indexUrl());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRankMathConSitemapUsaSuIndice(): void
    {
        eval('namespace RankMath\\Sitemap; class Sitemap {}');
        self::assertSame(home_url('/sitemap_index.xml'), $this->indexUrl());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRankMathDependeDelModuloActivo(): void
    {
        eval('namespace RankMath; class Helper { public static bool $active = false; public static function is_module_active($module) { return $module === "sitemap" && self::$active; } }');
        self::assertSame(home_url('/wp-sitemap.xml'), $this->indexUrl());
        \RankMath\Helper::$active = true;
        self::assertSame(home_url('/sitemap_index.xml'), $this->indexUrl());
    }

    public function testLasRutasHeredadasRedirigenSinNecesitarRewritesActualizadas(): void
    {
        $original = $_SERVER['REQUEST_URI'] ?? null;
        try {
            foreach (['homlity-search-sitemap.xml', 'homlity-properties-sitemap.xml', 'homlity-properties-sitemap-2.xml'] as $path) {
                $_SERVER['REQUEST_URI'] = '/' . $path . '?foo=bar';
                try {
                    (new SeoIntegrationService())->maybeRedirectLegacySitemap();
                    self::fail('La ruta heredada debe redirigir.');
                } catch (\HomlityTestRedirect $redirect) {
                    self::assertSame(['location' => home_url('/wp-sitemap.xml'), 'status' => 301], end(WpStubs::$redirects));
                }
            }
            $_SERVER['REQUEST_URI'] = '/sitemap_index.xml';
            $count = count(WpStubs::$redirects);
            (new SeoIntegrationService())->maybeRedirectLegacySitemap();
            self::assertCount($count, WpStubs::$redirects);
        } finally {
            if ($original === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $original;
            }
        }
    }

    public function testLasQueryVarsHeredadasTambienRedirigen(): void
    {
        foreach (['homlity_search_sitemap', 'homlity_properties_sitemap'] as $key) {
            WpStubs::$queryVars = [$key => '1'];
            try {
                (new SeoIntegrationService())->maybeRedirectLegacySitemap();
                self::fail('La query var debe redirigir.');
            } catch (\HomlityTestRedirect $redirect) {
                self::assertSame(301, end(WpStubs::$redirects)['status']);
            }
        }
    }
}
