<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
/**
 * Registers the /llms-full.txt virtual endpoint and serves Markdown content for AI systems.
 *
 * Also automatically injects discoverability references into:
 *  - /robots.txt  via WordPress's 'robots_txt' filter
 *  - /llms.txt    via PHP output buffering (works regardless of which SEO plugin serves it)
 */

namespace Homlity\PluginInmobiliario\Services\Ai;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class LlmsFullService implements ServiceInterface
{
    private const QUERY_VAR    = 'homlity_llms_full';
    private const REWRITE_SLUG = 'llms-full\.txt';

    public function register(): void
    {
        add_action('init',              [$this, 'addRewriteRule']);
        add_filter('query_vars',        [$this, 'registerQueryVar']);
        add_action('template_redirect', [$this, 'maybeServe'], 1);

        // Discoverability integrations
        add_filter('robots_txt',        [$this, 'appendToRobotsTxt'], 20, 2);
        add_action('template_redirect', [$this, 'maybeInterceptLlmsTxt'], 5);
    }

    // ── Rewrite rule ──────────────────────────────────────────────────────────

    public function addRewriteRule(): void
    {
        add_rewrite_rule(
            '^' . self::REWRITE_SLUG . '$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );
    }

    public function registerQueryVar(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    // ── /llms-full.txt endpoint ───────────────────────────────────────────────

    public function maybeServe(): void
    {
        if (!get_query_var(self::QUERY_VAR)) {
            return;
        }

        if (!$this->isEnabled()) {
            status_header(404);
            nocache_headers();
            header('Content-Type: text/plain; charset=utf-8');
            echo '404 Not Found';
            exit;
        }

        $content = apply_filters(
            'homlity_llms_full_content',
            (new LlmsFullGenerator())->generate()
        );

        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentional plain-text Markdown
        echo $content;
        exit;
    }

    // ── /robots.txt integration ───────────────────────────────────────────────

    /**
     * Appends an Allow directive and a Llms-Full reference to WordPress's
     * virtual robots.txt so AI crawlers can discover the extended context file.
     *
     * No-op when the site is not public, the feature is disabled, or the
     * reference is already present (e.g. added by the SEO plugin).
     *
     * @param string     $output Current robots.txt content built by WordPress.
     * @param string|int $public Value of the blog_public option (truthy = public).
     */
    public function appendToRobotsTxt(string $output, $public): string
    {
        if (!$public || !$this->isEnabled()) {
            return $output;
        }

        if (strpos($output, 'llms-full.txt') !== false) {
            return $output;
        }

        $url = esc_url_raw(home_url('/llms-full.txt'));

        $output .= "\n# Archivo de contexto extendido para modelos de IA\n";
        $output .= "Allow: /llms-full.txt\n";
        $output .= "Llms-Full: {$url}\n";

        return $output;
    }

    // ── /llms.txt interception ────────────────────────────────────────────────

    /**
     * Hooks into template_redirect at priority 5 (before most SEO plugins) and
     * starts an output buffer that appends an Optional link to /llms-full.txt.
     *
     * The ob_start callback is invoked during PHP buffer flush, which happens
     * even when the external plugin calls exit — so this works regardless of how
     * the SEO plugin terminates its output.
     *
     * No-op when the feature is disabled or the request is not for /llms.txt.
     */
    public function maybeInterceptLlmsTxt(): void
    {
        if (!$this->isEnabled() || !$this->isLlmsTxtRequest()) {
            return;
        }

        $url = esc_url_raw(home_url('/llms-full.txt'));

        ob_start(static function (string $buffer) use ($url): string {
            // Don't duplicate if the SEO plugin already lists llms-full.txt
            if (strpos($buffer, 'llms-full.txt') !== false) {
                return $buffer;
            }

            $link = "- [Contexto extendido para IA]({$url}): Información completa del sitio inmobiliario, servicios, tipos de inmuebles e integraciones.\n";

            // Inject inside an existing ## Optional section when one is present
            if (preg_match('/^## Optional[ \t]*$/m', $buffer)) {
                return (string) preg_replace_callback(
                    '/^(## Optional[ \t]*\n)/m',
                    static fn(array $m) => $m[1] . "\n" . $link,
                    $buffer,
                    1
                );
            }

            // Otherwise append a new Optional section at the end
            return rtrim($buffer) . "\n\n## Optional\n\n" . $link;
        });
    }

    // ── Activation / deactivation ─────────────────────────────────────────────

    /**
     * Called from register_activation_hook — registers the rule and flushes rewrite cache.
     */
    public static function activate(): void
    {
        add_rewrite_rule(
            '^' . self::REWRITE_SLUG . '$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );
        flush_rewrite_rules();
    }

    /**
     * Called from register_deactivation_hook — clears the stale rewrite cache.
     */
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function isEnabled(): bool
    {
        return (bool) apply_filters(
            'homlity_llms_full_enabled',
            get_option('homlity_llms_full_enabled', '1') !== '0'
        );
    }

    /**
     * Returns true when the current HTTP request is for /llms.txt.
     * Handles subdirectory WordPress installations by stripping the site path prefix.
     */
    private function isLlmsTxtRequest(): bool
    {
        $reqPath  = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $homePath = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');

        if ($homePath !== '' && strpos($reqPath, $homePath . '/') === 0) {
            $reqPath = trim(substr($reqPath, strlen($homePath) + 1), '/');
        }

        return $reqPath === 'llms.txt';
    }
}
