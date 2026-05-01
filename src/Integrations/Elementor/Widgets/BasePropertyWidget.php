<?php
/**
 * Base widget helpers.
 */

namespace Homlity\PluginInmobiliario\Integrations\Elementor\Widgets;

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
            'use_current_property',
            [
                'label' => __('Usar inmueble actual', 'homlity-plugin'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Sí', 'homlity-plugin'),
                'label_off' => __('No', 'homlity-plugin'),
                'default' => 'yes',
                'description' => __('Detecta automáticamente el inmueble según la consulta actual.', 'homlity-plugin'),
            ]
        );

        $this->add_control(
            'property_id',
            [
                'label' => __('ID de la propiedad', 'homlity-plugin'),
                'type' => Controls_Manager::NUMBER,
                'default' => get_queried_object_id(),
                'condition' => [
                    'use_current_property!' => 'yes',
                ],
            ]
        );
    }

    protected function current_property_id(): int
    {
        $settings = $this->get_settings_for_display();
        $useCurrent = ($settings['use_current_property'] ?? 'yes') === 'yes';
        if (!$useCurrent) {
            $id = isset($settings['property_id']) ? (int) $settings['property_id'] : 0;
            if ($id > 0) {
                return $id;
            }
        }

        $queriedId = (int) get_queried_object_id();
        if ($queriedId > 0) {
            return $queriedId;
        }

        $postId = (int) get_the_ID();
        if ($postId > 0) {
            return $postId;
        }

        global $post;
        if ($post instanceof \WP_Post) {
            return (int) $post->ID;
        }

        return 0;
    }

    public function get_categories(): array
    {
        return ['homlity-plugin'];
    }
}
