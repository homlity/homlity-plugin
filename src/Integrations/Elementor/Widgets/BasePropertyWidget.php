<?php
/**
 * Base widget helpers.
 */

namespace Codwelt\PluginInmobiliario\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BasePropertyWidget extends Widget_Base
{
    protected function register_property_control(): void
    {
        $this->add_control(
            'property_id',
            [
                'label' => __('ID de la propiedad', 'inmopress-listings-inmobiliaria'),
                'type' => Controls_Manager::NUMBER,
                'default' => get_queried_object_id(),
            ]
        );
    }

    protected function current_property_id(): int
    {
        $settings = $this->get_settings_for_display();
        $id = isset($settings['property_id']) ? (int) $settings['property_id'] : 0;
        return $id > 0 ? $id : (int) get_queried_object_id();
    }

    public function get_categories(): array
    {
        return ['inmopress-listings-inmobiliaria'];
    }
}
