<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

/** Renderiza la plantilla compartida de inmuebles retirados. @since 2.9.17 */
final class UnavailablePageRenderer
{
    /** Devuelve false sin imprimir nada cuando debe usarse la plantilla PHP. */
    public function renderConfiguredTemplate(): bool
    {
        $id = (int) get_option('homlity_plugin_unavailable_template_id', 0);
        if ($id <= 0 || get_post_status($id) !== 'publish') {
            return false;
        }
        $builder = TemplateService::detectTemplateBuilder($id);
        if ($builder === 'elementor') {
            if (!class_exists('\\Elementor\\Plugin')) {
                return false;
            }
            $frontend = \Elementor\Plugin::$instance->frontend ?? null;
            if (!is_object($frontend) || !is_callable([$frontend, 'get_builder_content_for_display'])) {
                return false;
            }
            $html = $frontend->get_builder_content_for_display($id, true);
            if (trim(wp_strip_all_tags($html)) === '') {
                return false;
            }
            // template_redirect termina antes del init normal de Elementor.
            add_action('wp_enqueue_scripts', static function () use ($frontend, $id): void {
                if (method_exists($frontend, 'enqueue_styles')) {
                    $frontend->enqueue_styles();
                }
                if (class_exists('\\Elementor\\Core\\Files\\CSS\\Post')) {
                    \Elementor\Core\Files\CSS\Post::create($id)->enqueue();
                }
            }, 20);
            foreach (['wp_head' => ['print_fonts_links', 7], 'wp_footer' => ['wp_footer', 10]] as $hook => [$method, $priority]) {
                if (method_exists($frontend, $method) && !has_action($hook, [$frontend, $method])) {
                    add_action($hook, [$frontend, $method], $priority);
                }
            }
        } else {
            global $post;
            $previousPost = $post ?? null;
            $post = get_post($id);
            if (!$post instanceof \WP_Post) {
                return false;
            }
            setup_postdata($post);
            try {
                $html = apply_filters('the_content', $post->post_content);
            } finally {
                wp_reset_postdata();
                $post = $previousPost;
                if ($previousPost instanceof \WP_Post) {
                    setup_postdata($previousPost);
                }
            }
            if (trim(wp_strip_all_tags($html)) === '') {
                return false;
            }
        }
        if (!has_action('wp_enqueue_scripts', [\Homlity\PluginInmobiliario\Listing\ListingRenderer::class, 'enqueueAssets'])) {
            add_action('wp_enqueue_scripts', [\Homlity\PluginInmobiliario\Listing\ListingRenderer::class, 'enqueueAssets'], 1);
        }
        // Pro registra las ubicaciones en prioridad 10; estas rutas terminan antes.
        if (class_exists('\\ElementorPro\\Modules\\ThemeBuilder\\Module')) {
            $locations = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_locations_manager();
            if (method_exists($locations, 'register_locations')) {
                $locations->register_locations();
            }
        }
        $content = static function () use ($html): void {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- El constructor sanea su propio contenido.
            echo $html;
        };
        self::renderDocument($content, (string) get_option('homlity_plugin_unavailable_page_layout', 'default'));
        return true;
    }

    /** Imprime un documento completo, también para la ficha en canvas. @since 2.9.17 */
    public static function renderDocument(callable $content, string $layout): void
    {
        if ($layout === 'elementor_canvas') {
            include dirname(__DIR__, 2) . '/templates/parts/canvas-document.php';
            return;
        }
        get_header();
        $content();
        get_footer();
    }
}
