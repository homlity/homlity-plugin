<?php
if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Elementor_Manager
{
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        if (did_action('elementor/loaded')) {
            self::setup();
        } else {
            add_action('elementor/loaded', [__CLASS__, 'setup']);
        }
    }

    public static function setup(): void
    {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }

        add_action('elementor/elements/categories_registered', [__CLASS__, 'register_category']);
        add_action('elementor/widgets/register', [__CLASS__, 'register_widgets']);
    }

    public static function register_category(\Elementor\Elements_Manager $elements_manager): void
    {
        $categories = $elements_manager->get_categories();
        if (isset($categories['homlity'])) {
            return;
        }
        $elements_manager->add_category('homlity', [
            'title' => 'Homlity',
            'icon'  => 'eicon-home',
        ]);
    }

    public static function register_widgets(\Elementor\Widgets_Manager $widgets_manager): void
    {
        $helpers_dir = HOMLITY_PLUGIN_PATH . 'includes/elementor/helpers/';
        $widgets_dir = HOMLITY_PLUGIN_PATH . 'includes/elementor/widgets/';

        require_once $helpers_dir . 'class-homlity-property-faq-generator.php';
        require_once $widgets_dir . 'class-homlity-property-faq-widget.php';

        $widgets_manager->register(new Homlity_Property_FAQ_Widget());
    }
}
