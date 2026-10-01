<?php

namespace Homlity\PluginInmobiliario\Integrations\Shared;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Las capas de compatibilidad de Divi y WPBakery registran los grupos como
 * box_shadow/text_shadow, mientras que Elementor los llama box-shadow y
 * text-shadow. Los traits compartidos usan el nombre de la capa y lo traducen
 * aquí solo cuando el widget es de Elementor.
 */
final class GroupControlType
{
    public static function resolve(object $widget, string $type): string
    {
        if (!$widget instanceof \Elementor\Widget_Base) {
            return $type;
        }

        return match ($type) {
            'box_shadow'  => 'box-shadow',
            'text_shadow' => 'text-shadow',
            default       => $type,
        };
    }
}
