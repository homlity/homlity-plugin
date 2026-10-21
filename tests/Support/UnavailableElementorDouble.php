<?php
// Dobles aislados: nunca se cargan en el proceso compartido de la suite.
namespace Elementor;
class Plugin { public static $instance; }
class UnavailableFrontendDouble {
    public string $html = '<style>.example{color:red}</style><svg><path /></svg><p>Recuperación</p>';
    public int $calls = 0;
    public function get_builder_content_for_display($id, $css): string { ++$this->calls; return $this->html; }
    public function enqueue_styles(): void { \wp_enqueue_style('elementor-frontend'); }
    public function print_fonts_links(): void { echo '<!-- fuentes -->'; }
    public function wp_footer(): void { echo '<!-- elementor footer -->'; }
}
namespace Elementor\Core\Files\CSS;
class Post {
    public function __construct(private int $id) {}
    public static function create($id): self { return new self($id); }
    public function enqueue(): void { \wp_enqueue_style('elementor-post-' . $this->id); }
}

namespace ElementorPro\Modules\ThemeBuilder;
class Module {
    private static $instance;
    public static function instance(): self { return self::$instance ??= new self(); }
    public function get_locations_manager(): object { return new LocationsDouble(); }
}
class LocationsDouble {
    public function register_locations(): void {
        \do_action('elementor/theme/register_locations');
        \add_action('wp_enqueue_scripts', static function (): void {
            \wp_enqueue_style('elementor-post-header');
            \wp_enqueue_style('elementor-post-footer');
        });
    }
}
